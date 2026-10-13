<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgarEvidencias extends Command
{
    protected $signature = 'aa:purgar-evidencias {--simular : Solo muestra qué se borraría}';

    protected $description = 'Borra las fotos y acuses con más años de los que marca la política de retención';

    public function handle(): int
    {
        $anios = (int) config('aa.retencion_fotos_anios', 2);
        $limite = now()->subYears($anios)->getTimestamp();
        $disco = Storage::disk('local');
        $borrados = [];

        foreach ($disco->allFiles('evidencias') as $ruta) {
            if ($disco->lastModified($ruta) >= $limite) {
                continue;
            }

            $borrados[] = $ruta;

            if (! $this->option('simular')) {
                $disco->delete($ruta);
            }
        }

        if ($borrados && ! $this->option('simular')) {
            // El pedido conserva todo su historial, solo pierde el enlace a la foto borrada.
            foreach (Pedido::EVIDENCIAS as $columna) {
                Pedido::query()->whereIn($columna, $borrados)->update([$columna => null]);
            }
        }

        $this->info(($this->option('simular') ? 'Se borrarían ' : 'Borradas ').count($borrados)
            ." evidencias de más de {$anios} años (corte: ".Carbon::createFromTimestamp($limite)->toDateString().').');

        return self::SUCCESS;
    }
}