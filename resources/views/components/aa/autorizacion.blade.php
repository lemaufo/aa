{{-- Campos para que un supervisor autorice desde el equipo del operador (trait PideAutorizacion). --}}
@props(['quienes' => \App\Services\Autorizacion::quienes()])

<div {{ $attributes->class('grid gap-4 sm:grid-cols-2') }}>
    <x-aa.campo label="Usuario del supervisor" for="aut_usuario" :error="$errors->first('aut_usuario')"
                ayuda="Autoriza un {{ $quienes }}.">
        <input id="aut_usuario" type="text" wire:model="aut_usuario" class="input" autocomplete="off" autocapitalize="none"
               @error('aut_usuario') aria-invalid="true" @enderror>
    </x-aa.campo>
    <x-aa.campo label="Contraseña del supervisor" for="aut_password" :error="$errors->first('aut_password')">
        <input id="aut_password" type="password" wire:model="aut_password" class="input" autocomplete="off"
               @error('aut_password') aria-invalid="true" @enderror>
    </x-aa.campo>
</div>
