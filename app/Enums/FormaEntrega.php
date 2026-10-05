<?php

namespace App\Enums;

enum FormaEntrega: string
{
    case Cedis = 'cedis';
    case Cliente = 'cliente';
    case Sucursal = 'sucursal';

    public function label(): string
    {
        return match ($this) {
            self::Cedis => 'Entrega en CEDIS',
            self::Cliente => 'Envío a cliente',
            self::Sucursal => 'Envío a sucursal',
        };
    }

    public function ayuda(): string
    {
        return match ($this) {
            self::Cedis => 'El cliente recoge en CEDIS.',
            self::Cliente => 'Sale con repartidor hacia el domicilio del cliente.',
            self::Sucursal => 'Se manda a la sucursal que hizo la venta.',
        };
    }
}