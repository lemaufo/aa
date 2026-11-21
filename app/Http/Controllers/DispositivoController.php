<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use App\Services\MacRed;
use App\Support\CodigoInicial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DispositivoController extends Controller
{
    public function mostrar()
    {
        return view('aa.dispositivo');
    }

    public function activar(Request $request, MacRed $red)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:64']]);

        $llave = 'dispositivo:'.$request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            return back()->withErrors(['codigo' => 'Demasiados intentos. Espera un minuto.']);
        }
        RateLimiter::hit($llave, 60);

        $codigo = (string) $request->string('codigo');

        if (CodigoInicial::coincide($codigo)) {
            $usuario = $request->user();

            if (! $usuario->esSysdba()) {
                DispositivoEvento::anotar(null, $usuario, 'Código inicial rechazado',
                    "{$usuario->username} ({$usuario->rol->label()}) intentó usar el código inicial; solo es para SYSDBA");

                return back()->withErrors(['codigo' => 'Este código solo lo puede usar SYSDBA.']);
            }

            if (CodigoInicial::usado()) {
                DispositivoEvento::anotar(null, $usuario, 'Código inicial rechazado', 'Ya se había usado');

                return back()->withErrors(['codigo' => 'El código inicial ya se usó. Para autorizar otra máquina, pon uno nuevo en el .env del servidor o pide un código al administrador.']);
            }

            return $this->conLlave($request, CodigoInicial::canjear($usuario, $red->de($request->ip())));
        }

        $token = Dispositivo::activar($codigo);

        if ($token === null) {
            return back()->withErrors(['codigo' => 'El código no es válido o ya venció. Pide uno nuevo al administrador.']);
        }

        return $this->conLlave($request, $token);
    }

    /** Dos años, solo HTTP, para que ningún script de la página lo lea. */
    private function conLlave(Request $request, string $token)
    {
        return redirect()->route('inicio')->withCookie(
            cookie(config('aa.dispositivos.cookie'), $token, 60 * 24 * 730, '/', null, $request->isSecure(), true, false, 'Lax')
        );
    }
}
