<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Pedido $pedido;

    public $foto = null;

    public bool $impresa = false;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function confirmar(FlujoService $flujo): void
    {
        $this->validate(['foto' => ['required', 'image', 'max:6144']]);

        if (! $this->impresa) {
            $this->addError('impresa', 'Imprime la etiqueta antes de confirmar.');

            return;
        }

        $ruta = $this->foto->store('evidencias', 'public');
        $teorico = $this->pedido->pesoTeorico();

        try {
            $flujo->aplicar($this->pedido, 'etiquetar', auth()->user(), [
                'evidencia_path' => $ruta,
                'peso_teorico' => $teorico,
            ], 'Una etiqueta por pedido. Peso teórico '.number_format($teorico, 2).' kg');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Pedido producido. Falta despacharlo a CEDIS.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Etiqueta y evidencia</h2>
    </div>

    <form wire:submit="confirmar" class="grid gap-6 px-5 py-5">
        <div class="grid gap-1">
            <p class="text-[15px] text-acero">Peso teórico del pedido</p>
            <p class="folio text-5xl leading-none">{{ number_format($pedido->pesoTeorico(), 2) }} kg</p>
            <p class="text-[15px] text-acero">{{ number_format($pedido->metros_traspaso, 2) }} m × {{ number_format($pedido->peso_por_metro, 3) }} kg por metro. Es el peso que CEDIS validará en la báscula.</p>
        </div>

        <div class="grid gap-2">
            <p class="text-[15px] font-semibold">1. Imprime la etiqueta</p>
            <p class="text-[15px] text-acero">Es una etiqueta por pedido, con el folio del traspaso, las piezas y su código de barras.</p>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('pedidos.etiqueta', $pedido) }}" target="_blank" rel="noopener"
                   x-on:click="$wire.impresa = true" class="btn btn-secundario">
                    <x-aa.icon name="printer" /> Imprimir etiqueta
                </a>
                @if ($impresa)
                    <span class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-ok">
                        <x-aa.icon name="check-circle" class="size-5" /> Etiqueta abierta para imprimir
                    </span>
                @endif
            </div>
            @error('impresa') <p class="text-[15px] font-medium text-peligro" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-2">
            <p class="text-[15px] font-semibold">2. Sube la foto de evidencia</p>

            <input id="foto" type="file" accept="image/*" capture="environment" wire:model="foto" class="peer sr-only">
            <label for="foto"
                   class="flex cursor-pointer flex-col items-center gap-2 rounded-md border border-dashed border-linea-fuerte bg-plancha px-4 py-7 text-center hover:border-naranja-fuerte peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-naranja-fuerte">
                <x-aa.icon name="camera" class="size-7 text-acero" />
                <span class="font-semibold">{{ $foto ? 'Cambiar la foto' : 'Toma o elige una foto' }}</span>
                <span class="text-[15px] text-acero">Foto del material terminado, con la etiqueta a la vista.</span>
            </label>

            <p wire:loading wire:target="foto" class="text-[15px] text-acero">Subiendo la foto...</p>

            @if ($foto && $foto->isPreviewable())
                <img src="{{ $foto->temporaryUrl() }}" alt="Vista previa de la foto de evidencia" class="max-h-60 w-fit rounded-md border border-linea object-cover">
            @endif

            @error('foto') <p class="text-[15px] font-medium text-peligro" role="alert">{{ $message }}</p> @enderror
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" @disabled(! $impresa || ! $foto) wire:loading.attr="disabled" wire:target="confirmar">
                Confirmar pedido producido
            </button>
        </div>
    </form>
</section>