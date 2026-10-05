<?php

use App\Models\Pedido;
use App\Support\Flujo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Pedido')] class extends Component {
    public Pedido $pedido;

    public function mount(Pedido $pedido): void
    {
        abort_unless($pedido->esVisiblePara(auth()->user()), 403);

        $this->pedido = $pedido;
    }

    /** Los paneles de acción avisan con este evento cuando cambian el pedido. */
    #[On('pedido-actualizado')]
    public function refrescar(): void
    {
        $this->pedido->refresh();
    }

    #[Computed]
    public function panel(): ?string
    {
        return Flujo::panelPara(auth()->user(), $this->pedido);
    }

    #[Computed]
    public function puedeCancelar(): bool
    {
        return Flujo::puede(auth()->user(), 'cancelar', $this->pedido);
    }

    #[Computed]
    public function eventos()
    {
        return $this->pedido->eventos()->with('user')->latest('created_at')->latest('id')->get();
    }

    #[Computed]
    public function responsable(): ?string
    {
        return Flujo::siguienteResponsable($this->pedido)?->label();
    }

    /** Líneas "piezas × medida" que se muestran en los datos del pedido. */
    public function piezas(): array
    {
        $cortes = $this->pedido->cortes_producidos ?? $this->pedido->cortes_solicitados ?? [];

        return collect($cortes)
            ->map(fn ($c) => $c['piezas'].' × '.number_format($c['medida'], 2).' m')
            ->all();
    }
}; ?>

<div class="grid gap-6">
    <a href="{{ route('pedidos.index') }}" wire:navigate class="inline-flex w-fit items-center gap-1.5 text-[15px] font-semibold text-acero hover:text-tinta">
        <x-aa.icon name="arrow-left" class="size-4" /> Pedidos
    </a>

    <header class="grid gap-5">
        <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
            <div>
                <h1 class="folio text-[46px] leading-none sm:text-[64px]">{{ $pedido->folio }}</h1>
                <p class="mt-2 text-xl font-semibold">{{ $pedido->cliente }}</p>
                <p class="text-acero">{{ $pedido->sucursal }}</p>
            </div>
            <x-aa.badge :estatus="$pedido->estatus" class="text-[18px]" />
        </div>

        <section class="panel px-5 pb-4 pt-5" aria-label="Avance del pedido">
            <x-aa.regla :estatus="$pedido->estatus" />
        </section>
    </header>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
        <div class="grid gap-6">

            {{-- Lo que le toca hacer a este usuario --}}
            @if ($this->panel)
                @livewire($this->panel, ['pedido' => $pedido], key($this->panel.'-'.$pedido->estatus->value.'-'.($pedido->captura_validada_at ? 'c' : 'x').'-'.($pedido->venta_cerrada ? 'v' : 'n')))
            @elseif ($pedido->estatus === \App\Enums\Estatus::Cancelado)
                <section class="panel border-peligro bg-peligro-tinte px-5 py-5">
                    <h2 class="panel-titulo text-peligro">Pedido cancelado</h2>
                    <p class="mt-1">{{ $pedido->motivo_cancelacion ?: 'No se registró un motivo.' }}</p>
                    <p class="mt-1 text-[15px] text-acero">Ya no avanza por el flujo. La bitácora conserva todo lo que pasó.</p>
                </section>
            @elseif ($pedido->estatus === \App\Enums\Estatus::Entregado && $pedido->venta_cerrada)
                <section class="panel border-ok bg-ok-tinte px-5 py-5">
                    <h2 class="panel-titulo text-ok">Pedido entregado y venta cerrada</h2>
                    <p class="mt-1 text-[15px] text-acero">No hay nada pendiente en este pedido.</p>
                </section>
            @else
                <section class="panel px-5 py-5">
                    <h2 class="panel-titulo">Sin acciones para ti en este momento</h2>
                    <p class="mt-1 text-acero">
                        @if ($this->responsable)
                            El siguiente paso le toca a <span class="font-semibold text-tinta">{{ $this->responsable }}</span>.
                        @endif
                        Con tu rol ({{ auth()->user()->rol->label() }}) puedes consultar el pedido y su bitácora.
                    </p>
                </section>
            @endif

            @if ($this->puedeCancelar)
                @livewire('aa.acciones.cancelar', ['pedido' => $pedido], key('cancelar-'.$pedido->estatus->value))
            @endif

            {{-- Datos del pedido --}}
            <section class="panel">
                <div class="panel-cabecera"><h2 class="panel-titulo">Datos del pedido</h2></div>
                <dl class="grid gap-x-8 gap-y-4 px-5 py-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-[15px] text-acero">Material</dt>
                        <dd class="font-semibold">{{ $pedido->material }}</dd>
                    </div>
                    <div>
                        <dt class="text-[15px] text-acero">Metros del traspaso</dt>
                        <dd class="font-semibold">{{ number_format($pedido->metros_traspaso, 2) }} m</dd>
                    </div>
                    <div>
                        <dt class="text-[15px] text-acero">Peso teórico</dt>
                        <dd class="font-semibold">
                            {{ number_format($pedido->pesoTeorico(), 2) }} kg
                            <span class="text-[15px] font-normal text-acero">({{ number_format($pedido->peso_por_metro, 3) }} kg por metro)</span>
                        </dd>
                    </div>
                    @if ($pedido->peso_real !== null)
                        <div>
                            <dt class="text-[15px] text-acero">Peso real en CEDIS</dt>
                            <dd class="font-semibold">
                                {{ number_format($pedido->peso_real, 2) }} kg
                                @php $d = $pedido->desviacion($pedido->peso_real); @endphp
                                <span class="text-[15px] font-normal text-acero">({{ $d > 0 ? '+' : '' }}{{ number_format($d, 2) }} %)</span>
                            </dd>
                        </div>
                    @endif
                    @if ($pedido->folio_venta)
                        <div>
                            <dt class="text-[15px] text-acero">Folio de venta</dt>
                            <dd class="folio text-xl">{{ $pedido->folio_venta }}</dd>
                        </div>
                    @endif
                    @if ($pedido->forma_entrega)
                        <div>
                            <dt class="text-[15px] text-acero">Forma de entrega</dt>
                            <dd class="font-semibold">{{ $pedido->forma_entrega->label() }}</dd>
                        </div>
                    @endif
                    @if (count($this->piezas()))
                        <div>
                            <dt class="text-[15px] text-acero">Piezas</dt>
                            <dd class="font-semibold">
                                @foreach ($this->piezas() as $linea)
                                    <span class="block">{{ $linea }}</span>
                                @endforeach
                            </dd>
                        </div>
                    @endif
                    @if ($pedido->direccion_entrega || $pedido->gps_link)
                        <div class="sm:col-span-2">
                            <dt class="text-[15px] text-acero">Dirección de entrega</dt>
                            <dd>
                                @if ($pedido->direccion_entrega)
                                    <span class="block font-semibold">{{ $pedido->direccion_entrega }}</span>
                                @endif
                                @if ($pedido->gps_link)
                                    <a href="{{ $pedido->gps_link }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1.5 font-semibold text-naranja-fuerte underline-offset-4 hover:underline">
                                        <x-aa.icon name="map-pin" class="size-4" /> Abrir ubicación en el mapa
                                    </a>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($pedido->camion || $pedido->repartidor)
                        <div class="sm:col-span-2">
                            <dt class="text-[15px] text-acero">Reparto</dt>
                            <dd class="font-semibold">{{ $pedido->camion }}{{ $pedido->camion && $pedido->repartidor ? ', ' : '' }}{{ $pedido->repartidor }}</dd>
                        </div>
                    @endif
                    @if ($pedido->recibe_nombre)
                        <div class="sm:col-span-2">
                            <dt class="text-[15px] text-acero">Recibió</dt>
                            <dd class="font-semibold">
                                {{ $pedido->recibe_nombre }}
                                @if ($pedido->documento_tipo)
                                    <span class="font-normal text-acero">({{ $pedido->documento_tipo }} {{ $pedido->documento_numero }})</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($pedido->instrucciones)
                        <div class="sm:col-span-2">
                            <dt class="text-[15px] text-acero">Notas de la requisición en Expertos</dt>
                            <dd>{{ $pedido->instrucciones }}</dd>
                        </div>
                    @endif
                    @if ($pedido->evidencia_path)
                        <div class="sm:col-span-2">
                            <dt class="text-[15px] text-acero">Evidencia de producción</dt>
                            <dd class="mt-1.5">
                                <img src="{{ Storage::disk('public')->url($pedido->evidencia_path) }}" alt="Foto de evidencia del pedido {{ $pedido->folio }}" class="max-h-64 rounded-md border border-linea object-cover">
                            </dd>
                        </div>
                    @endif
                </dl>
            </section>
        </div>

        {{-- Bitácora --}}
        <section class="panel" aria-labelledby="bitacora">
            <div class="panel-cabecera">
                <h2 id="bitacora" class="panel-titulo">Bitácora</h2>
                <p class="text-[15px] text-acero">{{ $this->eventos->count() }} {{ $this->eventos->count() === 1 ? 'movimiento' : 'movimientos' }}</p>
            </div>
            <ol class="px-5 py-5">
                @foreach ($this->eventos as $e)
                    <li class="relative grid grid-cols-[1.25rem_minmax(0,1fr)] gap-3 pb-6 last:pb-0" wire:key="evento-{{ $e->id }}">
                        <span class="relative flex justify-center">
                            <span @class(['mt-1.5 size-3 rounded-full', 'bg-naranja ring-4 ring-naranja/25' => $loop->first, 'bg-linea-fuerte' => ! $loop->first])></span>
                            @unless ($loop->last)
                                <span class="absolute -bottom-6 left-1/2 top-5 w-px -translate-x-1/2 bg-linea"></span>
                            @endunless
                        </span>
                        <div class="min-w-0">
                            <p class="font-semibold leading-snug">{{ $e->accion }}</p>
                            @if ($e->detalle)
                                <p class="text-[15px] text-tinta/80">{{ $e->detalle }}</p>
                            @endif
                            <p class="mt-0.5 text-[15px] text-acero">
                                {{ $e->user?->name ?? 'Sistema' }},
                                <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->translatedFormat('j M, H:i') }}</time>
                            </p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</div>