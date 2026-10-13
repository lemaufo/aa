<?php

namespace App\Console\Commands;

use App\Services\FlujoService;
use Illuminate\Console\Command;

class SincronizarCancelaciones extends Command
{
    protected $signature = 'aa:sincronizar-cancelaciones';

    protected $description = 'Cancela los pedidos cuyo traspaso o ticket ya se canceló en Expertos o Microsip';

    public function handle(FlujoService $flujo): int
    {
        $this->info('Pedidos cancelados desde Expertos/Microsip: '.$flujo->revisarTodas());

        return self::SUCCESS;
    }
}