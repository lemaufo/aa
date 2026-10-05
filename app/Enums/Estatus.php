<?php

namespace App\Enums;

enum Estatus: string
{
    case EnExpertos = 'en_expertos';
    case Cobrado = 'cobrado';
    case Producido = 'producido';
    case EnviadoACedis = 'enviado_a_cedis';
    case Validado = 'validado';
    case EnCedis = 'en_cedis';
    case EnviadoACliente = 'enviado_a_cliente';
    case EnviadoASucursal = 'enviado_a_sucursal';
    case EnSucursal = 'en_sucursal';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::EnExpertos => 'EN EXPERTOS',
            self::Cobrado => 'COBRADO',
            self::Producido => 'PRODUCIDO',
            self::EnviadoACedis => 'ENVIADO A CEDIS',
            self::Validado => 'VALIDADO',
            self::EnCedis => 'EN CEDIS',
            self::EnviadoACliente => 'ENVIADO A CLIENTE',
            self::EnviadoASucursal => 'ENVIADO A SUCURSAL',
            self::EnSucursal => 'EN SUCURSAL',
            self::Entregado => 'ENTREGADO',
            self::Cancelado => 'CANCELADO',
        };
    }

    /** Posición en la regla de avance (0 a 5). Cancelado queda fuera de la regla. */
    public function etapa(): int
    {
        return match ($this) {
            self::EnExpertos => 0,
            self::Cobrado => 1,
            self::Producido => 2,
            self::EnviadoACedis, self::Validado, self::EnCedis => 3,
            self::EnviadoACliente, self::EnviadoASucursal, self::EnSucursal => 4,
            self::Entregado => 5,
            self::Cancelado => -1,
        };
    }

    /** Nombre de cada etapa en la regla de avance. */
    public static function etapas(): array
    {
        return ['Traspaso', 'Cobrado', 'Producido', 'En CEDIS', 'En ruta', 'Entregado'];
    }

    /** Estilo visual del badge. */
    public function tono(): string
    {
        return match ($this) {
            self::EnExpertos => 'neutro',
            self::Entregado => 'final',
            self::Cancelado => 'cancelado',
            default => 'avance',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::Entregado, self::Cancelado], true);
    }
}