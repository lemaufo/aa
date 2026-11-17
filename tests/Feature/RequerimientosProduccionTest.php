<?php

use App\Enums\Estatus;
use App\Enums\Rol;
use App\Support\Etiqueta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

// Revisión del 6 de noviembre: báscula opcional, peso fuera de rango y reimpresión con supervisor,
// peso teórico sin catálogo y diseño de referencia para el canalón.

beforeEach(fn () => Storage::fake('local'));

it('termina producción solo con la foto del producto: la de la báscula es opcional', function () {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now(), 'peso_produccion' => 207]);

    Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->set('foto_producto', UploadedFile::fake()->image('producto.jpg'))
        ->call('terminar')
        ->assertHasNoErrors();

    $p->refresh();
    expect($p->estatus)->toBe(Estatus::Producido)
        ->and($p->evidencia_path)->not->toBeNull()
        ->and($p->evidencia_bascula_path)->toBeNull();
});

it('sin peso por metro en el catálogo no imprime la etiqueta ni da por buena la báscula', function () {
    $p = pedido(Estatus::Cobrado, ['peso_por_metro' => 0]);

    expect($p->dentroDeTolerancia(123.0))->toBeFalse();

    Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->assertSee('no tiene peso por metro')
        ->call('imprimir');

    expect($p->fresh()->etiqueta_impresa_at)->toBeNull();
});

it('un peso fuera de tolerancia pasa solo con nota y la contraseña de un supervisor', function () {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now()]); // teórico 207 kg
    $admin = usuario(Rol::Admin);
    $panel = fn () => Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->set('peso_produccion', '240');

    // Sin autorización se bloquea, como antes.
    $panel()->call('pesar')->assertHasErrors('peso_produccion');

    // Contraseña mala, o un usuario que no autoriza: no pasa.
    $panel()->set(['peso_nota' => 'El rollo viene más grueso', 'aut_usuario' => $admin->username, 'aut_password' => 'mala'])
        ->call('autorizarPeso')->assertHasErrors('aut_password');
    $panel()->set(['peso_nota' => 'El rollo viene más grueso', 'aut_usuario' => usuario(Rol::Cajero)->username, 'aut_password' => 'secreto123'])
        ->call('autorizarPeso')->assertHasErrors('aut_password');

    // La nota es obligatoria.
    $panel()->set(['aut_usuario' => $admin->username, 'aut_password' => 'secreto123'])
        ->call('autorizarPeso')->assertHasErrors('peso_nota');

    expect($p->fresh()->peso_produccion)->toBeNull();

    $panel()->set(['peso_nota' => 'El rollo viene más grueso, se revisaron piezas', 'aut_usuario' => $admin->username, 'aut_password' => 'secreto123'])
        ->call('autorizarPeso')->assertHasNoErrors();

    $p->refresh();
    expect($p->peso_produccion)->toEqual(240.0)
        ->and($p->peso_fuera_tolerancia)->toBeTrue()
        ->and($p->peso_autorizado_por)->toBe($admin->name)
        ->and($p->peso_nota)->toBe('El rollo viene más grueso, se revisaron piezas')
        ->and($p->eventos()->latest('id')->first()->accion)->toBe('Peso fuera de tolerancia autorizado');
});

it('almacén y producción imprimen el diseño en tamaño carta sin autorización; la caja no', function () {
    Storage::disk('local')->put('evidencias/1/diseno-20261117-100000-abc123.jpg', 'x');
    $p = pedido(Estatus::Cobrado, ['diseno_path' => 'evidencias/1/diseno-20261117-100000-abc123.jpg']);

    foreach ([Rol::Produccion, Rol::Cedis, Rol::Admin] as $rol) {
        Volt::actingAs(usuario($rol))->test('aa.diseno', ['pedido' => $p])
            ->assertSee('Imprimir (carta)')
            ->assertSee('id="diseno-carta-'.$p->id.'"', false)
            ->assertSee('size: letter', false);
    }

    Volt::actingAs(usuario(Rol::Cajero))->test('aa.diseno', ['pedido' => $p])
        ->assertSee('Ver diseño')
        ->assertDontSee('Imprimir (carta)')
        ->assertDontSee('id="diseno-carta-'.$p->id.'"', false);
});

it('producción no reimprime la etiqueta sin la autorización de un supervisor', function () {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now()]);
    $operador = usuario(Rol::Produccion);

    $this->actingAs($operador)->get(route('pedidos.etiqueta', $p))->assertForbidden();
    $this->actingAs(usuario(Rol::Admin))->get(route('pedidos.etiqueta', $p))->assertOk();

    Volt::actingAs($operador)->test('aa.acciones.produccion', ['pedido' => $p])
        ->assertDontSee('href="'.route('pedidos.etiqueta', [$p, 'auto' => 1]).'"', false)
        ->call('pedirAutorizacion', 'reimpresion')
        ->set(['aut_usuario' => usuario(Rol::Admin)->username, 'aut_password' => 'secreto123'])
        ->call('autorizarReimpresion')
        ->assertHasNoErrors()
        ->assertSet('reimpresionLista', true);

    expect(Etiqueta::puedeAbrir($operador, $p))->toBeTrue()
        ->and($p->eventos()->latest('id')->first()->accion)->toBe('Reimpresión de etiqueta autorizada');

    // La autorización dura 1 minuto: a los 61 segundos ya no abre.
    $this->travel(61)->seconds();
    expect(Etiqueta::puedeAbrir($operador, $p))->toBeFalse();
    $this->actingAs($operador)->get(route('pedidos.etiqueta', $p))->assertForbidden();
});

it('la etiqueta toma el tamaño configurado y lo acepta en in o mm', function () {
    $p = pedido(Estatus::Cobrado);
    $admin = usuario(Rol::Admin);

    $this->actingAs($admin)->get(route('pedidos.etiqueta', $p))->assertOk()
        ->assertSee('data-ancho="101.6" data-alto="152.4"', false)
        ->assertSee('size: 101.6mm 152.4mm', false);

    config(['aa.etiqueta' => '100x50mm']);
    $this->actingAs($admin)->get(route('pedidos.etiqueta', $p))->assertOk()
        ->assertSee('data-ancho="100" data-alto="50"', false)
        ->assertSee('size: 100mm 50mm', false);

    // Un valor que no se entiende cae en 4×6 in.
    config(['aa.etiqueta' => 'grande']);
    $this->actingAs($admin)->get(route('pedidos.etiqueta', $p))->assertSee('data-ancho="101.6" data-alto="152.4"', false);
});

it('la caja adjunta el diseño de referencia y producción lo ve arriba de la bitácora', function () {
    $p = pedido(Estatus::Cobrado);

    Volt::actingAs(usuario(Rol::Cajero))->test('aa.diseno', ['pedido' => $p])
        ->set('diseno', UploadedFile::fake()->image('canalon.png', 3000, 2000))
        ->assertHasNoErrors();

    $p->refresh();
    expect($p->diseno_path)->toStartWith("evidencias/{$p->id}/diseno-")
        ->and($p->eventos()->latest('id')->first()->accion)->toBe('Diseño de referencia adjuntado');
    Storage::disk('local')->assertExists($p->diseno_path);

    // Se guarda con más resolución que las fotos, para que el dibujo se lea al imprimirlo.
    expect(imagesx(imagecreatefromstring(Storage::disk('local')->get($p->diseno_path))))->toBe(2400);

    // Producción lo ve pero no lo cambia.
    Volt::actingAs(usuario(Rol::Produccion))->test('aa.diseno', ['pedido' => $p])
        ->assertSee('Diseño de referencia')
        ->assertSee(route('evidencia', [$p, 'diseno']), false)
        ->assertDontSee('Cambiar el diseño');

    // La caja lo puede quitar: se borra el archivo y queda en la bitácora.
    $ruta = $p->diseno_path;
    Volt::actingAs(usuario(Rol::Cajero))->test('aa.diseno', ['pedido' => $p])->call('eliminar');
    expect($p->fresh()->diseno_path)->toBeNull();
    Storage::disk('local')->assertMissing($ruta);

    // Sin diseño y sin poder adjuntarlo, la hoja no aparece.
    Volt::actingAs(usuario(Rol::Produccion))->test('aa.diseno', ['pedido' => pedido(Estatus::Cobrado)])
        ->assertDontSee('Diseño de referencia');
});
