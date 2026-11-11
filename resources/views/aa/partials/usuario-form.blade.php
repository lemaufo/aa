{{-- Formulario de usuario (pantalla Usuarios). Nuevo: arriba de la tabla. Editar: debajo de la fila del usuario. --}}
<form wire:submit="guardar" @class(['grid gap-5 px-5 py-5', 'panel panel-accion' => ! $editando])>
    <h3 class="panel-titulo">{{ $editando ? 'Editar usuario' : ($conMicrosip ? 'Asignar usuario de Microsip' : 'Nuevo usuario') }}</h3>

    @if ($conMicrosip && ! $editando)
        @if ($catalogo === null)
            <p class="font-medium text-peligro" role="alert">No se pudo consultar Microsip en este momento. Intenta de nuevo en unos minutos.</p>
        @elseif ($disponibles === [])
            <p class="text-acero">Todos los usuarios de Microsip ya están en la app. Los nuevos se crean en Microsip.</p>
        @endif

        <x-aa.campo label="Usuario de Microsip" for="u_microsip" :error="$errors->first('microsip')"
                    ayuda="Los usuarios de Microsip con acceso a {{ config('aa.empresa_microsip') }} que todavía no están en la app.">
            <select id="u_microsip" wire:model.live="microsip" class="input font-mono" @error('microsip') aria-invalid="true" @enderror>
                <option value="">Elige un usuario</option>
                @foreach ($disponibles as $m)
                    <option value="{{ $m['usuario'] }}" @disabled(! $m['activo'])>
                        {{ $m['usuario'] }}{{ $m['rol'] ? ' · rol '.$m['rol'] : '' }}{{ $m['activo'] ? '' : ' · baja en Microsip' }}
                    </option>
                @endforeach
            </select>
        </x-aa.campo>
    @endif

    <div class="grid gap-5 sm:grid-cols-2">
        <x-aa.campo label="Nombre" for="u_name" :error="$errors->first('name')">
            <input id="u_name" type="text" wire:model="name" class="input" autocomplete="off" @error('name') aria-invalid="true" @enderror>
        </x-aa.campo>
        <x-aa.campo label="Usuario (para entrar)" for="u_username" :error="$errors->first('username')"
                    ayuda="{{ $conMicrosip ? 'El mismo de Microsip; no se cambia aquí.' : 'Letras, números, guion o guion bajo. Sin espacios.' }}">
            <input id="u_username" type="text" wire:model="username" class="input font-mono" autocomplete="off" autocapitalize="none"
                   @disabled($conMicrosip) @error('username') aria-invalid="true" @enderror>
        </x-aa.campo>
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <x-aa.campo label="Rol" for="u_rol" :error="$errors->first('rol')">
            <select id="u_rol" wire:model.live="rol" class="input" @error('rol') aria-invalid="true" @enderror>
                <option value="">Elige un rol</option>
                @foreach (\App\Enums\Rol::cases() as $r)
                    <option value="{{ $r->value }}">{{ $r->label() }}</option>
                @endforeach
            </select>
        </x-aa.campo>

        @if ($limitado)
            <x-aa.campo label="Sucursal" for="u_sucursal" :error="$errors->first('sucursal')"
                        ayuda="Cajero y Sucursal solo ven los pedidos de esta sucursal.">
                <select id="u_sucursal" wire:model="sucursal" class="input" @error('sucursal') aria-invalid="true" @enderror>
                    <option value="">Elige una sucursal</option>
                    @foreach ($sucursales as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </x-aa.campo>
        @endif
    </div>

    @unless ($conMicrosip)
        <x-aa.campo label="{{ $editando ? 'Nueva contraseña (vacío = no cambia)' : 'Contraseña' }}" for="u_password"
                    :error="$errors->first('password')" ayuda="Mínimo 8 caracteres.">
            <input id="u_password" type="password" wire:model="password" class="input sm:max-w-sm" autocomplete="new-password"
                   @error('password') aria-invalid="true" @enderror>
        </x-aa.campo>
    @endunless

    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button type="button" wire:click="cerrar" class="btn btn-secundario">Cancelar</button>
        <button type="submit" class="btn btn-primario w-full sm:w-auto">Guardar</button>
    </div>
</form>
