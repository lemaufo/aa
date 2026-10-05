<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

new class extends Component {
    public Pedido $pedido;

    public string $motivo = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function cancelar(FlujoService $flujo): void
    {
        $this->validate(['motivo' => ['required', 'string', 'min:5', 'max:200']]);

        $motivo = trim($this->motivo);

        try {
            $flujo->aplicar($this->pedido, 'cancelar', auth()->user(), ['motivo_cancelacion' => $motivo], $motivo);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Traspaso cancelado. Ya no avanza por el flujo.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel" x-data="{ abierto: false }">
    <button type="button" x-on:click="abierto = !abierto" :aria-expanded="abierto"
            class="flex w-full items-center justify-between gap-3 px-5 py-3.5 text-left font-semibold hover:bg-plancha/60">
        Registrar la cancelación del traspaso
        <x-aa.icon name="chevron-down" class="text-acero transition-transform" ::class="abierto && 'rotate-180'" />
    </button>

    <form wire:submit="cancelar" x-show="abierto" x-cloak class="grid gap-4 border-t border-linea px-5 py-5">
        <p class="text-[15px] text-acero">Solo se puede cancelar antes de producir. El pedido queda en CANCELADO y su bitácora se conserva.</p>

        <x-aa.campo label="Motivo" for="motivo" :error="$errors->first('motivo')">
            <textarea id="motivo" wire:model="motivo" rows="2" class="input" placeholder="Por qué se cancela"
                      @error('motivo') aria-invalid="true" @enderror></textarea>
        </x-aa.campo>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-peligro w-full sm:w-auto" wire:loading.attr="disabled" wire:target="cancelar">
                Cancelar traspaso
            </button>
        </div>
    </form>
</section>