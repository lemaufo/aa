<?php

use App\Enums\EstatusDispositivo;
use App\Enums\Rol;
use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

/**
 * Dispositivos autorizados: alta (nombre + MAC), edición y estatus Activo / Suspendido / Baja.
 * Solo el administrador (SYSDBA entra siempre como administrador). El permiso se revisa en boot(), que corre
 * en cada petición al componente: al abrir la pantalla y en cada acción, no solo en lo que se ve.
 */
new #[Title('Dispositivos autorizados')] class extends Component {
    public string $q = '';

    public string $filtroEstatus = '';

    /** Id del dispositivo que se edita; null = alta nueva. */
    public ?int $editando = null;

    public string $nombre = '';

    public string $mac = '';

    /** @var array{nombre: string, codigo: string}|null Código recién generado: solo se muestra esta vez. */
    public ?array $codigoNuevo = null;

    public function boot(): void
    {
        $usuario = auth()->user();

        if ($usuario?->rol !== Rol::Admin) {
            DispositivoEvento::anotar(null, $usuario, 'Acceso denegado a Dispositivos autorizados',
                ($usuario ? $usuario->username.' ('.$usuario->rol->label().')' : 'Sin sesión').' intentó entrar o hacer un cambio');

            abort(403);
        }
    }

    #[Computed]
    public function dispositivos()
    {
        $texto = trim($this->q);
        $mac = strtoupper(str_replace(['-', '.'], ':', $texto));

        return Dispositivo::query()
            ->when($texto !== '', fn ($q) => $q->where(fn ($q) => $q->where('nombre', 'like', "%{$texto}%")->orWhere('mac', 'like', "%{$mac}%")))
            ->when(EstatusDispositivo::tryFrom($this->filtroEstatus), fn ($q, $e) => $q->where('estatus', $e))
            ->orderByRaw("case estatus when 'activo' then 0 when 'suspendido' then 1 else 2 end")
            ->orderBy('nombre')
            ->get();
    }

    #[Computed]
    public function conteo(): array
    {
        return Dispositivo::query()->selectRaw('estatus, count(*) as n')->groupBy('estatus')->pluck('n', 'estatus')->all();
    }

    #[Computed]
    public function eventos()
    {
        return DispositivoEvento::query()->with(['dispositivo', 'user'])->latest('id')->limit(40)->get();
    }

    public function nuevo(): void
    {
        $this->reset('editando', 'nombre', 'mac', 'codigoNuevo');
        $this->resetErrorBag();
    }

    public function editar(int $id): void
    {
        $d = Dispositivo::findOrFail($id);
        abort_if($d->estatus === EstatusDispositivo::Baja, 403);

        $this->editando = $d->id;
        $this->nombre = $d->nombre;
        $this->mac = (string) $d->mac;
        $this->codigoNuevo = null;
        $this->resetErrorBag();
    }

    public function guardar(): void
    {
        // Si no se pudo normalizar (formato malo, la MAC nula o la de difusión) se valida un valor que nunca pasa el
        // formato; vacía se queda vacía para que el mensaje sea "Falta la dirección MAC".
        $mac = trim($this->mac);
        $datos = ['nombre' => trim($this->nombre), 'mac' => $mac === '' ? '' : (Dispositivo::normalizarMac($mac) ?? 'invalida')];

        // Validator::make no limpia los errores del intento anterior como $this->validate().
        $this->resetErrorBag();

        Validator::make($datos, [
            'nombre' => ['required', 'string', 'max:80'],
            'mac' => [
                'required',
                // Ya normalizada: si no quedó AA:BB:CC:DD:EE:FF es que no era una MAC.
                'regex:/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/',
                // Única entre los dispositivos que pueden operar; una MAC en Baja se puede volver a dar de alta.
                Rule::unique('dispositivos', 'mac')->where(fn ($q) => $q->where('estatus', '!=', EstatusDispositivo::Baja->value))->ignore($this->editando),
            ],
        ], [
            'mac.regex' => 'La MAC no es válida. Son 12 dígitos hexadecimales, por ejemplo AA:BB:CC:DD:EE:FF.',
            'mac.unique' => 'Esa MAC ya está registrada en otro dispositivo activo o suspendido.',
        ], ['nombre' => 'el nombre del dispositivo', 'mac' => 'la dirección MAC'])->validate();

        if ($this->editando === null) {
            $d = Dispositivo::create($datos + ['estatus' => EstatusDispositivo::Activo]);
            $codigo = $d->nuevoCodigo();
            DispositivoEvento::anotar($d, auth()->user(), 'Alta de dispositivo', 'MAC '.$datos['mac']);

            $this->codigoNuevo = ['nombre' => $datos['nombre'], 'codigo' => $codigo];
            $this->reset('nombre', 'mac');
            $this->dispatch('toast', tipo: 'ok', mensaje: "Dispositivo {$datos['nombre']} dado de alta.");

            return;
        }

        $d = Dispositivo::findOrFail($this->editando);
        abort_if($d->estatus === EstatusDispositivo::Baja, 403);

        $cambios = collect(['nombre' => 'Nombre', 'mac' => 'MAC'])
            ->filter(fn ($etiqueta, $campo) => $d->{$campo} !== $datos[$campo])
            ->map(fn ($etiqueta, $campo) => "{$etiqueta}: ".($d->{$campo} ?? 'sin registrar')." → {$datos[$campo]}")
            ->implode('; ');

        if ($cambios !== '') {
            $d->update($datos);
            DispositivoEvento::anotar($d, auth()->user(), 'Dispositivo editado', $cambios);
        }

        $this->reset('editando', 'nombre', 'mac');
        $this->dispatch('toast', tipo: 'ok', mensaje: 'Dispositivo guardado.');
    }

    public function cambiarEstatus(int $id, string $estatus): void
    {
        $d = Dispositivo::findOrFail($id);
        $nuevo = EstatusDispositivo::tryFrom($estatus);

        // Solo las transiciones permitidas: Baja es definitiva.
        abort_unless($nuevo && in_array($nuevo, $d->estatus->siguientes(), true), 403);

        $anterior = $d->estatus;
        $d->update(['estatus' => $nuevo] + ($nuevo === EstatusDispositivo::Baja
            // Baja corta la llave y el código pendiente; el dispositivo no vuelve a operar.
            ? ['revocado_at' => now(), 'token_hash' => null, 'codigo_hash' => null]
            : []));

        DispositivoEvento::anotar($d, auth()->user(), 'Cambio de estatus: '.$anterior->label().' → '.$nuevo->label());
        $this->dispatch('toast', tipo: 'ok', mensaje: "{$d->nombre}: {$nuevo->label()}.");
    }

    /** Código nuevo para volver a vincular un dispositivo activo (p. ej. se borró la cookie del navegador). */
    public function nuevoCodigo(int $id): void
    {
        $d = Dispositivo::findOrFail($id);
        abort_unless($d->operable(), 403);

        $this->codigoNuevo = ['nombre' => $d->nombre, 'codigo' => $d->nuevoCodigo()];
        DispositivoEvento::anotar($d, auth()->user(), 'Código nuevo generado', 'La llave anterior deja de servir');
    }
}; ?>

@php
    $badge = fn (EstatusDispositivo $e) => match ($e) {
        EstatusDispositivo::Activo => 'badge-avance',
        EstatusDispositivo::Suspendido => 'badge-neutro',
        EstatusDispositivo::Baja => 'badge-cancelado',
    };
@endphp

<div class="grid gap-6">
    <header class="grid gap-1">
        <h1 class="font-display text-4xl font-semibold">Dispositivos autorizados</h1>
        <p class="text-acero">
            @if (config('aa.dispositivos.activo'))
                Solo los dispositivos activos entran al sistema (el rol Consulta queda exento).
                @if (config('aa.dispositivos.validar_mac'))
                    Si el servidor ve la MAC del dispositivo en la red local, debe coincidir con la registrada.
                @endif
            @else
                La restricción está apagada (AA_DISPOSITIVOS=false): cualquier equipo entra. Puedes registrar dispositivos desde ahora.
            @endif
        </p>
    </header>

    {{-- Alta / edición --}}
    <section class="panel">
        <div class="panel-cabecera">
            <h2 class="panel-titulo">{{ $editando ? 'Editar dispositivo' : 'Dar de alta un dispositivo' }}</h2>
        </div>
        <form wire:submit="guardar" class="grid gap-4 px-5 py-5 sm:grid-cols-[1fr_1fr_auto] sm:items-start">
            <x-aa.campo label="Nombre del dispositivo" for="nombre" :error="$errors->first('nombre')">
                <input id="nombre" type="text" wire:model="nombre" class="input" placeholder="Báscula CEDIS" autocomplete="off" maxlength="80"
                       @error('nombre') aria-invalid="true" @enderror>
            </x-aa.campo>
            <x-aa.campo label="Dirección MAC" for="mac" :error="$errors->first('mac')" ayuda="Windows: ipconfig /all. Celular: Ajustes → Wi-Fi → la red → MAC (sin MAC privada).">
                <input id="mac" type="text" wire:model="mac" class="input font-mono uppercase" placeholder="AA:BB:CC:DD:EE:FF" autocomplete="off" spellcheck="false" maxlength="20"
                       @error('mac') aria-invalid="true" @enderror>
            </x-aa.campo>
            <div class="flex gap-2 sm:pt-7">
                <button type="submit" class="btn btn-primario" wire:loading.attr="disabled" wire:target="guardar">{{ $editando ? 'Guardar' : 'Dar de alta' }}</button>
                @if ($editando)
                    <button type="button" wire:click="nuevo" class="btn btn-secundario">Cancelar</button>
                @endif
            </div>
        </form>

        @if ($codigoNuevo)
            <div class="mx-5 mb-5 rounded-md border border-naranja bg-naranja-tinte px-4 py-3" role="status">
                <p class="text-[15px] text-acero">Código para <b class="text-tinta">{{ $codigoNuevo['nombre'] }}</b>. Escríbelo en la pantalla /dispositivo de ese dispositivo.
                    Solo se muestra ahora, sirve una vez y vence en {{ config('aa.dispositivos.codigo_vigencia_horas') }} horas.</p>
                <p class="folio text-4xl">{{ $codigoNuevo['codigo'] }}</p>
            </div>
        @endif
    </section>

    {{-- Listado --}}
    <section class="grid gap-3" aria-labelledby="listado">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="listado" class="font-display text-[28px] font-semibold leading-none">Dispositivos</h2>
            <div class="w-full sm:w-72">
                <label for="q" class="sr-only">Buscar por nombre o MAC</label>
                <input id="q" type="search" wire:model.live.debounce.300ms="q" class="input" placeholder="Buscar por nombre o MAC" autocomplete="off">
            </div>
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estatus">
            <button type="button" class="chip" wire:click="$set('filtroEstatus', '')" aria-pressed="{{ $filtroEstatus === '' ? 'true' : 'false' }}">
                Todos <span>{{ array_sum($this->conteo) }}</span>
            </button>
            @foreach (EstatusDispositivo::cases() as $e)
                <button type="button" class="chip" wire:click="$set('filtroEstatus', '{{ $e->value }}')" aria-pressed="{{ $filtroEstatus === $e->value ? 'true' : 'false' }}">
                    {{ $e->label() }} <span>{{ $this->conteo[$e->value] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        <div class="panel overflow-x-auto">
            <table class="w-full min-w-[46rem] text-left">
                <thead>
                    <tr class="border-b border-linea bg-plancha/60">
                        <th scope="col" class="px-5 py-3 font-semibold">Dispositivo</th>
                        <th scope="col" class="px-3 py-3 font-semibold">MAC</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Estatus</th>
                        <th scope="col" class="px-3 py-3 font-semibold">Vinculación</th>
                        <th scope="col" class="px-5 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-linea">
                    @forelse ($this->dispositivos as $d)
                        <tr wire:key="disp-{{ $d->id }}">
                            <th scope="row" class="px-5 py-3 font-semibold">{{ $d->nombre }}</th>
                            <td class="px-3 py-3 font-mono text-[15px]">{{ $d->mac ?? 'Sin registrar' }}</td>
                            <td class="px-3 py-3"><span class="badge {{ $badge($d->estatus) }}">{{ $d->estatus->label() }}</span></td>
                            <td class="px-3 py-3 text-[15px] text-acero">
                                {{ $d->conexion() }}@if ($d->ultimo_uso_at)<br>Último uso {{ $d->ultimo_uso_at->diffForHumans() }}@endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($d->estatus !== EstatusDispositivo::Baja)
                                        <button type="button" wire:click="editar({{ $d->id }})" class="btn btn-secundario">Editar</button>
                                    @endif
                                    @if ($d->operable())
                                        <button type="button" wire:click="nuevoCodigo({{ $d->id }})"
                                                wire:confirm="¿Generar un código nuevo para {{ $d->nombre }}? La llave actual de ese dispositivo dejará de servir."
                                                class="btn btn-secundario">Nuevo código</button>
                                    @endif
                                    @foreach ($d->estatus->siguientes() as $s)
                                        <button type="button" wire:click="cambiarEstatus({{ $d->id }}, '{{ $s->value }}')"
                                                wire:confirm="{{ match ($s) {
                                                    EstatusDispositivo::Activo => '¿Reactivar '.$d->nombre.'?',
                                                    EstatusDispositivo::Suspendido => '¿Suspender '.$d->nombre.'? No podrá entrar hasta que lo reactives.',
                                                    EstatusDispositivo::Baja => '¿Dar de baja '.$d->nombre.'? Es definitivo: si vuelve a usarse habrá que darlo de alta otra vez.',
                                                } }}"
                                                @class(['btn', 'btn-secundario', 'text-peligro' => $s === EstatusDispositivo::Baja])>
                                            {{ match ($s) { EstatusDispositivo::Activo => 'Reactivar', EstatusDispositivo::Suspendido => 'Suspender', EstatusDispositivo::Baja => 'Dar de baja' } }}
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-5 text-acero">{{ $q !== '' || $filtroEstatus !== '' ? 'Ningún dispositivo coincide con la búsqueda.' : 'Todavía no hay dispositivos registrados.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Bitácora de dispositivos --}}
    <section class="panel" aria-labelledby="bitacora-dispositivos">
        <div class="panel-cabecera">
            <h2 id="bitacora-dispositivos" class="panel-titulo">Bitácora de dispositivos</h2>
            <p class="text-[15px] text-acero">Últimos {{ $this->eventos->count() }} movimientos</p>
        </div>
        <ul class="divide-y divide-linea">
            @forelse ($this->eventos as $e)
                <li class="grid gap-0.5 px-5 py-3" wire:key="dev-{{ $e->id }}">
                    <p class="font-semibold">{{ $e->accion }}@if ($e->dispositivo) · {{ $e->dispositivo->nombre }}@endif</p>
                    @if ($e->detalle)
                        <p class="text-[15px] text-tinta/80">{{ $e->detalle }}</p>
                    @endif
                    <p class="text-[15px] text-acero">
                        {{ $e->user?->name ?? 'Sistema' }}@if ($e->ip) · {{ $e->ip }}@endif,
                        <time datetime="{{ $e->created_at->toIso8601String() }}">{{ $e->created_at->translatedFormat('j M Y, H:i') }}</time>
                    </p>
                </li>
            @empty
                <li class="px-5 py-5 text-acero">Sin movimientos todavía.</li>
            @endforelse
        </ul>
    </section>
</div>
