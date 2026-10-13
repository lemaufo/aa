<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use App\Models\Dispositivo;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Solo deja pasar equipos autorizados (si AA_DISPOSITIVOS=true). El rol Consulta queda exento. */
class DispositivoAutorizado
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('aa.dispositivos.activo') || $request->routeIs('dispositivo.*')) {
            return $next($request);
        }

        if ($request->user()?->rol === Rol::Consulta) {
            return $next($request);
        }

        if (Dispositivo::autorizado($request->cookie(config('aa.dispositivos.cookie')))) {
            return $next($request);
        }

        return redirect()->route('dispositivo.mostrar');
    }
}