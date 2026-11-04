<?php

use App\Enums\Estatus;
use App\Enums\Rol;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

// Evidencias: se guardan comprimidas en el disco privado y se purgan por la fecha de la toma.

beforeEach(fn () => Storage::fake('local'));

it('guarda las fotos de producción reducidas, en JPEG y en la carpeta privada del pedido', function () {
    $p = pedido(Estatus::Cobrado, ['etiqueta_impresa_at' => now(), 'peso_produccion' => 207]);

    Volt::actingAs(usuario(Rol::Produccion))->test('aa.acciones.produccion', ['pedido' => $p])
        ->set('foto_producto', UploadedFile::fake()->image('producto.png', 2000, 1500))
        ->set('foto_bascula', UploadedFile::fake()->image('bascula.jpg', 800, 600))
        ->call('terminar')
        ->assertHasNoErrors();

    $p->refresh();
    expect($p->evidencia_path)->toStartWith("evidencias/{$p->id}/producto-")->toEndWith('.jpg')
        ->and($p->evidencia_bascula_path)->toStartWith("evidencias/{$p->id}/bascula-");

    $medida = function (string $ruta): array {
        $contenido = Storage::disk('local')->get($ruta);
        $img = imagecreatefromstring($contenido);

        return [substr($contenido, 0, 3) === "\xFF\xD8\xFF", imagesx($img), imagesy($img)];
    };

    expect($medida($p->evidencia_path))->toBe([true, 1600, 1200])
        // Una foto que ya es chica no se agranda.
        ->and($medida($p->evidencia_bascula_path))->toBe([true, 800, 600]);
});

it('purga las evidencias más viejas que la retención y deja el historial del pedido', function () {
    $vieja = 'evidencias/1/acuse-'.now()->subYears(2)->subDay()->format('Ymd-His').'-abc123.jpg';
    $nueva = 'evidencias/1/producto-'.now()->subYear()->format('Ymd-His').'-abc123.jpg';
    Storage::disk('local')->put($vieja, 'x');
    Storage::disk('local')->put($nueva, 'x');
    $p = pedido(Estatus::Entregado, ['acuse_path' => $vieja, 'evidencia_path' => $nueva]);

    $this->artisan('aa:purgar-evidencias --simular')->assertSuccessful();
    Storage::disk('local')->assertExists($vieja);

    $this->artisan('aa:purgar-evidencias')->assertSuccessful();

    Storage::disk('local')->assertMissing($vieja);
    Storage::disk('local')->assertExists($nueva);
    expect($p->fresh()->acuse_path)->toBeNull()
        ->and($p->fresh()->evidencia_path)->toBe($nueva);
});

it('muestra los errores de validación en español y con el nombre del campo', function () {
    $c = Volt::actingAs(usuario(Rol::Cedis))->test('aa.acciones.entrega-cedis', ['pedido' => pedido(Estatus::EnCedis)])
        ->call('entregar');

    expect($c->errors()->first('recibe_nombre'))->toBe('Falta el nombre de quien recibe.')
        ->and($c->errors()->first('foto_acuse'))->toBe('Falta la foto del acuse.');
});

it('tiene en español todas las reglas de validación de Laravel', function () {
    $ingles = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    $espanol = require lang_path('es/validation.php');

    expect(array_diff_key($ingles, $espanol))->toBe([]);
});
