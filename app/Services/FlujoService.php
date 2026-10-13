<?php

namespace App\Services;

use App\Contracts\TraspasosExpertos;
use App\Contracts\VentasMicrosip;
use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\User;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Aplica una acción del flujo: valida permiso y estado, guarda los cambios,
 * mueve el estatus y deja la línea en la bitácora. Todo en una transacción.
 * Al entregar, además actualiza la venta en Microsip (con reintento si falla).
 */
class FlujoService
{
    /** Acciones que cierran la entrega y por lo tanto actualizan la venta. */
    private const CIERRAN_VENTA = ['entregar_cedis', 'entregar_sucursal', 'reintentar_venta'];

    public function __construct(
        private VentasMicrosip $ventas,
        private TraspasosExpertos $expertos,
    ) {}

    /**
     * @param  array<string, mixed>  $cambios  Campos del pedido que se guardan con la acción.
     */
    public function aplicar(
        Pedido $pedido,
        string $accion,
        User $user,
        array $cambios = [],
        ?string $detalle = null,
        ?string $titulo = null,
    ): Pedido {
        $def = Flujo::definicion()[$accion]
            ?? throw new InvalidArgumentException("Acción desconocida: {$accion}");

        $actual = DB::transaction(function () use ($pedido, $accion, $user, $cambios, $detalle, $titulo, $def) {
            // Se vuelve a leer con bloqueo: otro usuario pudo mover el pedido mientras tanto.
            $actual = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();

            if (! Flujo::puede($user, $accion, $actual)) {
                throw new AuthorizationException(
                    'Este pedido ya cambió de estatus o no tienes permiso para esta acción. Actualiza la pantalla.'
                );
            }

            $destino = $def['hacia'] instanceof \Closure ? ($def['hacia'])($actual) : $def['hacia'];

            $actual->fill($cambios);
            if ($destino !== null) {
                $actual->estatus = $destino;
            }
            $actual->save();

            $actual->eventos()->create([
                'user_id' => $user->getKey(),
                'estatus' => $actual->estatus->value,
                'accion' => $titulo ?? $def['titulo'],
                'detalle' => $detalle,
            ]);

            return $actual;
        });

        if (in_array($accion, self::CIERRAN_VENTA, true)) {
            $actual = $this->sincronizarVenta($actual, $user);
        }

        return $actual;
    }

    /**
     * Marca la venta como entregada en Microsip. Si falla, el pedido queda ENTREGADO
     * con la sincronización en error y se puede reintentar desde la pantalla.
     */
    public function sincronizarVenta(Pedido $pedido, ?User $user = null): Pedido
    {
        try {
            $this->ventas->marcarEntregada((string) $pedido->folio_venta, $user?->name ?? 'Sistema', $pedido->recibe_nombre);

            $pedido->forceFill([
                'venta_sync_estado' => 'ok',
                'venta_sync_error' => null,
                'venta_sync_at' => now(),
            ])->save();

            $this->evento($pedido, $user, 'Venta actualizada en Microsip', 'Venta '.$pedido->folio_venta.' marcada como entregada');
        } catch (Throwable $e) {
            report($e);

            $pedido->forceFill([
                'venta_sync_estado' => 'error',
                'venta_sync_error' => Str::limit($e->getMessage(), 240, ''),
                'venta_sync_at' => now(),
            ])->save();

            $this->evento($pedido, $user, 'Error al actualizar Microsip', Str::limit($e->getMessage(), 200, ''));
        }

        return $pedido->refresh();
    }

    /**
     * Lee Expertos y Microsip: si el traspaso o el ticket ya se cancelaron allá, el pedido se cancela aquí.
     * La app nunca cancela por su cuenta. Devuelve true si el pedido se canceló.
     */
    public function revisarCancelacion(Pedido $pedido): bool
    {
        // Acordado en la reunión: solo se puede cancelar en el paso 1 (traspaso en Expertos, aún sin ligar a la venta).
        if ($pedido->estatus !== Estatus::EnExpertos) {
            return false;
        }

        try {
            $motivo = null;

            if ($this->expertos->estaCancelado($pedido->folio)) {
                $motivo = 'Traspaso cancelado en Expertos';
            } elseif ($pedido->folio_venta && ($this->ventas->consultar($pedido->folio_venta)['estado'] ?? null) === 'cancelada') {
                $motivo = 'Ticket '.$pedido->folio_venta.' cancelado en Microsip';
            }
        } catch (Throwable $e) {
            report($e);

            return false; // sin conexión no se asume nada
        }

        if ($motivo === null) {
            return false;
        }

        return DB::transaction(function () use ($pedido, $motivo) {
            $actual = Pedido::query()->whereKey($pedido->getKey())->lockForUpdate()->firstOrFail();

            if ($actual->estatus !== Estatus::EnExpertos) {
                return false;
            }

            $actual->forceFill([
                'estatus' => Estatus::Cancelado,
                'motivo_cancelacion' => $motivo,
                'cancelado_por_expertos' => true,
            ])->save();

            $this->evento($actual, null, $motivo, 'Detectado automáticamente. Si ya hay material producido o en ruta, revisar con Producción y CEDIS.');

            return true;
        });
    }

    /** Revisa todos los pedidos abiertos. Lo usa el comando programado. */
    public function revisarTodas(): int
    {
        $canceladas = 0;

        Pedido::query()
            ->where('estatus', Estatus::EnExpertos->value)
            ->each(function (Pedido $p) use (&$canceladas) {
                if ($this->revisarCancelacion($p)) {
                    $canceladas++;
                }
            });

        return $canceladas;
    }

    private function evento(Pedido $pedido, ?User $user, string $accion, ?string $detalle): void
    {
        $pedido->eventos()->create([
            'user_id' => $user?->getKey(),
            'estatus' => $pedido->estatus->value,
            'accion' => $accion,
            'detalle' => $detalle,
        ]);
    }
}