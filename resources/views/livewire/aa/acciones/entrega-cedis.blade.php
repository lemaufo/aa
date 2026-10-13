<?php

use App\Enums\Estatus;
use App\Livewire\Concerns\RegistraAcuse;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;

/** Entrega desde CEDIS (el cliente recoge, o regresa el repartidor). El acuse es obligatorio en ambos casos. */
new class extends Component {
    use RegistraAcuse;

    public Pedido $pedido;

    public string $recibe_nombre = '';

    public bool $repartidor_ok = false;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        // La caja ya capturó quién recibe en los envíos a domicilio: aquí solo se confirma o se corrige.
        $this->recibe_nombre = (string) $pedido->recibe_nombre;
    }

    public function recoge(): bool
    {
        return $this->pedido->estatus === Estatus::EnCedis;
    }

    public function entregar(FlujoService $flujo): void
    {
        $reglas = $this->reglasAcuse();
        if (! $this->recoge()) {
            $reglas['repartidor_ok'] = ['accepted'];
        }

        $this->validate($reglas, [], $this->atributosAcuse() + ['repartidor_ok' => 'confirmación del repartidor']);

        $nombre = trim($this->recibe_nombre);
        $cambios = ['recibe_nombre' => $nombre, 'acuse_path' => $this->guardarAcuse($this->pedido)];

        $detalle = $this->recoge()
            ? 'Recibió '.$nombre.'. Acuse con foto'
            : 'Regresó '.$this->pedido->repartidor.'. Recibió '.$nombre.'. Acuse con foto';

        try {
            $pedido = $flujo->aplicar($this->pedido, 'entregar_cedis', auth()->user(), $cambios, $detalle);
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
        <h2 class="panel-titulo">{{ $this->recoge() ? 'El cliente recoge en CEDIS' : 'Regresa el repartidor' }}</h2>
    </div>

    <form wire:submit="entregar" class="grid gap-5 px-5 py-5">
        @if ($pedido->ubicacion_cedis)
            <div class="flex items-center gap-3 rounded-md border border-naranja bg-naranja-tinte px-4 py-3">
                <x-aa.icon name="map-pin" class="size-6 text-naranja-fuerte" />
                <p>
                    <span class="block text-[15px] text-acero">El material está en la zona</span>
                    <span class="font-display text-2xl font-semibold leading-tight">{{ $pedido->ubicacion_cedis }}</span>
                </p>
            </div>
        @endif

        @if ($this->recoge())
            @php($avisoWhatsApp = $pedido->urlWhatsApp($pedido->mensajeListoEnCedis()))

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

            <p class="text-acero">Confirma que el pedido es el correcto. Solo se necesita el nombre de quien recibe y la foto del acuse firmado.</p>
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

        <x-aa.campo label="Nombre completo de quien recibe" for="recibe_nombre" :error="$errors->first('recibe_nombre')">
            <input id="recibe_nombre" type="text" wire:model="recibe_nombre" class="input" autocomplete="off"
                   @error('recibe_nombre') aria-invalid="true" @enderror>
        </x-aa.campo>

        <x-aa.subir label="Foto del acuse firmado" model="foto_acuse" :archivo="$foto_acuse" :error="$errors->first('foto_acuse')"
                    ayuda="Documento con la firma de quien recibe. Se guarda 2 años." />

        <p class="text-[15px] text-acero">Al marcarlo entregado, la venta {{ $pedido->folio_venta }} se actualiza sola en Microsip.</p>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="entregar,foto_acuse">
                Marcar entregado
            </button>
        </div>
    </form>
</section>