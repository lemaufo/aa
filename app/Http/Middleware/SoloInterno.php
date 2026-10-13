<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El rol de Consulta solo rastrea pedidos por folio: no entra a las pantallas internas
 * (lista de pedidos, detalle, usuarios). Lo regresa al rastreo.
 */
class SoloInterno
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->rol === Rol::Consulta) {
            return redirect()->route('rastreo');
        }

        return $next($request);
    }
}