@props(['estatus', 'compacta' => false])

@php
    $e = $estatus instanceof \App\Enums\Estatus ? $estatus : \App\Enums\Estatus::from($estatus);
    $etapas = \App\Enums\Estatus::etapas();
    $etapa = $e->etapa();
    $cancelado = $e === \App\Enums\Estatus::Cancelado;
    $avance = $cancelado ? 0 : ($etapa >= 5 ? 100 : (($etapa + 0.5) / 6) * 100);
@endphp

<div {{ $attributes }} style="--avance: {{ round($avance, 2) }}%" role="img"
     aria-label="Avance del pedido: {{ $cancelado ? 'cancelado' : $etapas[$etapa] }}">
    <div @class(['regla-cinta', 'regla-cinta-compacta' => $compacta])>
        <div class="regla-avance"></div>
        @unless ($compacta)
            <div class="regla-marcas"></div>
        @endunless
    </div>

    @unless ($compacta)
        <ol class="mt-2.5 grid grid-cols-6 text-center font-display text-[13px] leading-tight sm:text-[16px]">
            @foreach ($etapas as $i => $nombre)
                <li @class([
                    'text-[#8b9095]' => $cancelado || $i > $etapa,
                    'text-tinta' => ! $cancelado && $i < $etapa,
                    'font-bold text-tinta' => ! $cancelado && $i === $etapa,
                ])>{{ $nombre }}</li>
            @endforeach
        </ol>
    @endunless
</div>