<?php

namespace App\Services;

use App\Contracts\TraspasosExpertos;
use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Firebird\Microsip;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Alta de un pedido a partir de un traspaso real de Expertos o de Microsip Inventarios. En Expertos no hay una marca que
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
            ?? throw new DomainException("El traspaso {$folio} no existe en Expertos ni en Microsip.");

        if ($traspaso['estatus'] === 'C') {
            throw new DomainException("El traspaso {$folio} está cancelado en {$traspaso['origen']}.");
        }

        if ($traspaso['partidas'] === []) {
            throw new DomainException("El traspaso {$folio} no tiene artículos.");
        }

        return $traspaso;
    }

    /**
     * Da de alta solos los traspasos capturados en Microsip desde aa.traspasos_desde, para que el cajero los vea
     * en Inicio sin buscarlos. Con la primera partida y la sucursal de aa.sucursal_traspasos_microsip.
     * Devuelve cuántos pedidos creó; un traspaso que no se pueda leer se reporta y se intenta en la siguiente vuelta.
     */
    public function importarDeMicrosip(): int
    {
        $folios = $this->expertos->deMicrosipDesde((string) config('aa.traspasos_desde'));
        $nuevos = array_diff($folios, Pedido::whereIn('folio', $folios)->pluck('folio')->all());
        $creados = 0;

        foreach ($nuevos as $folio) {
            try {
                $traspaso = $this->buscar($folio);
                $this->registrar($traspaso, $traspaso['partidas'][0]['partida'], null, config('aa.sucursal_traspasos_microsip'));
                $creados++;
            } catch (DomainException) {
                // Sin artículos o ya registrado por otra vuelta: no hay nada que importar.
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $creados;
    }

    /**
     * Cajero y Sucursal registran para su sucursal; el administrador puede elegirla.
     * Sin usuario es la importación automática: queda a nombre del Sistema.
     */
    public function registrar(array $traspaso, int $partida, ?User $user, ?string $sucursal = null, ?string $cliente = null): Pedido
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
                'sucursal' => $user?->rol->limitadoASuSucursal() ? $user->sucursal : ($sucursal ?: ($traspaso['destino'] ?? 'Sin sucursal')),
                'cliente' => trim((string) $cliente) ?: 'Por capturar en caja',
                'material' => $linea['articulo'],
                'metros_traspaso' => $linea['cantidad'],
                'peso_por_metro' => $linea['peso_unitario'],
                'instrucciones' => trim(($traspaso['observaciones'] ?? '').' '.($linea['notas'] ?? '')) ?: null,
            ]);

            $pedido->eventos()->create([
                'user_id' => $user?->getKey(),
                'estatus' => $pedido->estatus->value,
                'accion' => "Traspaso leído de {$traspaso['origen']}",
                'detalle' => "Partida {$linea['partida']}: {$linea['articulo']} ({$linea['cantidad']} {$linea['unidad']}). Destino en {$traspaso['origen']}: ".($traspaso['destino'] ?? 'sin dato'),
            ]);

            return $pedido;
        });
    }
}
