{{-- Campo de foto: en el celular abre la cámara. $archivo es la propiedad Livewire (para la vista previa). --}}
@props(['label', 'model', 'archivo' => null, 'error' => null, 'ayuda' => null])

<x-aa.campo :label="$label" :for="$model" :error="$error" :ayuda="$ayuda">
    <input id="{{ $model }}" type="file" accept="image/*" capture="environment" wire:model="{{ $model }}"
           class="input file:mr-3 file:rounded file:border-0 file:bg-plancha file:px-3 file:py-1.5 file:font-semibold"
           @if ($error) aria-invalid="true" @endif>
    <div wire:loading wire:target="{{ $model }}" class="text-[15px] text-acero">Subiendo foto...</div>
    @if ($archivo && method_exists($archivo, 'isPreviewable') && $archivo->isPreviewable())
        <img src="{{ $archivo->temporaryUrl() }}" alt="Vista previa" class="mt-1 max-h-40 w-fit rounded-md border border-linea object-cover">
    @endif
</x-aa.campo>