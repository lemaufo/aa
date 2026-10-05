<?php

use App\Enums\Rol;
use App\Models\User;
use App\Support\Flujo;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Usuarios y permisos')] class extends Component {
    public function mount(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);
    }

    #[Computed]
    public function usuarios()
    {
        return User::orderBy('id')->get();
    }

    public function acciones(): array
    {
        return Flujo::definicion();
    }

    public function columnas(): array
    {
        return [Rol::Cajero, Rol::Produccion, Rol::Cedis, Rol::Sucursal];
    }
}; ?>

<div class="grid gap-8">
    <header class="grid gap-1.5">
        <h1 class="font-display text-[42px] font-semibold leading-none sm:text-5xl">Usuarios y permisos</h1>
        <p class="max-w-prose text-acero">Cada rol ve y hace solo lo que le toca. El administrador puede operar cualquier paso; el rol de consulta solo lee.</p>
    </header>

    <section class="grid gap-3" aria-labelledby="matriz">
        <h2 id="matriz" class="font-display text-[28px] font-semibold leading-none">Quién puede hacer qué</h2>
        <div class="panel overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left">
                <thead>
                    <tr class="border-b border-linea bg-plancha/60">
                        <th scope="col" class="px-5 py-3 font-semibold">Acción</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Desde el estatus</th>
                        @foreach ($this->columnas() as $rol)
                            <th scope="col" class="px-3 py-3 text-center font-semibold">{{ $rol->label() }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @foreach ($this->acciones() as $clave => $def)
                        <tr wire:key="accion-{{ $clave }}">
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $def['titulo'] }}</th>
                            <td class="px-3 py-3 text-[15px] text-acero">
                                {{ collect($def['desde'])->map(fn ($e) => $e->label())->join(', ') }}
                            </td>
                            @foreach ($this->columnas() as $rol)
                                <td class="px-3 py-3 text-center">
                                    @if (in_array($rol, $def['roles'], true))
                                        <x-aa.icon name="check-circle" class="mx-auto size-6 text-ok" />
                                        <span class="sr-only">Sí</span>
                                    @else
                                        <span class="text-linea-fuerte" aria-hidden="true">-</span>
                                        <span class="sr-only">No</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid gap-3" aria-labelledby="usuarios">
        <h2 id="usuarios" class="font-display text-[28px] font-semibold leading-none">Usuarios</h2>
        <div class="panel overflow-x-auto">
            <table class="w-full min-w-[34rem] text-left">
                <thead>
                    <tr class="border-b border-linea bg-plancha/60">
                        <th scope="col" class="px-5 py-3 font-semibold">Nombre</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Usuario</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Rol</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Sucursal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @foreach ($this->usuarios as $u)
                        <tr wire:key="usuario-{{ $u->id }}">
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $u->name }}</th>
                            <td class="px-3 py-3 font-mono text-[15px]">{{ $u->username }}</td>
                            <td class="px-3 py-3">{{ $u->rol->label() }}</td>
                            <td class="px-3 py-3 text-acero">{{ $u->sucursal ?? 'Todas' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>