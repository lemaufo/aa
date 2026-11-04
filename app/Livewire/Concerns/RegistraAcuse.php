<?php

namespace App\Livewire\Concerns;

use App\Models\Pedido;
use App\Support\Evidencia;
use Livewire\WithFileUploads;

/** Acuse de entrega obligatorio: nombre de quien recibe (el panel declara $recibe_nombre) y foto del documento firmado. */
trait RegistraAcuse
{
    use WithFileUploads;

    public $foto_acuse = null;

    /** @return array<string, mixed> */
    protected function reglasAcuse(): array
    {
        return [
            'recibe_nombre' => ['required', 'string', 'min:3', 'max:120'],
            'foto_acuse' => Evidencia::reglas(),
        ];
    }

    protected function guardarAcuse(Pedido $pedido): string
    {
        return Evidencia::guardar($this->foto_acuse, $pedido, 'acuse');
    }
}
