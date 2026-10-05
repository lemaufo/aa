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
            'cancelar' => [
                'titulo' => 'Traspaso cancelado',
                'roles' => [Rol::Cajero],
                'desde' => [Estatus::EnExpertos, Estatus::Cobrado],
                'hacia' => Estatus::Cancelado,
            ],
            'capturar' => [
                'titulo' => 'Captura de producción validada',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Cobrado],
                'hacia' => null,
            ],
            'inconsistencia' => [
                'titulo' => 'Inconsistencia reportada',
                'roles' => [Rol::Produccion],
                'desde' => [Estatus::Cobrado],
                'hacia' => null,
            ],
            'etiquetar' => [
                'titulo' => 'Etiqueta impresa y evidencia cargada',
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
                'titulo' => 'Recibido y pesado en CEDIS',
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
            'cerrar_venta' => [
                'titulo' => 'Venta cerrada en Microsip',
                'roles' => [Rol::Sucursal],
                'desde' => [Estatus::Entregado],
                'hacia' => null,
            ],
        ];
    }

    /** Pantalla (componente Volt) que atiende cada acción. Orden = prioridad. */
    private const PANELES = [
        'ligar' => 'aa.acciones.ligar',
        'capturar' => 'aa.acciones.captura',
        'etiquetar' => 'aa.acciones.etiqueta',
        'enviar_cedis' => 'aa.acciones.escaneo',
        'validar' => 'aa.acciones.recepcion',
        'despachar' => 'aa.acciones.despacho',
        'entregar_cedis' => 'aa.acciones.entrega-cedis',
        'recibir_sucursal' => 'aa.acciones.escaneo',
        'entregar_sucursal' => 'aa.acciones.escaneo',
        'cerrar_venta' => 'aa.acciones.cerrar-venta',
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
            'capturar', 'inconsistencia' => $pedido->captura_validada_at === null,
            'etiquetar' => $pedido->captura_validada_at !== null,
            'cerrar_venta' => ! $pedido->venta_cerrada,
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

    /** Acción que resuelve el panel genérico de escaneo según el estatus. */
    public static function accionEscaneo(Pedido $pedido): ?string
    {
        return match ($pedido->estatus) {
            Estatus::Producido => 'enviar_cedis',
            Estatus::EnviadoASucursal => 'recibir_sucursal',
            Estatus::EnSucursal => 'entregar_sucursal',
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
            Estatus::Entregado => $pedido->venta_cerrada ? null : Rol::Sucursal,
            Estatus::Cancelado => null,
        };
    }
}