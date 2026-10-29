<?php

use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Flujo;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Usuarios y permisos')] class extends Component {
    /** null = formulario cerrado, 0 = usuario nuevo, id = editando ese usuario. */
    public ?int $editando = null;

    public string $name = '';

    public string $username = '';

    public string $rol = '';

    public string $sucursal = '';

    public string $password = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);
    }

    #[Computed]
    public function usuarios()
    {
        return User::orderBy('id')->get();
    }

    /** Los almacenes de Microsip (en demo, las de configuración) más las que ya usan pedidos y usuarios. */
    #[Computed]
    public function sucursales()
    {
        return collect(app(\App\Services\Sucursales::class)->nombres())
            ->merge(User::whereNotNull('sucursal')->distinct()->pluck('sucursal'))
            ->merge(Pedido::distinct()->pluck('sucursal'))
            ->filter()->unique()->sort()->values();
    }

    public function nuevo(): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'username', 'rol', 'sucursal', 'password');
        $this->editando = 0;
    }

    public function editar(int $id): void
    {
        $u = User::findOrFail($id);

        $this->resetErrorBag();
        $this->editando = $u->id;
        $this->name = $u->name;
        $this->username = (string) $u->username;
        $this->rol = $u->rol->value;
        $this->sucursal = (string) $u->sucursal;
        $this->password = '';
    }

    public function cerrar(): void
    {
        $this->editando = null;
        $this->resetErrorBag();
    }

    public function limitado(): bool
    {
        return Rol::tryFrom($this->rol)?->limitadoASuSucursal() ?? false;
    }

    public function guardar(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);

        $this->username = strtolower(trim($this->username));

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash:ascii', 'max:40', Rule::unique('users', 'username')->ignore($this->editando ?: null)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'sucursal' => [Rule::requiredIf($this->limitado()), 'nullable', 'string', 'max:120'],
            'password' => [$this->editando ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
        ], [], ['name' => 'nombre', 'username' => 'usuario', 'rol' => 'rol', 'sucursal' => 'sucursal', 'password' => 'contraseña']);

        // Nunca dejar el sistema sin administrador: el admin no se quita su propio rol.
        if ($this->editando === auth()->id() && $datos['rol'] !== Rol::Admin->value) {
            $this->addError('rol', 'No puedes quitarte el rol de administrador a ti mismo.');

            return;
        }

        $datos['sucursal'] = $this->limitado() ? $datos['sucursal'] : null;
        if (blank($datos['password'])) {
            unset($datos['password']); // al editar, vacío = conserva la actual
        }

        $u = $this->editando ? tap(User::findOrFail($this->editando))->update($datos) : User::create($datos);

        $this->editando = null;
        unset($this->usuarios, $this->sucursales);
        $this->dispatch('toast', tipo: 'ok', mensaje: "Usuario {$u->username} guardado.");
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
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="usuarios" class="font-display text-[28px] font-semibold leading-none">Usuarios</h2>
            @if ($editando === null)
                <button type="button" wire:click="nuevo" class="btn btn-primario min-h-10 px-4">Nuevo usuario</button>
            @endif
        </div>

        @if ($editando !== null)
            <form wire:submit="guardar" class="panel panel-accion grid gap-5 px-5 py-5">
                <h3 class="panel-titulo">{{ $editando ? 'Editar usuario' : 'Nuevo usuario' }}</h3>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-aa.campo label="Nombre" for="u_name" :error="$errors->first('name')">
                        <input id="u_name" type="text" wire:model="name" class="input" autocomplete="off" @error('name') aria-invalid="true" @enderror>
                    </x-aa.campo>
                    <x-aa.campo label="Usuario (para entrar)" for="u_username" :error="$errors->first('username')"
                                ayuda="Letras, números, guion o guion bajo. Sin espacios.">
                        <input id="u_username" type="text" wire:model="username" class="input font-mono" autocomplete="off" autocapitalize="none"
                               @error('username') aria-invalid="true" @enderror>
                    </x-aa.campo>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-aa.campo label="Rol" for="u_rol" :error="$errors->first('rol')">
                        <select id="u_rol" wire:model.live="rol" class="input" @error('rol') aria-invalid="true" @enderror>
                            <option value="">Elige un rol</option>
                            @foreach (Rol::cases() as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                    </x-aa.campo>

                    @if ($this->limitado())
                        <x-aa.campo label="Sucursal" for="u_sucursal" :error="$errors->first('sucursal')"
                                    ayuda="Cajero y Sucursal solo ven los pedidos de esta sucursal.">
                            <select id="u_sucursal" wire:model="sucursal" class="input" @error('sucursal') aria-invalid="true" @enderror>
                                <option value="">Elige una sucursal</option>
                                @foreach ($this->sucursales as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </x-aa.campo>
                    @endif
                </div>

                <x-aa.campo label="{{ $editando ? 'Nueva contraseña (vacío = no cambia)' : 'Contraseña' }}" for="u_password"
                            :error="$errors->first('password')" ayuda="Mínimo 8 caracteres.">
                    <input id="u_password" type="password" wire:model="password" class="input sm:max-w-sm" autocomplete="new-password"
                           @error('password') aria-invalid="true" @enderror>
                </x-aa.campo>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cerrar" class="btn btn-secundario">Cancelar</button>
                    <button type="submit" class="btn btn-primario w-full sm:w-auto">Guardar</button>
                </div>
            </form>
        @endif

        <div class="panel overflow-x-auto">
            <table class="w-full min-w-[34rem] text-left">
                <thead>
                    <tr class="border-b border-linea bg-plancha/60">
                        <th scope="col" class="px-5 py-3 font-semibold">Nombre</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Usuario</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Rol</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Sucursal</th>
                        <th scope="col" class="px-3 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @foreach ($this->usuarios as $u)
                        <tr wire:key="usuario-{{ $u->id }}">
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $u->name }}</th>
                            <td class="px-3 py-3 font-mono text-[15px]">{{ $u->username }}</td>
                            <td class="px-3 py-3">{{ $u->rol->label() }}</td>
                            <td class="px-3 py-3 text-acero">{{ $u->sucursal ?? 'Todas' }}</td>
                            <td class="px-3 py-3 text-right">
                                <button type="button" wire:click="editar({{ $u->id }})" class="btn btn-secundario min-h-10 px-4">Editar</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>