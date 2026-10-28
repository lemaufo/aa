<?php

use App\Enums\Estatus;
use App\Enums\Rol;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

// Tolerancia de peso (±5 %) y acuse obligatorio, probados en los paneles que usa la gente.

beforeEach(fn () => Storage::fake('local'));

it('valida el peso dentro de la tolerancia y rechaza el que se sale', function (float $peso, bool $pasa) {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now()]); // teórico 207 kg

    $c = Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->set('peso_produccion', (string) $peso)
        ->call('pesar');

    $pasa ? $c->assertHasNoErrors() : $c->assertHasErrors('peso_produccion');
    expect($p->fresh()->peso_produccion)->toEqual($pasa ? $peso : null);
})->with([
    'exacto' => [207.0, true],
    '+5 % justo' => [217.35, true],
    '-5 % justo' => [196.65, true],
    '+5.1 %' => [217.6, false],
    '-6 %' => [194.0, false],
]);

it('no termina producción sin las dos fotos', function () {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now(), 'peso_produccion' => 207]);

    Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->set('foto_producto', UploadedFile::fake()->image('producto.jpg'))
        ->call('terminar')
        ->assertHasErrors('foto_bascula');

    expect($p->fresh()->estatus)->toBe(Estatus::Cobrado);
});

it('no entrega en CEDIS sin acuse firmado ni nombre de quien recibe', function () {
    $p = pedido(Estatus::EnCedis);

    Volt::actingAs(usuario(Rol::Cedis))->test('aa.acciones.entrega-cedis', ['pedido' => $p])
        ->call('entregar')
        ->assertHasErrors(['recibe_nombre', 'foto_acuse']);

    expect($p->fresh()->estatus)->toBe(Estatus::EnCedis);
});

it('entrega en CEDIS con acuse, lo guarda en el disco privado y actualiza la venta', function () {
    $p = pedido(Estatus::EnCedis);

    Volt::actingAs(usuario(Rol::Cedis))->test('aa.acciones.entrega-cedis', ['pedido' => $p])
        ->set('recibe_nombre', 'Juan Pérez')
        ->set('foto_acuse', UploadedFile::fake()->image('acuse.jpg'))
        ->call('entregar')
        ->assertHasNoErrors();

    $p->refresh();
    expect($p->estatus)->toBe(Estatus::Entregado)
        ->and($p->venta_sync_estado)->toBe('ok')
        ->and($p->acuse_path)->toStartWith("evidencias/{$p->id}/");
    Storage::disk('local')->assertExists($p->acuse_path);
});
