{{-- Campo del código de la etiqueta. En la PC el cursor ya está en el campo (sin abrir el teclado en el celular):
     la pistola escribe el folio y manda Enter, que envía el formulario.
     El botón Escanear abre la cámara (en vivo con HTTPS; sin HTTPS, toma una foto y la lee). --}}
@props(['folio', 'error' => null, 'model' => 'codigo'])

<x-aa.campo label="Código de la etiqueta" :for="$model" :error="$error"
            ayuda="Escanéalo con la pistola o con la cámara, o escribe el folio.">
    <div x-data="escaner" class="flex gap-2">
        <div class="relative min-w-0 flex-1">
            <x-aa.icon name="qr-code" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-acero" />
            <input id="{{ $model }}" x-ref="campo" type="text" wire:model="{{ $model }}"
                   x-init="matchMedia('(pointer: fine)').matches && $el.focus()"
                   class="input pl-11 font-display text-xl font-semibold"
                   placeholder="{{ $folio }}" autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="done"
                   @if ($error) aria-invalid="true" @endif>
        </div>

        <button type="button" x-on:click="abrir" class="btn btn-secundario shrink-0" x-bind:disabled="leyendo">
            <x-aa.icon name="camera" />
            <span x-text="leyendo ? 'Leyendo...' : 'Escanear'">Escanear</span>
        </button>

        {{-- Sin HTTPS el navegador no presta la cámara en vivo: se toma una foto de la etiqueta. --}}
        <input x-ref="foto" type="file" accept="image/*" capture="environment" class="hidden" x-on:change="desdeFoto">

        <template x-teleport="body">
            <div x-show="abierto" x-cloak x-on:keydown.escape.window="cerrar"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" role="dialog" aria-modal="true" aria-label="Escanear etiqueta">
                <div class="w-full max-w-md overflow-hidden rounded-lg bg-white">
                    <div class="flex items-center justify-between border-b border-linea px-4 py-3">
                        <span class="font-display text-xl font-semibold">Escanear etiqueta</span>
                        <button type="button" x-on:click="cerrar" class="btn btn-secundario">Cerrar</button>
                    </div>
                    <div class="relative aspect-[4/3] bg-black">
                        <video x-ref="video" class="size-full object-cover" playsinline muted></video>
                        {{-- Guía: el código de barras va acostado dentro del recuadro. --}}
                        <div class="pointer-events-none absolute inset-x-8 top-1/2 h-24 -translate-y-1/2 rounded-md border-2 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"></div>
                    </div>
                    <p class="px-4 py-3 text-[15px]" x-bind:class="mensaje ? 'text-red-700' : 'text-acero'"
                       x-text="mensaje || 'Apunta al código de barras de la etiqueta. Se lee solo.'"></p>
                </div>
            </div>
        </template>
    </div>
</x-aa.campo>
