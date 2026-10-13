{{-- Evidencia privada: se sirve por una ruta con sesión, nunca por una URL pública. --}}
@props(['pedido', 'tipo', 'titulo'])

@php
    $columna = \App\Models\Pedido::EVIDENCIAS[$tipo];
    $ruta = $pedido->{$columna};
    $existe = $ruta && \Illuminate\Support\Facades\Storage::disk('local')->exists($ruta);
@endphp

<figure {{ $attributes->class('grid gap-1.5') }}>
    <figcaption class="text-[15px] text-acero">{{ $titulo }}</figcaption>
    @if ($existe)
        <a href="{{ route('evidencia', [$pedido, $tipo]) }}" target="_blank" rel="noopener">
            <img src="{{ route('evidencia', [$pedido, $tipo]) }}" alt="{{ $titulo }} del pedido {{ $pedido->folio }}" loading="lazy"
                 class="max-h-56 w-full rounded-md border border-linea object-cover sm:w-auto">
        </a>
    @else
        <p class="rounded-md bg-plancha px-3 py-2 text-[15px] text-acero">
            {{ $ruta ? 'La foto ya fue depurada por la política de retención.' : 'Sin foto registrada.' }}
        </p>
    @endif
</figure>