<?php

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $username = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * Entra con nombre de usuario y contraseña (no con correo).
     */
    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        $credenciales = ['username' => Str::lower(trim($this->username)), 'password' => $this->password];

        if (! Auth::attempt($credenciales, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $this->redirectIntended(default: route('inicio', absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $segundos = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => __('auth.throttle', ['seconds' => $segundos]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->username).'|'.request()->ip());
    }
}; ?>

<div class="grid gap-8">
    <header class="grid gap-2">
        <h1 class="font-display text-[44px] font-semibold leading-none">Entrar</h1>
        <p class="text-acero">Usa tu nombre de usuario y tu contraseña.</p>
    </header>

    <form wire:submit="login" class="grid gap-5">
        <x-aa.campo label="Usuario" for="username" :error="$errors->first('username')">
            <input id="username" type="text" wire:model="username" class="input" autocomplete="username"
                   autocapitalize="none" spellcheck="false" autofocus required
                   @error('username') aria-invalid="true" @enderror>
        </x-aa.campo>

        <div x-data="{ ver: false }">
            <x-aa.campo label="Contraseña" for="password" :error="$errors->first('password')">
                <div class="relative">
                    <input id="password" :type="ver ? 'text' : 'password'" wire:model="password" class="input pr-12"
                           autocomplete="current-password" required>
                    <button type="button" x-on:click="ver = !ver"
                            class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-acero hover:text-tinta"
                            :aria-label="ver ? 'Ocultar contraseña' : 'Mostrar contraseña'">
                        <x-aa.icon name="eye" />
                    </button>
                </div>
            </x-aa.campo>
        </div>

        <label class="flex items-center gap-3 text-[16px]">
            <input type="checkbox" wire:model="remember" class="check">
            Mantener la sesión abierta en este equipo
        </label>

        <button type="submit" class="btn btn-primario w-full" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Entrando...</span>
        </button>
    </form>

    @if (config('aa.demo'))
        <section class="grid gap-3 border-t border-linea pt-6" aria-labelledby="demo-titulo">
            <div>
                <h2 id="demo-titulo" class="font-display text-xl font-semibold">Usuarios de prueba</h2>
                <p class="text-[15px] text-acero">Elige uno para llenar el formulario y entra para ver lo que ve ese rol.</p>
            </div>
            <ul class="flex flex-wrap gap-2">
                @foreach (config('aa.usuarios') as $u)
                    <li>
                        <button type="button" class="chip"
                                x-on:click="$wire.username = @js($u['username']); $wire.password = @js($u['password'])">
                            {{ \App\Enums\Rol::from($u['rol'])->label() }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>