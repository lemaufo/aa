<?php

use App\Enums\Estatus;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Services\FlujoService;
use App\Support\Evidencia;
use App\Support\Flujo;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Diseño de referencia del pedido (canalón y piezas sin clave propia: se cobran como hoja más doblez).
 * Va arriba de la bitácora como una nota adhesiva pequeña con un clip; el dibujo solo se ve al abrirlo,
 * en un modal de consulta. La caja lo adjunta, lo cambia o lo quita mientras el pedido no pasa de producción.
 */
new class extends Component {
    use WithFileUploads;

    public Pedido $pedido;

    public $diseno = null;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
    }

    /** La caja, antes de que el pedido quede producido. */
    public function puedeAdjuntar(): bool
    {
        return Flujo::tieneRol(auth()->user(), [Rol::Cajero])
            && $this->pedido->esVisiblePara(auth()->user())
            && in_array($this->pedido->estatus, [Estatus::EnExpertos, Estatus::Cobrado], true);
    }

    public function updatedDiseno(FlujoService $flujo): void
    {
        abort_unless($this->puedeAdjuntar(), 403);

        $this->validate(['diseno' => Evidencia::reglas()]);

        $anterior = $this->pedido->diseno_path;
        $this->pedido->update(['diseno_path' => Evidencia::guardar($this->diseno, $this->pedido, 'diseno', Evidencia::LADO_MAX_DISENO)]);
        if ($anterior) {
            Storage::disk('local')->delete($anterior);
        }
        $flujo->evento($this->pedido, auth()->user(), $anterior ? 'Diseño de referencia cambiado' : 'Diseño de referencia adjuntado', null);

        $this->reset('diseno');
        $this->dispatch('toast', tipo: 'ok', mensaje: 'Diseño de referencia guardado. Producción lo verá antes de cortar.');
        $this->dispatch('pedido-actualizado');
    }

    public function eliminar(FlujoService $flujo): void
    {
        abort_unless($this->puedeAdjuntar() && $this->pedido->diseno_path, 403);

        Storage::disk('local')->delete($this->pedido->diseno_path);
        $this->pedido->update(['diseno_path' => null]);
        $flujo->evento($this->pedido, auth()->user(), 'Diseño de referencia eliminado', null);

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Diseño de referencia eliminado.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

@php
    $hay = (bool) $pedido->diseno_path;
    $puede = $this->puedeAdjuntar();
    $url = $hay ? route('evidencia', [$pedido, 'diseno']) : null;
@endphp

<div>
    @if ($hay || $puede)
        <style>
            /* Nota adhesiva pequeña, con otra detrás y un clip rojo sujetándolas por arriba. */
            .notas { position: relative; padding: 1rem .5rem .5rem; }
            .nota { position: relative; background: #fbef9d; border: 2px solid #1f2023; border-radius: 2px; }
            .nota-atras { position: absolute; inset: 1.35rem .8rem .2rem .9rem; transform: rotate(-3deg); box-shadow: 2px 3px 0 rgb(0 0 0 / .12); }
            .nota-frente { transform: rotate(.8deg); box-shadow: 3px 3px 0 rgb(0 0 0 / .14); animation: nota-entra .4s ease-out both; }
            .nota-clip { position: absolute; top: 0; left: 1.2rem; z-index: 2; width: 1.5rem; height: auto; transform: rotate(-8deg); }
            .notas:hover .nota-clip { animation: clip-mece .5s ease-in-out; }
            @keyframes nota-entra { from { opacity: 0; transform: translateY(-6px) rotate(-.5deg); } to { opacity: 1; transform: rotate(.8deg); } }
            @keyframes clip-mece { 0%, 100% { transform: rotate(-8deg); } 40% { transform: rotate(-14deg); } 70% { transform: rotate(-5deg); } }
            @media (prefers-reduced-motion: reduce) { .nota-frente, .notas:hover .nota-clip { animation: none; } }
        </style>

        <section class="notas" aria-labelledby="diseno-titulo" x-data="{ abierto: false }" x-on:keydown.escape.window="abierto = false">
            <div class="nota nota-atras" aria-hidden="true"></div>

            <svg class="nota-clip" viewBox="0 0 34 80" fill="none" aria-hidden="true">
                <path d="M11 22V60a7 7 0 0 0 14 0V12a9 9 0 0 0-18 0v44" stroke="#5b0f0a" stroke-width="7" stroke-linecap="round"/>
                <path d="M11 22V60a7 7 0 0 0 14 0V12a9 9 0 0 0-18 0v44" stroke="#e3241b" stroke-width="4" stroke-linecap="round"/>
            </svg>

            <div class="nota nota-frente grid gap-2 px-4 pb-3 pt-3">
                <div class="pl-8">
                    <h2 id="diseno-titulo" class="font-display text-lg font-semibold leading-tight">Diseño de referencia</h2>
                    <p class="text-[14px] text-tinta/75">{{ $hay ? 'Dibujo del cliente con sus medidas.' : 'Canalón u otra pieza especial: adjunta el boceto del cliente.' }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[15px] font-semibold">
                    @if ($hay)
                        <button type="button" x-on:click="abierto = true" class="inline-flex items-center gap-1.5 underline underline-offset-4 hover:text-naranja-fuerte">
                            <x-aa.icon name="eye" class="size-4" /> Ver diseño
                        </button>
                    @endif

                    @if ($puede)
                        <label for="diseno" class="inline-flex cursor-pointer items-center gap-1.5 underline underline-offset-4 hover:text-naranja-fuerte">
                            <x-aa.icon name="{{ $hay ? 'camera' : 'paper-clip' }}" class="size-4" /> {{ $hay ? 'Cambiar' : 'Adjuntar diseño' }}
                        </label>
                        <input id="diseno" type="file" accept="image/*" wire:model="diseno" class="sr-only">

                        @if ($hay)
                            <button type="button" wire:click="eliminar" wire:confirm="¿Eliminar el diseño de referencia de este pedido?"
                                    class="inline-flex items-center gap-1.5 text-peligro underline underline-offset-4">
                                <x-aa.icon name="trash" class="size-4" /> Eliminar
                            </button>
                        @endif
                    @endif
                </div>

                <p wire:loading wire:target="diseno" class="text-[14px] text-tinta/75">Subiendo el diseño...</p>
                @error('diseno') <p class="text-[14px] font-medium text-peligro" role="alert">{{ $message }}</p> @enderror
            </div>

            @if ($hay)
                {{-- Modal de consulta: el diseño en grande, sin salir del pedido --}}
                <template x-teleport="body">
                    <div x-show="abierto" x-cloak x-transition.opacity class="fixed inset-0 z-50 grid place-items-center bg-black/60 p-4"
                         x-on:click.self="abierto = false" role="dialog" aria-modal="true" aria-label="Diseño de referencia del pedido {{ $pedido->folio }}">
                        <div class="grid max-h-full w-full max-w-4xl gap-3 rounded-md bg-white p-4 shadow-xl" x-trap.noscroll="abierto">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-display text-xl font-semibold">Diseño de referencia · <span class="folio">{{ $pedido->folio }}</span></p>
                                <button type="button" x-on:click="abierto = false" class="btn btn-secundario">Cerrar</button>
                            </div>
                            {{-- La imagen se pide hasta que se abre el modal --}}
                            <template x-if="abierto">
                                <img src="{{ $url }}" alt="Diseño de referencia del pedido {{ $pedido->folio }}"
                                     class="mx-auto max-h-[calc(100dvh-9rem)] w-auto max-w-full rounded-sm border border-linea object-contain">
                            </template>
                        </div>
                    </div>
                </template>
            @endif
        </section>
    @endif
</div>
