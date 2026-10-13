<?php

namespace App\Contracts;

/** Lectura del traspaso en AdminExpertos. La app nunca cancela: solo lee lo que Expertos ya cancelo. */
interface TraspasosExpertos
{
    public function estaCancelado(string $folio): bool;
}