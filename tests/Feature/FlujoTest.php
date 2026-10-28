<?php

use App\Enums\Estatus;
use App\Enums\FormaEntrega;
use App\Enums\Rol;
use App\Services\FlujoService;
use App\Support\Flujo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

// Transiciones del flujo (Flujo, FlujoService): quién puede, desde qué estatus y hacia cuál.

it('solo el cajero de la sucursal liga un traspaso en Expertos', function () {
    $p = pedido();

    expect(Flujo::puede(usuario(Rol::Cajero), 'ligar', $p))->toBeTrue()
        ->and(Flujo::puede(usuario(Rol::Cajero, 'Sucursal Norte'), 'ligar', $p))->toBeFalse()
        ->and(Flujo::puede(usuario(Rol::Produccion), 'ligar', $p))->toBeFalse()
        ->and(Flujo::puede(usuario(Rol::Consulta), 'ligar', $p))->toBeFalse()
        ->and(Flujo::puede(usuario(Rol::Admin), 'ligar', $p))->toBeTrue();
});

it('no aplica una acción desde un estatus que no le corresponde', function () {
    $p = pedido(Estatus::Cobrado);

    app(FlujoService::class)->aplicar($p, 'validar', usuario(Rol::Cedis));
})->throws(AuthorizationException::class);

it('producción sigue sus 3 pasos en orden', function () {
    $p = pedido(Estatus::Cobrado);
    $prod = usuario(Rol::Produccion);

    expect(Flujo::puede($prod, 'pesar_produccion', $p))->toBeFalse()
        ->and(Flujo::puede($prod, 'producir', $p))->toBeFalse()
        ->and(Flujo::puede($prod, 'imprimir_etiqueta', $p))->toBeTrue();

    $p = app(FlujoService::class)->aplicar($p, 'imprimir_etiqueta', $prod, ['etiqueta_impresa_at' => now()]);
    expect(Flujo::puede($prod, 'imprimir_etiqueta', $p))->toBeFalse()
        ->and(Flujo::puede($prod, 'pesar_produccion', $p))->toBeTrue();

    $p = app(FlujoService::class)->aplicar($p, 'pesar_produccion', $prod, ['peso_produccion' => 207]);
    expect(Flujo::puede($prod, 'producir', $p))->toBeTrue();
});

it('recorre el pedido de punta a punta y marca la venta como entregada', function () {
    $flujo = app(FlujoService::class);
    $p = pedido(Estatus::Cobrado, ['forma_entrega' => FormaEntrega::Sucursal]);
    $prod = usuario(Rol::Produccion);
    $cedis = usuario(Rol::Cedis);
    $suc = usuario(Rol::Sucursal);

    $p = $flujo->aplicar($p, 'imprimir_etiqueta', $prod, ['etiqueta_impresa_at' => now()]);
    $p = $flujo->aplicar($p, 'pesar_produccion', $prod, ['peso_produccion' => 207]);
    $p = $flujo->aplicar($p, 'producir', $prod);
    $p = $flujo->aplicar($p, 'enviar_cedis', $prod);
    $p = $flujo->aplicar($p, 'validar', $cedis);
    $p = $flujo->aplicar($p, 'despachar', $cedis);
    expect($p->estatus)->toBe(Estatus::EnviadoASucursal);

    $p = $flujo->aplicar($p, 'recibir_sucursal', $suc);
    $p = $flujo->aplicar($p, 'entregar_sucursal', $suc, ['recibe_nombre' => 'Juan Pérez', 'acuse_path' => 'x.jpg']);

    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('ok')
        ->and(DB::table('demo_ventas')->where('folio', $p->folio_venta)->value('entregada'))->toBeTruthy()
        ->and($p->eventos()->count())->toBe(9); // 8 acciones + "Venta actualizada en Microsip"
});

it('si Microsip falla, el pedido queda entregado con error y se puede reintentar', function () {
    config(['aa.demo_falla_microsip' => true]);
    $p = pedido(Estatus::EnCedis);
    $cedis = usuario(Rol::Cedis);

    $p = app(FlujoService::class)->aplicar($p, 'entregar_cedis', $cedis, ['recibe_nombre' => 'Juan Pérez', 'acuse_path' => 'x.jpg']);

    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('error')
        ->and(Flujo::puede($cedis, 'reintentar_venta', $p))->toBeTrue();

    config(['aa.demo_falla_microsip' => false]);
    $p = app(FlujoService::class)->aplicar($p, 'reintentar_venta', $cedis);

    expect($p->venta_sync_estado)->toBe('ok')
        ->and(Flujo::puede($cedis, 'reintentar_venta', $p))->toBeFalse();
});

it('cancela solo mientras el traspaso sigue en Expertos', function () {
    $enExpertos = pedido();
    $cobrado = pedido(Estatus::Cobrado);
    foreach ([$enExpertos, $cobrado] as $p) {
        DB::table('demo_traspasos')->insert(['folio' => $p->folio, 'cancelado' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    expect(app(FlujoService::class)->revisarTodas())->toBe(1)
        ->and($enExpertos->fresh()->estatus)->toBe(Estatus::Cancelado)
        ->and($enExpertos->fresh()->cancelado_por_expertos)->toBeTrue()
        ->and($cobrado->fresh()->estatus)->toBe(Estatus::Cobrado);
});

it('no cancela nada si no hay cancelación en Expertos', function () {
    $p = pedido();

    expect(app(FlujoService::class)->revisarCancelacion($p))->toBeFalse()
        ->and($p->fresh()->estatus)->toBe(Estatus::EnExpertos);
});
