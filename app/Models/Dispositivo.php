<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Equipo autorizado. Un administrador genera un código de un solo uso; al escribirlo en el equipo
 * se guarda una llave en una cookie (aquí solo vive su hash). Revocar un equipo corta su acceso.
 */
class Dispositivo extends Model
{
    protected $table = 'dispositivos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'codigo_expira_at' => 'datetime',
            'activado_at' => 'datetime',
            'ultimo_uso_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    public function estado(): string
    {
        return match (true) {
            $this->revocado_at !== null => 'Revocado',
            $this->activado_at !== null => 'Activo',
            $this->codigo_expira_at?->isPast() => 'Código vencido',
            default => 'Esperando activación',
        };
    }

    /** Crea el equipo y devuelve el código en claro (solo se ve esta vez). */
    public static function generarCodigo(string $nombre): string
    {
        $codigo = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        static::create([
            'nombre' => $nombre,
            'codigo_hash' => hash('sha256', $codigo),
            'codigo_expira_at' => now()->addHours((int) config('aa.dispositivos.codigo_vigencia_horas', 24)),
        ]);

        return $codigo;
    }

    /** Canjea el código. Devuelve la llave en claro para la cookie, o null si no sirve. */
    public static function activar(string $codigo): ?string
    {
        $codigo = strtoupper(trim($codigo));

        $dispositivo = static::query()
            ->where('codigo_hash', hash('sha256', $codigo))
            ->whereNull('activado_at')
            ->whereNull('revocado_at')
            ->where('codigo_expira_at', '>', now())
            ->first();

        if (! $dispositivo) {
            return null;
        }

        $token = Str::random(64);

        $dispositivo->update([
            'token_hash' => hash('sha256', $token),
            'codigo_hash' => null,
            'activado_at' => now(),
            'ultimo_uso_at' => now(),
        ]);

        return $token;
    }

    public static function autorizado(?string $token): bool
    {
        if (! $token || strlen($token) !== 64) {
            return false;
        }

        $dispositivo = static::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revocado_at')
            ->first();

        if (! $dispositivo) {
            return false;
        }

        if ($dispositivo->ultimo_uso_at === null || $dispositivo->ultimo_uso_at->diffInMinutes(now()) >= 5) {
            $dispositivo->forceFill(['ultimo_uso_at' => now()])->save();
        }

        return true;
    }
}