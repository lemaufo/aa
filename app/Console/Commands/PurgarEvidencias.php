<?php

namespace App\Console\Commands;

use App\Models\Pedido;
use App\Support\Evidencia;
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
        $simular = (bool) $this->option('simular');
        $disco = Storage::disk('local');
        $borrados = [];

        foreach ($disco->allFiles('evidencias') as $ruta) {
            // La fecha de la toma va en el nombre; las fotos anteriores a ese formato usan la del archivo.
            if ((Evidencia::fecha($ruta) ?? $disco->lastModified($ruta)) >= $limite) {
                continue;
            }

            $borrados[] = $ruta;

            if (! $simular) {
                $disco->delete($ruta);
            }
        }

        if ($borrados && ! $simular) {
            // El pedido conserva todo su historial, solo pierde el enlace a la foto borrada.
            foreach (Pedido::EVIDENCIAS as $columna) {
                foreach (array_chunk($borrados, 500) as $lote) {
                    Pedido::query()->whereIn($columna, $lote)->update([$columna => null]);
                }
            }

            // Carpetas de pedidos que se quedaron sin fotos.
            foreach ($disco->directories('evidencias') as $carpeta) {
                if ($disco->allFiles($carpeta) === []) {
                    $disco->deleteDirectory($carpeta);
                }
            }
        }

        $this->info(($simular ? 'Se borrarían ' : 'Borradas ').count($borrados)
            ." evidencias de más de {$anios} años (corte: ".Carbon::createFromTimestamp($limite)->toDateString().').');

        return self::SUCCESS;
    }
}
