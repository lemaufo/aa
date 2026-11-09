<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use App\Services\Autorizacion;
use DomainException;

/** Usuario y contraseña de un supervisor, escritos en el equipo del operador (ver App\Services\Autorizacion). */
trait PideAutorizacion
{
    public string $aut_usuario = '';

    public string $aut_password = '';

    /** @return array<string, mixed> */
    protected function reglasAutorizacion(): array
    {
        return [
            'aut_usuario' => ['required', 'string', 'max:60'],
            'aut_password' => ['required', 'string', 'max:72'],
        ];
    }

    /** El supervisor que autoriza, o null con el error ya marcado. La contraseña nunca se queda en el formulario. */
    protected function supervisor(): ?User
    {
        try {
            return app(Autorizacion::class)->autorizar(auth()->user(), $this->aut_usuario, $this->aut_password);
        } catch (DomainException $e) {
            $this->addError('aut_password', $e->getMessage());

            return null;
        } finally {
            $this->aut_password = '';
        }
    }

    protected function limpiarAutorizacion(): void
    {
        $this->reset('aut_usuario', 'aut_password');
        $this->resetErrorBag(['aut_usuario', 'aut_password']);
    }
}
