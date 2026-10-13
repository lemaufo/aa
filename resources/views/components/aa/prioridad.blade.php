@props(['pedido'])

@php($p = $pedido->prioridad())

@if ($p)
    <span {{ $attributes->class([
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-[14px] font-semibold',
        'bg-peligro-tinte text-peligro' => in_array($p['nivel'], ['vencido', 'hoy'], true),
        'bg-naranja-tinte text-naranja-fuerte' => $p['nivel'] === 'pronto',
        'bg-plancha text-acero' => $p['nivel'] === 'normal',
    ]) }} title="Fecha prometida: {{ $pedido->fecha_prometida->translatedFormat('j M Y') }}">{{ $p['texto'] }}</span>
@endif