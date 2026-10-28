<?php

namespace App\Services;

use App\Contracts\TraspasosExpertos;
use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Firebird\Microsip;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Alta de un pedido a partir de un traspaso real de Expertos. En Expertos no hay una marca que
 * distinga el pedido especial del resurtido normal, así que el cajero lo registra con su folio.
 * Un pedido es un artículo: se toma una partida del traspaso.
 */
class AltaPedido
{
    public function __construct(private TraspasosExpertos $expertos) {}

    /**
     * Lee el traspaso y revisa que se pueda registrar. Los mensajes son para mostrarse tal cual.
     *
     * @throws DomainException
     */
    public function buscar(string $folio): array
    {
        $folio = Microsip::folio($folio);

        if ($folio === '') {
            throw new DomainException('Captura el folio del traspaso.');
        }

        if ($existente = Pedido::where('folio', $folio)->first()) {
            throw new DomainException("El traspaso {$folio} ya está registrado como pedido ({$existente->estatus->label()}).");
        }

        $traspaso = $this->expertos->leer($folio)
            ?? throw new DomainException("El traspaso {$folio} no existe en Expertos.");

        if ($traspaso['estatus'] === 'C') {
            throw new DomainException("El traspaso {$folio} está cancelado en Expertos.");
        }

        if ($traspaso['partidas'] === []) {
            throw new DomainException("El traspaso {$folio} no tiene artículos.");
        }

        return $traspaso;
    }

    /** Cajero y Sucursal registran para su sucursal; el administrador puede elegirla. */
    public function registrar(array $traspaso, int $partida, User $user, ?string $sucursal = null, ?string $cliente = null): Pedido
    {
        $linea = collect($traspaso['partidas'])->firstWhere('partida', $partida)
            ?? throw new DomainException("La partida {$partida} no existe en el traspaso.");

        return DB::transaction(function () use ($traspaso, $linea, $user, $sucursal, $cliente) {
            if (Pedido::where('folio', $traspaso['folio'])->lockForUpdate()->exists()) {
                throw new DomainException("El traspaso {$traspaso['folio']} ya está registrado como pedido.");
            }

            $pedido = Pedido::create([
                'folio' => $traspaso['folio'],
                'estatus' => Estatus::EnExpertos,
                'sucursal' => $user->rol->limitadoASuSucursal() ? $user->sucursal : ($sucursal ?: ($traspaso['destino'] ?? 'Sin sucursal')),
                'cliente' => trim((string) $cliente) ?: 'Por capturar en caja',
                'material' => $linea['articulo'],
                'metros_traspaso' => $linea['cantidad'],
                'peso_por_metro' => $linea['peso_unitario'],
                'instrucciones' => trim(($traspaso['observaciones'] ?? '').' '.($linea['notas'] ?? '')) ?: null,
            ]);

            $pedido->eventos()->create([
                'user_id' => $user->getKey(),
                'estatus' => $pedido->estatus->value,
                'accion' => 'Traspaso leído de Expertos',
                'detalle' => "Partida {$linea['partida']}: {$linea['articulo']} ({$linea['cantidad']} {$linea['unidad']}). Destino en Expertos: ".($traspaso['destino'] ?? 'sin dato'),
            ]);

            return $pedido;
        });
    }
}
