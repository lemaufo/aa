<?php

use App\Models\Pedido;
use App\Services\FlujoService;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Producción en 3 pasos, en orden. Las piezas y medidas no se editan: vienen de caja.
 *   1) Imprimir la etiqueta (es la orden de producción)
 *   2) Capturar el peso de la báscula y validarlo contra el teórico (con el lote del rollo)
 *   3) Subir la foto del producto terminado con la etiqueta visible y la foto de la báscula
 */
new class extends Component {
    use WithFileUploads;

    public Pedido $pedido;

    public string $peso_produccion = '';

    public string $lote_rollo = '';

    public $foto_producto = null;

    public $foto_bascula = null;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->lote_rollo = (string) $pedido->lote_rollo;
    }

    public function paso(): int
    {
        return Flujo::pasoProduccion($this->pedido);
    }

    public function desviacion(): ?float
    {
        return is_numeric($this->peso_produccion) ? $this->pedido->desviacion((float) $this->peso_produccion) : null;
    }

    /** Botones de ajuste fino del modo balanza. Parte del peso capturado, o del teórico si todavía no hay. */
    public function ajustar(float $delta): void
    {
        $base = is_numeric($this->peso_produccion) ? (float) $this->peso_produccion : $this->pedido->pesoTeorico();

        $this->peso_produccion = (string) max(0, round($base + $delta, 2));
        $this->resetErrorBag('peso_produccion');
    }

    public function dentro(): bool
    {
        return is_numeric($this->peso_produccion) && $this->pedido->dentroDeTolerancia((float) $this->peso_produccion);
    }

    /** Paso 1: se registra al abrir la etiqueta (el enlace abre otra pestaña). */
    public function imprimir(FlujoService $flujo): void
    {
        $renglones = count($this->pedido->cortes_solicitados ?? []);

        $this->ejecutar($flujo, 'imprimir_etiqueta', ['etiqueta_impresa_at' => now()],
            'Orden de producción impresa: '.$this->pedido->totalPiezas().' piezas en '.$renglones.($renglones === 1 ? ' medida' : ' medidas'),
            'Etiqueta registrada como impresa. Ahora pesa el material.');
    }

    public function pesar(FlujoService $flujo): void
    {
        $this->validate([
            'peso_produccion' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'lote_rollo' => ['nullable', 'string', 'max:60'],
        ], [], ['peso_produccion' => 'peso de la báscula', 'lote_rollo' => 'rollo o lote']);

        if (! $this->dentro()) {
            $this->addError('peso_produccion', 'El peso está fuera de la tolerancia de ±'.config('aa.tolerancia_peso').' %. Revisa piezas y medidas contra la etiqueta y vuelve a pesar.');

            return;
        }

        $peso = (float) $this->peso_produccion;
        $d = $this->pedido->desviacion($peso);
        $lote = trim($this->lote_rollo) ?: null;

        $this->ejecutar($flujo, 'pesar_produccion', ['peso_produccion' => $peso, 'lote_rollo' => $lote],
            number_format($peso, 2).' kg, '.($d > 0 ? '+' : '').number_format($d, 2).' % contra el teórico'.($lote ? '. Rollo o lote: '.$lote : ''),
            'Peso validado. Falta la evidencia.');
    }

    public function terminar(FlujoService $flujo): void
    {
        $this->validate([
            'foto_producto' => ['required', 'image', 'max:10240'],
            'foto_bascula' => ['required', 'image', 'max:10240'],
        ], [], ['foto_producto' => 'foto del producto terminado', 'foto_bascula' => 'foto de la báscula']);

        $carpeta = 'evidencias/'.$this->pedido->id;

        $this->ejecutar($flujo, 'producir', [
            'evidencia_path' => $this->foto_producto->store($carpeta, 'local'),
            'evidencia_bascula_path' => $this->foto_bascula->store($carpeta, 'local'),
            'peso_teorico' => $this->pedido->pesoTeorico(),
        ], 'Fotos del producto con etiqueta y de la báscula', 'Producción terminada. Despáchalo a CEDIS.');
    }

    private function ejecutar(FlujoService $flujo, string $accion, array $cambios, string $detalle, string $ok): void
    {
        try {
            $flujo->aplicar($this->pedido, $accion, auth()->user(), $cambios, $detalle);
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: $ok);
        $this->dispatch('pedido-actualizado');
    }
}; ?>

@php
    $paso = $this->paso();
    $teorico = $pedido->pesoTeorico();
    $tol = max((float) config('aa.tolerancia_peso'), 0.1);
    $d = $this->desviacion();
    $dentro = $this->dentro();
    $pasos = [1 => 'Imprimir etiqueta', 2 => 'Pesar', 3 => 'Evidencia'];
@endphp

<section class="panel panel-accion">
    <style>
        .balanza { position: relative; display: flex; align-items: center; height: 2.5rem; --c: var(--color-linea-fuerte, #9ca3af); }
        .balanza[data-estado="ok"] { --c: var(--color-ok, #15803d); }
        .balanza[data-estado="mal"] { --c: var(--color-peligro, #b91c1c); }
        .balanza::before { content: ""; position: absolute; inset-inline: 0; top: 50%; height: .75rem; transform: translateY(-50%); border-radius: 9999px; background: var(--color-plancha, #e5e7eb); }
        .balanza-banda { position: absolute; left: 25%; width: 50%; top: 50%; height: .75rem; transform: translateY(-50%); border-radius: 9999px; background: var(--color-ok-tinte, #dcfce7); border: 1px solid color-mix(in srgb, var(--color-ok, #15803d) 40%, transparent); }
        .balanza input[type="range"] { position: relative; z-index: 1; width: 100%; height: 2.5rem; margin: 0; background: transparent; cursor: pointer; -webkit-appearance: none; appearance: none; touch-action: pan-y; }
        .balanza input[type="range"]::-webkit-slider-runnable-track { height: .75rem; background: transparent; }
        .balanza input[type="range"]::-moz-range-track { height: .75rem; background: transparent; }
        .balanza input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 1.5rem; height: 2.25rem; margin-top: -.75rem; border-radius: .5rem; background: var(--c); border: 3px solid #fff; box-shadow: 0 1px 4px rgb(0 0 0 / .35); }
        .balanza input[type="range"]::-moz-range-thumb { width: 1.5rem; height: 2.25rem; border-radius: .5rem; background: var(--c); border: 3px solid #fff; box-shadow: 0 1px 4px rgb(0 0 0 / .35); }
        .balanza input[type="range"]:focus-visible { outline: 2px solid var(--color-naranja, #f97316); outline-offset: 2px; border-radius: .5rem; }
        .balanza-escala { position: relative; height: 1.25rem; }
        .balanza-escala span { position: absolute; transform: translateX(-50%); white-space: nowrap; }
    </style>
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Producción</h2>
        <p class="text-[15px] text-acero">Paso {{ $paso }} de 3</p>
    </div>

    <div class="grid gap-6 px-5 py-5">
        <ol class="grid grid-cols-3 gap-2" aria-label="Pasos de producción">
            @foreach ($pasos as $n => $nombre)
                <li @class([
                    'rounded-md border px-3 py-2 text-[15px] font-semibold',
                    'border-naranja bg-naranja-tinte text-tinta' => $n === $paso,
                    'border-ok bg-ok-tinte text-ok' => $n < $paso,
                    'border-linea bg-plancha text-acero' => $n > $paso,
                ])>
                    <span class="block text-[13px] font-normal">Paso {{ $n }}</span>{{ $nombre }}
                </li>
            @endforeach
        </ol>

        {{-- Lo que se produce: viene de caja y no se edita --}}
        <div class="rounded-md bg-plancha px-4 py-3">
            <p class="text-[15px] font-semibold">Orden de producción (capturada en caja)</p>
            <p class="mt-0.5 text-acero">{{ $pedido->material }}, {{ number_format($pedido->metros_traspaso, 2) }} m en {{ $pedido->totalPiezas() }} piezas</p>
            <ul class="mt-1 grid gap-0.5 sm:grid-cols-2">
                @foreach ($pedido->cortes_solicitados ?? [] as $c)
                    <li class="font-semibold">{{ $c['piezas'] }} × {{ number_format($c['medida'], 2) }} m</li>
                @endforeach
            </ul>
            @if ($pedido->fecha_prometida)
                <p class="mt-2 flex flex-wrap items-center gap-2 text-[15px] text-acero">
                    Prometido para el {{ $pedido->fecha_prometida->translatedFormat('j M Y') }} <x-aa.prioridad :pedido="$pedido" />
                </p>
            @endif
        </div>

        @if ($paso === 1)
            <div class="grid gap-3">
                <p class="text-acero">La etiqueta es la orden de producción. Imprímela antes de cortar.</p>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('pedidos.etiqueta', [$pedido, 'auto' => 1]) }}" target="_blank" rel="noopener" wire:click="imprimir" class="btn btn-primario w-full sm:w-auto">
                        Imprimir etiqueta
                    </a>
                </div>
            </div>
        @endif

        @if ($paso === 2)
            @php
                $inf = round($teorico * (1 - $tol / 100), 2);
                $sup = round($teorico * (1 + $tol / 100), 2);
                $min = round($teorico * (1 - 2 * $tol / 100), 2);
                $max = round($teorico * (1 + 2 * $tol / 100), 2);
                $valor = is_numeric($peso_produccion) ? max($min, min($max, (float) $peso_produccion)) : $teorico;
                $estado = $d === null ? 'vacio' : ($dentro ? 'ok' : 'mal');
                $tolTxt = rtrim(rtrim(number_format($tol, 2), '0'), '.');
            @endphp

            <form wire:submit="pesar" class="grid gap-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-[15px] text-acero">Peso teórico</p>
                        <p class="folio text-5xl leading-none">{{ number_format($teorico, 2) }} <span class="text-2xl">kg</span></p>
                    </div>
                    <x-aa.campo label="Peso real en la báscula (kg)" for="peso_produccion" :error="$errors->first('peso_produccion')">
                        <input id="peso_produccion" type="number" step="0.01" min="0" inputmode="decimal" wire:model.live.debounce.400ms="peso_produccion"
                               class="input font-display text-2xl font-semibold" placeholder="0.00" @error('peso_produccion') aria-invalid="true" @enderror>
                    </x-aa.campo>
                </div>

                {{-- Modo balanza: desliza hasta el peso que marca la báscula. La banda verde es la tolerancia permitida. --}}
                <div class="grid gap-2" role="group" aria-label="Modo balanza">
                    <div class="balanza" data-estado="{{ $estado }}">
                        <div class="balanza-banda"></div>
                        <input type="range" min="{{ $min }}" max="{{ $max }}" step="0.01" value="{{ $valor }}"
                               wire:model.live.debounce.150ms="peso_produccion" aria-label="Peso en la báscula, en kilogramos"
                               aria-valuetext="{{ is_numeric($peso_produccion) ? number_format((float) $peso_produccion, 2).' kilogramos' : 'sin capturar' }}">
                    </div>
                    <div class="balanza-escala text-[14px] text-acero" aria-hidden="true">
                        <span style="left: 25%">{{ number_format($inf, 2) }}</span>
                        <span style="left: 50%">Teórico</span>
                        <span style="left: 75%">{{ number_format($sup, 2) }}</span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" wire:click="ajustar(-1)" class="btn btn-secundario">-1 kg</button>
                        <button type="button" wire:click="ajustar(-0.1)" class="btn btn-secundario">-0.1</button>
                        <button type="button" wire:click="ajustar(0.1)" class="btn btn-secundario">+0.1</button>
                        <button type="button" wire:click="ajustar(1)" class="btn btn-secundario">+1 kg</button>
                    </div>
                </div>

                <p @class(['font-semibold', 'text-ok' => $dentro, 'text-peligro' => $d !== null && ! $dentro, 'text-acero' => $d === null]) role="status" aria-live="polite">
                    @if ($d === null)
                        Desliza el control o escribe el peso para compararlo con el teórico (tolerancia ±{{ $tolTxt }} %).
                    @elseif ($dentro)
                        {{ $d > 0 ? '+' : '' }}{{ number_format($d, 2) }} % contra el teórico. Está dentro de la tolerancia.
                    @else
                        {{ $d > 0 ? '+' : '' }}{{ number_format($d, 2) }} % contra el teórico. Fuera de tolerancia: revisa piezas y medidas contra la etiqueta.
                    @endif
                </p>

                <x-aa.campo label="Rollo o lote de materia prima (opcional)" for="lote_rollo" :error="$errors->first('lote_rollo')"
                            ayuda="Número del rollo del que se cortó este pedido.">
                    <input id="lote_rollo" type="text" wire:model="lote_rollo" class="input" autocomplete="off" placeholder="R-24817"
                           @error('lote_rollo') aria-invalid="true" @enderror>
                </x-aa.campo>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('pedidos.etiqueta', $pedido) }}" target="_blank" rel="noopener" class="text-[15px] font-semibold text-naranja-fuerte underline-offset-4 hover:underline">Reimprimir etiqueta</a>
                    <button type="submit" class="btn btn-primario w-full sm:w-auto" @disabled(! $dentro) wire:loading.attr="disabled" wire:target="pesar">Validar peso</button>
                </div>
            </form>
        @endif

        @if ($paso === 3)
            <form wire:submit="terminar" class="grid gap-5">
                <p class="text-acero">Peso validado: {{ number_format($pedido->peso_produccion, 2) }} kg. {{ $pedido->lote_rollo ? 'Rollo o lote '.$pedido->lote_rollo.'.' : '' }} Sube las dos fotos para terminar.</p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-aa.subir label="Foto del producto terminado" model="foto_producto" :archivo="$foto_producto" :error="$errors->first('foto_producto')"
                                ayuda="Que se vea la etiqueta pegada al producto." />
                    <x-aa.subir label="Foto de la báscula" model="foto_bascula" :archivo="$foto_bascula" :error="$errors->first('foto_bascula')"
                                ayuda="Que se lea el peso en la pantalla." />
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <a href="{{ route('pedidos.etiqueta', $pedido) }}" target="_blank" rel="noopener" class="text-[15px] font-semibold text-naranja-fuerte underline-offset-4 hover:underline">Reimprimir etiqueta</a>
                    <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="terminar,foto_producto,foto_bascula">Terminar producción</button>
                </div>
            </form>
        @endif
    </div>
</section>