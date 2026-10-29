<?php

namespace App\Console\Commands;

use App\Services\AltaPedido;
use Illuminate\Console\Command;

class ImportarTraspasosMicrosip extends Command
{
    protected $signature = 'aa:importar-traspasos';

    protected $description = 'Da de alta como pedido los traspasos de salida capturados en Microsip (aa.conceptos_traspaso)';

    public function handle(AltaPedido $alta): int
    {
        $this->info('Pedidos creados desde Microsip: '.$alta->importarDeMicrosip());

        return self::SUCCESS;
    }
}
