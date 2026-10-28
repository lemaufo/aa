<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use App\Services\AltaPedido;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

/** Lo mismo que "Registrar traspaso" en la pantalla de Pedidos, a nombre del administrador. */
class ImportarTraspaso extends Command
{
    protected $signature = 'aa:importar-traspaso
        {folio : Folio del traspaso en Expertos, ej. TSA013341}
        {--partida=1 : Partida del traspaso que se convierte en pedido}
        {--cliente= : Nombre del cliente (en caja se puede corregir al ligar)}
        {--sucursal= : Sucursal del pedido en la app; por defecto el destino del traspaso}';

    protected $description = 'Crea un pedido a partir de un traspaso real de AdminExpertos';

    public function handle(AltaPedido $alta): int
    {
        $admin = User::where('rol', Rol::Admin->value)->first();
        if ($admin === null) {
            $this->error('No hay un usuario administrador a cuyo nombre registrar el pedido.');

            return self::FAILURE;
        }

        try {
            $traspaso = $alta->buscar($this->argument('folio'));

            $this->table(['Partida', 'Clave', 'Artículo', 'Cantidad', 'Unidad', 'Peso unitario'], array_map(
                fn ($p) => [$p['partida'], $p['clave'], $p['articulo'], $p['cantidad'], $p['unidad'], $p['peso_unitario']],
                $traspaso['partidas']
            ));

            $pedido = $alta->registrar($traspaso, (int) $this->option('partida'), $admin, $this->option('sucursal'), $this->option('cliente'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('No se pudo leer Expertos: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pedido {$pedido->folio} creado en EN EXPERTOS para {$pedido->sucursal}: {$pedido->material}.");

        return self::SUCCESS;
    }
}
