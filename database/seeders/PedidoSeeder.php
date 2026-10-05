<?php

namespace Database\Seeders;

use App\Enums\Estatus;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Pedidos de ejemplo repartidos en todos los estatus, con su bitácora,
 * para poder recorrer cada pantalla desde el primer minuto.
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

        foreach ($this->especificaciones() as $i => $spec) {
            $this->crear($spec, $i, $usuarios);
        }
    }

    /** @return list<array<string, mixed>> */
    private function especificaciones(): array
    {
        return [
            ['folio' => 'TR0004821', 'cliente' => 'Constructora Los Pinos', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[6, 8]], 'forma' => 'sucursal', 'hasta' => 'expertos', 'horas' => 2,
                'nota' => 'Constructora Los Pinos. 6 pzas de 8 m. Entrega en sucursal, avisar al Ing. Ramírez.'],
            ['folio' => 'TR0004822', 'cliente' => 'Herrería Hernández', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[10, 3]], 'forma' => 'cedis', 'hasta' => 'expertos', 'horas' => 1,
                'nota' => 'Hdez. 10 pzas de 3 m. Recoge en CEDIS mañana, trae su ticket.'],
            ['folio' => 'TR0004815', 'cliente' => 'Ferretería El Tornillo', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada28', 'cortes' => [[12, 3]], 'forma' => 'sucursal', 'hasta' => 'cobrado', 'horas' => 5,
                'nota' => 'El Tornillo. 12 pzas de 3 m, dobles en los extremos.'],
            ['folio' => 'TR0004809', 'cliente' => 'Agroindustrias del Sur', 'sucursal' => 'Sucursal Norte', 'mat' => 'acanalada26', 'cortes' => [[4, 6], [2, 9]], 'forma' => 'cliente', 'hasta' => 'captura', 'horas' => 9,
                'nota' => 'Agro del Sur. 4 pzas de 6 m y 2 de 9 m. Entrega en bodega del cliente.'],
            ['folio' => 'TR0004801', 'cliente' => 'Taller Mecánico Ruiz', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[8, 2.5]], 'forma' => 'sucursal', 'hasta' => 'producido', 'horas' => 14,
                'nota' => 'Taller Ruiz. 8 pzas de 2.5 m.'],
            ['folio' => 'TR0004796', 'cliente' => 'Bodegas Maya', 'sucursal' => 'Sucursal Norte', 'mat' => 'pintro26', 'cortes' => [[5, 6]], 'forma' => 'cedis', 'hasta' => 'cedis', 'horas' => 20,
                'nota' => 'Bodegas Maya. 5 pzas de 6 m. Recoge en CEDIS.'],
            ['folio' => 'TR0004790', 'cliente' => 'Constructora Alpha', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[3, 12]], 'forma' => 'cliente', 'hasta' => 'validado', 'horas' => 26,
                'nota' => 'Alpha. 3 pzas de 12 m. Entrega en obra, preguntar por el residente.'],
            ['folio' => 'TR0004785', 'cliente' => 'Cocina Industrial Sol', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[6, 4]], 'forma' => 'cedis', 'hasta' => 'despacho', 'horas' => 30,
                'nota' => 'Cocina Sol. 6 pzas de 4 m. Recoge en CEDIS.'],
            ['folio' => 'TR0004780', 'cliente' => 'Naves Industriales GP', 'sucursal' => 'Sucursal Norte', 'mat' => 'acanalada28', 'cortes' => [[7, 6]], 'forma' => 'cliente', 'hasta' => 'despacho', 'horas' => 34,
                'nota' => 'Naves GP. 7 pzas de 6 m. Entrega en nave 3.'],
            ['folio' => 'TR0004774', 'cliente' => 'Rancho San Isidro', 'sucursal' => 'Sucursal Centro', 'mat' => 'pintro26', 'cortes' => [[9, 3]], 'forma' => 'sucursal', 'hasta' => 'despacho', 'horas' => 40,
                'nota' => 'Rancho San Isidro. 9 pzas de 3 m.'],
            ['folio' => 'TR0004770', 'cliente' => 'Techos y Más', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[10, 3.6]], 'forma' => 'sucursal', 'hasta' => 'recibe', 'horas' => 46,
                'nota' => 'Techos y Más. 10 pzas de 3.6 m.'],
            ['folio' => 'TR0004763', 'cliente' => 'Distribuidora Pérez', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada28', 'cortes' => [[5, 5]], 'forma' => 'cedis', 'hasta' => 'entrega_cedis', 'horas' => 52,
                'nota' => 'Distribuidora Pérez. 5 pzas de 5 m.'],
            ['folio' => 'TR0004750', 'cliente' => 'Corporativo Delta', 'sucursal' => 'Sucursal Centro', 'mat' => 'galvanizada22', 'cortes' => [[12, 2]], 'forma' => 'sucursal', 'hasta' => 'cierre', 'horas' => 70,
                'nota' => 'Delta. 12 pzas de 2 m.'],
            ['folio' => 'TR0004744', 'cliente' => 'Hermanos Cruz', 'sucursal' => 'Sucursal Centro', 'mat' => 'acanalada26', 'cortes' => [[4, 4]], 'forma' => 'sucursal', 'hasta' => 'cobrado', 'horas' => 60,
                'cancelado' => 'El cliente cambió de proveedor antes de producir.',
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

        // Secuencia de pasos según la forma de entrega.
        $secuencia = ['expertos', 'cobrado', 'captura', 'producido', 'cedis', 'validado', 'despacho'];
        $secuencia = array_merge($secuencia, $forma === 'sucursal' ? ['recibe', 'entrega_suc'] : ['entrega_cedis'], ['cierre']);
        $tope = array_search($spec['hasta'], $secuencia, true);
        $alcanzados = array_slice($secuencia, 0, $tope + 1);
        $alcanza = fn (string $paso) => in_array($paso, $alcanzados, true);

        $despacho = match ($forma) {
            'cedis' => [Estatus::EnCedis, 'Listo en CEDIS para entrega'],
            'cliente' => [Estatus::EnviadoACliente, 'Enviado con repartidor'],
            default => [Estatus::EnviadoASucursal, 'Enviado a '.$spec['sucursal']],
        };

        $pasos = [
            'expertos' => [Estatus::EnExpertos, 'Traspaso creado en Expertos', number_format($metros, 2).' m lineales', 'cajero'],
            'cobrado' => [Estatus::Cobrado, 'Traspaso ligado a la venta', "Venta {$venta}", 'cajero'],
            'captura' => [Estatus::Cobrado, 'Captura de producción validada', 'Piezas × medida = '.number_format($metros, 2).' m, coincide con el traspaso', 'produccion'],
            'producido' => [Estatus::Producido, 'Etiqueta impresa y evidencia cargada', 'Peso teórico '.number_format($teorico, 2).' kg', 'produccion'],
            'cedis' => [Estatus::EnviadoACedis, 'Despachado a CEDIS', null, 'produccion'],
            'validado' => [Estatus::Validado, 'Recibido y pesado en CEDIS', 'Peso real '.number_format($teorico * 1.004, 2).' kg, +0.40 % contra el teórico', 'cedis'],
            'despacho' => [$despacho[0], $despacho[1], null, 'cedis'],
            'entrega_cedis' => [Estatus::Entregado, 'Entregado al cliente', null, 'cedis'],
            'recibe' => [Estatus::EnSucursal, 'Recibido en sucursal', null, 'sucursal'],
            'entrega_suc' => [Estatus::Entregado, 'Entregado al cliente', null, 'sucursal'],
            'cierre' => [Estatus::Entregado, 'Venta cerrada en Microsip', "Venta {$venta}", 'sucursal'],
        ];

        $eventos = array_map(fn ($id) => $pasos[$id], $alcanzados);
        if (isset($spec['cancelado'])) {
            $eventos[] = [Estatus::Cancelado, 'Traspaso cancelado', $spec['cancelado'], 'cajero'];
        }

        // Fechas: los eventos se reparten entre el inicio y hoy.
        $inicio = now()->subHours($spec['horas']);
        $salto = intdiv($spec['horas'] * 60, max(count($eventos), 1));
        $marca = fn (int $i) => $inicio->copy()->addMinutes($i * $salto);

        $atributos = [
            'folio' => $spec['folio'],
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
            $atributos += ['folio_venta' => $venta, 'forma_entrega' => $forma, 'cortes_solicitados' => $cortes];
        }
        if ($alcanza('captura')) {
            $atributos += [
                'cortes_producidos' => $cortes,
                'captura_validada_at' => $marca(array_search('captura', $alcanzados, true)),
                'direccion_entrega' => $forma === 'cliente' ? 'Carretera a la bodega, km 4. Portón azul, junto a la gasolinera.' : null,
                'gps_link' => $forma === 'cliente' ? 'https://maps.google.com/?q=16.7569,-93.1292' : null,
            ];
        }
        if ($alcanza('producido')) {
            $atributos['peso_teorico'] = $teorico;
        }
        if ($alcanza('validado')) {
            $atributos['peso_real'] = round($teorico * 1.004, 2);
        }
        if ($alcanza('despacho') && $forma === 'cliente') {
            $atributos += ['camion' => 'Camión 07, placas RK-4521-A', 'repartidor' => 'Jorge L.'];
        }
        if ($alcanza('entrega_cedis') || $alcanza('entrega_suc')) {
            $atributos += ['recibe_nombre' => 'Encargado de '.$spec['cliente']];
            if ($forma === 'cedis') {
                $atributos += ['documento_tipo' => 'INE', 'documento_numero' => '0412'];
            }
        }
        if ($alcanza('cierre')) {
            $atributos['venta_cerrada'] = true;
        }
        if (isset($spec['cancelado'])) {
            $atributos['motivo_cancelacion'] = $spec['cancelado'];
        }

        $pedido = Pedido::create($atributos);

        foreach ($eventos as $i => [$estatus, $accion, $detalle, $usuario]) {
            $pedido->eventos()->create([
                'user_id' => $usuarios[$usuario] ?? null,
                'estatus' => $estatus->value,
                'accion' => $accion,
                'detalle' => $detalle,
                'created_at' => $marca($i),
                'updated_at' => $marca($i),
            ]);
        }
    }
}