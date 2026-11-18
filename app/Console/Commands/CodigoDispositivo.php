<?php

namespace App\Console\Commands;

use App\Enums\EstatusDispositivo;
use App\Models\Dispositivo;
use App\Models\DispositivoEvento;
use Illuminate\Console\Command;

class CodigoDispositivo extends Command
{
    protected $signature = 'aa:dispositivo-codigo
        {nombre : Cómo se llama el dispositivo, por ejemplo "PC Administración"}
        {--mac= : Dirección MAC del dispositivo (AA:BB:CC:DD:EE:FF)}';

    protected $description = 'Da de alta un dispositivo autorizado y genera su código de un solo uso (para el primero, antes de poder entrar a la app)';

    public function handle(): int
    {
        $mac = null;

        if (filled($this->option('mac'))) {
            $mac = Dispositivo::normalizarMac($this->option('mac'));

            if ($mac === null) {
                $this->error('La MAC no es válida. Son 12 dígitos hexadecimales, por ejemplo AA:BB:CC:DD:EE:FF.');

                return self::FAILURE;
            }

            if (Dispositivo::where('mac', $mac)->where('estatus', '!=', EstatusDispositivo::Baja->value)->exists()) {
                $this->error("La MAC {$mac} ya está registrada en otro dispositivo activo o suspendido.");

                return self::FAILURE;
            }
        }

        $dispositivo = Dispositivo::create(['nombre' => $this->argument('nombre'), 'mac' => $mac, 'estatus' => EstatusDispositivo::Activo]);
        $codigo = $dispositivo->nuevoCodigo();
        DispositivoEvento::anotar($dispositivo, null, 'Alta de dispositivo', 'Desde consola'.($mac ? ". MAC {$mac}" : ''));

        $this->info("Código para «{$dispositivo->nombre}»: {$codigo}");
        $this->line('Se escribe en la pantalla /dispositivo de ese dispositivo. Vence en '.config('aa.dispositivos.codigo_vigencia_horas').' horas.');

        return self::SUCCESS;
    }
}
