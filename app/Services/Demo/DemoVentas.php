<?php

namespace App\Services\Demo;

use App\Contracts\VentasMicrosip;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoVentas implements VentasMicrosip
{
    public function consultar(string $folio): ?array
    {
        $v = DB::table('demo_ventas')->where('folio', $folio)->first();

        return $v ? [
            'folio' => $v->folio,
            'estado' => $v->estado,
            'articulo' => $v->articulo,
            'lineas_ajenas' => (int) $v->lineas_ajenas,
            'entregada' => (bool) $v->entregada,
        ] : null;
    }

    public function marcarEntregada(string $folio, string $usuario, ?string $recibe): void
    {
        if (config('aa.demo_falla_microsip')) {
            throw new RuntimeException('Microsip no respondió (falla simulada).');
        }

        $filas = DB::table('demo_ventas')->where('folio', $folio)->update([
            'entregada' => true,
            'entregada_por' => $usuario,
            'entregada_at' => now(),
            'updated_at' => now(),
        ]);

        if ($filas === 0) {
            throw new RuntimeException("El ticket {$folio} no existe en Microsip.");
        }
    }
}