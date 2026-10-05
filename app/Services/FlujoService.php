<?php

namespace App\Services;

use App\Models\Pedido;
use App\Models\User;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aplica una acción del flujo: valida permiso y estado, guarda los cambios,
 * mueve el estatus y deja la línea en la bitácora. Todo en una transacción.
 */
class FlujoService
{
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

        return DB::transaction(function () use ($pedido, $accion, $user, $cambios, $detalle, $titulo, $def) {
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
    }
}