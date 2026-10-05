@props(['estatus'])

@php
    $e = $estatus instanceof \App\Enums\Estatus ? $estatus : \App\Enums\Estatus::from($estatus);
@endphp

<span {{ $attributes->class(['badge', 'badge-'.$e->tono()]) }}>{{ $e->label() }}</span>