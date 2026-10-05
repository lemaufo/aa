<?php

use App\Enums\FormaEntrega;
use App\Livewire\Concerns\ManejaCortes;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    use ManejaCortes;

    public Pedido $pedido;

    public string $folio_venta = '';

    public string $cliente = '';

    public string $forma_entrega = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->cliente = $pedido->cliente;
        $this->forma_entrega = $pedido->forma_entrega?->value ?? '';
        $this->iniciarCortes($pedido->cortes_solicitados);
    }

    public function ligar(FlujoService $flujo): void
    {
        $this->validate([
            'folio_venta' => ['required', 'string', 'max:30'],
            'cliente' => ['required', 'string', 'max:120'],
            'forma_entrega' => ['required', Rule::enum(FormaEntrega::class)],
        ] + $this->reglasCortes());

        $venta = trim($this->folio_venta);

        try {
            $flujo->aplicar($this->pedido, 'ligar', auth()->user(), [
                'folio_venta' => $venta,
                'cliente' => trim($this->cliente),
                'forma_entrega' => $this->forma_entrega,
                'cortes_solicitados' => $this->cortesLimpios(),
            ], "Venta {$venta}, ".FormaEntrega::from($this->forma_entrega)->label());
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Traspaso ligado a la venta. Producción ya puede tomarlo.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Liga el traspaso con la venta</h2>
    </div>

    <form wire:submit="ligar" class="grid gap-5 px-5 py-5">
        @if ($pedido->instrucciones)
            <div class="rounded-md bg-plancha px-4 py-3">
                <p class="text-[15px] font-semibold">Lo que se escribió en Expertos</p>
                <p class="mt-0.5 text-acero">{{ $pedido->instrucciones }}</p>
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-aa.campo label="Folio de venta (ticket)" for="folio_venta" :error="$errors->first('folio_venta')">
                <input id="folio_venta" type="text" wire:model="folio_venta" class="input" placeholder="V-118204"
                       autocomplete="off" @error('folio_venta') aria-invalid="true" @enderror>
            </x-aa.campo>

            <x-aa.campo label="Cliente" for="cliente" :error="$errors->first('cliente')">
                <input id="cliente" type="text" wire:model="cliente" class="input" autocomplete="off"
                       @error('cliente') aria-invalid="true" @enderror>
            </x-aa.campo>
        </div>

        <x-aa.campo label="Forma de entrega" for="forma_entrega" :error="$errors->first('forma_entrega')">
            <select id="forma_entrega" wire:model="forma_entrega" class="input" @error('forma_entrega') aria-invalid="true" @enderror>
                <option value="">Elige una opción</option>
                @foreach (\App\Enums\FormaEntrega::cases() as $forma)
                    <option value="{{ $forma->value }}">{{ $forma->label() }}</option>
                @endforeach
            </select>
        </x-aa.campo>

        <x-aa.cortes :cortes="$cortes" :total="$this->totalMetros()" :meta="$pedido->metros_traspaso"
                     titulo="Piezas y medidas del pedido" :errores="$this->erroresCortes()" />

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="ligar">
                <span wire:loading.remove wire:target="ligar">Ligar traspaso y venta</span>
                <span wire:loading wire:target="ligar">Guardando...</span>
            </button>
        </div>
    </form>
</section>