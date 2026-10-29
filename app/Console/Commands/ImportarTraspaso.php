<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use App\Services\AltaPedido;
use DomainException;
use Illuminate\Console\Command;
use Throwable;

/** Alta manual de un traspaso (los de Microsip entran solos con aa:importar-traspasos), a nombre del administrador. */
class ImportarTraspaso extends Command
{
    protected $signature = 'aa:importar-traspaso
        {folio : Folio del traspaso en Expertos o Microsip, ej. TSA013341}
        {--partida=1 : Partida del traspaso que se convierte en pedido}
        {--cliente= : Nombre del cliente (en caja se puede corregir al ligar)}
        {--sucursal= : Sucursal del pedido en la app; por defecto el destino del traspaso}';

    protected $description = 'Crea un pedido a partir de un traspaso real de AdminExpertos o Microsip';

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
            $this->error('No se pudo leer Expertos/Microsip: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pedido {$pedido->folio} creado en EN EXPERTOS para {$pedido->sucursal}: {$pedido->material}.");

        return self::SUCCESS;
    }
}
