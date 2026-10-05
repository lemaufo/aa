{{-- Piezas × medida, con la calculadora de control contra los metros del traspaso. --}}
@props(['cortes', 'total', 'meta', 'titulo' => 'Piezas y medidas', 'errores' => []])

@php
    $cuadra = abs($total - $meta) < 0.005;
    $diferencia = round($meta - $total, 2);
@endphp

<fieldset class="grid gap-3">
    <legend class="mb-1 text-[15px] font-semibold">{{ $titulo }}</legend>

    <div class="grid gap-2">
        @foreach ($cortes as $i => $corte)
            <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)_auto] items-center gap-2 sm:grid-cols-[7.5rem_auto_7.5rem_1fr_auto]" wire:key="corte-{{ $i }}">
                <div>
                    <label class="sr-only" for="piezas-{{ $i }}">Piezas de la línea {{ $i + 1 }}</label>
                    <input id="piezas-{{ $i }}" type="number" min="1" step="1" inputmode="numeric" class="input" placeholder="Piezas"
                           wire:model.live.debounce.300ms="cortes.{{ $i }}.piezas">
                </div>
                <span class="text-acero" aria-hidden="true">×</span>
                <div>
                    <label class="sr-only" for="medida-{{ $i }}">Medida en metros de la línea {{ $i + 1 }}</label>
                    <input id="medida-{{ $i }}" type="number" min="0.1" step="0.01" inputmode="decimal" class="input" placeholder="Metros"
                           wire:model.live.debounce.300ms="cortes.{{ $i }}.medida">
                </div>
                <p class="hidden text-acero sm:block">
                    =
                    <span class="folio text-xl text-tinta">{{ number_format((float) ($corte['piezas'] ?: 0) * (float) ($corte['medida'] ?: 0), 2) }}</span>
                    m
                </p>
                <button type="button" wire:click="quitarCorte({{ $i }})" class="btn btn-secundario px-3" aria-label="Quitar la línea {{ $i + 1 }}">
                    <x-aa.icon name="trash" />
                </button>
            </div>
        @endforeach
    </div>

    <button type="button" wire:click="agregarCorte" class="btn btn-secundario w-fit">
        <x-aa.icon name="plus" /> Agregar otra medida
    </button>

    <div @class([
        'rounded-md border px-4 py-3',
        'border-ok bg-ok-tinte' => $cuadra,
        'border-linea-fuerte bg-plancha' => ! $cuadra,
    ]) role="status" aria-live="polite">
        <p class="flex flex-wrap items-baseline gap-x-2">
            <span @class(['folio text-3xl', 'text-ok' => $cuadra])>{{ number_format($total, 2) }} m</span>
            <span class="text-acero">de {{ number_format($meta, 2) }} m del traspaso</span>
        </p>
        <p @class(['mt-0.5 text-[15px] font-semibold', 'text-ok' => $cuadra, 'text-peligro' => ! $cuadra && $total > 0, 'text-acero' => $total <= 0])>
            @if ($cuadra)
                Coincide con el traspaso.
            @elseif ($total <= 0)
                Captura las piezas y las medidas.
            @elseif ($diferencia > 0)
                Faltan {{ number_format($diferencia, 2) }} m para igualar el traspaso.
            @else
                Sobran {{ number_format(abs($diferencia), 2) }} m contra el traspaso.
            @endif
        </p>
    </div>

    @foreach ($errores as $mensaje)
        <p class="text-[15px] font-medium text-peligro" role="alert">{{ $mensaje }}</p>
    @endforeach
</fieldset>