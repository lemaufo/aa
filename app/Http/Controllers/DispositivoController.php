<?php

namespace App\Http\Controllers;

use App\Models\Dispositivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DispositivoController extends Controller
{
    public function mostrar()
    {
        return view('aa.dispositivo');
    }

    public function activar(Request $request)
    {
        $request->validate(['codigo' => ['required', 'string', 'max:20']], [], ['codigo' => 'código']);

        $llave = 'dispositivo:'.$request->ip();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            return back()->withErrors(['codigo' => 'Demasiados intentos. Espera un minuto.']);
        }
        RateLimiter::hit($llave, 60);

        $token = Dispositivo::activar($request->string('codigo'));

        if ($token === null) {
            return back()->withErrors(['codigo' => 'El código no es válido o ya venció. Pide uno nuevo al administrador.']);
        }

        // Dos años, solo HTTP, para que ningún script de la página lo lea.
        return redirect()->route('inicio')->withCookie(
            cookie(config('aa.dispositivos.cookie'), $token, 60 * 24 * 730, '/', null, $request->isSecure(), true, false, 'Lax')
        );
    }
}