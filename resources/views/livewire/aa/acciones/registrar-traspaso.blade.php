<?php

use App\Enums\Rol;
use App\Services\AltaPedido;
use Livewire\Volt\Component;

/** El cajero da de alta el pedido especial con el folio del traspaso que capturó en Expertos. */
new class extends Component {
    public string $folio = '';

    public ?array $traspaso = null;

    public int $partida = 1;

    public string $cliente = '';

    public string $sucursal = '';

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()->rol, [Rol::Cajero, Rol::Admin], true), 403);
    }

    public function eligeSucursal(): bool
    {
        return ! auth()->user()->rol->limitadoASuSucursal();
    }

    public function buscar(AltaPedido $alta): void
    {
        $this->resetErrorBag();
        $this->traspaso = null;

        try {
            $this->traspaso = $alta->buscar($this->folio);
        } catch (DomainException $e) {
            $this->addError('folio', $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('folio', 'No se pudo consultar Expertos en este momento. Intenta de nuevo en unos minutos.');

            return;
        }

        $this->folio = $this->traspaso['folio'];
        $this->partida = $this->traspaso['partidas'][0]['partida'];
        $this->sucursal = $this->traspaso['destino'] ?? '';
    }

    public function registrar(AltaPedido $alta): void
    {
        if ($this->traspaso === null) {
            return;
        }

        $this->validate([
            'partida' => ['required', 'integer'],
            'cliente' => ['nullable', 'string', 'max:120'],
            'sucursal' => [$this->eligeSucursal() ? 'required' : 'nullable', 'string', 'max:120'],
        ], [], ['partida' => 'artículo', 'sucursal' => 'sucursal']);

        try {
            $pedido = $alta->registrar($this->traspaso, $this->partida, auth()->user(), $this->sucursal, $this->cliente);
        } catch (DomainException $e) {
            $this->addError('folio', $e->getMessage());
            $this->traspaso = null;

            return;
        }

        // Al abrir el pedido, el cajero ya ve el panel para ligarlo con su ticket.
        $this->redirectRoute('pedidos.show', $pedido, navigate: true);
    }

    public function cancelar(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Registrar traspaso de Expertos</h2>
    </div>

    <div class="grid gap-5 px-5 py-5">
        <form wire:submit="buscar" class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <x-aa.campo label="Folio del traspaso" for="folio_traspaso" :error="$errors->first('folio')" class="flex-1"
                        ayuda="El que capturaste en AdminExpertos, por ejemplo TSA013341. Se lee directo de Expertos.">
                <input id="folio_traspaso" type="text" wire:model="folio" class="input" placeholder="TSA013341"
                       autocomplete="off" @disabled($traspaso) @error('folio') aria-invalid="true" @enderror>
            </x-aa.campo>
            @unless ($traspaso)
                <button type="submit" class="btn btn-secundario shrink-0 sm:mt-7" wire:loading.attr="disabled" wire:target="buscar">
                    <span wire:loading.remove wire:target="buscar">Buscar en Expertos</span>
                    <span wire:loading wire:target="buscar">Buscando…</span>
                </button>
            @endunless
        </form>

        @if ($traspaso)
            <form wire:submit="registrar" class="grid gap-5">
                <div class="rounded-md bg-plancha px-4 py-3">
                    <p class="text-[15px] font-semibold">
                        Traspaso {{ $traspaso['folio'] }} del {{ \Illuminate\Support\Carbon::parse($traspaso['fecha'])->translatedFormat('j M Y') }}
                        @if ($traspaso['destino']) hacia {{ $traspaso['destino'] }} @endif
                    </p>
                    @if ($traspaso['observaciones'])
                        <p class="mt-0.5 text-acero">{{ $traspaso['observaciones'] }}</p>
                    @endif
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-[15px] font-semibold">Artículo del pedido</legend>
                    @foreach ($traspaso['partidas'] as $p)
                        <label wire:key="partida-{{ $p['partida'] }}" class="flex cursor-pointer items-start gap-3 rounded-md border border-linea px-4 py-3 has-[:checked]:border-acero has-[:checked]:bg-plancha/60">
                            <input type="radio" wire:model="partida" value="{{ $p['partida'] }}" class="mt-1">
                            <span class="min-w-0">
                                <span class="block font-semibold">{{ $p['articulo'] }}</span>
                                <span class="block text-[15px] text-acero">
                                    {{ rtrim(rtrim(number_format($p['cantidad'], 4), '0'), '.') }} {{ $p['unidad'] }}
                                    @if ($p['clave']) · {{ $p['clave'] }} @endif
                                    @if ($p['peso_unitario'] > 0) · {{ $p['peso_unitario'] }} kg c/u @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </fieldset>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-aa.campo label="Cliente (opcional)" for="cliente_traspaso" :error="$errors->first('cliente')"
                                ayuda="Si lo dejas vacío, lo capturas al ligar el ticket.">
                        <input id="cliente_traspaso" type="text" wire:model="cliente" class="input" autocomplete="off">
                    </x-aa.campo>

                    @if ($this->eligeSucursal())
                        <x-aa.campo label="Sucursal" for="sucursal_traspaso" :error="$errors->first('sucursal')">
                            <select id="sucursal_traspaso" wire:model="sucursal" class="input">
                                @foreach (collect(config('aa.sucursales'))->push($traspaso['destino'])->filter()->unique() as $s)
                                    <option value="{{ $s }}">{{ $s }}</option>
                                @endforeach
                            </select>
                        </x-aa.campo>
                    @else
                        <x-aa.campo label="Sucursal" for="sucursal_fija">
                            <input id="sucursal_fija" type="text" value="{{ auth()->user()->sucursal }}" class="input" disabled>
                        </x-aa.campo>
                    @endif
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" wire:click="cancelar" class="btn btn-secundario">Otro folio</button>
                    <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled">Registrar pedido</button>
                </div>
            </form>
        @endif
    </div>
</section>
