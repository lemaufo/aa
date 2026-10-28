<?php

use App\Livewire\Concerns\RegistraAcuse;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

/** La sucursal entrega al cliente. Escanea la etiqueta y registra el acuse. */
new class extends Component {
    use RegistraAcuse;

    public Pedido $pedido;

    public string $codigo = '';

    public string $recibe_nombre = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    public function simular(): void
    {
        $this->codigo = $this->pedido->folio;
        $this->resetErrorBag('codigo');
    }

    public function entregar(FlujoService $flujo): void
    {
        $this->validate($this->reglasAcuse() + ['codigo' => ['required', 'string', 'max:60']], [], $this->atributosAcuse() + ['codigo' => 'código de la etiqueta']);

        if (strcasecmp(trim($this->codigo), $this->pedido->folio) !== 0) {
            $this->addError('codigo', 'Ese código no es de este pedido. El folio es '.$this->pedido->folio.'.');

            return;
        }

        $nombre = trim($this->recibe_nombre);

        try {
            $pedido = $flujo->aplicar($this->pedido, 'entregar_sucursal', auth()->user(), [
                'recibe_nombre' => $nombre,
                'acuse_path' => $this->guardarAcuse($this->pedido),
            ], 'Recibió '.$nombre.'. Acuse con foto');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast',
            tipo: $pedido->venta_sync_estado === 'ok' ? 'ok' : 'error',
            mensaje: $pedido->venta_sync_estado === 'ok'
                ? 'Pedido entregado y venta actualizada en Microsip.'
                : 'Pedido entregado, pero no se pudo actualizar Microsip. Reintenta desde el pedido.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Entrega el pedido al cliente</h2>
    </div>

    <form wire:submit="entregar" class="grid gap-5 px-5 py-5">
        @php($avisoWhatsApp = $pedido->urlWhatsApp($pedido->mensajeListoEnSucursal()))

        <div class="flex flex-wrap items-center gap-3">
            @if ($avisoWhatsApp)
                <a href="{{ $avisoWhatsApp }}" target="_blank" rel="noopener" class="btn btn-secundario">
                    <x-aa.icon name="chat-bubble-left-ellipsis" /> Avisar al cliente por WhatsApp
                </a>
                <span class="text-[15px] text-acero">Abre WhatsApp con el mensaje ya escrito. Tú decides si lo envías.</span>
            @else
                <span class="text-[15px] text-acero">Este pedido no tiene un teléfono válido para avisar por WhatsApp.</span>
            @endif
        </div>

        <p class="text-acero">Escanea la etiqueta, escribe quién recibe y sube la foto del acuse firmado.</p>

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

        <x-aa.campo label="Nombre completo de quien recibe" for="recibe_nombre" :error="$errors->first('recibe_nombre')">
            <input id="recibe_nombre" type="text" wire:model="recibe_nombre" class="input" autocomplete="off"
                   @error('recibe_nombre') aria-invalid="true" @enderror>
        </x-aa.campo>

        <x-aa.subir label="Foto del acuse firmado" model="foto_acuse" :archivo="$foto_acuse" :error="$errors->first('foto_acuse')"
                    ayuda="Documento con la firma de quien recibe. Se guarda 2 años." />

        <p class="text-[15px] text-acero">Al marcarlo entregado, la venta {{ $pedido->folio_venta }} se actualiza sola en Microsip.</p>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="entregar,foto_acuse">Marcar entregado</button>
        </div>
    </form>
</section>