<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\TraspasosExpertos;
use App\Contracts\VentasMicrosip;
use App\Http\Middleware\DispositivoAutorizado;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         // Con AA_ORIGEN=firebird se usan las clases de Microsip/Expertos en vez de las tablas demo.
        $demo = config('aa.origen', 'demo') === 'demo';

        $this->app->bind(VentasMicrosip::class, $demo ? \App\Services\Demo\DemoVentas::class : \App\Services\Firebird\FirebirdVentas::class);
        $this->app->bind(TraspasosExpertos::class, $demo ? \App\Services\Demo\DemoTraspasos::class : \App\Services\Firebird\FirebirdTraspasos::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Para que las acciones de Livewire (no solo la carga de la página) también exijan equipo autorizado.
        Livewire::addPersistentMiddleware([DispositivoAutorizado::class]);
    }
}
