<?php

namespace App\Support;

use App\Enums\EstatusDispositivo;
use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Código inicial para autorizar la primera máquina (la de SYSDBA) sin usar la consola.
 * Vive solo en el .env del servidor (AA_DISPOSITIVOS_CODIGO_INICIAL), nunca en Git ni en la base.
 * Solo lo puede canjear SYSDBA y sirve una vez: para otra (p. ej. SYSDBA cambió de PC) se pone uno distinto.
 */
class CodigoInicial
{
    /** Igual que los códigos de los demás dispositivos (XXXX-XXXX): 8 caracteres sin contar guiones. */
    public const MINIMO = 8;

    /** El código configurado, o null si está apagado (vacío o demasiado corto). */
    public static function configurado(): ?string
    {
        $codigo = self::normalizar((string) config('aa.dispositivos.codigo_inicial'));

        return strlen($codigo) >= self::MINIMO ? $codigo : null;
    }

    public static function coincide(string $codigo): bool
    {
        $configurado = self::configurado();

        return $configurado !== null && hash_equals($configurado, self::normalizar($codigo));
    }

    /** Sin espacios ni guiones y en mayúsculas: "ab12-cd34" y "AB12CD34" son el mismo código. */
    private static function normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[\s\-]/', '', $codigo));
    }

    public static function usado(): bool
    {
        $configurado = self::configurado();

        return $configurado !== null
            && Dispositivo::withTrashed()->where('codigo_inicial_hash', hash('sha256', $configurado))->exists();
    }

    /**
     * Crea y vincula el dispositivo de SYSDBA. Devuelve la llave para la cookie.
     * La MAC se toma de la red si el servidor la ve y no la tiene otro dispositivo; si no, se captura luego en Editar.
     */
    public static function canjear(User $sysdba, ?string $macVista): string
    {
        $mac = $macVista && ! Dispositivo::macOcupada($macVista) ? $macVista : null;
        $token = Str::random(64);

        $dispositivo = Dispositivo::create([
            'nombre' => 'PC Administración (SYSDBA)',
            'mac' => $mac,
            'estatus' => EstatusDispositivo::Activo,
            'codigo_inicial_hash' => hash('sha256', self::configurado()),
            'token_hash' => hash('sha256', $token),
            'activado_at' => now(),
            'ultimo_uso_at' => now(),
        ]);

        DispositivoEvento::anotar($dispositivo, $sysdba, 'Primer dispositivo autorizado con el código inicial',
            'MAC '.($mac ?? 'sin registrar (se captura en Editar)'));

        return $token;
    }
}
