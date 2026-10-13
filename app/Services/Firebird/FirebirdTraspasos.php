<?php

namespace App\Services\Firebird;

use App\Contracts\TraspasosExpertos;
use LogicException;

/** Pendiente de la fase de integración (tablas propias de AdminExpertos). Solo lectura. */
class FirebirdTraspasos implements TraspasosExpertos
{
    public function estaCancelado(string $folio): bool
    {
        throw new LogicException('FirebirdTraspasos::estaCancelado pendiente de la integración con AdminExpertos.');
    }
}