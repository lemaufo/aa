<?php

namespace App\Enums;

enum Rol: string
{
    case Admin = 'admin';
    case Cajero = 'cajero';
    case Produccion = 'produccion';
    case Cedis = 'cedis';
    case Sucursal = 'sucursal';
    case Consulta = 'consulta';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Cajero => 'Cajero',
            self::Produccion => 'Producción',
            self::Cedis => 'CEDIS',
            self::Sucursal => 'Sucursal',
            self::Consulta => 'Consulta',
        };
    }

    /** Lo que hace este rol, en una frase, para la pantalla de inicio. */
    public function resumen(): string
    {
        return match ($this) {
            self::Admin => 'Puedes operar cualquier paso del flujo y revisar permisos.',
            self::Cajero => 'Ligas cada traspaso con su venta cobrada y capturas los datos del pedido.',
            self::Produccion => 'Capturas lo que se cortó, imprimes la etiqueta, subes la evidencia y despachas a CEDIS.',
            self::Cedis => 'Recibes, pesas, validas y entregas, ya sea en CEDIS, con repartidor o hacia sucursal.',
            self::Sucursal => 'Recibes el pedido, lo entregas al cliente y cierras la venta en Microsip.',
            self::Consulta => 'Consultas el estatus y la bitácora de cualquier pedido. No modificas nada.',
        };
    }

    /** Cajero y Sucursal solo ven los pedidos de su propia sucursal. */
    public function limitadoASuSucursal(): bool
    {
        return in_array($this, [self::Cajero, self::Sucursal], true);
    }
}