<?php

namespace App\Livewire\Concerns;

use App\Models\Pedido;
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
            'foto_acuse' => ['required', 'image', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    protected function atributosAcuse(): array
    {
        return ['recibe_nombre' => 'nombre de quien recibe', 'foto_acuse' => 'foto del acuse'];
    }

    protected function guardarAcuse(Pedido $pedido): string
    {
        return $this->foto_acuse->store('evidencias/'.$pedido->id, 'local');
    }
}