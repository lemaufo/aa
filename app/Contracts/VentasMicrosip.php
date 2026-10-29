<?php

namespace App\Contracts;

/** Lo que la app necesita saber (y escribir) del ticket de venta en Microsip. */
interface VentasMicrosip
{
    /**
     * @return array{folio: string, estado: string, articulo: string, cantidad: ?float, lineas_ajenas: int, entregada: bool}|null
     *         estado: pagada | pendiente | cancelada. cantidad: unidades vendidas del artículo (null = no se sabe, no se valida).
     *         Null si el ticket no existe.
     */
    public function consultar(string $folio): ?array;

    /** Marca la venta como entregada en el sistema. Lanza excepción si Microsip no responde. */
    public function marcarEntregada(string $folio, string $usuario, ?string $recibe): void;
}