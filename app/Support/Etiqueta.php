<?php

namespace App\Support;

use App\Models\Pedido;
use App\Models\User;
use App\Services\Autorizacion;

/**
 * Quién abre la etiqueta imprimible. La primera impresión es libre (es la orden de producción); después,
 * reimprimir pide la contraseña de un supervisor, salvo que quien opera ya tenga un rol que autoriza.
 * La autorización vale unos minutos en la sesión de ese equipo, para el pedido que se autorizó.
 */
final class Etiqueta
{
    /** Minutos que dura una reimpresión autorizada. */
    private const MINUTOS = 10;

    public static function puedeAbrir(User $user, Pedido $pedido): bool
    {
        return $pedido->etiqueta_impresa_at === null
            || Autorizacion::puedeAutorizar($user)
            || session(self::llave($pedido), 0) >= now()->getTimestamp();
    }

    /**
     * Deja abrir la etiqueta de este pedido en esta sesión durante unos minutos. La primera impresión
     * también lo usa (2 min): el botón abre la etiqueta y a la vez la marca como impresa, y la pestaña
     * puede llegar cuando ya quedó marcada.
     */
    public static function permitir(Pedido $pedido, int $minutos = self::MINUTOS): void
    {
        session()->put(self::llave($pedido), now()->addMinutes($minutos)->getTimestamp());
    }

    public static function minutos(): int
    {
        return self::MINUTOS;
    }

    private static function llave(Pedido $pedido): string
    {
        return 'etiqueta.permiso.'.$pedido->getKey();
    }
}
