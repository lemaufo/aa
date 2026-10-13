<?php

namespace App\Livewire\Concerns;

/**
 * Lista de "piezas × medida" con tope de renglones (el que cabe en la etiqueta).
 * El componente usa $cortes, agregarCorte(), quitarCorte() y totalMetros().
 */
trait ManejaCortes
{
    /** @var list<array{piezas: int|string, medida: float|string}> */
    public array $cortes = [];

    protected function iniciarCortes(?array $guardados): void
    {
        $this->cortes = collect($guardados ?: [])
            ->map(fn ($c) => ['piezas' => $c['piezas'] ?? '', 'medida' => $c['medida'] ?? ''])
            ->values()
            ->all();

        if ($this->cortes === []) {
            $this->cortes = [['piezas' => '', 'medida' => '']];
        }
    }

    protected function maxCortes(): int
    {
        return (int) config('aa.max_medidas', 11);
    }

    public function agregarCorte(): void
    {
        if (count($this->cortes) >= $this->maxCortes()) {
            $this->addError('cortes', 'La etiqueta admite hasta '.$this->maxCortes().' medidas distintas. Si necesitas más, divide el pedido en dos traspasos.');

            return;
        }

        $this->resetErrorBag('cortes');
        $this->cortes[] = ['piezas' => '', 'medida' => ''];
    }

    public function quitarCorte(int $indice): void
    {
        unset($this->cortes[$indice]);
        $this->cortes = array_values($this->cortes);
        $this->resetErrorBag('cortes');

        if ($this->cortes === []) {
            $this->cortes = [['piezas' => '', 'medida' => '']];
        }
    }

    public function totalMetros(): float
    {
        return round(collect($this->cortes)->sum(
            fn ($c) => (float) ($c['piezas'] ?: 0) * (float) ($c['medida'] ?: 0)
        ), 2);
    }

    /** @return array<string, mixed> */
    protected function reglasCortes(): array
    {
        return [
            'cortes' => ['required', 'array', 'min:1', 'max:'.$this->maxCortes()],
            'cortes.*.piezas' => ['required', 'integer', 'min:1', 'max:9999'],
            'cortes.*.medida' => ['required', 'numeric', 'min:0.1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    protected function atributosCortes(): array
    {
        return ['cortes' => 'piezas y medidas', 'cortes.*.piezas' => 'piezas', 'cortes.*.medida' => 'medida'];
    }

    /** @return list<array{piezas: int, medida: float}> */
    protected function cortesLimpios(): array
    {
        return collect($this->cortes)
            ->map(fn ($c) => ['piezas' => (int) $c['piezas'], 'medida' => round((float) $c['medida'], 2)])
            ->values()
            ->all();
    }

    /** Valida que el total cuadre con los metros del traspaso. Devuelve false (y marca el error) si no. */
    protected function cuadraConTraspaso(float $meta): bool
    {
        if (abs($this->totalMetros() - $meta) < 0.005) {
            return true;
        }

        $this->addError('cortes', 'Las piezas suman '.number_format($this->totalMetros(), 2).' m y el traspaso es de '.number_format($meta, 2).' m. Deben coincidir.');

        return false;
    }

    /** @return list<string> */
    public function erroresCortes(): array
    {
        return collect($this->getErrorBag()->getMessages())
            ->filter(fn ($mensajes, $campo) => $campo === 'cortes' || str_starts_with($campo, 'cortes.'))
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }
}