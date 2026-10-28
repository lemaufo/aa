<?php

namespace App\Services\Firebird;

/**
 * Catálogo de artículos de Microsip (ARTICULOS, CLAVES_ARTICULOS, LINEAS_ARTICULOS). Solo lectura.
 * ESTATUS: A = activo, S = suspendido (con CAUSA_SUSP y FECHA_SUSP); las demás letras se muestran tal cual.
 */
class FirebirdArticulos
{
    /** Clave principal del artículo (ROLES_CLAVES_ARTICULOS.ES_PPAL = 'S'). */
    private const CLAVE_PRINCIPAL = "(select first 1 c.clave_articulo from claves_articulos c
            join roles_claves_articulos r on r.rol_clave_art_id = c.rol_clave_art_id
           where c.articulo_id = a.articulo_id order by case r.es_ppal when 'S' then 0 else 1 end, c.clave_articulo_id)";

    /**
     * @return array{total: int, filas: list<array{articulo_id: int, clave: ?string, nombre: string, linea: ?string, unidad: ?string, peso_unitario: float, estatus: string}>}
     */
    public function buscar(string $texto = '', string $estatus = '', int $pagina = 1, int $porPagina = 25): array
    {
        [$where, $params] = $this->filtros($texto, $estatus);
        $db = Microsip::db();

        $total = (int) array_values((array) $db->selectOne("select count(*) from articulos a {$where}", $params))[0];

        $saltar = max(0, ($pagina - 1) * $porPagina);
        $filas = array_map(fn ($f) => [
            'articulo_id' => (int) $f['articulo_id'],
            'clave' => $f['clave'],
            'nombre' => (string) $f['nombre'],
            'linea' => $f['linea'],
            'unidad' => $f['unidad'],
            'peso_unitario' => (float) $f['peso_unitario'],
            'estatus' => (string) $f['estatus'],
        ], array_map(Microsip::fila(...), $db->select(
            'select first '.(int) $porPagina.' skip '.(int) $saltar.' a.articulo_id, '.self::CLAVE_PRINCIPAL.' as clave,
                    a.nombre, l.nombre as linea, a.unidad_venta as unidad, coalesce(a.peso_unitario, 0) as peso_unitario, a.estatus
               from articulos a left join lineas_articulos l on l.linea_articulo_id = a.linea_articulo_id
             '.$where.' order by a.nombre',
            $params
        )));

        return ['total' => $total, 'filas' => $filas];
    }

    /** Ficha del artículo con todas sus claves. Null si no existe. */
    public function detalle(int $articuloId): ?array
    {
        $db = Microsip::db();

        $a = Microsip::fila($db->selectOne(
            'select a.articulo_id, a.nombre, a.estatus, a.causa_susp, a.fecha_susp, l.nombre as linea,
                    a.unidad_venta, a.unidad_compra, a.contenido_unidad_compra, coalesce(a.peso_unitario, 0) as peso_unitario,
                    a.es_almacenable, a.es_juego, a.es_peso_variable, a.fecha_hora_creacion, a.fecha_hora_ult_modif
               from articulos a left join lineas_articulos l on l.linea_articulo_id = a.linea_articulo_id
              where a.articulo_id = ?',
            [$articuloId]
        ));

        if ($a === null) {
            return null;
        }

        $a['claves'] = array_map(Microsip::fila(...), $db->select(
            'select c.clave_articulo as clave, r.nombre as tipo
               from claves_articulos c join roles_claves_articulos r on r.rol_clave_art_id = c.rol_clave_art_id
              where c.articulo_id = ? order by case r.es_ppal when \'S\' then 0 else 1 end, c.clave_articulo',
            [$articuloId]
        ));

        return $a;
    }

    /** Estatus distintos con su conteo, para el filtro. */
    public function estatus(): array
    {
        return collect(Microsip::db()->select('select estatus, count(*) as n from articulos group by 1 order by 2 desc'))
            ->map(Microsip::fila(...))
            ->mapWithKeys(fn ($f) => [$f['estatus'] => (int) $f['n']])
            ->all();
    }

    /**
     * CONTAINING no distingue mayúsculas; la clave se busca también entre las alternas.
     * Microsip guarda casi todo sin acentos (LAMINA), así que también se busca el texto sin acentos.
     */
    private function filtros(string $texto, string $estatus): array
    {
        $condiciones = [];
        $params = [];

        if (($texto = trim($texto)) !== '') {
            $sinAcentos = \Illuminate\Support\Str::ascii($texto);
            $condiciones[] = '(a.nombre containing ? or a.nombre containing ?'
                .' or exists (select 1 from claves_articulos c where c.articulo_id = a.articulo_id and c.clave_articulo containing ?))';
            array_push($params, $texto, $sinAcentos, $sinAcentos);
        }

        if ($estatus !== '') {
            $condiciones[] = 'a.estatus = ?';
            $params[] = $estatus;
        }

        return [$condiciones ? 'where '.implode(' and ', $condiciones) : '', $params];
    }
}
