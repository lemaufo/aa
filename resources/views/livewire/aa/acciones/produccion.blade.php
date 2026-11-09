<?php

use App\Livewire\Concerns\PideAutorizacion;
use App\Models\Pedido;
use App\Services\Autorizacion;
use App\Services\FlujoService;
use App\Services\PesoCatalogo;
use App\Support\Etiqueta;
use App\Support\Evidencia;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Producción en 3 pasos, en orden. Las piezas y medidas no se editan: vienen de caja.
 *   1) Imprimir la etiqueta (es la orden de producción). Antes se vuelve a leer el peso por metro de Microsip.
 *   2) Capturar el peso de la báscula y validarlo contra el teórico (con el lote del rollo). Fuera de
 *      tolerancia solo sigue con la autorización de un supervisor y una nota.
 *   3) Subir la foto del producto terminado con la etiqueta visible; la de la báscula es opcional
 *      (las láminas largas no caben en la báscula).
 * Reimprimir la etiqueta pide la autorización de un supervisor (App\Support\Etiqueta).
 */
new class extends Component {
    use PideAutorizacion;
    use WithFileUploads;

    public Pedido $pedido;

    public string $peso_produccion = '';

    public string $lote_rollo = '';

    public string $peso_nota = '';

    public $foto_producto = null;

    public $foto_bascula = null;

    /** Qué pide autorización ahora: null, 'reimpresion' o 'peso'. */
    public ?string $autorizando = null;

    /** La reimpresión ya está autorizada: se muestra el enlace para abrir la etiqueta. */
    public bool $reimpresionLista = false;

    public function mount(Pedido $pedido, PesoCatalogo $catalogo, FlujoService $flujo): void
    {
        $this->pedido = $pedido;
        $this->lote_rollo = (string) $pedido->lote_rollo;

        if ($this->paso() === 1 && Flujo::puede(auth()->user(), 'imprimir_etiqueta', $pedido)) {
            // Antes de imprimir, el peso por metro que diga hoy el catálogo de Microsip.
            if ($cambio = $catalogo->actualizar($pedido)) {
                $pedido->save();
                $flujo->evento($pedido, auth()->user(), 'Peso por metro actualizado', $cambio);
            }

            Etiqueta::permitir($pedido, 2);
        }
    }

    public function paso(): int
    {
        return Flujo::pasoProduccion($this->pedido);
    }

    public function desviacion(): ?float
    {
        return is_numeric($this->peso_produccion) && $this->pedido->tienePesoTeorico()
            ? $this->pedido->desviacion((float) $this->peso_produccion)
            : null;
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

    /** Quien opera ya tiene un rol que autoriza: no se le pide contraseña de supervisor. */
    public function autoriza(): bool
    {
        return Autorizacion::puedeAutorizar(auth()->user());
    }

    /** Paso 1: se registra al abrir la etiqueta (el enlace abre otra pestaña). */
    public function imprimir(FlujoService $flujo): void
    {
        if (! $this->pedido->tienePesoTeorico()) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'El artículo no tiene peso por metro en Microsip. Pide que lo capturen en el catálogo.');

            return;
        }

        $renglones = count($this->pedido->cortes_solicitados ?? []);

        $this->ejecutar($flujo, 'imprimir_etiqueta', ['etiqueta_impresa_at' => now()],
            'Orden de producción impresa: '.$this->pedido->totalPiezas().' piezas en '.$renglones.($renglones === 1 ? ' medida' : ' medidas'),
            'Etiqueta registrada como impresa. Ahora pesa el material.');
    }

    public function pedirAutorizacion(string $que): void
    {
        $this->autorizando = in_array($que, ['reimpresion', 'peso'], true) ? $que : null;
        $this->reimpresionLista = false;
        $this->limpiarAutorizacion();
    }

    public function cancelarAutorizacion(): void
    {
        $this->autorizando = null;
        $this->limpiarAutorizacion();
        $this->resetErrorBag('peso_nota');
    }

    /** Un supervisor autoriza reimprimir: la etiqueta se puede abrir unos minutos en este equipo. */
    public function autorizarReimpresion(FlujoService $flujo): void
    {
        $this->validate($this->reglasAutorizacion());

        if (! $supervisor = $this->supervisor()) {
            return;
        }

        Etiqueta::permitir($this->pedido);
        $flujo->evento($this->pedido, auth()->user(), 'Reimpresión de etiqueta autorizada', 'Autorizó '.$supervisor->name);

        $this->autorizando = null;
        $this->reimpresionLista = true;
        $this->limpiarAutorizacion();
    }

    public function pesar(FlujoService $flujo): void
    {
        $this->validate([
            'peso_produccion' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'lote_rollo' => ['nullable', 'string', 'max:60'],
        ]);

        if (! $this->dentro()) {
            $this->addError('peso_produccion', $this->pedido->tienePesoTeorico()
                ? 'El peso está fuera de la tolerancia de ±'.config('aa.tolerancia_peso').' %. Revisa piezas y medidas contra la etiqueta y vuelve a pesar.'
                : 'El artículo no tiene peso por metro en Microsip: no hay contra qué validar. Se necesita la autorización de un supervisor.');

            return;
        }

        $this->guardarPeso($flujo);
    }

    /** Peso fuera de tolerancia (o sin peso teórico): sigue con la contraseña de un supervisor y una nota. */
    public function autorizarPeso(FlujoService $flujo): void
    {
        $this->validate([
            'peso_produccion' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'lote_rollo' => ['nullable', 'string', 'max:60'],
            'peso_nota' => ['required', 'string', 'min:10', 'max:200'],
        ] + ($this->autoriza() ? [] : $this->reglasAutorizacion()));

        if ($this->dentro()) {
            $this->cancelarAutorizacion();
            $this->guardarPeso($flujo);

            return;
        }

        // Si quien opera ya autoriza (administrador), basta con su nota.
        $supervisor = $this->autoriza() ? auth()->user() : $this->supervisor();
        if (! $supervisor) {
            return;
        }

        $this->guardarPeso($flujo, $supervisor->name, trim($this->peso_nota));
    }

    private function guardarPeso(FlujoService $flujo, ?string $autorizo = null, ?string $nota = null): void
    {
        $peso = (float) $this->peso_produccion;
        $lote = trim($this->lote_rollo) ?: null;

        if ($this->pedido->tienePesoTeorico()) {
            $d = $this->pedido->desviacion($peso);
            $comparacion = ($d > 0 ? '+' : '').number_format($d, 2).' % contra el teórico';
        } else {
            $comparacion = 'sin peso teórico en Microsip';
        }

        $detalle = number_format($peso, 2).' kg, '.$comparacion.($lote ? '. Rollo o lote: '.$lote : '');

        $this->ejecutar($flujo, 'pesar_produccion', [
            'peso_produccion' => $peso,
            'lote_rollo' => $lote,
            'peso_fuera_tolerancia' => $autorizo !== null,
            'peso_nota' => $nota,
            'peso_autorizado_por' => $autorizo,
        ],
            $autorizo ? mb_strimwidth($detalle.'. Autorizó '.$autorizo.': '.$nota, 0, 255, '…') : $detalle,
            $autorizo ? 'Peso autorizado por '.$autorizo.'. Falta la evidencia.' : 'Peso validado. Falta la evidencia.',
            $autorizo ? 'Peso fuera de tolerancia autorizado' : null);

        $this->autorizando = null;
        $this->limpiarAutorizacion();
    }

    public function terminar(FlujoService $flujo): void
    {
        $this->validate([
            'foto_producto' => Evidencia::reglas(),
            // Opcional: las láminas largas no caben en la báscula y se dañan si se fuerzan.
            'foto_bascula' => ['nullable', ...array_slice(Evidencia::reglas(), 1)],
        ]);

        $this->ejecutar($flujo, 'producir', [
            'evidencia_path' => Evidencia::guardar($this->foto_producto, $this->pedido, 'producto'),
            'evidencia_bascula_path' => $this->foto_bascula ? Evidencia::guardar($this->foto_bascula, $this->pedido, 'bascula') : null,
            'peso_teorico' => $this->pedido->pesoTeorico(),
        ], $this->foto_bascula ? 'Fotos del producto con etiqueta y de la báscula' : 'Foto del producto con etiqueta (sin foto de báscula)',
            'Producción terminada. Despáchalo a CEDIS.');
    }

    private function ejecutar(FlujoService $flujo, string $accion, array $cambios, string $detalle, string $ok, ?string $titulo = null): void
    {
        try {
            $flujo->aplicar($this->pedido, $accion, auth()->user(), $cambios, $detalle, $titulo);
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
    $conTeorico = $pedido->tienePesoTeorico();
    $tol = max((float) config('aa.tolerancia_peso'), 0.1);
    $d = $this->desviacion();
    $dentro = $this->dentro();
    $autoriza = $this->autoriza();
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

        {{-- Diseño que adjuntó la caja (canalón y piezas sin clave): hay que verlo antes de cortar y doblar --}}
        @if ($pedido->diseno_path)
            <div class="flex flex-wrap items-center gap-4 rounded-md border-2 border-naranja bg-naranja-tinte px-4 py-3">
                <a href="{{ route('evidencia', [$pedido, 'diseno']) }}" target="_blank" rel="noopener" class="shrink-0">
                    <img src="{{ route('evidencia', [$pedido, 'diseno']) }}" alt="Diseño de referencia del pedido {{ $pedido->folio }}"
                         class="h-24 w-24 rounded-md border border-linea bg-white object-cover">
                </a>
                <div class="grid min-w-0 flex-1 gap-1">
                    <p class="flex items-center gap-1.5 font-semibold"><x-aa.icon name="paper-clip" /> Este pedido trae un diseño de referencia</p>
                    <p class="text-[15px] text-acero">Revísalo antes de cortar y doblar.</p>
                    <a href="{{ route('evidencia', [$pedido, 'diseno']) }}" target="_blank" rel="noopener"
                       class="text-[15px] font-semibold text-naranja-fuerte underline-offset-4 hover:underline">Ver e imprimir el diseño</a>
                </div>
            </div>
        @endif

        @if ($paso === 1)
            <div class="grid gap-3">
                @if ($conTeorico)
                    <p class="text-acero">La etiqueta es la orden de producción. Imprímela antes de cortar.
                        Peso teórico: <span class="font-semibold text-tinta">{{ number_format($teorico, 2) }} kg</span>
                        ({{ number_format($pedido->peso_por_metro, 3) }} kg por metro, del catálogo de Microsip).</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('pedidos.etiqueta', [$pedido, 'auto' => 1]) }}" target="_blank" rel="noopener" wire:click="imprimir" class="btn btn-primario w-full sm:w-auto">
                            Imprimir etiqueta
                        </a>
                    </div>
                @else
                    <p class="flex items-start gap-2 rounded-md border border-peligro px-4 py-3 font-medium text-peligro" role="alert">
                        <x-aa.icon name="exclamation-triangle" class="mt-0.5" />
                        <span>{{ $pedido->material }} no tiene peso por metro en el catálogo de Microsip, así que no se puede calcular el peso teórico.
                            Pide que lo capturen en Microsip y vuelve a abrir este pedido.</span>
                    </p>
                @endif
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
                $fuera = is_numeric($peso_produccion) && (float) $peso_produccion > 0 && ! $dentro;
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

                @if ($conTeorico)
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
                @else
                    <p class="font-semibold text-peligro" role="alert">El artículo no tiene peso por metro en Microsip: el peso solo puede registrarse con la autorización de un supervisor.</p>
                @endif

                <x-aa.campo label="Rollo o lote de materia prima (opcional)" for="lote_rollo" :error="$errors->first('lote_rollo')"
                            ayuda="Número del rollo del que se cortó este pedido.">
                    <input id="lote_rollo" type="text" wire:model="lote_rollo" class="input" autocomplete="off" placeholder="R-24817"
                           @error('lote_rollo') aria-invalid="true" @enderror>
                </x-aa.campo>

                <div class="flex flex-wrap items-center justify-end gap-3">
                    @if ($fuera && $autorizando !== 'peso')
                        <button type="button" wire:click="pedirAutorizacion('peso')" class="btn btn-secundario w-full sm:w-auto">
                            <x-aa.icon name="lock-closed" /> Autorizar peso fuera de rango
                        </button>
                    @endif
                    <button type="submit" class="btn btn-primario w-full sm:w-auto" @disabled(! $dentro) wire:loading.attr="disabled" wire:target="pesar">Validar peso</button>
                </div>
            </form>

            @if ($autorizando === 'peso' && $fuera)
                <form wire:submit="autorizarPeso" class="grid gap-4 rounded-md border border-peligro px-4 py-4">
                    <div>
                        <p class="flex items-center gap-1.5 font-semibold"><x-aa.icon name="lock-closed" /> Autorizar peso fuera de rango</p>
                        <p class="text-[15px] text-acero">
                            Se registrará {{ number_format((float) $peso_produccion, 2) }} kg{{ $d !== null ? ' ('.($d > 0 ? '+' : '').number_format($d, 2).' %)' : '' }}.
                            @unless ($autoriza) Lo autoriza un {{ \App\Services\Autorizacion::quienes() }} con su contraseña. @endunless
                        </p>
                    </div>

                    <x-aa.campo label="Nota: ¿por qué el peso sale de rango?" for="peso_nota" :error="$errors->first('peso_nota')"
                                ayuda="Obligatoria. Queda en la bitácora del pedido.">
                        <textarea id="peso_nota" wire:model="peso_nota" rows="2" maxlength="200" class="input"
                                  placeholder="Ej. el rollo viene con calibre más grueso, se revisaron piezas y medidas"
                                  @error('peso_nota') aria-invalid="true" @enderror></textarea>
                    </x-aa.campo>

                    @unless ($autoriza)
                        <x-aa.autorizacion />
                    @endunless

                    <div class="flex flex-wrap justify-end gap-3">
                        <button type="button" wire:click="cancelarAutorizacion" class="btn btn-secundario w-full sm:w-auto">Cancelar</button>
                        <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="autorizarPeso">Autorizar y registrar peso</button>
                    </div>
                </form>
            @endif
        @endif

        @if ($paso === 3)
            <form wire:submit="terminar" class="grid gap-5">
                <p class="text-acero">
                    Peso {{ $pedido->peso_fuera_tolerancia ? 'autorizado por '.$pedido->peso_autorizado_por : 'validado' }}: {{ number_format($pedido->peso_produccion, 2) }} kg.
                    {{ $pedido->lote_rollo ? 'Rollo o lote '.$pedido->lote_rollo.'.' : '' }} Sube la foto del producto para terminar.
                </p>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-aa.subir label="Foto del producto terminado" model="foto_producto" :archivo="$foto_producto" :error="$errors->first('foto_producto')"
                                ayuda="Obligatoria. Que se vea la etiqueta pegada al producto." />
                    <x-aa.subir label="Foto de la báscula (opcional)" model="foto_bascula" :archivo="$foto_bascula" :error="$errors->first('foto_bascula')"
                                ayuda="Que se lea el peso. Si la lámina es muy larga y no cabe en la báscula, déjala sin foto." />
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="terminar,foto_producto,foto_bascula">Terminar producción</button>
                </div>
            </form>
        @endif

        {{-- Reimprimir: libre para quien autoriza; los demás piden la contraseña de un supervisor --}}
        @if ($paso > 1)
            <div class="grid gap-3 border-t border-linea pt-4">
                @if ($autoriza || $reimpresionLista)
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('pedidos.etiqueta', [$pedido, 'auto' => 1]) }}" target="_blank" rel="noopener" class="btn btn-secundario">
                            <x-aa.icon name="printer" /> Reimprimir etiqueta
                        </a>
                        @if ($reimpresionLista)
                            <span class="text-[15px] font-semibold text-ok">Reimpresión autorizada por {{ \App\Support\Etiqueta::minutos() }} minutos en este equipo.</span>
                        @endif
                    </div>
                @elseif ($autorizando === 'reimpresion')
                    <form wire:submit="autorizarReimpresion" class="grid gap-4 rounded-md border border-linea px-4 py-4">
                        <p class="flex items-center gap-1.5 font-semibold"><x-aa.icon name="lock-closed" /> Reimprimir etiqueta</p>
                        <p class="text-[15px] text-acero">Para reimprimir se necesita la autorización de un {{ \App\Services\Autorizacion::quienes() }}. Queda en la bitácora.</p>
                        <x-aa.autorizacion />
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" wire:click="cancelarAutorizacion" class="btn btn-secundario w-full sm:w-auto">Cancelar</button>
                            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="autorizarReimpresion">Autorizar reimpresión</button>
                        </div>
                    </form>
                @else
                    <div>
                        <button type="button" wire:click="pedirAutorizacion('reimpresion')"
                                class="inline-flex items-center gap-1.5 text-[15px] font-semibold text-acero underline-offset-4 hover:underline">
                            <x-aa.icon name="lock-closed" class="size-4" /> Reimprimir etiqueta (requiere supervisor)
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </div>
</section>
