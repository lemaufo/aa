<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

new class extends Component {
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function cerrar(FlujoService $flujo): void
    {
        try {
            $flujo->aplicar($this->pedido, 'cerrar_venta', auth()->user(), ['venta_cerrada' => true],
                'Venta '.$this->pedido->folio_venta.' marcada como ENTREGADA en Microsip');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Venta cerrada. El pedido quedó completo.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Cierra la venta en Microsip</h2>
    </div>

    <div class="grid gap-5 px-5 py-5">
        <p class="text-acero">
            El pedido ya se entregó al cliente. En Microsip, cambia la venta de mostrador
            <span class="folio text-xl text-tinta">{{ $pedido->folio_venta }}</span> a ENTREGADO y después confírmalo aquí.
        </p>

        <div class="flex justify-end">
            <button type="button" wire:click="cerrar" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="cerrar">
                Ya cerré la venta en Microsip
            </button>
        </div>
    </div>
</section>