<?php

use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\PedidoEvento;
use App\Support\Flujo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Inicio')] class extends Component {
    #[Computed]
    public function visibles()
    {
        return Pedido::visiblePara(auth()->user())->get();
    }

    #[Computed]
    public function porAtender()
    {
        $usuario = auth()->user();

        return $this->visibles
            ->filter(fn (Pedido $p) => Flujo::panelPara($usuario, $p) !== null)
            ->sortBy('updated_at')
            ->values();
    }

    #[Computed]
    public function enProceso(): int
    {
        return $this->visibles->reject(fn (Pedido $p) => $p->estatus->esFinal())->count();
    }

    #[Computed]
    public function entregadosHoy(): int
    {
        return $this->visibles
            ->filter(fn (Pedido $p) => $p->estatus === Estatus::Entregado && $p->updated_at->isToday())
            ->count();
    }

    #[Computed]
    public function actividad()
    {
        $usuario = auth()->user();

        return PedidoEvento::with(['pedido', 'user'])
            ->whereHas('pedido', fn ($q) => $q->visiblePara($usuario))
            ->latest()
            ->limit(6)
            ->get();
    }

    public function saludo(): string
    {
        $hora = now()->hour;

        return $hora < 12 ? 'Buen día' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    }
}; ?>

<div class="grid gap-8">
    <header class="grid gap-1.5">
        <h1 class="font-display text-[42px] font-semibold leading-none sm:text-5xl">
            {{ $this->saludo() }}, {{ \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') }}
        </h1>
        <p class="max-w-prose text-acero">{{ auth()->user()->rol->resumen() }}</p>
    </header>

    <section class="panel grid grid-cols-3 divide-x divide-linea" aria-label="Resumen de pedidos">
        @foreach ([
            [$this->porAtender->count(), 'Por atender', true],
            [$this->enProceso, 'En proceso', false],
            [$this->entregadosHoy, 'Entregados hoy', false],
        ] as [$cifra, $texto, $destacar])
            <div class="px-4 py-4 sm:px-6 sm:py-5">
                <p @class(['folio text-[44px] leading-none sm:text-6xl', 'text-naranja-fuerte' => $destacar && $cifra > 0])>{{ $cifra }}</p>
                <p class="mt-1.5 text-[15px] text-acero">{{ $texto }}</p>
            </div>
        @endforeach
    </section>

    <section class="grid gap-3" aria-labelledby="por-atender">
        <div class="flex items-end justify-between gap-4">
            <h2 id="por-atender" class="font-display text-[28px] font-semibold leading-none">Por atender</h2>
            <a href="{{ route('pedidos.index') }}" wire:navigate class="text-[15px] font-semibold text-naranja-fuerte underline-offset-4 hover:underline">Ver todos los pedidos</a>
        </div>

        @if ($this->porAtender->isEmpty())
            <div class="panel px-5 py-8 text-center">
                <p class="font-semibold">No tienes pedidos por atender.</p>
                <p class="mt-1 text-acero">
                    @if (auth()->user()->rol === \App\Enums\Rol::Consulta)
                        Tu rol solo consulta. Busca cualquier pedido en la lista para ver su bitácora.
                    @else
                        Cuando llegue uno a tu paso, aparecerá aquí.
                    @endif
                </p>
            </div>
        @else
            <ul class="panel divide-y divide-linea overflow-hidden">
                @foreach ($this->porAtender as $p)
                    <li wire:key="atender-{{ $p->id }}">
                        <a href="{{ route('pedidos.show', $p) }}" wire:navigate
                           class="grid gap-x-4 gap-y-3 px-4 pb-4 pt-4 hover:bg-plancha/60 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
                            <span class="folio text-[28px] leading-none">{{ $p->folio }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $p->cliente }}</span>
                                <span class="block truncate text-[15px] text-acero">{{ $p->material }}, {{ number_format($p->metros_traspaso, 2) }} m, {{ $p->sucursal }}</span>
                            </span>
                            <span class="flex items-center justify-between gap-3 sm:justify-end">
                                <x-aa.badge :estatus="$p->estatus" />
                                <span class="btn btn-primario min-h-10 px-4">Atender</span>
                            </span>
                            <x-aa.regla :estatus="$p->estatus" compacta class="sm:col-span-full" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($this->actividad->isNotEmpty())
        <section class="grid gap-3" aria-labelledby="actividad">
            <h2 id="actividad" class="font-display text-[28px] font-semibold leading-none">Actividad reciente</h2>
            <ul class="panel divide-y divide-linea">
                @foreach ($this->actividad as $e)
                    <li class="grid gap-x-4 px-4 py-3 sm:grid-cols-[1fr_auto] sm:px-5">
                        <p class="min-w-0">
                            <span class="font-semibold">{{ $e->accion }}</span>
                            <a href="{{ route('pedidos.show', $e->pedido) }}" wire:navigate class="folio text-lg text-naranja-fuerte hover:underline">{{ $e->pedido->folio }}</a>
                        </p>
                        <p class="text-[15px] text-acero">{{ $e->user?->name ?? 'Sistema' }}, {{ $e->created_at->diffForHumans() }}</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>