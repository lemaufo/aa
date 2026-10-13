<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

/**
 * Panel de "escanea la etiqueta": envío a CEDIS y recepción en sucursal.
 * Un lector de código de barras escribe el folio y manda Enter; en el celular se puede teclear.
 */
new class extends Component {
    public Pedido $pedido;

    public string $codigo = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    #[Computed]
    public function accion(): ?string
    {
        return Flujo::accionEscaneo($this->pedido);
    }

    public function textos(): array
    {
        return match ($this->accion) {
            'enviar_cedis' => [
                'titulo' => 'Despacha a CEDIS',
                'ayuda' => 'Escanea la etiqueta del pedido al sacarlo de producción.',
                'boton' => 'Confirmar envío a CEDIS',
                'ok' => 'Pedido enviado a CEDIS.',
            ],
            default => [
                'titulo' => 'Recibe el pedido en la sucursal',
                'ayuda' => 'Escanea la etiqueta al bajar el pedido del transporte.',
                'boton' => 'Confirmar recepción',
                'ok' => 'Pedido recibido en la sucursal. Ya puedes entregarlo al cliente.',
            ],
        };
    }

    /** Solo para pruebas sin lector: llena el código con el folio del pedido. */
    public function simular(): void
    {
        $this->codigo = $this->pedido->folio;
        $this->resetErrorBag('codigo');
    }

    public function confirmar(FlujoService $flujo): void
    {
        $this->validate(['codigo' => ['required', 'string', 'max:60']], [], ['codigo' => 'código de la etiqueta']);

        if (strcasecmp(trim($this->codigo), $this->pedido->folio) !== 0) {
            $this->addError('codigo', 'Ese código no es de este pedido. El folio es '.$this->pedido->folio.'.');

            return;
        }

        $textos = $this->textos();

        try {
            $flujo->aplicar($this->pedido, $this->accion, auth()->user());
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: $textos['ok']);
        $this->dispatch('pedido-actualizado');
    }
}; ?>

@php($t = $this->textos())

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">{{ $t['titulo'] }}</h2>
    </div>

    <form wire:submit="confirmar" class="grid gap-5 px-5 py-5">
        <p class="text-acero">{{ $t['ayuda'] }}</p>

        <x-aa.campo label="Código de la etiqueta" for="codigo" :error="$errors->first('codigo')">
            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1">
                    <x-aa.icon name="qr-code" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-acero" />
                    <input id="codigo" type="text" wire:model="codigo" class="input pl-11 font-display text-xl font-semibold"
                           placeholder="{{ $pedido->folio }}" autocomplete="off" autocapitalize="characters" spellcheck="false"
                           @error('codigo') aria-invalid="true" @enderror>
                </div>
                <button type="button" wire:click="simular" class="btn btn-secundario shrink-0">Usar folio</button>
            </div>
        </x-aa.campo>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="confirmar">
                {{ $t['boton'] }}
            </button>
        </div>
    </form>
</section>