<?php

namespace App\Services\Firebird;

use App\Contracts\VentasMicrosip;
use LogicException;

/**
 * Pendiente de la fase de integración: se escribe cuando se tenga el metadata de la base
 * (ticket en DOCTOS_PV, y cómo marca hoy la venta como entregada "Control de Entregas").
 * Recomendado: usuario Firebird propio con permisos mínimos, solo sobre lo que se escribe.
 */
class FirebirdVentas implements VentasMicrosip
{
    public function consultar(string $folio): ?array
    {
        throw new LogicException('FirebirdVentas::consultar pendiente de la integración con Microsip.');
    }

    public function marcarEntregada(string $folio, string $usuario, ?string $recibe): void
    {
        throw new LogicException('FirebirdVentas::marcarEntregada pendiente de la integración con Microsip.');
    }
}