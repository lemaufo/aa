<?php

namespace App\Http\Middleware;

use App\Enums\Rol;
use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use App\Services\MacRed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo deja pasar dispositivos autorizados y activos (si AA_DISPOSITIVOS=true). El rol Consulta queda exento.
 * Un dispositivo suspendido, dado de baja o eliminado, o cuya MAC en la red local no coincide con la registrada, se
 * bloquea y queda en la bitácora de dispositivos (una vez cada pocos minutos, para no llenarla en cada clic).
 */
class DispositivoAutorizado
{
    public function __construct(private MacRed $red) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('aa.dispositivos.activo') || $request->routeIs('dispositivo.*')) {
            return $next($request);
        }

        if ($request->user()?->rol === Rol::Consulta) {
            return $next($request);
        }

        $dispositivo = Dispositivo::porLlave($request->cookie(config('aa.dispositivos.cookie')));

        if ($dispositivo === null) {
            return redirect()->route('dispositivo.mostrar');
        }

        if (! $dispositivo->operable()) {
            $this->anotarBloqueo($dispositivo, $request, $dispositivo->motivoBloqueo());

            return redirect()->route('dispositivo.mostrar')->with('bloqueo', $dispositivo->avisoBloqueo());
        }

        if (config('aa.dispositivos.validar_mac') && $dispositivo->mac && ($vista = $this->red->de($request->ip())) && $vista !== $dispositivo->mac) {
            $this->anotarBloqueo($dispositivo, $request, 'MAC no coincide', "Registrada {$dispositivo->mac}, vista en la red {$vista}");

            return redirect()->route('dispositivo.mostrar')
                ->with('bloqueo', 'La llave de este navegador es de otro dispositivo. Pide al administrador un código para este equipo.');
        }

        $dispositivo->marcarUso();

        return $next($request);
    }

    private function anotarBloqueo(Dispositivo $dispositivo, Request $request, string $accion, ?string $detalle = null): void
    {
        if (Cache::add("dispositivo-bloqueo:{$dispositivo->id}:{$accion}", true, now()->addMinutes(10))) {
            DispositivoEvento::anotar($dispositivo, $request->user(), $accion, $detalle ?? 'Ruta '.$request->path());
        }
    }
}
