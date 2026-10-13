<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

/** SOLO DEMO (administrador): imita que Expertos o Microsip cancelaron algo, para probar la lectura de cancelaciones. */
new class extends Component {
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        abort_unless(auth()->user()->rol === \App\Enums\Rol::Admin && config('aa.origen') === 'demo', 403);
        $this->pedido = $pedido;
    }

    public function cancelarTraspaso(FlujoService $flujo): void
    {
        DB::table('demo_traspasos')->updateOrInsert(['folio' => $this->pedido->folio], ['cancelado' => true, 'updated_at' => now(), 'created_at' => now()]);
        $this->revisar($flujo);
    }

    public function cancelarTicket(FlujoService $flujo): void
    {
        DB::table('demo_ventas')->where('folio', $this->pedido->folio_venta)->update(['estado' => 'cancelada', 'updated_at' => now()]);
        $this->revisar($flujo);
    }

    private function revisar(FlujoService $flujo): void
    {
        $this->dispatch('toast', tipo: 'ok', mensaje: $flujo->revisarCancelacion($this->pedido->fresh())
            ? 'La app leyó la cancelación y canceló el pedido.' : 'Nada que cancelar en este estatus.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel border-dashed">
    <div class="panel-cabecera"><h2 class="panel-titulo">Solo demo: simular cancelación en Expertos</h2></div>
    <div class="flex flex-wrap gap-3 px-5 py-5">
        <button type="button" wire:click="cancelarTraspaso" class="btn btn-secundario">Cancelar el traspaso en Expertos</button>
        @if ($pedido->folio_venta)
            <button type="button" wire:click="cancelarTicket" class="btn btn-secundario">Cancelar el ticket en Microsip</button>
        @endif
    </div>
</section>