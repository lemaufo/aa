<?php

use App\Enums\FormaEntrega;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

/** CEDIS recibe: escanea la etiqueta y anota la zona del patio. El pesaje se hace en producción. */
new class extends Component {
    public Pedido $pedido;

    public string $codigo = '';

    public string $ubicacion_cedis = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->ubicacion_cedis = (string) $pedido->ubicacion_cedis;
    }

    public function recogeEnCedis(): bool
    {
        return $this->pedido->forma_entrega === FormaEntrega::Cedis;
    }

    public function validar(FlujoService $flujo): void
    {
        $this->validate([
            'codigo' => ['required', 'string', 'max:60'],
            'ubicacion_cedis' => [$this->recogeEnCedis() ? 'required' : 'nullable', 'string', 'max:80'],
        ], [], ['codigo' => 'el código de la etiqueta']);

        if (strcasecmp(trim($this->codigo), $this->pedido->folio) !== 0) {
            $this->addError('codigo', 'Ese código no es de este pedido. El folio es '.$this->pedido->folio.'.');

            return;
        }

        $ubicacion = trim($this->ubicacion_cedis) ?: null;

        try {
            $flujo->aplicar($this->pedido, 'validar', auth()->user(), [
                'ubicacion_cedis' => $ubicacion,
            ], $ubicacion ? 'Zona del patio: '.$ubicacion : null);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Pedido validado. Define ahora cómo sale.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

@php($forma = $pedido->forma_entrega)

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Recibe y valida</h2>
    </div>

    <form wire:submit="validar" class="grid gap-6 px-5 py-5">
        <x-aa.codigo-etiqueta :folio="$pedido->folio" :error="$errors->first('codigo')" />

        {{-- La forma de entrega la definió la caja. Si hay que corregirla, la caja la modifica con su contraseña. --}}
        @if ($forma)
            <div class="rounded-md bg-plancha px-4 py-3">
                <span class="block text-[15px] text-acero">Forma de entrega, definida en caja</span>
                <span class="font-semibold">{{ $forma->label() }}</span>
            </div>
        @endif

        <x-aa.campo label="Zona del patio en CEDIS" for="ubicacion_cedis" :error="$errors->first('ubicacion_cedis')"
                    :ayuda="$this->recogeEnCedis() ? 'Obligatoria: el cliente recoge aquí y hay que encontrar el material.' : 'Opcional: sirve para ubicar el material mientras sale.'">
            <input id="ubicacion_cedis" type="text" wire:model="ubicacion_cedis" class="input" placeholder="Zona B, junto al portón 2" autocomplete="off"
                   @error('ubicacion_cedis') aria-invalid="true" @enderror>
        </x-aa.campo>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="validar">
                Validar pedido
            </button>
        </div>
    </form>
</section>