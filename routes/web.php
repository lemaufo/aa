<?php

use App\Models\Pedido;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/inicio');

Route::middleware('auth')->group(function () {
    Volt::route('inicio', 'aa.inicio')->name('inicio');

    Volt::route('pedidos', 'aa.pedidos.index')->name('pedidos.index');
    Volt::route('pedidos/{pedido}', 'aa.pedidos.show')->name('pedidos.show');

    // Etiqueta imprimible (una por pedido, con el folio del traspaso).
    Route::get('pedidos/{pedido}/etiqueta', function (Pedido $pedido) {
        abort_unless($pedido->esVisiblePara(auth()->user()), 403);

        return view('aa.etiqueta', ['pedido' => $pedido]);
    })->name('pedidos.etiqueta');

    Volt::route('usuarios', 'aa.usuarios')->name('usuarios');
});

require __DIR__.'/auth.php';