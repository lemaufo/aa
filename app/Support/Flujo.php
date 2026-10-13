<?php

namespace App\Support;

use App\Enums\Estatus;
use App\Enums\FormaEntrega;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Models\User;
use Closure;
use DomainException;

/**
 * Única fuente de verdad del flujo operativo: qué acción existe, quién puede hacerla
 * y desde qué estatus. Las pantallas, la bitácora y la matriz de permisos salen de aquí.
 * No hay acción de cancelar: la cancelación se lee de Expertos/Microsip (FlujoService::revisarCancelacion).
 */
final class Flujo
{
    /**
     * @return array<string, array{titulo: string, roles: list<Rol>, desde: list<Estatus>, hacia: Estatus|Closure|null}>
     */
    public static function definicion(): array
    {
        return [
            'ligar' => [
                'titulo' => 'Traspaso ligado a la venta',
                'roles' => [Rol::Cajero],
                'desde' => [Estatus::EnExpertos],
                'hacia' => Estatus::Cobrado,
            ],
            // Antes de que llegue a CEDIS, con la contraseña del cajero.
            'cambiar_entrega' => [
                'titulo' => 'Datos de entrega modificados',
                'roles' => [Rol::Cajero],
                'desde' => [Estatus::Cobrado, Estatus::Producido],
                'hacia' => null,
            ],
            // Producción, 3 pasos en orden.
            'imprimir_etiqueta' => [
                'titulo' => 'Etiqueta impresa',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Cobrado],
                'hacia' => null,
            ],
            'pesar_produccion' => [
                'titulo' => 'Peso de producción validado',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Cobrado],
                'hacia' => null,
            ],
            'producir' => [
                'titulo' => 'Producción terminada con evidencia',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Cobrado],
                'hacia' => Estatus::Producido,
            ],
            'enviar_cedis' => [
                'titulo' => 'Despachado a CEDIS',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Producido],
                'hacia' => Estatus::EnviadoACedis,
            ],
            'validar' => [
                'titulo' => 'Recibido y validado en CEDIS',
                'roles' => [Rol::Cedis],
                'desde' => [Estatus::EnviadoACedis],
                'hacia' => Estatus::Validado,
            ],
            'despachar' => [
                'titulo' => 'Despacho definido',
                'roles' => [Rol::Cedis],
                'desde' => [Estatus::Validado],
                'hacia' => fn (Pedido $p) => match ($p->forma_entrega) {
                    FormaEntrega::Cedis => Estatus::EnCedis,
                    FormaEntrega::Cliente => Estatus::EnviadoACliente,
                    FormaEntrega::Sucursal => Estatus::EnviadoASucursal,
                    default => throw new DomainException('Falta definir la forma de entrega.'),
                },
            ],
            'entregar_cedis' => [
                'titulo' => 'Entregado al cliente',
                'roles' => [Rol::Cedis],
                'desde' => [Estatus::EnCedis, Estatus::EnviadoACliente],
                'hacia' => Estatus::Entregado,
            ],
            'recibir_sucursal' => [
                'titulo' => 'Recibido en sucursal',
                'roles' => [Rol::Sucursal],
                'desde' => [Estatus::EnviadoASucursal],
                'hacia' => Estatus::EnSucursal,
            ],
            'entregar_sucursal' => [
                'titulo' => 'Entregado al cliente',
                'roles' => [Rol::Sucursal],
                'desde' => [Estatus::EnSucursal],
                'hacia' => Estatus::Entregado,
            ],
            // La venta se actualiza sola al entregar; si Microsip falló, se reintenta aquí.
            'reintentar_venta' => [
                'titulo' => 'Reintento de actualización de venta',
                'roles' => [Rol::Cedis, Rol::Sucursal],
                'desde' => [Estatus::Entregado],
                'hacia' => null,
            ],
        ];
    }

    /** Pantalla (componente Volt) que atiende cada acción. Orden = prioridad. */
    private const PANELES = [
        'ligar' => 'aa.acciones.ligar',
        'imprimir_etiqueta' => 'aa.acciones.produccion',
        'pesar_produccion' => 'aa.acciones.produccion',
        'producir' => 'aa.acciones.produccion',
        'enviar_cedis' => 'aa.acciones.escaneo',
        'validar' => 'aa.acciones.recepcion',
        'despachar' => 'aa.acciones.despacho',
        'entregar_cedis' => 'aa.acciones.entrega-cedis',
        'recibir_sucursal' => 'aa.acciones.escaneo',
        'entregar_sucursal' => 'aa.acciones.entrega-sucursal',
        'reintentar_venta' => 'aa.acciones.reintentar-venta',
    ];

    public static function tieneRol(User $user, array $roles): bool
    {
        return $user->rol === Rol::Admin || in_array($user->rol, $roles, true);
    }

    public static function puede(User $user, string $accion, Pedido $pedido): bool
    {
        $def = self::definicion()[$accion] ?? null;

        if ($def === null
            || ! $pedido->esVisiblePara($user)
            || ! self::tieneRol($user, $def['roles'])
            || ! in_array($pedido->estatus, $def['desde'], true)) {
            return false;
        }

        return match ($accion) {
            'imprimir_etiqueta' => $pedido->etiqueta_impresa_at === null,
            'pesar_produccion' => $pedido->etiqueta_impresa_at !== null && $pedido->peso_produccion === null,
            'producir' => $pedido->peso_produccion !== null,
            'reintentar_venta' => $pedido->venta_sync_estado === 'error',
            default => true,
        };
    }

    /** Componente que debe mostrarse a este usuario para este pedido, o null si no le toca nada. */
    public static function panelPara(User $user, Pedido $pedido): ?string
    {
        foreach (self::PANELES as $accion => $panel) {
            if (self::puede($user, $accion, $pedido)) {
                return $panel;
            }
        }

        return null;
    }

    /** Paso de producción en el que va el pedido (1 etiqueta, 2 peso, 3 evidencia). */
    public static function pasoProduccion(Pedido $pedido): int
    {
        return match (true) {
            $pedido->etiqueta_impresa_at === null => 1,
            $pedido->peso_produccion === null => 2,
            default => 3,
        };
    }

    /** Acción que resuelve el panel genérico de escaneo según el estatus. */
    public static function accionEscaneo(Pedido $pedido): ?string
    {
        return match ($pedido->estatus) {
            Estatus::Producido => 'enviar_cedis',
            Estatus::EnviadoASucursal => 'recibir_sucursal',
            default => null,
        };
    }

    /** A quién le toca el siguiente paso (para decirlo cuando el usuario no puede actuar). */
    public static function siguienteResponsable(Pedido $pedido): ?Rol
    {
        return match ($pedido->estatus) {
            Estatus::EnExpertos => Rol::Cajero,
            Estatus::Cobrado, Estatus::Producido => Rol::Produccion,
            Estatus::EnviadoACedis, Estatus::Validado, Estatus::EnCedis, Estatus::EnviadoACliente => Rol::Cedis,
            Estatus::EnviadoASucursal, Estatus::EnSucursal => Rol::Sucursal,
            Estatus::Entregado => $pedido->venta_sync_estado === 'error'
                ? ($pedido->forma_entrega === FormaEntrega::Sucursal ? Rol::Sucursal : Rol::Cedis)
                : null,
            Estatus::Cancelado => null,
        };
    }
}