<?php

use App\Models\Pedido;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/**
 * Rastreo por folio, pensado para que el cliente consulte su pedido sin ver nada más:
 * no hay lista de pedidos, la búsqueda es por folio exacto y no se muestran nombres
 * del personal, pesos, ventas ni evidencias.
 */
new #[Title('Rastreo')] class extends Component {
    #[Url(as: 'folio')]
    public string $consulta = '';

    public string $folio = '';

    public function mount(): void
    {
        $this->folio = $this->consulta;
    }

    public function buscar(): void
    {
        $this->consulta = Str::upper(trim($this->folio));
        $this->folio = $this->consulta;
    }

    public function limpiar(): void
    {
        $this->consulta = '';
        $this->folio = '';
    }

    #[Computed]
    public function pedido(): ?Pedido
    {
        return $this->consulta === '' ? null : Pedido::where('folio', $this->consulta)->first();
    }

    public function piezas(Pedido $pedido): array
    {
        return collect($pedido->cortes_solicitados ?? [])
            ->map(fn ($c) => $c['piezas'].' × '.number_format($c['medida'], 2).' m')
            ->all();
    }
}; ?>

<div class="mx-auto grid max-w-3xl gap-8">
    <header class="grid gap-1.5">
        <h1 class="font-display text-[42px] font-semibold leading-none sm:text-5xl">Rastrea tu pedido</h1>
        <p class="max-w-prose text-acero">Escribe o escanea el folio de tu traspaso, el que aparece en tu etiqueta, para ver en qué paso va.</p>
    </header>

    <form wire:submit="buscar" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
        <div class="relative">
            <label for="folio" class="sr-only">Folio del pedido</label>
            <x-aa.icon name="qr-code" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-acero" />
            <input id="folio" type="text" wire:model="folio" class="input min-h-14 pl-11 font-display text-2xl font-semibold"
                   placeholder="TSA0004821" autocomplete="off" autocapitalize="characters" spellcheck="false" autofocus>
        </div>
        <button type="submit" class="btn btn-primario min-h-14 px-8 text-[18px]" wire:loading.attr="disabled" wire:target="buscar">
            Buscar
        </button>
    </form>

    @if ($consulta !== '')
        @if (! $this->pedido)
            <section class="panel px-5 py-8 text-center" role="status">
                <h2 class="panel-titulo">No encontramos ese folio</h2>
                <p class="mt-1 text-acero">Revisa que esté completo, con las letras y todos los números, por ejemplo TSA0004821.</p>
                <button type="button" wire:click="limpiar" class="btn btn-secundario mt-4">Buscar otro folio</button>
            </section>
        @else
            @php($p = $this->pedido)

            <section class="grid gap-5" aria-label="Resultado del rastreo">
                <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-3">
                    <div>
                        <p class="text-[15px] text-acero">Folio</p>
                        <h2 class="folio text-[44px] leading-none sm:text-6xl">{{ $p->folio }}</h2>
                    </div>
                    <span class="badge badge-{{ $p->estatus->tono() }} text-[18px]">{{ $p->estatus->tituloPublico() }}</span>
                </div>

                @if ($p->estatus === \App\Enums\Estatus::Cancelado)
                    <div class="panel border-peligro bg-peligro-tinte px-5 py-5">
                        <h3 class="panel-titulo text-peligro">Este pedido fue cancelado</h3>
                        <p class="mt-1 text-[15px] text-acero">Si tienes dudas, comunícate con la sucursal donde lo hiciste.</p>
                    </div>
                @else
                    <div class="panel px-5 pb-4 pt-5">
                        <x-aa.regla :estatus="$p->estatus" publica />
                    </div>
                @endif

                <div class="grid gap-5 sm:grid-cols-2 sm:items-start">
                    <section class="panel">
                        <div class="panel-cabecera"><h3 class="panel-titulo">Tu pedido</h3></div>
                        <dl class="grid gap-4 px-5 py-5">
                            <div>
                                <dt class="text-[15px] text-acero">Artículo</dt>
                                <dd class="font-semibold">{{ $p->material }}</dd>
                            </div>
                            <div>
                                <dt class="text-[15px] text-acero">Metros</dt>
                                <dd class="font-semibold">{{ number_format($p->metros_traspaso, 2) }} m</dd>
                            </div>
                            @if (count($this->piezas($p)))
                                <div>
                                    <dt class="text-[15px] text-acero">Piezas y medidas</dt>
                                    <dd class="font-semibold">
                                        @foreach ($this->piezas($p) as $linea)
                                            <span class="block">{{ $linea }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                            @endif
                            @if ($p->forma_entrega)
                                <div>
                                    <dt class="text-[15px] text-acero">Entrega</dt>
                                    <dd class="font-semibold">{{ $p->forma_entrega->label() }}</dd>
                                </div>
                            @endif
                        </dl>
                    </section>

                    <section class="panel" aria-labelledby="historial">
                        <div class="panel-cabecera"><h3 id="historial" class="panel-titulo">Historial</h3></div>
                        <ol class="px-5 py-5">
                            @foreach ($p->eventosPublicos() as $e)
                                <li class="relative grid grid-cols-[1.25rem_minmax(0,1fr)] gap-3 pb-6 last:pb-0" wire:key="hist-{{ $loop->index }}">
                                    <span class="relative flex justify-center">
                                        <span @class(['mt-1.5 size-3 rounded-full', 'bg-naranja ring-4 ring-naranja/25' => $loop->first, 'bg-linea-fuerte' => ! $loop->first])></span>
                                        @unless ($loop->last)
                                            <span class="absolute -bottom-6 left-1/2 top-5 w-px -translate-x-1/2 bg-linea"></span>
                                        @endunless
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold leading-snug">{{ $e['titulo'] }}</p>
                                        <p class="text-[15px] text-acero">
                                            <time datetime="{{ $e['fecha']->toIso8601String() }}">{{ $e['fecha']->translatedFormat('j M, H:i') }}</time>
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                </div>
            </section>
        @endif
    @endif
</div>