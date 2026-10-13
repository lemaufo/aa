<?php

use App\Models\Dispositivo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Equipos autorizados')] class extends Component {
    public string $nombre = '';

    public ?string $codigoNuevo = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->rol === \App\Enums\Rol::Admin, 403);
    }

    #[Computed]
    public function dispositivos()
    {
        return Dispositivo::query()->latest('id')->get();
    }

    public function generar(): void
    {
        $this->validate(['nombre' => ['required', 'string', 'max:80']], [], ['nombre' => 'nombre del equipo']);

        $this->codigoNuevo = Dispositivo::generarCodigo(trim($this->nombre));
        $this->nombre = '';
    }

    public function revocar(int $id): void
    {
        Dispositivo::query()->whereKey($id)->whereNull('revocado_at')->update([
            'revocado_at' => now(),
            'codigo_hash' => null,
        ]);
    }
}; ?>

<div class="grid gap-6">
    <header>
        <h1 class="font-display text-4xl font-semibold">Equipos autorizados</h1>
        <p class="text-acero">
            @if (config('aa.dispositivos.activo'))
                Solo estos equipos entran al sistema (el rol Consulta queda exento).
            @else
                La restricción está apagada (AA_DISPOSITIVOS=false). Puedes registrar equipos desde ahora.
            @endif
        </p>
    </header>

    <section class="panel">
        <div class="panel-cabecera"><h2 class="panel-titulo">Autorizar un equipo nuevo</h2></div>
        <form wire:submit="generar" class="grid gap-4 px-5 py-5 sm:grid-cols-[1fr_auto] sm:items-end">
            <x-aa.campo label="Nombre del equipo" for="nombre" :error="$errors->first('nombre')">
                <input id="nombre" type="text" wire:model="nombre" class="input" placeholder="Báscula CEDIS" autocomplete="off">
            </x-aa.campo>
            <button type="submit" class="btn btn-primario">Generar código</button>
        </form>

        @if ($codigoNuevo)
            <div class="mx-5 mb-5 rounded-md border border-naranja bg-naranja-tinte px-4 py-3" role="status">
                <p class="text-[15px] text-acero">Escríbelo en la pantalla /dispositivo del equipo. Solo se muestra ahora y sirve una vez.</p>
                <p class="folio text-4xl">{{ $codigoNuevo }}</p>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-cabecera"><h2 class="panel-titulo">Equipos</h2></div>
        <ul class="divide-y divide-linea">
            @forelse ($this->dispositivos as $d)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-4" wire:key="disp-{{ $d->id }}">
                    <div>
                        <p class="font-semibold">{{ $d->nombre }}</p>
                        <p class="text-[15px] text-acero">
                            {{ $d->estado() }}@if ($d->ultimo_uso_at), último uso {{ $d->ultimo_uso_at->diffForHumans() }}@endif
                        </p>
                    </div>
                    @unless ($d->revocado_at)
                        <button type="button" wire:click="revocar({{ $d->id }})" wire:confirm="¿Revocar {{ $d->nombre }}? Dejará de poder entrar." class="btn btn-secundario">Revocar</button>
                    @endunless
                </li>
            @empty
                <li class="px-5 py-5 text-acero">Todavía no hay equipos registrados.</li>
            @endforelse
        </ul>
    </section>
</div>