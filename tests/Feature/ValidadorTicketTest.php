<?php

use App\Enums\Estatus;
use App\Services\ValidadorTicket;
use Illuminate\Support\Facades\DB;

// Doble validación al ligar: ticket de Microsip contra traspaso de Expertos.

beforeEach(fn () => $this->p = pedido());

it('acepta un ticket cobrado del mismo artículo y sin productos de línea', function () {
    venta('V-100');

    expect(app(ValidadorTicket::class)->validar($this->p, ' V-100 '))->toBe([]);
});

it('compara el artículo sin importar mayúsculas ni espacios', function () {
    venta('V-101', articulo: '  LÁMINA   acanalada CAL. 26 ');

    expect(app(ValidadorTicket::class)->validar($this->p, 'V-101'))->toBe([]);
});

it('rechaza cada caso con su motivo', function (string $folio, array $venta, string $mensaje) {
    if ($venta) {
        venta($folio, ...$venta);
    }

    expect(implode(' ', app(ValidadorTicket::class)->validar($this->p, $folio)))->toContain($mensaje);
})->with([
    'vacío' => ['', [], 'Captura el folio'],
    'no existe' => ['V-404', [], 'no existe en Microsip'],
    'cancelado' => ['V-201', ['cancelada'], 'cancelado en Microsip'],
    'sin cobrar' => ['V-202', ['pendiente'], 'todavía no está cobrado'],
    'otro artículo' => ['V-203', ['pagada', 'Lámina pintro blanca'], 'Debe ser el mismo artículo'],
    'productos de línea' => ['V-204', ['pagada', 'Lámina acanalada cal. 26', 2], 'ticket independiente'],
]);

it('rechaza un ticket que ya está ligado a otro traspaso', function () {
    $otro = pedido(Estatus::Cobrado);

    expect(implode(' ', app(ValidadorTicket::class)->validar($this->p, $otro->folio_venta)))
        ->toContain("ya está ligado al traspaso {$otro->folio}");
});

it('rechaza si el traspaso ya se canceló en Expertos', function () {
    venta('V-300');
    DB::table('demo_traspasos')->insert(['folio' => $this->p->folio, 'cancelado' => true, 'created_at' => now(), 'updated_at' => now()]);

    expect(app(ValidadorTicket::class)->validar($this->p, 'V-300'))->toBe(["El traspaso {$this->p->folio} está cancelado en Expertos."]);
});

it('exige que el ticket venda la misma cantidad que el traspaso', function (float $vendida, bool $valido) {
    app()->instance(App\Contracts\VentasMicrosip::class, new class($vendida) implements App\Contracts\VentasMicrosip
    {
        public function __construct(private float $vendida) {}

        public function consultar(string $folio): ?array
        {
            return ['folio' => $folio, 'estado' => 'pagada', 'articulo' => 'Lámina acanalada cal. 26',
                'cantidad' => $this->vendida, 'lineas_ajenas' => 0, 'entregada' => false];
        }

        public function marcarEntregada(string $folio, string $usuario, ?string $recibe): void {}
    });

    $errores = app(ValidadorTicket::class)->validar($this->p, 'A00000005');

    $valido
        ? expect($errores)->toBe([])
        : expect($errores)->toBe(["El ticket vende {$vendida} y el traspaso es de 60. Deben ser la misma cantidad."]);
})->with([
    'igual' => [60.0, true],
    'de menos' => [55.0, false],
    'de más' => [61.5, false],
]);
