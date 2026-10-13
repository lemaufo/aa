<?php

use App\Enums\FormaEntrega;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

new class extends Component {
    public Pedido $pedido;

    public string $camion = '';

    public string $repartidor = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->camion = (string) $pedido->camion;
        $this->repartidor = (string) $pedido->repartidor;
    }

    public function despachar(FlujoService $flujo): void
    {
        $forma = $this->pedido->forma_entrega;
        $cambios = [];
        $detalle = null;

        if ($forma === FormaEntrega::Cliente) {
            $this->validate([
                'camion' => ['required', 'string', 'max:80'],
                'repartidor' => ['required', 'string', 'max:80'],
            ]);

            $cambios = ['camion' => trim($this->camion), 'repartidor' => trim($this->repartidor)];
            $detalle = $cambios['camion'].', '.$cambios['repartidor'];
        }

        $titulo = match ($forma) {
            FormaEntrega::Cedis => 'Listo en CEDIS para entrega',
            FormaEntrega::Cliente => 'Enviado con repartidor',
            default => 'Enviado a '.$this->pedido->sucursal,
        };

        try {
            $flujo->aplicar($this->pedido, 'despachar', auth()->user(), $cambios, $detalle, $titulo);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: $titulo.'.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Define cómo sale el pedido</h2>
        @if ($pedido->forma_entrega)
            <span class="rounded-full bg-plancha px-3 py-1 text-[15px] font-semibold">{{ $pedido->forma_entrega->label() }}</span>
        @endif
    </div>

    <form wire:submit="despachar" class="grid gap-5 px-5 py-5">
        @if ($pedido->forma_entrega === \App\Enums\FormaEntrega::Cliente)
            <p class="text-acero">El pedido sale con repartidor al domicilio del cliente. Registra quién lo lleva.</p>

            <div class="rounded-md bg-plancha px-4 py-3">
                <p class="text-[15px] font-semibold">Dónde se entrega, según la caja</p>
                @if ($pedido->recibe_nombre)
                    <p class="mt-0.5">Recibe {{ $pedido->recibe_nombre }}{{ $pedido->telefono ? ', '.$pedido->telefono : '' }}</p>
                @endif
                @if ($pedido->direccion_entrega)
                    <p>{{ $pedido->direccion_entrega }}</p>
                @endif
                @if ($pedido->gps_link)
                    <a href="{{ $pedido->gps_link }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1.5 font-semibold text-naranja-fuerte underline-offset-4 hover:underline">
                        <x-aa.icon name="map-pin" class="size-4" /> Abrir ubicación en el mapa
                    </a>
                @endif
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-aa.campo label="Camión" for="camion" :error="$errors->first('camion')">
                    <input id="camion" type="text" wire:model="camion" class="input" placeholder="Camión 07, placas RK-4521-A"
                           autocomplete="off" @error('camion') aria-invalid="true" @enderror>
                </x-aa.campo>
                <x-aa.campo label="Repartidor" for="repartidor" :error="$errors->first('repartidor')">
                    <input id="repartidor" type="text" wire:model="repartidor" class="input" placeholder="Nombre del repartidor"
                           autocomplete="off" @error('repartidor') aria-invalid="true" @enderror>
                </x-aa.campo>
            </div>
        @elseif ($pedido->forma_entrega === \App\Enums\FormaEntrega::Cedis)
            <p class="text-acero">El cliente recoge en CEDIS. Deja el pedido listo y cuando llegue se valida su identidad.</p>

            @if ($pedido->ubicacion_cedis)
                <div class="flex items-center gap-3 rounded-md bg-plancha px-4 py-3">
                    <x-aa.icon name="map-pin" class="text-acero" />
                    <p><span class="text-[15px] text-acero">El material está en la zona</span> <span class="font-semibold">{{ $pedido->ubicacion_cedis }}</span></p>
                </div>
            @endif
        @else
            <p class="text-acero">El pedido va a <span class="font-semibold text-tinta">{{ $pedido->sucursal }}</span>, que hizo la venta. Ahí lo reciben y lo entregan al cliente.</p>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="despachar">
                @if ($pedido->forma_entrega === \App\Enums\FormaEntrega::Cliente)
                    Enviar con repartidor
                @elseif ($pedido->forma_entrega === \App\Enums\FormaEntrega::Cedis)
                    Dejar listo en CEDIS
                @else
                    Enviar a {{ $pedido->sucursal }}
                @endif
            </button>
        </div>
    </form>
</section>