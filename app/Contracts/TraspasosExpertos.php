<?php

namespace App\Contracts;

/** Lectura del traspaso en AdminExpertos. La app nunca cancela: solo lee lo que Expertos ya cancelo. */
interface TraspasosExpertos
{
    public function estaCancelado(string $folio): bool;

    /**
     * Encabezado y partidas del traspaso, para darlo de alta como pedido. Null si no existe.
     *
     * @return array{folio: string, estatus: string, fecha: string, destino: ?string, observaciones: ?string,
     *               partidas: list<array{partida: int, clave: ?string, articulo: string, cantidad: float, unidad: ?string, peso_unitario: float, notas: ?string}>}|null
     */
    public function leer(string $folio): ?array;
}
