<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de dispositivos autorizados: altas, ediciones, cambios de estatus, intentos de uso con un
 * dispositivo bloqueado e intentos de entrar al apartado sin permiso. Separada de la bitácora de pedidos.
 */
class DispositivoEvento extends Model
{
    protected $guarded = [];

    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(Dispositivo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Anota un movimiento. $usuario null = el sistema (p. ej. un intento de uso bloqueado sin sesión). */
    public static function anotar(?Dispositivo $dispositivo, ?User $usuario, string $accion, ?string $detalle = null): self
    {
        return static::create([
            'dispositivo_id' => $dispositivo?->getKey(),
            'user_id' => $usuario?->getKey(),
            'accion' => $accion,
            'detalle' => $detalle === null ? null : mb_strimwidth($detalle, 0, 255, '…'),
            'ip' => request()?->ip(),
        ]);
    }
}
