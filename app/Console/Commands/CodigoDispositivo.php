<?php

namespace App\Console\Commands;

use App\Models\Dispositivo;
use Illuminate\Console\Command;

class CodigoDispositivo extends Command
{
    protected $signature = 'aa:dispositivo-codigo {nombre : Cómo se llama el equipo, por ejemplo "Báscula CEDIS"}';

    protected $description = 'Genera un código de un solo uso para autorizar un equipo';

    public function handle(): int
    {
        $codigo = Dispositivo::generarCodigo($this->argument('nombre'));

        $this->info("Código para «{$this->argument('nombre')}»: {$codigo}");
        $this->line('Se escribe en la pantalla /dispositivo del equipo. Vence en '.config('aa.dispositivos.codigo_vigencia_horas').' horas.');

        return self::SUCCESS;
    }
}