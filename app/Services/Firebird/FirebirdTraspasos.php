<?php

namespace App\Services\Firebird;

use App\Contracts\TraspasosExpertos;

/**
 * Traspasos de AdminExpertos (EXP_TRASPASOS / EXP_TRASPASOS_DET). Solo lectura: la app nunca cancela.
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
        ], array_map(Microsip::fila(...), Microsip::db()->select(
            'select d.partida, d.clave_articulo as clave, a.nombre as articulo, d.cantidad,
                    coalesce(d.unidad, a.unidad_venta) as unidad, coalesce(a.peso_unitario, 0) as peso_unitario, d.notas
               from exp_traspasos_det d join articulos a on a.articulo_id = d.articulo_id
              where d.exp_trasp_id = ? order by d.partida',
            [$traspaso['exp_trasp_id']]
        )));
        unset($traspaso['exp_trasp_id']);

        return $traspaso;
    }

    private function encabezado(string $folio): ?array
    {
        // El folio se repite entre sucursales de origen; manda el más reciente.
        return Microsip::fila(Microsip::db()->selectOne(
            'select first 1 t.exp_trasp_id, t.folio, t.estatus, t.fecha, s.nombre as destino, t.observaciones
               from exp_traspasos t left join exp_sync_sucursales s on s.sucursal_id = t.destino
              where t.folio = ? order by t.exp_trasp_id desc',
            [Microsip::folio($folio)]
        ));
    }
}
