<?php

use App\Enums\Estatus;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

new class extends Component {
    public Pedido $pedido;

    public string $documento_tipo = 'INE';

    public string $documento_numero = '';

    public string $recibe_nombre = '';

    public bool $repartidor_ok = false;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function recoge(): bool
    {
        return $this->pedido->estatus === Estatus::EnCedis;
    }

    public function entregar(FlujoService $flujo): void
    {
        if ($this->recoge()) {
            $this->validate([
                'documento_tipo' => ['required', 'in:INE,Ticket de venta,Otro'],
                'documento_numero' => ['required', 'string', 'max:40'],
                'recibe_nombre' => ['required', 'string', 'max:120'],
            ]);

            $cambios = [
                'documento_tipo' => $this->documento_tipo,
                'documento_numero' => trim($this->documento_numero),
                'recibe_nombre' => trim($this->recibe_nombre),
            ];
            $detalle = 'Recibió '.$cambios['recibe_nombre'].' ('.$this->documento_tipo.' '.$cambios['documento_numero'].')';
        } else {
            $this->validate([
                'repartidor_ok' => ['accepted'],
                'recibe_nombre' => ['required', 'string', 'max:120'],
            ]);

            $cambios = ['recibe_nombre' => trim($this->recibe_nombre)];
            $detalle = 'Regresó '.$this->pedido->repartidor.'. Recibió '.$cambios['recibe_nombre'].'. Datos del repartidor y del camión verificados';
        }

        try {
            $flujo->aplicar($this->pedido, 'entregar_cedis', auth()->user(), $cambios, $detalle);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Pedido entregado. Se avisó a '.$this->pedido->sucursal.' para que cierre su venta.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">{{ $this->recoge() ? 'El cliente recoge en CEDIS' : 'Regresa el repartidor' }}</h2>
    </div>

    <form wire:submit="entregar" class="grid gap-5 px-5 py-5">
        @if ($this->recoge())
            <p class="text-acero">Pide una identificación o el ticket de venta y confirma que el pedido es el correcto.</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-aa.campo label="Documento que presenta" for="documento_tipo" :error="$errors->first('documento_tipo')">
                    <select id="documento_tipo" wire:model="documento_tipo" class="input">
                        <option>INE</option>
                        <option>Ticket de venta</option>
                        <option>Otro</option>
                    </select>
                </x-aa.campo>
                <x-aa.campo label="Número o folio del documento" for="documento_numero" :error="$errors->first('documento_numero')">
                    <input id="documento_numero" type="text" wire:model="documento_numero" class="input" autocomplete="off"
                           @error('documento_numero') aria-invalid="true" @enderror>
                </x-aa.campo>
            </div>
        @else
            <div class="rounded-md bg-plancha px-4 py-3">
                <p class="text-[15px] font-semibold">Datos de reparto</p>
                <p class="mt-0.5">{{ $pedido->camion }}</p>
                <p>{{ $pedido->repartidor }}</p>
            </div>

            <div class="grid gap-1.5">
                <label class="flex items-start gap-3">
                    <input type="checkbox" wire:model="repartidor_ok" class="check mt-0.5 shrink-0">
                    <span>Confirmo que el repartidor y el camión son los que salieron con este pedido.</span>
                </label>
                @error('repartidor_ok') <p class="text-[15px] font-medium text-peligro" role="alert">{{ $message }}</p> @enderror
            </div>
        @endif

        <x-aa.campo label="Nombre de quien recibe" for="recibe_nombre" :error="$errors->first('recibe_nombre')">
            <input id="recibe_nombre" type="text" wire:model="recibe_nombre" class="input" autocomplete="off"
                   @error('recibe_nombre') aria-invalid="true" @enderror>
        </x-aa.campo>

        <p class="text-[15px] text-acero">Al marcarlo entregado, la sucursal {{ $pedido->sucursal }} recibe el aviso para cerrar su venta.</p>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="entregar">
                Marcar entregado
            </button>
        </div>
    </form>
</section>