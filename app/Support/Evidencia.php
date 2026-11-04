<?php

namespace App\Support;

use App\Models\Pedido;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fotos de evidencia y acuses. Siempre en el disco privado, en evidencias/{pedido}/, y nunca en public/.
 *
 * Antes de guardarla, la foto se endereza, se reduce a LADO_MAX y se vuelve a comprimir en JPEG:
 * una foto de celular pasa de 3-8 MB a unos 200-400 KB y sigue leyéndose la etiqueta o la firma.
 * De paso se pierden los metadatos EXIF (modelo del teléfono, ubicación GPS).
 *
 * El nombre lleva la fecha de la toma (producto-20261104-153012-k3f9qa.jpg); la purga se guía por
 * esa fecha y no por la del archivo, que cambia al copiar o restaurar un respaldo en otro servidor.
 */
class Evidencia
{
    /** Lado más largo, en píxeles, con el que se guarda la foto. */
    public const LADO_MAX = 1600;

    /** Calidad JPEG (0-100). */
    public const CALIDAD = 75;

    /** Peso máximo, en KB, de la foto que sube el usuario, antes de comprimirla. Debe caber en upload_max_filesize. */
    public const MAX_KB = 10240;

    /** @return list<string> */
    public static function reglas(): array
    {
        return ['required', 'image', 'max:'.self::MAX_KB];
    }

    /** Guarda la foto del pedido y devuelve su ruta en el disco privado. $tipo es una llave de Pedido::EVIDENCIAS. */
    public static function guardar(UploadedFile $foto, Pedido $pedido, string $tipo): string
    {
        $carpeta = 'evidencias/'.$pedido->id;
        $nombre = $tipo.'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6));

        $jpeg = self::comprimir($foto);

        // Si GD no puede leerla se guarda la original: una evidencia nunca se pierde por no poder comprimirla.
        if ($jpeg === null) {
            return $foto->storeAs($carpeta, $nombre.'.'.($foto->guessExtension() ?: 'jpg'), 'local');
        }

        Storage::disk('local')->put($ruta = $carpeta.'/'.$nombre.'.jpg', $jpeg);

        return $ruta;
    }

    /** Fecha de la toma según el nombre del archivo. Null si la foto es anterior a este formato de nombre. */
    public static function fecha(string $ruta): ?int
    {
        return preg_match('/-(\d{8}-\d{6})-[a-z0-9]+\.\w+$/', $ruta, $m)
            ? Carbon::createFromFormat('Ymd-His', $m[1])->getTimestamp()
            : null;
    }

    private static function comprimir(UploadedFile $foto): ?string
    {
        $contenido = @file_get_contents($foto->getRealPath());
        $original = $contenido === false ? false : @imagecreatefromstring($contenido);

        if (! $original instanceof GdImage) {
            return null;
        }

        $original = self::enderezar($original, $foto);

        $ancho = imagesx($original);
        $alto = imagesy($original);
        $factor = min(1, self::LADO_MAX / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto = max(1, (int) round($alto * $factor));

        // Fondo blanco: un PNG con transparencia saldría negro en JPEG.
        $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagejpeg($lienzo, null, self::CALIDAD);

        return ob_get_clean() ?: null;
    }

    /** El celular guarda la foto acostada y anota el giro en el EXIF; al quitar el EXIF hay que aplicarlo. */
    private static function enderezar(GdImage $imagen, UploadedFile $foto): GdImage
    {
        if (! function_exists('exif_read_data') || $foto->getMimeType() !== 'image/jpeg') {
            return $imagen;
        }

        $exif = @exif_read_data($foto->getRealPath());
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $grados === 0 ? $imagen : (imagerotate($imagen, $grados, 0) ?: $imagen);
    }
}
