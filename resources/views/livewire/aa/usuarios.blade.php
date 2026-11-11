<?php

use App\Contracts\UsuariosMicrosip;
use App\Enums\Rol;
use App\Exceptions\MicrosipNoResponde;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Flujo;
use Illuminate\Support\Str;
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

    /** Con Microsip: el usuario de Microsip que se va a asignar (USUARIOS.NOMBRE). */
    public string $microsip = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);
    }

    #[Computed]
    public function usuarios()
    {
        return User::orderBy('id')->get();
    }

    /** Con Microsip se asignan sus usuarios y la contraseña es la de Microsip; en demo, las de la app. */
    public function conMicrosip(): bool
    {
        return (bool) config('aa.login_microsip');
    }

    /** Catálogo de usuarios de Microsip, o null si no contesta. */
    #[Computed]
    public function catalogo(): ?array
    {
        if (! $this->conMicrosip()) {
            return [];
        }

        try {
            return app(UsuariosMicrosip::class)->listar();
        } catch (MicrosipNoResponde $e) {
            report($e);

            return null;
        }
    }

    /** Los de Microsip que todavía no están en la app. */
    public function disponibles(): array
    {
        $asignados = User::pluck('username')->all();

        return array_values(array_filter($this->catalogo ?? [], fn ($m) => ! in_array(strtolower($m['usuario']), $asignados, true)));
    }

    /** Al elegir un usuario de Microsip se sugiere el nombre y el rol de la app según su rol en Microsip. */
    public function updatedMicrosip(string $valor): void
    {
        $m = collect($this->catalogo ?? [])->firstWhere('usuario', $valor);

        $this->username = strtolower($valor);
        $this->name = $this->name ?: Str::title($valor);
        $this->rol = (string) (config('aa.roles_microsip')[strtoupper((string) ($m['rol'] ?? ''))] ?? $this->rol);
    }

    /** Vuelve a leer de Microsip el rol y el estatus de todos los usuarios de la app. */
    public function sincronizar(): void
    {
        abort_unless(auth()->user()->rol === Rol::Admin, 403);
        unset($this->catalogo);

        if ($this->catalogo === null) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'No se pudo consultar Microsip en este momento.');

            return;
        }

        $catalogo = collect($this->catalogo)->keyBy(fn ($m) => strtolower($m['usuario']));

        foreach (User::all() as $u) {
            // Si ya no está en Microsip (o perdió acceso a la empresa) queda marcado y no puede entrar.
            $u->sincronizarMicrosip($catalogo[$u->username] ?? ['id' => null, 'rol' => null, 'activo' => false]);
            $u->save();
        }

        unset($this->usuarios);
        $this->dispatch('toast', tipo: 'ok', mensaje: 'Usuarios actualizados desde Microsip.');
    }

    /**
     * Los almacenes de Microsip (en demo, las de configuración) más las que ya usan pedidos y usuarios,
     * sin los almacenes que no son sucursal (CEDIS, producción, materia prima, tránsito).
     */
    #[Computed]
    public function sucursales()
    {
        $servicio = app(\App\Services\Sucursales::class);

        return collect($servicio->nombres())
            ->merge(User::whereNotNull('sucursal')->distinct()->pluck('sucursal'))
            ->merge(Pedido::distinct()->pluck('sucursal'))
            ->filter()->unique()->diff($servicio->noSucursales())->sort()->values();
    }

    public function nuevo(): void
    {
        $this->resetErrorBag();
        $this->reset('name', 'username', 'rol', 'sucursal', 'password', 'microsip');
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

        if ($this->conMicrosip()) {
            $this->guardarMicrosip();

            return;
        }

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'alpha_dash:ascii', 'max:40', Rule::unique('users', 'username')->ignore($this->editando ?: null)],
            'rol' => ['required', Rule::enum(Rol::class)],
            'sucursal' => [Rule::requiredIf($this->limitado()), 'nullable', 'string', 'max:120'],
            'password' => [$this->editando ? 'nullable' : 'required', 'string', 'min:8', 'max:72'],
        ]);

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

    /** Con Microsip no hay contraseña en la app: se asigna un usuario de Microsip con rol y sucursal. */
    private function guardarMicrosip(): void
    {
        $nuevo = ! $this->editando;
        $m = $nuevo ? collect($this->disponibles())->firstWhere('usuario', $this->microsip) : null;

        if ($nuevo && $m === null) {
            $this->addError('microsip', 'Elige un usuario de Microsip que todavía no esté en la app.');

            return;
        }

        $datos = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'rol' => ['required', Rule::enum(Rol::class)],
            'sucursal' => [Rule::requiredIf($this->limitado()), 'nullable', 'string', 'max:120'],
        ]);

        $u = $nuevo ? new User(['username' => strtolower($m['usuario'])]) : User::findOrFail($this->editando);

        // SYSDBA es el supervisor de Microsip y el administrador de la app; tampoco te quitas tu propio rol.
        if (($u->esSysdba() || $u->id === auth()->id()) && $datos['rol'] !== Rol::Admin->value) {
            $this->addError('rol', $u->esSysdba() ? 'SYSDBA siempre es administrador.' : 'No puedes quitarte el rol de administrador a ti mismo.');

            return;
        }

        $u->fill(['name' => $datos['name'], 'rol' => $datos['rol'], 'sucursal' => $this->limitado() ? $datos['sucursal'] : null]);

        if ($nuevo) {
            $u->sincronizarMicrosip($m);
            $u->password = Str::random(40); // la que vale es la de Microsip; se guarda al primer acceso
        }

        $u->save();

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
                <div class="flex flex-wrap gap-2">
                    @if ($this->conMicrosip())
                        <button type="button" wire:click="sincronizar" class="btn btn-secundario min-h-10 px-4" wire:loading.attr="disabled" wire:target="sincronizar">
                            Actualizar desde Microsip
                        </button>
                    @endif
                    <button type="button" wire:click="nuevo" class="btn btn-primario min-h-10 px-4">
                        {{ $this->conMicrosip() ? 'Asignar usuario de Microsip' : 'Nuevo usuario' }}
                    </button>
                </div>
            @endif
        </div>

        @if ($this->conMicrosip())
            <p class="max-w-prose text-[15px] text-acero">
                Se entra con el usuario y la contraseña de Microsip. Aquí solo se elige qué usuarios de Microsip usan la app y con qué rol;
                las contraseñas se cambian en Microsip.
            </p>
        @endif

        @php($formulario = [
            'editando' => $editando,
            'conMicrosip' => $this->conMicrosip(),
            'catalogo' => $editando === 0 ? $this->catalogo : [],
            'disponibles' => $editando === 0 ? $this->disponibles() : [],
            'sucursales' => $editando !== null ? $this->sucursales : collect(),
            'limitado' => $this->limitado(),
        ])

        {{-- Usuario nuevo: arriba. Al editar, el formulario sale debajo de la fila de ese usuario. --}}
        @if ($editando === 0)
            @include('aa.partials.usuario-form', $formulario)
        @endif

        <div class="panel overflow-x-auto">
            <table class="w-full min-w-[34rem] text-left">
                <thead>
                    <tr class="border-b border-linea bg-plancha/60">
                        <th scope="col" class="px-5 py-3 font-semibold">Nombre</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Usuario</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Rol</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Sucursal</th>
                        @if ($this->conMicrosip())
                            <th scope="col" class="px-3 py-3 font-semibold">En Microsip</th>
                        @endif
                        <th scope="col" class="px-3 py-3"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @foreach ($this->usuarios as $u)
                        <tr wire:key="usuario-{{ $u->id }}" @class(["bg-naranja-tinte/60" => $editando === $u->id])>
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $u->name }}</th>
                            <td class="px-3 py-3 font-mono text-[15px]">{{ $u->username }}</td>
                            <td class="px-3 py-3">{{ $u->rol->label() }}</td>
                            <td class="px-3 py-3 text-acero">{{ $u->sucursal ?? 'Todas' }}</td>
                            @if ($this->conMicrosip())
                                <td class="px-3 py-3 text-[15px]">
                                    @if ($u->esSysdba() && $u->microsip_sync_at === null)
                                        <span class="font-semibold text-ok">Supervisor de Microsip</span>
                                    @elseif ($u->microsip_sync_at === null)
                                        <span class="text-acero">Sin ligar · no puede entrar</span>
                                    @elseif (! $u->microsip_activo)
                                        <span class="font-semibold text-peligro">Baja · no puede entrar</span>
                                    @else
                                        <span class="font-semibold text-ok">Activo</span>
                                        <span class="text-acero">{{ $u->esSysdba() ? '· supervisor' : ($u->microsip_rol ? '· rol '.$u->microsip_rol : '') }}</span>
                                    @endif
                                    @if ($u->microsip_sync_at)
                                        <span class="block text-[13px] text-acero">Revisado {{ $u->microsip_sync_at->diffForHumans() }}</span>
                                    @endif
                                </td>
                            @endif
                            <td class="px-3 py-3 text-right">
                                @if ($editando === $u->id)
                                    <button type="button" wire:click="cerrar" class="btn btn-secundario min-h-10 px-4">Cerrar</button>
                                @else
                                    <button type="button" wire:click="editar({{ $u->id }})" class="btn btn-secundario min-h-10 px-4">Editar</button>
                                @endif
                            </td>
                        </tr>
                        @if ($editando === $u->id)
                            <tr wire:key="editar-{{ $u->id }}" class="bg-naranja-tinte/60">
                                <td colspan="{{ $this->conMicrosip() ? 6 : 5 }}" class="border-l-4 border-naranja p-0"
                                    x-init="$el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); $el.querySelector('#u_name')?.focus({ preventScroll: true })">
                                    @include('aa.partials.usuario-form', $formulario)
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>