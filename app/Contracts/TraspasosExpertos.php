<?php

namespace App\Contracts;

/** Lectura del traspaso en AdminExpertos o Microsip. La app nunca cancela: solo lee lo que ya se canceló allá. */
interface TraspasosExpertos
{
    public function estaCancelado(string $folio): bool;

    /**
     * Encabezado y partidas del traspaso, para darlo de alta como pedido. Null si no existe.
     *
     * @return array{folio: string, estatus: string, fecha: string, destino: ?string, observaciones: ?string, origen: string,
     *               partidas: list<array{partida: int, clave: ?string, articulo: string, cantidad: float, unidad: ?string, peso_unitario: float, notas: ?string}>}|null
     */
    public function leer(string $folio): ?array;

    /**
     * Folios de los traspasos capturados directo en Microsip (no los que genera Expertos) desde esa fecha,
     * sin cancelados. Son los que entran solos como pedido.
     *
     * @return list<string>
     */
    public function deMicrosipDesde(string $fecha): array;
}
