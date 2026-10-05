<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Acceso por nombre de usuario. No hay registro público: los usuarios los da de alta el administrador.
Route::middleware('guest')->group(function () {
    Volt::route('login', 'auth.login')->name('login');
});

Route::post('logout', App\Livewire\Actions\Logout::class)->name('logout');