<?php

use App\Enums\FormaEntrega;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

/** Corrige los datos de entrega mientras el pedido no haya llegado a CEDIS. Pide la contraseña del cajero. */
new class extends Component {
    public Pedido $pedido;

    public bool $abierto = false;

    public string $forma_entrega = '';

    public string $telefono = '';

    public string $recibe_nombre = '';

    public string $direccion_entrega = '';

    public string $gps_link = '';

    public string $password = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->forma_entrega = $pedido->forma_entrega?->value ?? '';
        $this->telefono = (string) $pedido->telefono;
        $this->recibe_nombre = (string) $pedido->recibe_nombre;
        $this->direccion_entrega = (string) $pedido->direccion_entrega;
        $this->gps_link = (string) $pedido->gps_link;
    }

    public function aDomicilio(): bool
    {
        return $this->forma_entrega === FormaEntrega::Cliente->value;
    }

    public function guardar(FlujoService $flujo): void
    {
        $this->validate([
            'forma_entrega' => ['required', Rule::enum(FormaEntrega::class)],
            'telefono' => ['required', 'string', 'regex:/^[0-9\s\-\(\)\+]{10,18}$/'],
            'recibe_nombre' => [Rule::requiredIf($this->aDomicilio()), 'nullable', 'string', 'max:120'],
            'direccion_entrega' => [Rule::requiredIf($this->aDomicilio()), 'nullable', 'string', 'max:255'],
            'gps_link' => ['nullable', 'url', 'max:255'],
            'password' => ['required', 'string'],
        ], [], [
            'forma_entrega' => 'forma de entrega',
            'telefono' => 'teléfono',
            'recibe_nombre' => 'persona que recibe',
            'direccion_entrega' => 'dirección',
            'gps_link' => 'enlace de ubicación',
            'password' => 'contraseña',
        ]);

        $llave = 'cambiar-entrega:'.auth()->id();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            $this->addError('password', 'Demasiados intentos. Espera un minuto.');

            return;
        }

        if (! Hash::check($this->password, auth()->user()->password)) {
            RateLimiter::hit($llave, 60);
            $this->addError('password', 'La contraseña no es correcta.');

            return;
        }
        RateLimiter::clear($llave);

        $domicilio = $this->aDomicilio();
        $antes = $this->pedido->forma_entrega?->label() ?? 'sin definir';
        $ahora = FormaEntrega::from($this->forma_entrega)->label();

        try {
            $flujo->aplicar($this->pedido, 'cambiar_entrega', auth()->user(), [
                'forma_entrega' => $this->forma_entrega,
                'telefono' => trim($this->telefono),
                'recibe_nombre' => $domicilio ? trim($this->recibe_nombre) : null,
                'direccion_entrega' => $domicilio ? trim($this->direccion_entrega) : null,
                'gps_link' => $domicilio ? (trim($this->gps_link) ?: null) : null,
            ], $antes === $ahora ? "Se corrigieron datos de entrega ({$ahora})" : "Forma de entrega: {$antes} a {$ahora}");
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->password = '';
        $this->abierto = false;
        $this->dispatch('toast', tipo: 'ok', mensaje: 'Datos de entrega actualizados.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Datos de entrega</h2>
        <button type="button" wire:click="$toggle('abierto')" class="text-[15px] font-semibold text-naranja-fuerte underline-offset-4 hover:underline">
            {{ $abierto ? 'Cerrar' : 'Modificar' }}
        </button>
    </div>

    @if ($abierto)
        <form wire:submit="guardar" class="grid gap-5 px-5 py-5">
            <p class="text-acero">Se puede corregir hasta que el pedido llegue a CEDIS. Queda registrado en la bitácora y necesitas tu contraseña.</p>

            <div class="grid gap-5 sm:grid-cols-2">
                <x-aa.campo label="Forma de entrega" for="ce_forma" :error="$errors->first('forma_entrega')">
                    <select id="ce_forma" wire:model.live="forma_entrega" class="input">
                        @foreach (\App\Enums\FormaEntrega::cases() as $forma)
                            <option value="{{ $forma->value }}">{{ $forma->label() }}</option>
                        @endforeach
                    </select>
                </x-aa.campo>
                <x-aa.campo label="Teléfono del cliente" for="ce_tel" :error="$errors->first('telefono')">
                    <input id="ce_tel" type="tel" inputmode="tel" wire:model="telefono" class="input" autocomplete="off">
                </x-aa.campo>
            </div>

            @if ($this->aDomicilio())
                <x-aa.campo label="Persona que recibe" for="ce_recibe" :error="$errors->first('recibe_nombre')">
                    <input id="ce_recibe" type="text" wire:model="recibe_nombre" class="input" autocomplete="off">
                </x-aa.campo>
                <x-aa.campo label="Dirección completa, con referencias" for="ce_dir" :error="$errors->first('direccion_entrega')">
                    <textarea id="ce_dir" wire:model="direccion_entrega" rows="3" class="input"></textarea>
                </x-aa.campo>
                <x-aa.campo label="Enlace de ubicación (Google Maps)" for="ce_gps" :error="$errors->first('gps_link')">
                    <input id="ce_gps" type="url" wire:model="gps_link" class="input" inputmode="url">
                </x-aa.campo>
            @endif

            <x-aa.campo label="Tu contraseña, para autorizar el cambio" for="ce_pass" :error="$errors->first('password')">
                <input id="ce_pass" type="password" wire:model="password" class="input" autocomplete="current-password">
            </x-aa.campo>

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="guardar">Autorizar y guardar</button>
            </div>
        </form>
    @endif
</section>