<?php

namespace App\Livewire\Concerns;

/**
 * Captura de "piezas × medida": una o varias líneas cuya suma de metros
 * debe coincidir con los metros lineales del traspaso (la calculadora de control).
 */
trait ManejaCortes
{
    /** @var array<int, array{piezas: mixed, medida: mixed}> */
    public array $cortes = [];

    public function iniciarCortes(?array $base): void
    {
        $this->cortes = ! empty($base) ? array_values($base) : [['piezas' => '', 'medida' => '']];
    }

    public function agregarCorte(): void
    {
        $this->cortes[] = ['piezas' => '', 'medida' => ''];
    }

    public function quitarCorte(int $indice): void
    {
        unset($this->cortes[$indice]);
        $this->cortes = array_values($this->cortes) ?: [['piezas' => '', 'medida' => '']];
    }

    public function totalMetros(): float
    {
        return round(collect($this->cortes)->sum(
            fn ($c) => (float) ($c['piezas'] ?? 0) * (float) ($c['medida'] ?? 0)
        ), 2);
    }

    /** @return array<int, array{piezas: int, medida: float}> */
    public function cortesLimpios(): array
    {
        return collect($this->cortes)
            ->map(fn ($c) => ['piezas' => (int) $c['piezas'], 'medida' => (float) $c['medida']])
            ->values()
            ->all();
    }

    /** Mensajes de error que pertenecen a las líneas de piezas y medidas. */
    public function erroresCortes(): array
    {
        return collect($this->getErrorBag()->getMessages())
            ->filter(fn ($mensajes, $campo) => str_starts_with($campo, 'cortes'))
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    protected function reglasCortes(): array
    {
        return [
            'cortes' => ['required', 'array', 'min:1'],
            'cortes.*.piezas' => ['required', 'integer', 'min:1', 'max:9999'],
            'cortes.*.medida' => ['required', 'numeric', 'min:0.1', 'max:99'],
        ];
    }
}