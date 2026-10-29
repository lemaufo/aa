<?php

use App\Enums\Estatus;
use App\Models\Pedido;
use App\Support\Flujo;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Pedidos')] class extends Component {
    #[Url(as: 'buscar')]
    public string $q = '';

    #[Url(as: 'estado')]
    public string $filtro = 'todos';

    #[Computed]
    public function base()
    {
        return Pedido::visiblePara(auth()->user())->prioritarios()->get();
    }

    private function coincide(Pedido $p, string $filtro): bool
    {
        return match ($filtro) {
            'atender' => Flujo::panelPara(auth()->user(), $p) !== null,
            'proceso' => ! $p->estatus->esFinal(),
            'entregados' => $p->estatus === Estatus::Entregado,
            'cancelados' => $p->estatus === Estatus::Cancelado,
            default => true,
        };
    }

    #[Computed]
    public function conteos(): array
    {
        return collect(['todos', 'atender', 'proceso', 'entregados', 'cancelados'])
            ->mapWithKeys(fn ($f) => [$f => $this->base->filter(fn ($p) => $this->coincide($p, $f))->count()])
            ->all();
    }

    #[Computed]
    public function pedidos()
    {
        $texto = Str::lower(trim($this->q));

        return $this->base
            ->filter(fn (Pedido $p) => $this->coincide($p, $this->filtro))
            ->when($texto !== '', fn ($c) => $c->filter(
                fn (Pedido $p) => str_contains(Str::lower("{$p->folio} {$p->cliente} {$p->folio_venta} {$p->material}"), $texto)
            ))
            ->values();
    }

    public function filtros(): array
    {
        return ['todos' => 'Todos', 'atender' => 'Por atender', 'proceso' => 'En proceso', 'entregados' => 'Entregados', 'cancelados' => 'Cancelados'];
    }
}; ?>

<div class="grid gap-6">
    <header class="grid gap-1.5">
        <h1 class="font-display text-[42px] font-semibold leading-none sm:text-5xl">Pedidos</h1>
        <p class="max-w-prose text-acero">Busca por folio, cliente o folio de venta. Abre un pedido para ver su avance y su bitácora.</p>
    </header>

    <div class="grid gap-3">
        <div class="relative">
            <label for="buscar" class="sr-only">Buscar pedidos</label>
            <x-aa.icon name="magnifying-glass" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-acero" />
            <input id="buscar" type="search" wire:model.live.debounce.300ms="q" class="input pl-11"
                   placeholder="Folio, cliente o venta" autocomplete="off">
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
            @foreach ($this->filtros() as $clave => $texto)
                <button type="button" wire:click="$set('filtro', '{{ $clave }}')" class="chip"
                        aria-pressed="{{ $filtro === $clave ? 'true' : 'false' }}">
                    {{ $texto }}
                    <span class="folio text-[17px]">{{ $this->conteos[$clave] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    @if ($this->pedidos->isEmpty())
        <div class="panel px-5 py-10 text-center">
            <p class="font-semibold">No hay pedidos con ese filtro.</p>
            <p class="mt-1 text-acero">Cambia el estado o borra la búsqueda para ver más.</p>
        </div>
    @else
        <ul class="panel divide-y divide-linea overflow-hidden">
            @foreach ($this->pedidos as $p)
                <li wire:key="pedido-{{ $p->id }}">
                    <a href="{{ route('pedidos.show', $p) }}" wire:navigate
                       class="grid gap-x-4 gap-y-3 px-4 pt-4 hover:bg-plancha/60 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
                        <span class="folio text-[28px] leading-none">{{ $p->folio }}</span>
                        <span class="min-w-0">
                            <span class="block truncate font-semibold">{{ $p->cliente }}</span>
                            <span class="block truncate text-[15px] text-acero">{{ $p->material }}, {{ number_format($p->metros_traspaso, 2) }} m, {{ $p->sucursal }}</span>
                        </span>
                        <span class="flex items-center sm:justify-end">
                            <x-aa.badge :estatus="$p->estatus" />
                            <x-aa.prioridad :pedido="$p" />
                        </span>
                        <x-aa.regla :estatus="$p->estatus" compacta class="sm:col-span-full" />
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>