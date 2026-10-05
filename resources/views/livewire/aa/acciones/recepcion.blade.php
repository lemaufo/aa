<?php

use App\Enums\FormaEntrega;
use App\Models\Pedido;
use App\Services\FlujoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public Pedido $pedido;

    public string $codigo = '';

    public string $peso_real = '';

    public string $forma_entrega = '';

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->forma_entrega = $pedido->forma_entrega?->value ?? '';
    }

    public function desviacion(): ?float
    {
        return is_numeric($this->peso_real) ? $this->pedido->desviacion((float) $this->peso_real) : null;
    }

    public function dentro(): bool
    {
        return is_numeric($this->peso_real) && $this->pedido->dentroDeTolerancia((float) $this->peso_real);
    }

    public function simular(): void
    {
        $this->codigo = $this->pedido->folio;
        $this->resetErrorBag('codigo');
    }

    public function validar(FlujoService $flujo): void
    {
        $this->validate([
            'codigo' => ['required', 'string', 'max:60'],
            'peso_real' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'forma_entrega' => ['required', Rule::enum(FormaEntrega::class)],
        ]);

        if (strcasecmp(trim($this->codigo), $this->pedido->folio) !== 0) {
            $this->addError('codigo', 'Ese código no es de este pedido. El folio es '.$this->pedido->folio.'.');

            return;
        }

        if (! $this->dentro()) {
            $this->addError('peso_real', 'El peso está fuera de la tolerancia de ±'.config('aa.tolerancia_peso').' %. Revisa si faltan o sobran láminas y vuelve a pesar.');

            return;
        }

        $real = (float) $this->peso_real;
        $d = $this->pedido->desviacion($real);

        try {
            $flujo->aplicar($this->pedido, 'validar', auth()->user(), [
                'peso_real' => $real,
                'forma_entrega' => $this->forma_entrega,
            ], 'Peso real '.number_format($real, 2).' kg, '.($d > 0 ? '+' : '').number_format($d, 2).' % contra el teórico. '
                .FormaEntrega::from($this->forma_entrega)->label());
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Pedido validado. Define ahora cómo sale.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

@php
    $teorico = $pedido->pesoTeorico();
    $tol = max((float) config('aa.tolerancia_peso'), 0.1);
    $d = $this->desviacion();
    $dentro = $this->dentro();
    $posicion = $d === null ? 50 : max(3, min(97, 50 + ($d / ($tol * 2)) * 50));
@endphp

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Recibe, pesa y valida</h2>
    </div>

    <form wire:submit="validar" class="grid gap-6 px-5 py-5">
        <x-aa.campo label="Código de la etiqueta" for="codigo" :error="$errors->first('codigo')">
            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1">
                    <x-aa.icon name="qr-code" class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-acero" />
                    <input id="codigo" type="text" wire:model="codigo" class="input pl-11 font-display text-xl font-semibold"
                           placeholder="{{ $pedido->folio }}" autocomplete="off" autocapitalize="characters" spellcheck="false"
                           @error('codigo') aria-invalid="true" @enderror>
                </div>
                <button type="button" wire:click="simular" class="btn btn-secundario shrink-0">Usar folio</button>
            </div>
        </x-aa.campo>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-[15px] text-acero">Peso teórico</p>
                <p class="folio text-5xl leading-none">{{ number_format($teorico, 2) }} <span class="text-2xl">kg</span></p>
            </div>
            <x-aa.campo label="Peso real en la báscula (kg)" for="peso_real" :error="$errors->first('peso_real')">
                <input id="peso_real" type="number" step="0.01" min="0" inputmode="decimal" wire:model.live.debounce.400ms="peso_real"
                       class="input font-display text-2xl font-semibold" placeholder="0.00"
                       @error('peso_real') aria-invalid="true" @enderror>
            </x-aa.campo>
        </div>

        {{-- Medidor: la banda verde es la tolerancia permitida --}}
        <div class="grid gap-2" role="status" aria-live="polite">
            <div class="relative h-3 rounded-full bg-plancha">
                <div class="absolute inset-y-0 left-1/4 w-1/2 rounded-full border border-ok/40 bg-ok-tinte"></div>
                <div @class(['absolute -top-1.5 h-6 w-1.5 -translate-x-1/2 rounded-full', 'bg-ok' => $dentro, 'bg-peligro' => $d !== null && ! $dentro, 'bg-linea-fuerte' => $d === null])
                     style="left: {{ $posicion }}%"></div>
            </div>
            <div class="flex justify-between text-[14px] text-acero">
                <span>-{{ rtrim(rtrim(number_format($tol, 2), '0'), '.') }} %</span>
                <span>Teórico</span>
                <span>+{{ rtrim(rtrim(number_format($tol, 2), '0'), '.') }} %</span>
            </div>
            <p @class(['font-semibold', 'text-ok' => $dentro, 'text-peligro' => $d !== null && ! $dentro, 'text-acero' => $d === null])>
                @if ($d === null)
                    Captura el peso real para compararlo con el teórico.
                @elseif ($dentro)
                    {{ $d > 0 ? '+' : '' }}{{ number_format($d, 2) }} % contra el teórico. Está dentro de la tolerancia.
                @else
                    {{ $d > 0 ? '+' : '' }}{{ number_format($d, 2) }} % contra el teórico. Está fuera de la tolerancia: revisa si faltan o sobran láminas y vuelve a pesar.
                @endif
            </p>
        </div>

        <x-aa.campo label="Forma de entrega" for="forma_entrega" :error="$errors->first('forma_entrega')"
                    :ayuda="$forma_entrega ? \App\Enums\FormaEntrega::tryFrom($forma_entrega)?->ayuda() : null">
            <select id="forma_entrega" wire:model="forma_entrega" class="input" @error('forma_entrega') aria-invalid="true" @enderror>
                <option value="">Elige una opción</option>
                @foreach (\App\Enums\FormaEntrega::cases() as $forma)
                    <option value="{{ $forma->value }}">{{ $forma->label() }}</option>
                @endforeach
            </select>
        </x-aa.campo>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" @disabled(! $dentro) wire:loading.attr="disabled" wire:target="validar">
                Validar pedido
            </button>
        </div>
    </form>
</section>