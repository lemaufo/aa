<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

/** El pedido ya está entregado pero Microsip no recibió la actualización de la venta. */
new class extends Component {
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function reintentar(FlujoService $flujo): void
    {
        try {
            $pedido = $flujo->aplicar($this->pedido, 'reintentar_venta', auth()->user());
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast',
            tipo: $pedido->venta_sync_estado === 'ok' ? 'ok' : 'error',
            mensaje: $pedido->venta_sync_estado === 'ok' ? 'Venta actualizada en Microsip.' : 'Microsip sigue sin responder. Intenta de nuevo en unos minutos.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion border-peligro">
    <div class="panel-cabecera">
        <h2 class="panel-titulo text-peligro">La venta no se actualizó en Microsip</h2>
    </div>

    <div class="grid gap-4 px-5 py-5">
        <p>El pedido ya está entregado y el acuse quedó guardado, pero la venta <span class="folio">{{ $pedido->folio_venta }}</span> no se marcó como entregada.</p>
        @if ($pedido->venta_sync_error)
            <p class="rounded-md bg-plancha px-4 py-3 text-[15px] text-acero">Detalle: {{ $pedido->venta_sync_error }}</p>
        @endif
        <div class="flex justify-end">
            <button type="button" wire:click="reintentar" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="reintentar">
                <span wire:loading.remove wire:target="reintentar">Reintentar ahora</span>
                <span wire:loading wire:target="reintentar">Reintentando...</span>
            </button>
        </div>
    </div>
</section>