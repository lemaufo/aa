<?php

use App\Enums\Estatus;
use App\Enums\Rol;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

// Cada vista con el rol que la usa y con uno que no debe verla.

beforeEach(fn () => $this->withoutVite());

it('pide sesión para entrar', function (string $ruta) {
    $this->get($ruta)->assertRedirect(route('login'));
})->with(['/inicio', '/pedidos', '/rastreo', '/usuarios']);

it('deja entrar al Inicio y a Pedidos a los roles internos', function (Rol $rol) {
    $u = usuario($rol);

    $this->actingAs($u)->get(route('inicio'))->assertOk();
    $this->actingAs($u)->get(route('pedidos.index'))->assertOk();
})->with([Rol::Admin, Rol::Cajero, Rol::Produccion, Rol::Cedis, Rol::Sucursal]);

it('manda al rol Consulta al rastreo y no a las pantallas internas', function () {
    $u = usuario(Rol::Consulta);
    $p = pedido();

    $this->actingAs($u)->get(route('inicio'))->assertRedirect(route('rastreo'));
    $this->actingAs($u)->get(route('pedidos.show', $p))->assertRedirect(route('rastreo'));
    $this->actingAs($u)->get(route('rastreo'))->assertOk();
});

it('el cajero solo ve los pedidos de su sucursal', function () {
    $centro = pedido();
    $norte = pedido(extra: ['sucursal' => 'Sucursal Norte']);
    $cajero = usuario(Rol::Cajero, 'Sucursal Centro');

    $this->actingAs($cajero)->get(route('pedidos.show', $centro))->assertOk()->assertSee($centro->folio);
    $this->actingAs($cajero)->get(route('pedidos.show', $norte))->assertForbidden();
    $this->actingAs($cajero)->get(route('pedidos.index'))->assertSee($centro->folio)->assertDontSee($norte->folio);
    $this->actingAs(usuario(Rol::Produccion))->get(route('pedidos.show', $norte))->assertOk();
});

it('no deja ver la etiqueta ni las evidencias de otra sucursal', function () {
    Storage::fake('local');
    Storage::disk('local')->put('evidencias/acuse.jpg', 'x');
    $p = pedido(Estatus::Cobrado, ['sucursal' => 'Sucursal Norte', 'acuse_path' => 'evidencias/acuse.jpg']);

    $this->actingAs(usuario(Rol::Cajero, 'Sucursal Centro'))->get(route('pedidos.etiqueta', $p))->assertForbidden();
    $this->actingAs(usuario(Rol::Cajero, 'Sucursal Centro'))->get(route('evidencia', [$p, 'acuse']))->assertForbidden();
    $this->actingAs(usuario(Rol::Cajero, 'Sucursal Norte'))->get(route('evidencia', [$p, 'acuse']))->assertOk();
    $this->actingAs(usuario(Rol::Produccion))->get(route('pedidos.etiqueta', $p))->assertOk()->assertSee($p->folio);
});

it('solo el administrador entra a Usuarios y Equipos', function (string $ruta) {
    $this->actingAs(usuario(Rol::Admin))->get(route($ruta))->assertOk();
    $this->actingAs(usuario(Rol::Cajero))->get(route($ruta))->assertForbidden();
    $this->actingAs(usuario(Rol::Cedis))->get(route($ruta))->assertForbidden();
})->with(['usuarios', 'dispositivos', 'articulos']);

it('el catálogo de artículos avisa que Microsip no está conectado en modo demo', function () {
    $this->actingAs(usuario(Rol::Admin))->get(route('articulos'))->assertOk()->assertSee('Microsip no está conectado');
});

it('el administrador da de alta y edita usuarios con su sucursal', function () {
    $admin = usuario(Rol::Admin);

    Volt::actingAs($admin)->test('aa.usuarios')
        ->call('nuevo')
        ->set('name', 'Cajera Norte')->set('username', ' Cajera.Norte ')->set('rol', 'cajero')->set('password', 'secreto123')
        ->call('guardar')
        ->assertHasErrors(['username', 'sucursal']) // punto no permitido; cajero sin sucursal
        ->set('username', 'cajera_norte')->set('sucursal', 'Sucursal Norte')
        ->call('guardar')
        ->assertHasNoErrors();

    $u = App\Models\User::where('username', 'cajera_norte')->firstOrFail();
    expect($u->rol)->toBe(Rol::Cajero)->and($u->sucursal)->toBe('Sucursal Norte')
        ->and(Illuminate\Support\Facades\Hash::check('secreto123', $u->password))->toBeTrue();

    // Editar sin contraseña conserva la actual; pasar a Producción quita la sucursal.
    Volt::actingAs($admin)->test('aa.usuarios')->call('editar', $u->id)->set('rol', 'produccion')->call('guardar')->assertHasNoErrors();
    $u->refresh();
    expect($u->rol)->toBe(Rol::Produccion)->and($u->sucursal)->toBeNull()
        ->and(Illuminate\Support\Facades\Hash::check('secreto123', $u->password))->toBeTrue();
});

it('el administrador no puede quitarse su propio rol', function () {
    $admin = usuario(Rol::Admin);

    Volt::actingAs($admin)->test('aa.usuarios')->call('editar', $admin->id)->set('rol', 'consulta')->call('guardar')->assertHasErrors('rol');
    expect($admin->fresh()->rol)->toBe(Rol::Admin);
});

it('ofrece avisar por WhatsApp cuando el pedido está listo en CEDIS o en sucursal', function (Estatus $estatus, Rol $rol, string $donde) {
    $p = pedido($estatus);

    $this->actingAs(usuario($rol))->get(route('pedidos.show', $p))
        ->assertSee('Avisar al cliente por WhatsApp')
        ->assertSee('https://wa.me/52'.$p->telefono, false)
        ->assertSee(rawurlencode($donde), false);
})->with([
    'CEDIS' => [Estatus::EnCedis, Rol::Cedis, 'recoger en CEDIS'],
    'sucursal' => [Estatus::EnSucursal, Rol::Sucursal, 'recoger en Sucursal Centro'],
]);

it('muestra a cada rol solo el panel de su paso', function () {
    $p = pedido(Estatus::Cobrado);

    $this->actingAs(usuario(Rol::Produccion))->get(route('pedidos.show', $p))->assertSee('Imprimir');
    $this->actingAs(usuario(Rol::Cedis))->get(route('pedidos.show', $p))->assertDontSee('Imprimir la etiqueta');
});

it('registrar traspaso es solo para cajero y administrador', function () {
    Volt::actingAs(usuario(Rol::Cajero))->test('aa.acciones.registrar-traspaso')->assertOk();
    Volt::actingAs(usuario(Rol::Admin))->test('aa.acciones.registrar-traspaso')->assertOk();
    Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.registrar-traspaso')->assertForbidden();
});

it('solo enseña Registrar traspaso con Microsip conectado', function () {
    $cajero = usuario(Rol::Cajero);

    $this->actingAs($cajero)->get(route('pedidos.index'))->assertDontSee('Registrar traspaso de Expertos');

    config(['aa.origen' => 'firebird']);
    $this->actingAs($cajero)->get(route('pedidos.index'))->assertSee('Registrar traspaso de Expertos');
    $this->actingAs(usuario(Rol::Produccion))->get(route('pedidos.index'))->assertDontSee('Registrar traspaso de Expertos');
});
