<?php

use App\Enums\FormaEntrega;
use App\Livewire\Concerns\ManejaCortes;
use App\Models\Pedido;
use App\Services\FlujoService;
use App\Services\ValidadorTicket;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    use ManejaCortes;

    public Pedido $pedido;

    public string $folio_venta = '';

    public string $cliente = '';

    public string $telefono = '';

    public string $forma_entrega = '';

    public string $fecha_prometida = '';

    // Solo cuando el pedido se envía al domicilio del cliente.
    public string $recibe_nombre = '';

    public string $direccion_entrega = '';

    public string $gps_link = '';

    /** @var list<string> */
    public array $ticketErrores = [];

    public bool $ticketOk = false;

    public function mount(Pedido $pedido): void
    {
        $this->pedido = $pedido;
        $this->cliente = $pedido->cliente;
        $this->telefono = (string) $pedido->telefono;
        $this->forma_entrega = $pedido->forma_entrega?->value ?? '';
        $this->fecha_prometida = $pedido->fecha_prometida?->toDateString() ?? '';
        $this->recibe_nombre = (string) $pedido->recibe_nombre;
        $this->direccion_entrega = (string) $pedido->direccion_entrega;
        $this->gps_link = (string) $pedido->gps_link;
        $this->iniciarCortes($pedido->cortes_solicitados);
    }

    public function aDomicilio(): bool
    {
        return $this->forma_entrega === FormaEntrega::Cliente->value;
    }

    /** Al salir del campo del ticket se valida contra Microsip y el traspaso. */
    public function verificarTicket(): void
    {
        $this->ticketErrores = trim($this->folio_venta) === ''
            ? []
            : app(ValidadorTicket::class)->validar($this->pedido, $this->folio_venta);
        $this->ticketOk = trim($this->folio_venta) !== '' && $this->ticketErrores === [];
    }

    public function ligar(FlujoService $flujo): void
    {
        $this->validate([
            'folio_venta' => ['required', 'string', 'max:30'],
            'cliente' => ['required', 'string', 'max:120'],
            'telefono' => ['required', 'string', 'regex:/^[0-9\s\-\(\)\+]{10,18}$/'],
            'forma_entrega' => ['required', Rule::enum(FormaEntrega::class)],
            'fecha_prometida' => ['required', 'date', 'after_or_equal:today'],
            'recibe_nombre' => [Rule::requiredIf($this->aDomicilio()), 'nullable', 'string', 'max:120'],
            'direccion_entrega' => [Rule::requiredIf($this->aDomicilio()), 'nullable', 'string', 'max:255'],
            'gps_link' => ['nullable', 'url', 'max:255'],
        ] + $this->reglasCortes(), [], [
            'folio_venta' => 'folio del ticket',
            'cliente' => 'cliente',
            'telefono' => 'teléfono',
            'forma_entrega' => 'forma de entrega',
            'fecha_prometida' => 'fecha prometida',
            'recibe_nombre' => 'persona que recibe',
            'direccion_entrega' => 'dirección',
            'gps_link' => 'enlace de ubicación',
        ] + $this->atributosCortes());

        if (! $this->cuadraConTraspaso($this->pedido->metros_traspaso)) {
            return;
        }

        $this->verificarTicket();
        if ($this->ticketErrores !== []) {
            $this->addError('folio_venta', $this->ticketErrores[0]);

            return;
        }

        // Con Microsip se guarda el folio oficial (TBA074886) aunque se haya tecleado corto (tba74886).
        $venta = config('aa.origen') === 'firebird'
            ? \App\Services\Firebird\Microsip::folio($this->folio_venta)
            : trim($this->folio_venta);
        $domicilio = $this->aDomicilio();

        try {
            $flujo->aplicar($this->pedido, 'ligar', auth()->user(), [
                'folio_venta' => $venta,
                'ticket_validado_at' => now(),
                'cliente' => trim($this->cliente),
                'telefono' => trim($this->telefono),
                'forma_entrega' => $this->forma_entrega,
                'fecha_prometida' => $this->fecha_prometida,
                'recibe_nombre' => $domicilio ? trim($this->recibe_nombre) : null,
                'direccion_entrega' => $domicilio ? trim($this->direccion_entrega) : null,
                'gps_link' => $domicilio ? (trim($this->gps_link) ?: null) : null,
                'cortes_solicitados' => $this->cortesLimpios(),
            ], "Ticket {$venta} validado contra el traspaso. ".FormaEntrega::from($this->forma_entrega)->label()
                .'. Prometido para el '.\Illuminate\Support\Carbon::parse($this->fecha_prometida)->translatedFormat('j M Y')
                .($domicilio ? '. Recibe: '.trim($this->recibe_nombre) : ''));
        } catch (AuthorizationException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
            $this->dispatch('pedido-actualizado');

            return;
        }

        $this->dispatch('toast', tipo: 'ok', mensaje: 'Traspaso ligado a la venta. Producción ya puede tomarlo.');
        $this->dispatch('pedido-actualizado');
    }
}; ?>

<section class="panel panel-accion">
    <div class="panel-cabecera">
        <h2 class="panel-titulo">Liga el traspaso con la venta</h2>
    </div>

    <form wire:submit="ligar" class="grid gap-5 px-5 py-5">
        @if ($pedido->instrucciones)
            <div class="rounded-md bg-plancha px-4 py-3">
                <p class="text-[15px] font-semibold">Lo que se escribió en Expertos</p>
                <p class="mt-0.5 text-acero">{{ $pedido->instrucciones }}</p>
            </div>
        @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="grid content-start gap-2">
                <x-aa.campo label="Folio de venta (ticket)" for="folio_venta" :error="$errors->first('folio_venta')"
                            ayuda="El pedido especial se vende en un ticket independiente, sin productos de línea.">
                    <input id="folio_venta" type="text" wire:model="folio_venta" wire:change="verificarTicket" class="input" placeholder="V-118204"
                           autocomplete="off" @error('folio_venta') aria-invalid="true" @enderror>
                </x-aa.campo>

                @if ($ticketOk)
                    <p class="text-[15px] font-semibold text-ok" role="status">Ticket cobrado y coincide con el traspaso.</p>
                @endif
                @if ($ticketErrores && ! $errors->has('folio_venta'))
                    <ul class="grid gap-1 text-[15px] font-medium text-peligro" role="alert">
                        @foreach ($ticketErrores as $mensaje)
                            <li>{{ $mensaje }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <x-aa.campo label="Cliente" for="cliente" :error="$errors->first('cliente')">
                <input id="cliente" type="text" wire:model="cliente" class="input" autocomplete="off"
                       @error('cliente') aria-invalid="true" @enderror>
            </x-aa.campo>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-aa.campo label="Teléfono del cliente" for="telefono" :error="$errors->first('telefono')"
                        ayuda="Se usa para avisarle por WhatsApp cuando su pedido esté listo.">
                <input id="telefono" type="tel" inputmode="tel" wire:model="telefono" class="input" placeholder="961 123 4567"
                       autocomplete="off" @error('telefono') aria-invalid="true" @enderror>
            </x-aa.campo>

            <x-aa.campo label="Fecha prometida de entrega" for="fecha_prometida" :error="$errors->first('fecha_prometida')"
                        ayuda="Con esta fecha se ordena qué se produce y qué se entrega primero.">
                <input id="fecha_prometida" type="date" wire:model="fecha_prometida" min="{{ now()->toDateString() }}" class="input"
                       @error('fecha_prometida') aria-invalid="true" @enderror>
            </x-aa.campo>
        </div>

        <x-aa.campo label="Forma de entrega" for="forma_entrega" :error="$errors->first('forma_entrega')">
            <select id="forma_entrega" wire:model.live="forma_entrega" class="input" @error('forma_entrega') aria-invalid="true" @enderror>
                <option value="">Elige una opción</option>
                @foreach (\App\Enums\FormaEntrega::cases() as $forma)
                    <option value="{{ $forma->value }}">{{ $forma->label() }}</option>
                @endforeach
            </select>
        </x-aa.campo>

        @if ($this->aDomicilio())
            <fieldset class="grid gap-5 rounded-md border border-linea bg-plancha/50 px-4 pb-5 pt-3">
                <legend class="px-1 font-display text-xl font-semibold">Datos de entrega a domicilio</legend>

                <x-aa.campo label="Persona que recibe" for="recibe_nombre" :error="$errors->first('recibe_nombre')">
                    <input id="recibe_nombre" type="text" wire:model="recibe_nombre" class="input" autocomplete="off"
                           @error('recibe_nombre') aria-invalid="true" @enderror>
                </x-aa.campo>

                <x-aa.campo label="Dirección completa, con referencias" for="direccion_entrega" :error="$errors->first('direccion_entrega')">
                    <textarea id="direccion_entrega" wire:model="direccion_entrega" rows="3" class="input"
                              placeholder="Calle, número, colonia y referencias para llegar"
                              @error('direccion_entrega') aria-invalid="true" @enderror></textarea>
                </x-aa.campo>

                <x-aa.campo label="Enlace de ubicación (Google Maps)" for="gps_link" :error="$errors->first('gps_link')"
                            ayuda="Pega el enlace que da la opción Compartir de Google Maps.">
                    <input id="gps_link" type="url" wire:model="gps_link" class="input" inputmode="url"
                           placeholder="https://maps.google.com/..." @error('gps_link') aria-invalid="true" @enderror>
                </x-aa.campo>
            </fieldset>
        @endif

        <x-aa.cortes :cortes="$cortes" :total="$this->totalMetros()" :meta="$pedido->metros_traspaso"
                     titulo="Piezas y medidas del pedido" :errores="$this->erroresCortes()" />

        <div class="flex flex-wrap justify-end gap-3">
            <button type="submit" class="btn btn-primario w-full sm:w-auto" wire:loading.attr="disabled" wire:target="ligar">
                <span wire:loading.remove wire:target="ligar">Validar ticket y ligar</span>
                <span wire:loading wire:target="ligar">Validando...</span>
            </button>
        </div>
    </form>
</section>