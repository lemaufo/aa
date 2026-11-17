<?php

namespace App\Support;

use App\Models\Pedido;
use App\Models\User;
use App\Services\Autorizacion;

/**
 * Quién abre la etiqueta imprimible. La primera impresión es libre (es la orden de producción); después,
 * reimprimir pide la contraseña de un supervisor, salvo que quien opera ya tenga un rol que autoriza.
 * La autorización vale SEGUNDOS en la sesión de ese equipo, solo para el pedido que se autorizó.
 */
final class Etiqueta
{
    /** Segundos que dura una reimpresión autorizada (y la ventana de la primera impresión). */
    public const SEGUNDOS = 60;

    public static function puedeAbrir(User $user, Pedido $pedido): bool
    {
        return $pedido->etiqueta_impresa_at === null
            || Autorizacion::puedeAutorizar($user)
            || session(self::llave($pedido), 0) >= now()->getTimestamp();
    }

    /**
     * Deja abrir la etiqueta de este pedido en esta sesión durante SEGUNDOS. La primera impresión también
     * lo usa: el botón abre la etiqueta y a la vez la marca como impresa, y la pestaña puede llegar cuando
     * ya quedó marcada.
     */
    public static function permitir(Pedido $pedido): void
    {
        session()->put(self::llave($pedido), now()->addSeconds(self::SEGUNDOS)->getTimestamp());
    }

    /** "1 minuto", "90 segundos"... para los mensajes de la pantalla. */
    public static function duracion(): string
    {
        $s = self::SEGUNDOS;

        return $s % 60 === 0
            ? ($s / 60).($s === 60 ? ' minuto' : ' minutos')
            : $s.' segundos';
    }

    private static function llave(Pedido $pedido): string
    {
        return 'etiqueta.permiso.'.$pedido->getKey();
    }
}
