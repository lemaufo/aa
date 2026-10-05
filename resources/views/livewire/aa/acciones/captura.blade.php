<?php

use App\Enums\FormaEntrega;
use App\Livewire\Concerns\ManejaCortes;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    use ManejaCortes;

    public Pedido $pedido;

    public string $direccion_entrega = '';

    public string $gps_link = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->direccion_entrega = (string) $pedido->direccion_entrega;
        $this->gps_link = (string) $pedido->gps_link;
        $this->iniciarCortes($pedido->cortes_producidos ?? $pedido->cortes_solicitados);
    }

    public function cuadra(): bool
    {
        return abs($this->totalMetros() - $this->pedido->metros_traspaso) < 0.005;
    }

    public function inconsistenciaReportada(): bool
    {
        return $this->pedido->eventos()->where('accion', 'Inconsistencia reportada')->exists();
    }

    public function validarCaptura(FlujoService $flujo): void
    {
        $this->validate([
            'direccion_entrega' => [
                Rule::requiredIf($this->pedido->forma_entrega === FormaEntrega::Cliente),
                'nullable', 'string', 'max:255',
            ],
            'gps_link' => ['nullable', 'url', 'max:255'],
        ] + $this->reglasCortes());

        if (! $this->cuadra()) {
            $this->addError('cortes', 'Piezas × medida da '.number_format($this->totalMetros(), 2).' m y el traspaso es de '
                .number_format($this->pedido->metros_traspaso, 2).' m. Corrige la captura o repórtala como inconsistencia.');

            return;
        }

        try {
            $flujo->aplicar($this->pedido, 'capturar', auth()->user(), [
                'cortes_producidos' => $this->cortesLimpios(),
                'direccion_entrega' => trim($this->direccion_entrega) ?: null,
                'gps_link' => trim($this->gps_link) ?: null,
                'captura_validada_at' => now(),
            ], 'Piezas × medida = '.number_format($this->totalMetros(), 2).' m, coincide con el traspaso');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Captura validada. Ya puedes imprimir la etiqueta.');
        $this->dispatch('pedido-actualizado');
    }

    public function reportarInconsistencia(FlujoService $flujo): void
    {
        $this->validate($this->reglasCortes());

        try {
            $flujo->aplicar($this->pedido, 'inconsistencia', auth()->user(), [],
                'Capturado '.number_format($this->totalMetros(), 2).' m contra '.number_format($this->pedido->metros_traspaso, 2).' m del traspaso');
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Inconsistencia registrada en la bitácora para su revisión.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Captura de producción</h2>
    </div>

    <form wire:submit="validarCaptura" class="grid gap-5 px-5 py-5">
        <p class="text-acero">Captura lo que realmente se cortó. El sistema compara piezas × medida contra los metros del traspaso antes de dejarte imprimir la etiqueta.</p>

        @if ($this->inconsistenciaReportada())
            <div class="flex gap-3 rounded-md border border-peligro bg-peligro-tinte px-4 py-3" role="status">
                <x-aa.icon name="exclamation-triangle" class="mt-0.5 text-peligro" />
                <p class="text-[15px]">Ya se reportó una inconsistencia en este pedido. Queda en la bitácora para revisión; puedes corregir la captura y validarla.</p>
            </div>
        @endif

        <x-aa.cortes :cortes="$cortes" :total="$this->totalMetros()" :meta="$pedido->metros_traspaso"
                     titulo="Piezas y medidas cortadas" :errores="$this->erroresCortes()" />

        <div class="grid gap-5">
            <x-aa.campo label="Dirección de entrega, con referencias" for="direccion_entrega"
                        :error="$errors->first('direccion_entrega')"
                        :ayuda="$pedido->forma_entrega === \App\Enums\FormaEntrega::Cliente ? null : 'Solo hace falta si el pedido se envía al domicilio del cliente.'">
                <textarea id="direccion_entrega" wire:model="direccion_entrega" rows="3" class="input"
                          placeholder="Calle, número, colonia y referencias para llegar"
                          @error('direccion_entrega') aria-invalid="true" @enderror></textarea>
            </x-aa.campo>

            <x-aa.campo label="Enlace de ubicación (Google Maps)" for="gps_link" :error="$errors->first('gps_link')"
                        ayuda="Pega el enlace que da la opción Compartir de Google Maps.">
                <input id="gps_link" type="url" wire:model="gps_link" class="input" inputmode="url"
                       placeholder="https://maps.google.com/..." @error('gps_link') aria-invalid="true" @enderror>
            </x-aa.campo>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            @if (! $this->cuadra() && $this->totalMetros() > 0)
                <button type="button" wire:click="reportarInconsistencia" class="btn btn-peligro">
                    Reportar inconsistencia
                </button>
            @endif
            <button type="submit" class="btn btn-primario" @disabled(! $this->cuadra()) wire:loading.attr="disabled" wire:target="validarCaptura">
                Validar captura
            </button>
        </div>
    </form>
</section>