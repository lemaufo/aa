@props(['label', 'for' => null, 'error' => null, 'ayuda' => null])

<div {{ $attributes->class('grid gap-1.5') }}>
    <label @if ($for) for="{{ $for }}" @endif class="text-[15px] font-semibold">{{ $label }}</label>

    {{ $slot }}

    @if ($error)
        <p class="text-[15px] font-medium text-peligro" role="alert">{{ $error }}</p>
    @elseif ($ayuda)
        <p class="text-[15px] text-acero">{{ $ayuda }}</p>
    @endif
</div>