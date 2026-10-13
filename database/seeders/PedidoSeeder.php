<?php

namespace Database\Seeders;

use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pedidos de ejemplo repartidos en todos los estatus, con su bitácora, y los tickets de
 * "Microsip" para probar la doble validación. Recorre cada pantalla desde el primer minuto.
 *
 * Tickets para probar en caja (traspasos TSA4821 y TSA4822):
 *   V-118500  Lámina acanalada cal. 26       -> válido para TSA4821
 *   V-118501  Lámina galvanizada cal. 22     -> válido para TSA4822
 *   V-118510  acanalada 26 con productos de línea mezclados -> rechazado
 *   V-118511  Lámina pintro blanca (otro artículo)          -> rechazado
 *   V-118512  cancelado en Microsip                         -> rechazado
 *   V-118513  sin cobrar                                    -> rechazado
 */
class PedidoSeeder extends Seeder
{
    private const MATERIALES = [
        'acanalada26' => ['Lámina acanalada cal. 26', 3.450],
        'acanalada28' => ['Lámina acanalada cal. 28', 2.780],
        'galvanizada22' => ['Lámina galvanizada cal. 22', 4.100],
        'pintro26' => ['Lámina pintro blanca cal. 26', 3.550],
    ];

    public function run(): void
    {
        $usuarios = User::pluck('id', 'username');

        $this->ticketsDeMostrador();

        foreach ($this->especificaciones() as $i => $spec) {
            $this->crear($spec, $i, $usuarios);
        }
    }

    private function ticketsDeMostrador(): void
    {
        $filas = [
            ['V-118500', 'pagada', self::MATERIALES['acanalada26'][0], 0],
            ['V-118501', 'pagada', self::MATERIALES['galvanizada22'][0], 0],
            ['V-118510', 'pagada', self::MATERIALES['acanalada26'][0], 2],
            ['V-118511', 'pagada', self::MATERIALES['pintro26'][0], 0],
            ['V-118512', 'cancelada', self::MATERIALES['acanalada26'][0], 0],
            ['V-118513', 'pendiente', self::MATERIALES['acanalada26'][0], 0],
        ];

        foreach ($filas as [$folio, $estado, $articulo, $ajenas]) {
            DB::table('demo_ventas')->updateOrInsert(['folio' => $folio], [
                'estado' => $estado, 'articulo' => $articulo, 'lineas_ajenas' => $ajenas,
                'entregada' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * hasta: último paso alcanzado. dias: días que faltan para la fecha prometida (negativo = vencido).
     *
     * @return list<array<string, mixed>>
     */
    private function especificaciones(): array
    {
        return [
            ['folio' => 'TSA4821', 'cliente' => 'Constructora Los Pinos', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[6, 8]], 'forma' => 'sucursal', 'hasta' => 'expertos', 'horas' => 2, 'dias' => 6,
                'nota' => 'Constructora Los Pinos. 6 pzas de 8 m. Entrega en sucursal, avisar al Ing. Ramírez.'],
            ['folio' => 'TSA4822', 'cliente' => 'Herrería Hernández', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[10, 3]], 'forma' => 'cedis', 'hasta' => 'expertos', 'horas' => 1, 'dias' => 1,
                'nota' => 'Hdez. 10 pzas de 3 m. Recoge en CEDIS mañana, trae su ticket.'],
            ['folio' => 'TSA4815', 'cliente' => 'Ferretería El Tornillo', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada28', 'cortes' => [[12, 3]], 'forma' => 'sucursal', 'hasta' => 'cobrado', 'horas' => 5, 'dias' => 0,
                'nota' => 'El Tornillo. 12 pzas de 3 m, dobles en los extremos.'],
            // 9 medidas distintas: sirve para probar la etiqueta compacta.
            ['folio' => 'TSA4812', 'cliente' => 'Naves y Techumbres del Sureste S.A. de C.V.', 'sucursal' => 'Sucursal Norte', 'mat' => 'acanalada26',
                'cortes' => [[4, 2.5], [6, 3], [2, 3.6], [8, 4], [3, 4.5], [5, 5], [2, 5.5], [6, 6], [4, 7]], 'forma' => 'cedis', 'hasta' => 'cobrado', 'horas' => 4, 'dias' => -1,
                'nota' => 'Nave industrial, 9 medidas distintas. Marcar cada paquete por medida.'],
            ['folio' => 'TSA4809', 'cliente' => 'Agroindustrias del Sur', 'sucursal' => 'Sucursal Norte', 'mat' => 'acanalada26', 'cortes' => [[4, 6], [2, 9]], 'forma' => 'cliente', 'hasta' => 'impresa', 'horas' => 9, 'dias' => 2,
                'nota' => 'Agro del Sur. 4 pzas de 6 m y 2 de 9 m. Entrega en bodega del cliente.'],
            ['folio' => 'TSA4801', 'cliente' => 'Taller Mecánico Ruiz', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[8, 2.5]], 'forma' => 'sucursal', 'hasta' => 'producido', 'horas' => 14, 'dias' => 3,
                'nota' => 'Taller Ruiz. 8 pzas de 2.5 m.'],
            ['folio' => 'TSA4796', 'cliente' => 'Bodegas Maya', 'sucursal' => 'Sucursal Norte', 'mat' => 'pintro26', 'cortes' => [[5, 6]], 'forma' => 'cedis', 'hasta' => 'cedis', 'horas' => 20, 'dias' => 1,
                'nota' => 'Bodegas Maya. 5 pzas de 6 m. Recoge en CEDIS.'],
            ['folio' => 'TSA4790', 'cliente' => 'Constructora Alpha', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[3, 12]], 'forma' => 'cliente', 'hasta' => 'validado', 'horas' => 26, 'dias' => 4,
                'nota' => 'Alpha. 3 pzas de 12 m. Entrega en obra, preguntar por el residente.'],
            ['folio' => 'TSA4785', 'cliente' => 'Cocina Industrial Sol', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[6, 4]], 'forma' => 'cedis', 'hasta' => 'despacho', 'horas' => 30, 'dias' => 0,
                'nota' => 'Cocina Sol. 6 pzas de 4 m. Recoge en CEDIS.'],
            ['folio' => 'TSA4780', 'cliente' => 'Naves Industriales GP', 'sucursal' => 'Sucursal Norte', 'mat' => 'acanalada28', 'cortes' => [[7, 6]], 'forma' => 'cliente', 'hasta' => 'despacho', 'horas' => 34, 'dias' => 2,
                'nota' => 'Naves GP. 7 pzas de 6 m. Entrega en nave 3.'],
            ['folio' => 'TSA4774', 'cliente' => 'Rancho San Isidro', 'sucursal' => 'Sucursal Centro', 'mat' => 'pintro26', 'cortes' => [[9, 3]], 'forma' => 'sucursal', 'hasta' => 'despacho', 'horas' => 40, 'dias' => 5,
                'nota' => 'Rancho San Isidro. 9 pzas de 3 m.'],
            ['folio' => 'TSA4770', 'cliente' => 'Techos y Más', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[10, 3.6]], 'forma' => 'sucursal', 'hasta' => 'recibe', 'horas' => 46, 'dias' => 1,
                'nota' => 'Techos y Más. 10 pzas de 3.6 m.'],
            ['folio' => 'TSA4763', 'cliente' => 'Distribuidora Pérez', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada28', 'cortes' => [[5, 5]], 'forma' => 'cedis', 'hasta' => 'entrega_cedis', 'horas' => 52, 'dias' => -2,
                'nota' => 'Distribuidora Pérez. 5 pzas de 5 m.', 'sync_error' => true],
            ['folio' => 'TSA4750', 'cliente' => 'Corporativo Delta', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[12, 2]], 'forma' => 'sucursal', 'hasta' => 'cierre', 'horas' => 70, 'dias' => -3,
                'nota' => 'Delta. 12 pzas de 2 m.'],
            ['folio' => 'TSA4744', 'cliente' => 'Hermanos Cruz', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[4, 4]], 'forma' => 'sucursal', 'hasta' => 'cobrado', 'horas' => 60, 'dias' => 5,
                'cancelado' => 'Traspaso cancelado en Expertos',
                'nota' => 'Hnos. Cruz. 4 pzas de 4 m.'],
        ];
    }

    private function crear(array $spec, int $indice, $usuarios): void
    {
        [$material, $pesoPorMetro] = self::MATERIALES[$spec['mat']];
        $cortes = array_map(fn ($c) => ['piezas' => $c[0], 'medida' => (float) $c[1]], $spec['cortes']);
        $metros = round(collect($cortes)->sum(fn ($c) => $c['piezas'] * $c['medida']), 2);
        $teorico = round($metros * $pesoPorMetro, 2);
        $forma = $spec['forma'];
        $venta = 'V-'.(118200 + $indice * 7);
        $folio = $spec['folio'];

        // Secuencia de pasos según la forma de entrega.
        $secuencia = ['expertos', 'cobrado', 'impresa', 'pesada', 'producido', 'cedis', 'validado', 'despacho'];
        $secuencia = array_merge($secuencia, $forma === 'sucursal' ? ['recibe', 'entrega_suc'] : ['entrega_cedis'], ['cierre']);
        $tope = array_search($spec['hasta'], $secuencia, true);
        $alcanzados = array_slice($secuencia, 0, $tope + 1);
        $alcanza = fn (string $paso) => in_array($paso, $alcanzados, true);

        $despacho = match ($forma) {
            'cedis' => [Estatus::EnCedis, 'Listo en CEDIS para entrega'],
            'cliente' => [Estatus::EnviadoACliente, 'Enviado con repartidor'],
            default => [Estatus::EnviadoASucursal, 'Enviado a '.$spec['sucursal']],
        };
        $entregaRol = $forma === 'sucursal' ? 'sucursal' : 'cedis';
        $pesoProd = round($teorico * 0.992, 2);

        $pasos = [
            'expertos' => [Estatus::EnExpertos, 'Traspaso creado en Expertos', number_format($metros, 2).' m lineales', 'cajero'],
            'cobrado' => [Estatus::Cobrado, 'Traspaso ligado a la venta', "Ticket {$venta} validado contra el traspaso", 'cajero'],
            'impresa' => [Estatus::Cobrado, 'Etiqueta impresa', 'Orden de producción impresa: '.collect($cortes)->sum('piezas').' piezas en '.count($cortes).' medidas', 'produccion'],
            'pesada' => [Estatus::Cobrado, 'Peso de producción validado', number_format($pesoProd, 2).' kg, -0.80 % contra el teórico. Rollo o lote: R-'.(24800 + $indice), 'produccion'],
            'producido' => [Estatus::Producido, 'Producción terminada con evidencia', 'Fotos del producto con etiqueta y de la báscula', 'produccion'],
            'cedis' => [Estatus::EnviadoACedis, 'Despachado a CEDIS', null, 'produccion'],
            'validado' => [Estatus::Validado, 'Recibido y validado en CEDIS', null, 'cedis'],
            'despacho' => [$despacho[0], $despacho[1], null, 'cedis'],
            'entrega_cedis' => [Estatus::Entregado, 'Entregado al cliente', 'Acuse con foto', 'cedis'],
            'recibe' => [Estatus::EnSucursal, 'Recibido en sucursal', null, 'sucursal'],
            'entrega_suc' => [Estatus::Entregado, 'Entregado al cliente', 'Acuse con foto', 'sucursal'],
            'cierre' => [Estatus::Entregado, 'Venta actualizada en Microsip', "Venta {$venta} marcada como entregada", $entregaRol],
        ];

        $eventos = array_map(fn ($id) => $pasos[$id], $alcanzados);
        if (! empty($spec['sync_error'])) {
            $eventos[] = [Estatus::Entregado, 'Error al actualizar Microsip', 'Microsip no respondió (falla simulada).', $entregaRol];
        }
        if (isset($spec['cancelado'])) {
            $eventos[] = [Estatus::Cancelado, $spec['cancelado'], 'Detectado automáticamente. Si ya hay material producido o en ruta, revisar con Producción y CEDIS.', null];
        }

        // Fechas: los eventos se reparten entre el inicio y hoy.
        $inicio = now()->subHours($spec['horas']);
        $salto = intdiv($spec['horas'] * 60, max(count($eventos), 1));
        $marca = fn (int $i) => $inicio->copy()->addMinutes($i * $salto);

        $atributos = [
            'folio' => $folio,
            'estatus' => end($eventos)[0],
            'sucursal' => $spec['sucursal'],
            'cliente' => $spec['cliente'],
            'material' => $material,
            'metros_traspaso' => $metros,
            'peso_por_metro' => $pesoPorMetro,
            'instrucciones' => $spec['nota'],
            'created_at' => $inicio,
            'updated_at' => $marca(count($eventos) - 1),
        ];

        if ($alcanza('cobrado')) {
            $atributos += [
                'folio_venta' => $venta,
                'ticket_validado_at' => $marca(1),
                'forma_entrega' => $forma,
                'fecha_prometida' => now()->addDays($spec['dias'])->toDateString(),
                'cortes_solicitados' => $cortes,
                'telefono' => '961'.str_pad((string) (1000 + $indice), 7, '0', STR_PAD_LEFT),
                'direccion_entrega' => $forma === 'cliente' ? 'Carretera a la bodega, km 4. Portón azul, junto a la gasolinera.' : null,
                'gps_link' => $forma === 'cliente' ? 'https://maps.google.com/?q=16.7569,-93.1292' : null,
            ];
            if ($forma === 'cliente') {
                $atributos['recibe_nombre'] = 'Residente de '.$spec['cliente'];
            }

            DB::table('demo_ventas')->updateOrInsert(['folio' => $venta], [
                'estado' => 'pagada', 'articulo' => $material, 'lineas_ajenas' => 0,
                'entregada' => $alcanza('cierre'), 'entregada_por' => $alcanza('cierre') ? 'Sistema' : null,
                'entregada_at' => $alcanza('cierre') ? $marca(count($eventos) - 1) : null,
                'created_at' => $inicio, 'updated_at' => now(),
            ]);
        }
        if ($alcanza('impresa')) {
            $atributos['etiqueta_impresa_at'] = $marca(array_search('impresa', $alcanzados, true));
        }
        if ($alcanza('pesada')) {
            $atributos['peso_produccion'] = $pesoProd;
            $atributos['lote_rollo'] = 'R-'.(24800 + $indice);
        }
        if ($alcanza('producido')) {
            $atributos['peso_teorico'] = $teorico;
            $atributos['evidencia_path'] = $this->foto($folio, 'producto', 'Producto terminado con etiqueta');
            $atributos['evidencia_bascula_path'] = $this->foto($folio, 'bascula', 'Báscula de producción');
        }
        if ($alcanza('validado')) {
            $atributos['ubicacion_cedis'] = 'Zona '.chr(65 + $indice % 4).', portón '.(1 + $indice % 3);
        }
        if ($alcanza('despacho') && $forma === 'cliente') {
            $atributos += ['camion' => 'Camión 07, placas RK-4521-A', 'repartidor' => 'Jorge L.'];
        }
        if ($alcanza('entrega_cedis') || $alcanza('entrega_suc')) {
            $atributos['recibe_nombre'] = 'Encargado de '.$spec['cliente'];
            $atributos['acuse_path'] = $this->foto($folio, 'acuse', 'Acuse de entrega firmado');
            $atributos['venta_sync_estado'] = ! empty($spec['sync_error']) ? 'error' : 'ok';
            $atributos['venta_sync_at'] = $marca(count($eventos) - 1);
            if (! empty($spec['sync_error'])) {
                $atributos['venta_sync_error'] = 'Microsip no respondió (falla simulada).';
            }
        }
        if (isset($spec['cancelado'])) {
            $atributos['motivo_cancelacion'] = $spec['cancelado'];
            $atributos['cancelado_por_expertos'] = true;
        }

        $pedido = Pedido::create($atributos);

        DB::table('demo_traspasos')->updateOrInsert(['folio' => $folio], [
            'cancelado' => isset($spec['cancelado']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($eventos as $i => [$estatus, $accion, $detalle, $usuario]) {
            $pedido->eventos()->create([
                'user_id' => $usuario ? ($usuarios[$usuario] ?? null) : null,
                'estatus' => $estatus->value,
                'accion' => $accion,
                'detalle' => $detalle,
                'created_at' => $marca($i),
                'updated_at' => $marca($i),
            ]);
        }
    }

    /** Imagen de relleno en el disco privado (en producción son las fotos reales). */
    private function foto(string $folio, string $tipo, string $texto): string
    {
        $ruta = "evidencias/demo/{$folio}-{$tipo}.svg";

        Storage::disk('local')->put($ruta, '<svg xmlns="http://www.w3.org/2000/svg" width="640" height="420" viewBox="0 0 640 420">'
            .'<rect width="640" height="420" fill="#e5e7eb"/><rect x="24" y="24" width="592" height="372" fill="none" stroke="#9ca3af" stroke-width="3" stroke-dasharray="10 8"/>'
            .'<text x="320" y="190" text-anchor="middle" font-family="Arial" font-size="30" font-weight="700" fill="#374151">'.e($texto).'</text>'
            .'<text x="320" y="236" text-anchor="middle" font-family="Arial" font-size="24" fill="#6b7280">'.e($folio).' (imagen de ejemplo)</text></svg>');

        return $ruta;
    }
}