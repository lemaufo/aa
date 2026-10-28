<?php

use App\Enums\Rol;
use App\Models\Pedido;
use App\Services\Firebird\FirebirdArticulos;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

/** Catálogo de artículos leído directo de Microsip (solo lectura). Solo administrador. */
new #[Title('Artículos')] class extends Component {
    private const POR_PAGINA = 25;

    #[Url(as: 'buscar')]
    public string $q = '';

    #[Url(as: 'estatus')]
    public string $estatus = '';

    #[Url(as: 'pagina')]
    public int $pagina = 1;

    public ?int $seleccionado = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);
    }

    public function conectado(): bool
    {
        return config('aa.origen') === 'firebird';
    }

    public function updatedQ(): void
    {
        $this->pagina = 1;
        $this->seleccionado = null;
    }

    public function filtrar(string $estatus): void
    {
        $this->estatus = $estatus;
        $this->pagina = 1;
        $this->seleccionado = null;
    }

    public function irA(int $pagina): void
    {
        $this->pagina = max(1, min($pagina, $this->paginas()));
        $this->seleccionado = null;
    }

    public function ver(int $id): void
    {
        $this->seleccionado = $this->seleccionado === $id ? null : $id;
    }

    /** Lo que devuelve Microsip, o el error para mostrarlo sin romper la pantalla. */
    #[Computed]
    public function resultado(): array
    {
        if (! $this->conectado()) {
            return ['total' => 0, 'filas' => [], 'error' => null];
        }

        try {
            return app(FirebirdArticulos::class)->buscar($this->q, $this->estatus, $this->pagina, self::POR_PAGINA) + ['error' => null];
        } catch (Throwable $e) {
            report($e);

            return ['total' => 0, 'filas' => [], 'error' => 'No se pudo consultar Microsip en este momento. Intenta de nuevo en unos minutos.'];
        }
    }

    #[Computed]
    public function conteos(): array
    {
        try {
            return $this->conectado() ? app(FirebirdArticulos::class)->estatus() : [];
        } catch (Throwable) {
            return [];
        }
    }

    #[Computed]
    public function detalle(): ?array
    {
        if ($this->seleccionado === null) {
            return null;
        }

        try {
            $a = app(FirebirdArticulos::class)->detalle($this->seleccionado);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($a !== null) {
            $a['pedidos'] = Pedido::where('material', $a['nombre'])->count();
        }

        return $a;
    }

    public function paginas(): int
    {
        return max(1, (int) ceil($this->resultado['total'] / self::POR_PAGINA));
    }

    public function etiquetaEstatus(string $e): string
    {
        return match ($e) {
            'A' => 'Activos',
            'S' => 'Suspendidos',
            default => 'Estatus '.$e,
        };
    }

    public function kg(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 3), '0'), '.');
    }
}; ?>

<div class="grid gap-6">
    <header class="grid gap-1.5">
        <h1 class="font-display text-[42px] font-semibold leading-none sm:text-5xl">Artículos</h1>
        <p class="max-w-prose text-acero">Catálogo de artículos de Microsip, leído en vivo. Busca por nombre o clave. Aquí no se modifica nada.</p>
    </header>

    @unless ($this->conectado())
        <div class="panel px-5 py-10 text-center">
            <p class="font-semibold">Microsip no está conectado.</p>
            <p class="mt-1 text-acero">La app corre con datos demo (AA_ORIGEN=demo). El catálogo se ve con AA_ORIGEN=firebird.</p>
        </div>
    @else
        <div class="grid gap-3">
            <div class="relative">
                <label for="buscar_articulo" class="sr-only">Buscar artículos</label>
                <x-aa.icon name="magnifying-glass" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-acero" />
                <input id="buscar_articulo" type="search" wire:model.live.debounce.400ms="q" class="input pl-11"
                       placeholder="Nombre o clave, por ejemplo lámina galv o 07002011A" autocomplete="off">
            </div>

            <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estatus">
                <button type="button" wire:click="filtrar('')" class="chip" aria-pressed="{{ $estatus === '' ? 'true' : 'false' }}">
                    Todos <span class="folio text-[17px]">{{ array_sum($this->conteos) }}</span>
                </button>
                @foreach ($this->conteos as $clave => $n)
                    <button type="button" wire:click="filtrar('{{ $clave }}')" class="chip" aria-pressed="{{ $estatus === $clave ? 'true' : 'false' }}">
                        {{ $this->etiquetaEstatus($clave) }} <span class="folio text-[17px]">{{ $n }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        @if ($this->resultado['error'])
            <div class="panel px-5 py-6 text-center" role="alert">
                <p class="font-semibold text-peligro">{{ $this->resultado['error'] }}</p>
            </div>
        @elseif ($this->resultado['filas'] === [])
            <div class="panel px-5 py-10 text-center">
                <p class="font-semibold">No hay artículos con esa búsqueda.</p>
                <p class="mt-1 text-acero">Prueba con menos palabras o con la clave.</p>
            </div>
        @else
            <p class="text-[15px] text-acero" wire:loading.class="opacity-50">
                {{ number_format($this->resultado['total']) }} {{ $this->resultado['total'] === 1 ? 'artículo' : 'artículos' }}
                · página {{ $pagina }} de {{ $this->paginas() }}
            </p>

            <ul class="panel divide-y divide-linea overflow-hidden" wire:loading.class="opacity-50">
                @foreach ($this->resultado['filas'] as $a)
                    <li wire:key="articulo-{{ $a['articulo_id'] }}">
                        <button type="button" wire:click="ver({{ $a['articulo_id'] }})" aria-expanded="{{ $seleccionado === $a['articulo_id'] ? 'true' : 'false' }}"
                                class="grid w-full gap-x-4 gap-y-1 px-4 py-3 text-left hover:bg-plancha/60 sm:grid-cols-[9rem_minmax(0,1fr)_auto] sm:items-center sm:px-5">
                            <span class="font-mono text-[15px] text-acero">{{ $a['clave'] ?? 'Sin clave' }}</span>
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $a['nombre'] }}</span>
                                <span class="block truncate text-[15px] text-acero">
                                    {{ $a['linea'] ?? 'Sin línea' }} · {{ $a['unidad'] ?? 'sin unidad' }}
                                    @if ($a['peso_unitario'] > 0) · {{ $this->kg($a['peso_unitario']) }} kg c/u @endif
                                </span>
                            </span>
                            @if ($a['estatus'] !== 'A')
                                <span class="chip pointer-events-none text-[15px]">{{ $this->etiquetaEstatus($a['estatus']) }}</span>
                            @endif
                        </button>

                        @if ($seleccionado === $a['articulo_id'] && ($d = $this->detalle))
                            <div class="grid gap-4 bg-plancha/50 px-4 py-4 sm:px-5">
                                <dl class="grid gap-x-6 gap-y-3 text-[15px] sm:grid-cols-3">
                                    <div><dt class="text-acero">Unidad de venta</dt><dd class="font-semibold">{{ $d['unidad_venta'] ?? '—' }}</dd></div>
                                    <div><dt class="text-acero">Unidad de compra</dt>
                                        <dd class="font-semibold">{{ $d['unidad_compra'] ?? '—' }}@if ((float) $d['contenido_unidad_compra'] > 0) ({{ $this->kg((float) $d['contenido_unidad_compra']) }} por unidad de compra)@endif</dd></div>
                                    <div><dt class="text-acero">Peso unitario</dt><dd class="font-semibold">{{ $this->kg((float) $d['peso_unitario']) }} kg</dd></div>
                                    <div><dt class="text-acero">Almacenable / juego / peso variable</dt>
                                        <dd class="font-semibold">{{ $d['es_almacenable'] === 'S' ? 'Sí' : 'No' }} / {{ $d['es_juego'] === 'S' ? 'Sí' : 'No' }} / {{ $d['es_peso_variable'] === 'S' ? 'Sí' : 'No' }}</dd></div>
                                    <div><dt class="text-acero">Alta en Microsip</dt>
                                        <dd class="font-semibold">{{ $d['fecha_hora_creacion'] ? \Illuminate\Support\Carbon::parse($d['fecha_hora_creacion'])->translatedFormat('j M Y') : '—' }}</dd></div>
                                    <div><dt class="text-acero">Pedidos en la app con este artículo</dt><dd class="font-semibold">{{ $d['pedidos'] }}</dd></div>
                                </dl>

                                @if ($d['estatus'] === 'S' && ($d['causa_susp'] || $d['fecha_susp']))
                                    <p class="text-[15px] text-peligro">
                                        Suspendido{{ $d['fecha_susp'] ? ' el '.\Illuminate\Support\Carbon::parse($d['fecha_susp'])->translatedFormat('j M Y') : '' }}{{ $d['causa_susp'] ? ': '.$d['causa_susp'] : '' }}
                                    </p>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    @foreach ($d['claves'] as $c)
                                        <span class="rounded-md border border-linea bg-white px-3 py-1 text-[15px]">
                                            <span class="font-mono">{{ $c['clave'] }}</span> <span class="text-acero">· {{ $c['tipo'] }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($this->paginas() > 1)
                <nav class="flex items-center justify-between gap-3" aria-label="Páginas">
                    <button type="button" wire:click="irA({{ $pagina - 1 }})" class="btn btn-secundario" @disabled($pagina <= 1)>Anterior</button>
                    <span class="text-[15px] text-acero">Página {{ $pagina }} de {{ $this->paginas() }}</span>
                    <button type="button" wire:click="irA({{ $pagina + 1 }})" class="btn btn-secundario" @disabled($pagina >= $this->paginas())>Siguiente</button>
                </nav>
            @endif
        @endif
    @endunless
</div>
