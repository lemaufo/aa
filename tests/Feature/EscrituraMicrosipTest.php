<?php

use App\Contracts\VentasMicrosip;
use App\Enums\Estatus;
use App\Enums\Rol;
use App\Models\Pedido;
use App\Services\Firebird\FirebirdVentas;
use App\Services\FlujoService;
use App\Support\Flujo;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;

/*
| La única escritura de la app en Microsip: marcar la venta como entregada (LIBRES_VTA_PV.ENTREGADO = 'S').
| Se prueba la clase real FirebirdVentas; lo único simulado es la conexión 'firebird', que devuelve la fila
| del ticket que necesita cada caso o falla como si el servidor estuviera apagado. No hace falta Firebird.
*/

/**
 * Cambia la conexión 'firebird' por un doble. $ticket es la fila de DOCTOS_PV + LIBRES_VTA_PV
 * (null = el ticket no existe); $falla, la excepción que lanzaría Firebird apagado.
 */
function firebirdSimulado(?array $ticket, int $filasActualizadas = 1, ?Throwable $falla = null): MockInterface
{
    $db = Mockery::mock(Connection::class)->shouldIgnoreMissing();
    $db->shouldReceive('setReadWriteType')->andReturnSelf(); // la configura el DatabaseManager en cadena

    $falla
        ? $db->shouldReceive('selectOne')->andThrow($falla)
        : $db->shouldReceive('selectOne')->andReturn($ticket === null ? null : (object) $ticket);
    $db->shouldReceive('update')->andReturn($filasActualizadas)->byDefault();

    DB::purge('firebird');
    DB::extend('firebird', fn () => $db);
    app()->bind(VentasMicrosip::class, FirebirdVentas::class);

    return $db;
}

/** Fila del ticket como la regresa Firebird (columnas en mayúsculas y CHAR con espacios). */
function ticketMicrosip(array $cambios = []): array
{
    return array_merge(['DOCTO_PV_ID' => 4815, 'FOLIO' => 'TBA074886', 'ESTATUS' => 'N', 'APLICADO' => 'S', 'ENTREGADO' => 'N'], $cambios);
}

function entregarEnCedis(Pedido $p): Pedido
{
    return app(FlujoService::class)->aplicar($p, 'entregar_cedis', usuario(Rol::Cedis), ['recibe_nombre' => 'Juan Pérez', 'acuse_path' => 'x.jpg']);
}

/** Acciones de la bitácora del pedido, en orden. */
function bitacora(Pedido $p): array
{
    return $p->eventos()->orderBy('id')->pluck('accion')->all();
}

it('marca la venta como entregada en Microsip y lo anota en la bitácora', function () {
    $db = firebirdSimulado(ticketMicrosip());
    $db->shouldReceive('update')->once()
        ->with(Mockery::pattern('/update libres_vta_pv set entregado = \'S\'/'), [4815])
        ->andReturn(1);

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('ok')
        ->and($p->venta_sync_error)->toBeNull()
        ->and(bitacora($p))->toContain('Venta actualizada en Microsip');
});

it('no vuelve a escribir si la venta ya estaba entregada en Microsip y no lo cuenta como error', function () {
    $db = firebirdSimulado(ticketMicrosip(['ENTREGADO' => 'S']));
    $db->shouldNotReceive('update');

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    expect($p->venta_sync_estado)->toBe('ok')
        ->and(bitacora($p))->toContain('Venta actualizada en Microsip')
        ->not->toContain('Error al actualizar Microsip');
});

it('con el ticket cancelado en Microsip no escribe, deja el error y permite reintentar', function () {
    $db = firebirdSimulado(ticketMicrosip(['ESTATUS' => 'C']));
    $db->shouldNotReceive('update');

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('error')
        ->and($p->venta_sync_error)->toContain('está cancelado en Microsip')
        ->and($p->eventos()->latest('id')->first()->only('accion', 'detalle'))->toMatchArray([
            'accion' => 'Error al actualizar Microsip',
            'detalle' => $p->venta_sync_error,
        ])
        ->and(Flujo::puede(usuario(Rol::Cedis), 'reintentar_venta', $p))->toBeTrue();
});

it('con el ticket sin datos de entrega en Microsip deja el error con el motivo', function () {
    // El ticket existe pero no tiene fila en LIBRES_VTA_PV: el left join trae ENTREGADO en null y el update no toca nada.
    firebirdSimulado(ticketMicrosip(['ENTREGADO' => null]), filasActualizadas: 0);

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    expect($p->venta_sync_estado)->toBe('error')
        ->and($p->venta_sync_error)->toContain('no tiene datos de entrega en Microsip')
        ->and(bitacora($p))->toContain('Error al actualizar Microsip');
});

it('con un ticket que ya no existe en Microsip deja el error', function () {
    firebirdSimulado(null)->shouldNotReceive('update');

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    expect($p->venta_sync_estado)->toBe('error')
        ->and($p->venta_sync_error)->toContain('no existe en Microsip');
});

it('con Firebird apagado la entrega queda hecha y la venta se actualiza al reintentar con Firebird encendido', function () {
    firebirdSimulado(null, falla: new PDOException('SQLSTATE[08006]: Unable to complete network request to host "127.0.0.1".'));

    $p = entregarEnCedis(pedido(Estatus::EnCedis));

    // La entrega no se pierde: el pedido queda entregado con la venta pendiente de actualizar.
    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('error')
        ->and($p->venta_sync_error)->toContain('Unable to complete network request')
        ->and($p->venta_sync_at)->not->toBeNull();

    // Firebird vuelve: CEDIS reintenta desde el pedido.
    firebirdSimulado(ticketMicrosip())->shouldReceive('update')->once()->andReturn(1);
    $p = app(FlujoService::class)->aplicar($p, 'reintentar_venta', usuario(Rol::Cedis));

    expect($p->venta_sync_estado)->toBe('ok')
        ->and($p->venta_sync_error)->toBeNull()
        ->and(Flujo::puede(usuario(Rol::Cedis), 'reintentar_venta', $p))->toBeFalse();

    // Un evento por cada intento, sin duplicados: el error, el reintento y el éxito.
    $escrituras = array_values(array_filter(bitacora($p), fn ($a) => str_contains($a, 'Microsip') || str_contains($a, 'Reintento')));
    expect($escrituras)->toBe(['Error al actualizar Microsip', 'Reintento de actualización de venta', 'Venta actualizada en Microsip']);

    // Nada de esto lo ve el cliente al rastrear su pedido.
    expect(Pedido::EVENTOS_INTERNOS)->toContain('Error al actualizar Microsip', 'Venta actualizada en Microsip', 'Reintento de actualización de venta')
        ->and($p->eventosPublicos()->count())->toBe($p->eventos()->whereNotIn('accion', Pedido::EVENTOS_INTERNOS)->count());
});
