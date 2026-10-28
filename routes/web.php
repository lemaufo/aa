<?php

use App\Http\Controllers\DispositivoController;
use App\Http\Middleware\DispositivoAutorizado;
use App\Http\Middleware\SoloInterno;
use App\Models\Pedido;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

Route::redirect('/', '/inicio');

Route::middleware('auth')->group(function () {
    // Pantalla para autorizar este equipo con un código de un solo uso.
    // Queda fuera de DispositivoAutorizado: si no, un equipo nuevo nunca podría activarse.
    Route::get('dispositivo', [DispositivoController::class, 'mostrar'])->name('dispositivo.mostrar');
    Route::post('dispositivo', [DispositivoController::class, 'activar'])->name('dispositivo.activar');

    // Todo lo demás exige equipo autorizado (solo si AA_DISPOSITIVOS=true; el rol Consulta queda exento).
    Route::middleware(DispositivoAutorizado::class)->group(function () {
        // Rastreo por folio: lo usa el rol de Consulta (y cualquiera que ya tenga sesión).
        Volt::route('rastreo', 'aa.rastreo')->name('rastreo');

        // Pantallas internas: el rol de Consulta no entra, se va al rastreo.
        Route::middleware(SoloInterno::class)->group(function () {
            Volt::route('inicio', 'aa.inicio')->name('inicio');

            Volt::route('pedidos', 'aa.pedidos.index')->name('pedidos.index');
            Volt::route('pedidos/{pedido}', 'aa.pedidos.show')->name('pedidos.show');

            // Etiqueta imprimible (una por pedido, con el folio del traspaso).
            Route::get('pedidos/{pedido}/etiqueta', function (Pedido $pedido) {
                abort_unless($pedido->esVisiblePara(auth()->user()), 403);

                return view('aa.etiqueta', ['pedido' => $pedido]);
            })->name('pedidos.etiqueta');

            // Fotos y acuses: privadas, solo con sesión y solo del pedido que el usuario puede ver.
            Route::get('evidencias/{pedido}/{tipo}', function (Pedido $pedido, string $tipo) {
                abort_unless($pedido->esVisiblePara(auth()->user()), 403);

                $columna = Pedido::EVIDENCIAS[$tipo] ?? abort(404);
                $ruta = $pedido->{$columna};
                abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

                return Storage::disk('local')->response($ruta);
            })->name('evidencia');

            Volt::route('usuarios', 'aa.usuarios')->name('usuarios');

            // Catálogo de artículos de Microsip, solo lectura (solo administrador: la propia pantalla lo verifica).
            Volt::route('articulos', 'aa.articulos')->name('articulos');

            // Equipos autorizados (solo administrador: la propia pantalla lo verifica).
            Volt::route('dispositivos', 'aa.dispositivos')->name('dispositivos');
        });
    });
});

require __DIR__.'/auth.php';