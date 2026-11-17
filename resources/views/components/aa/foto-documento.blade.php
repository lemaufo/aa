{{-- Foto de un documento (acuse firmado): "Tomar foto" abre la cámara del dispositivo dentro de la página
     (celular o webcam; sin HTTPS, la cámara nativa del celular) y "Adjuntar archivo" deja elegir una imagen
     ya guardada (galería, PC, escaneo). Ambos llenan la misma propiedad Livewire ($model). --}}
@props(['label', 'model', 'archivo' => null, 'error' => null, 'ayuda' => null])

<x-aa.campo :label="$label" :for="$model.'-camara'" :error="$error" :ayuda="$ayuda">
    <div x-data="camaraFoto(@js($model))" class="grid gap-3">
        <div class="flex flex-wrap gap-2">
            <button id="{{ $model }}-camara" type="button" x-on:click="tomar" class="btn btn-secundario flex-1 sm:flex-none"
                    x-bind:disabled="subiendo" @if ($error) aria-invalid="true" @endif>
                <x-aa.icon name="camera" /> Tomar foto
            </button>
            <button type="button" x-on:click="$refs.archivo.value = ''; $refs.archivo.click()" class="btn btn-secundario flex-1 sm:flex-none"
                    x-bind:disabled="subiendo">
                <x-aa.icon name="paper-clip" /> Adjuntar archivo
            </button>
        </div>

        {{-- Sin HTTPS: la cámara nativa del celular. Adjuntar: galería o archivo de la PC. --}}
        <input x-ref="nativa" type="file" accept="image/*" capture="environment" wire:model="{{ $model }}" class="hidden" tabindex="-1">
        <input x-ref="archivo" type="file" accept="image/*" wire:model="{{ $model }}" class="hidden" tabindex="-1">

        <div x-show="subiendo" x-cloak class="text-[15px] text-acero">Subiendo foto...</div>
        <div wire:loading wire:target="{{ $model }}" class="text-[15px] text-acero">Subiendo foto...</div>

        @if ($archivo && method_exists($archivo, 'isPreviewable') && $archivo->isPreviewable())
            <div class="flex flex-wrap items-end gap-3">
                <img src="{{ $archivo->temporaryUrl() }}" alt="Vista previa del acuse" class="max-h-48 w-fit rounded-md border border-linea object-cover">
                <span class="flex items-center gap-1.5 text-[15px] font-semibold text-ok"><x-aa.icon name="check-circle" /> Foto lista. Puedes volver a tomarla si no se lee bien.</span>
            </div>
        @endif

        <template x-teleport="body">
            <div x-show="abierto" x-cloak x-on:keydown.escape.window="cerrar"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/85 p-3" role="dialog" aria-modal="true" aria-label="{{ $label }}">
                <div class="flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white">
                    <div class="flex items-center justify-between border-b border-linea px-4 py-3">
                        <span class="font-display text-xl font-semibold">{{ $label }}</span>
                        <button type="button" x-on:click="cerrar" class="btn btn-secundario">Cerrar</button>
                    </div>
                    <div class="relative min-h-0 flex-1 bg-black">
                        <video x-ref="video" class="mx-auto max-h-[65vh] w-full object-contain" playsinline muted></video>
                        {{-- Guía: el documento completo dentro del marco. --}}
                        <div class="pointer-events-none absolute inset-6 rounded-md border-2 border-dashed border-white/80"></div>
                    </div>
                    <div class="grid gap-3 px-4 py-3">
                        <p class="text-[15px]" x-bind:class="mensaje ? 'text-peligro' : 'text-acero'"
                           x-text="mensaje || 'Que se vea todo el documento y la firma, con buena luz y sin sombras.'"></p>
                        <button type="button" x-on:click="disparar" x-bind:disabled="!listo" class="btn btn-primario w-full">
                            <x-aa.icon name="camera" /> Tomar foto
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-aa.campo>
