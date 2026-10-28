<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/** Usuario con un rol. Cajero y Sucursal quedan en 'Sucursal Centro' salvo que se indique otra. */
function usuario(App\Enums\Rol $rol, ?string $sucursal = null): App\Models\User
{
    static $n = 0;
    $n++;

    return App\Models\User::create([
        'name' => $rol->label().' '.$n,
        'username' => $rol->value.$n,
        'email' => $rol->value.$n.'@prueba.test',
        'rol' => $rol,
        'sucursal' => $sucursal ?? ($rol->limitadoASuSucursal() ? 'Sucursal Centro' : null),
        'password' => 'secreto123',
    ]);
}

/**
 * Pedido listo para el estatus pedido: 10 piezas de 6 m de un artículo de 3.45 kg/m (teórico 207 kg).
 * Desde COBRADO ya trae ticket V-1 ligado y su venta demo cobrada.
 */
function pedido(App\Enums\Estatus $estatus = App\Enums\Estatus::EnExpertos, array $extra = []): App\Models\Pedido
{
    static $n = 0;
    $n++;

    $base = [
        'folio' => 'TSA'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
        'estatus' => $estatus,
        'sucursal' => 'Sucursal Centro',
        'cliente' => 'Cliente de prueba',
        'material' => 'Lámina acanalada cal. 26',
        'metros_traspaso' => 60,
        'peso_por_metro' => 3.45,
    ];

    if ($estatus !== App\Enums\Estatus::EnExpertos && $estatus !== App\Enums\Estatus::Cancelado) {
        $base += [
            'folio_venta' => 'V-'.$n,
            'ticket_validado_at' => now(),
            'forma_entrega' => App\Enums\FormaEntrega::Cedis,
            'fecha_prometida' => now()->addDays(2)->toDateString(),
            'cortes_solicitados' => [['piezas' => 10, 'medida' => 6]],
            'telefono' => '9611234567',
        ];
        venta('V-'.$n);
    }

    return App\Models\Pedido::create(array_merge($base, $extra));
}

/** Ticket en la tabla demo de Microsip. */
function venta(string $folio, string $estado = 'pagada', string $articulo = 'Lámina acanalada cal. 26', int $ajenas = 0): void
{
    Illuminate\Support\Facades\DB::table('demo_ventas')->updateOrInsert(['folio' => $folio], [
        'estado' => $estado, 'articulo' => $articulo, 'lineas_ajenas' => $ajenas,
        'entregada' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
