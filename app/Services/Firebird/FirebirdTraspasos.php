<?php

namespace App\Services\Firebird;

use App\Contracts\TraspasosExpertos;

/**
 * Traspasos de AdminExpertos (EXP_TRASPASOS / EXP_TRASPASOS_DET) y, si el folio no está ahí, los capturados
 * directo en Microsip Inventarios (DOCTOS_IN con el concepto de traspaso, aa.conceptos_traspaso).
 * Los de Expertos también generan su DOCTOS_IN, pero se leen de Expertos porque traen destino y estatus de envío.
 * Solo lectura: la app nunca cancela.
 * ESTATUS: C = cancelado. En la bitácora se ve P -> E -> F al enviarse y recibirse; A falta confirmarlo.
 */
class FirebirdTraspasos implements TraspasosExpertos
{
    public function estaCancelado(string $folio): bool
    {
        return ($this->encabezado($folio)['estatus'] ?? null) === 'C';
    }

    public function leer(string $folio): ?array
    {
        $traspaso = $this->encabezado($folio);

        if ($traspaso === null) {
            return null;
        }

        $traspaso['partidas'] = array_map(fn ($p) => [
            'partida' => (int) $p['partida'],
            'clave' => $p['clave'],
            'articulo' => (string) $p['articulo'],
            'cantidad' => (float) $p['cantidad'],
            'unidad' => $p['unidad'],
            'peso_unitario' => (float) $p['peso_unitario'],
            'notas' => $p['notas'] ?: null,
        ], array_map(Microsip::fila(...), $traspaso['origen'] === 'Expertos'
            ? Microsip::db()->select(
                'select d.partida, d.clave_articulo as clave, a.nombre as articulo, d.cantidad,
                        coalesce(d.unidad, a.unidad_venta) as unidad, coalesce(a.peso_unitario, 0) as peso_unitario, d.notas
                   from exp_traspasos_det d join articulos a on a.articulo_id = d.articulo_id
                  where d.exp_trasp_id = ? order by d.partida',
                [$traspaso['id']]
            )
            // DOCTOS_IN_DET no numera partidas: se numeran en el orden de captura. Sin componentes de juego (ROL C).
            : Microsip::db()->select(
                'select row_number() over (order by d.docto_in_det_id) as partida, d.clave_articulo as clave,
                        a.nombre as articulo, d.unidades as cantidad, a.unidad_venta as unidad,
                        coalesce(a.peso_unitario, 0) as peso_unitario, null as notas
                   from doctos_in_det d join articulos a on a.articulo_id = d.articulo_id
                  where d.docto_in_id = ? and coalesce(d.rol, \'N\') <> \'C\' order by d.docto_in_det_id',
                [$traspaso['id']]
            )
        ));
        unset($traspaso['id']);

        return $traspaso;
    }

    /** SISTEMA_ORIGEN IN = capturado en Microsip Inventarios; los de Expertos llegan como EX. */
    public function deMicrosipDesde(string $fecha): array
    {
        $conceptos = $this->conceptos();

        if ($conceptos === []) {
            return [];
        }

        return array_map(fn ($f) => Microsip::fila($f)['folio'], Microsip::db()->select(
            "select d.folio from doctos_in d
              where d.concepto_in_id in ({$this->marcas($conceptos)}) and d.sistema_origen = 'IN'
                and d.cancelado = 'N' and d.fecha >= ?
              order by d.docto_in_id",
            [...$conceptos, $fecha]
        ));
    }

    private function encabezado(string $folio): ?array
    {
        $folio = Microsip::folio($folio);

        // El folio se repite entre sucursales de origen; manda el más reciente.
        $traspaso = Microsip::fila(Microsip::db()->selectOne(
            "select first 1 t.exp_trasp_id as id, t.folio, t.estatus, t.fecha, s.nombre as destino, t.observaciones,
                    'Expertos' as origen
               from exp_traspasos t left join exp_sync_sucursales s on s.sucursal_id = t.destino
              where t.folio = ? order by t.exp_trasp_id desc",
            [$folio]
        ));

        return $traspaso ?? $this->deMicrosip($folio);
    }

    /** Traspaso de salida capturado en Microsip. CANCELADO = 'S' se traduce al mismo estatus C de Expertos. */
    private function deMicrosip(string $folio): ?array
    {
        $conceptos = $this->conceptos();

        if ($conceptos === []) {
            return null;
        }

        return Microsip::fila(Microsip::db()->selectOne(
            "select first 1 d.docto_in_id as id, d.folio, case when d.cancelado = 'S' then 'C' else 'A' end as estatus,
                    d.fecha, a.nombre as destino, d.descripcion as observaciones, 'Microsip' as origen
               from doctos_in d left join almacenes a on a.almacen_id = d.almacen_destino_id
              where d.folio = ? and d.concepto_in_id in ({$this->marcas($conceptos)})
              order by d.docto_in_id desc",
            [$folio, ...$conceptos]
        ));
    }

    /** @return list<int> */
    private function conceptos(): array
    {
        return array_map('intval', (array) config('aa.conceptos_traspaso'));
    }

    private function marcas(array $valores): string
    {
        return implode(', ', array_fill(0, count($valores), '?'));
    }
}
