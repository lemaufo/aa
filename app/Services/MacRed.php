<?php

namespace App\Services;

use App\Models\Dispositivo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * MAC del dispositivo vista desde el servidor. El navegador no puede dar la MAC, así que se busca la IP del
 * cliente en la tabla ARP del servidor (arp -a en Windows / ip neigh en Linux). Solo sirve si el dispositivo
 * está en la misma red local que el servidor: por VPN, a través de un router o desde fuera no se ve, y entonces
 * se devuelve null (la validación queda solo con la cookie). Los celulares usan una MAC distinta por red Wi-Fi
 * ("MAC privada") salvo que se desactive para la red de la empresa.
 */
class MacRed
{
    /** MAC normalizada (AA:BB:CC:DD:EE:FF) de esa IP, o null si el servidor no la ve. */
    public function de(?string $ip): ?string
    {
        // Solo IPv4 privadas de la red local: localhost no tiene MAC y una IP pública nunca está en la ARP.
        $ipv4 = $ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $publica = $ipv4 && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if (! $ipv4 || $publica || str_starts_with($ip, '127.')) {
            return null;
        }

        return Cache::remember('mac-red:'.$ip, now()->addMinutes(5), fn () => $this->buscar($ip) ?? false) ?: null;
    }

    private function buscar(string $ip): ?string
    {
        try {
            // Arreglo de argumentos: no pasa por la consola, la IP no se interpreta como comando.
            $salida = Process::timeout(3)->run(PHP_OS_FAMILY === 'Windows' ? ['arp', '-a', $ip] : ['ip', 'neigh', 'show', $ip])->output();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        foreach (preg_split('/\R/', $salida) as $linea) {
            if (preg_match('/(?<![\d.])'.preg_quote($ip, '/').'(?![\d.])/', $linea)
                && preg_match('/([0-9a-f]{2}[:-]){5}[0-9a-f]{2}/i', $linea, $m)) {
                return Dispositivo::normalizarMac($m[0]);
            }
        }

        return null;
    }
}
