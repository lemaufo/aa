<?php

namespace App\Services\Firebird;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

/** Lo común a las lecturas de Microsip / AdminExpertos (misma base Firebird). */
final class Microsip
{
    public static function db(): Connection
    {
        return DB::connection('firebird');
    }

    /**
     * Microsip guarda el folio en 9 caracteres: serie + número con ceros (CA0078212, TSA013341).
     * Acepta lo que teclea el cajero: minúsculas, espacios, guiones o sin ceros (ca-78212).
     */
    public static function folio(string $folio): string
    {
        $folio = strtoupper(preg_replace('/[\s\-]+/', '', $folio));

        if (preg_match('/^([A-Z]+)0*(\d+)$/', $folio, $m) && strlen($m[1]) < 9) {
            return $m[1].str_pad($m[2], max(9 - strlen($m[1]), strlen($m[2])), '0', STR_PAD_LEFT);
        }

        return $folio;
    }

    /** Fila de Firebird con llaves en minúsculas y sin el relleno de los CHAR. */
    public static function fila(?object $fila): ?array
    {
        return $fila === null ? null : array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            array_change_key_case((array) $fila, CASE_LOWER)
        );
    }
}
