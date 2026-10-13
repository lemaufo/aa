@props(['estatus', 'compacta' => false, 'publica' => false])

@php
    $e = $estatus instanceof \App\Enums\Estatus ? $estatus : \App\Enums\Estatus::from($estatus);

    // Vista pública (rastreo del cliente): sin la etapa interna "Traspaso".
    $etapas = $publica
        ? ['Pedido realizado', 'Producido', 'En CEDIS', 'En ruta', 'Entregado']
        : \App\Enums\Estatus::etapas();
    $n = count($etapas);
    $etapa = $e->etapa() - ($publica ? 1 : 0);
    $cancelado = $e === \App\Enums\Estatus::Cancelado;
    $avance = $cancelado ? 0 : ($etapa >= $n - 1 ? 100 : max(0, (($etapa + 0.5) / $n) * 100));
@endphp

<div {{ $attributes }} style="--avance: {{ round($avance, 2) }}%; --n: {{ $n }}" role="img"
     aria-label="Avance del pedido: {{ $cancelado ? 'cancelado' : ($etapas[$etapa] ?? 'sin iniciar') }}">
    <div @class(['regla-cinta', 'regla-cinta-compacta' => $compacta])>
        <div class="regla-avance"></div>
        @unless ($compacta)
            <div class="regla-marcas"></div>
        @endunless
    </div>

    @unless ($compacta)
        <ol class="mt-2.5 grid text-center font-display text-[13px] leading-tight sm:text-[16px]"
            style="grid-template-columns: repeat({{ $n }}, minmax(0, 1fr))">
            @foreach ($etapas as $i => $nombre)
                <li @class([
                    'px-0.5',
                    'text-[#8b9095]' => $cancelado || $i > $etapa,
                    'text-tinta' => ! $cancelado && $i < $etapa,
                    'font-bold text-tinta' => ! $cancelado && $i === $etapa,
                ])>{{ $nombre }}</li>
            @endforeach
        </ol>
    @endunless
</div>