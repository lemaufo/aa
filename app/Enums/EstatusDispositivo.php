<?php

namespace App\Enums;

/** Estatus de un dispositivo autorizado. Suspendido se reactiva; Baja es definitiva. */
enum EstatusDispositivo: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';
    case Baja = 'baja';

    public function label(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
            self::Baja => 'Baja',
        };
    }

    /** Estatus a los que se puede pasar desde este. Baja no sale de baja: si el equipo vuelve, se da de alta otra vez. */
    public function siguientes(): array
    {
        return match ($this) {
            self::Activo => [self::Suspendido, self::Baja],
            self::Suspendido => [self::Activo, self::Baja],
            self::Baja => [],
        };
    }

    /** Lo que ve el equipo bloqueado en la pantalla de autorización. */
    public function avisoBloqueo(): string
    {
        return match ($this) {
            self::Suspendido => 'Este dispositivo está suspendido. Pide al administrador que lo reactive.',
            self::Baja => 'Este dispositivo está dado de baja y ya no puede usar el sistema.',
            self::Activo => '',
        };
    }
}
