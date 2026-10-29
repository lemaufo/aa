<?php

namespace App\Services;

use App\Contracts\TraspasosExpertos;
use App\Contracts\VentasMicrosip;
use App\Models\Pedido;
use Throwable;

/**
 * Doble validación al ligar: el ticket de Microsip contra el traspaso de Expertos.
 * Devuelve la lista de problemas; si está vacía, el ticket sirve para este pedido.
 */
class ValidadorTicket
{
    public function __construct(
        private VentasMicrosip $ventas,
        private TraspasosExpertos $traspasos,
    ) {}

    /** @return list<string> */
    public function validar(Pedido $pedido, string $folio): array
    {
        $folio = trim($folio);

        if ($folio === '') {
            return ['Captura el folio del ticket.'];
        }

        try {
            if ($this->traspasos->estaCancelado($pedido->folio)) {
                return ["El traspaso {$pedido->folio} está cancelado en Expertos."];
            }

            $ticket = $this->ventas->consultar($folio);
        } catch (Throwable $e) {
            report($e);

            return ['No se pudo consultar Microsip en este momento. Intenta de nuevo en unos minutos.'];
        }

        if ($ticket === null) {
            return ["El ticket {$folio} no existe en Microsip."];
        }

        $errores = [];

        if ($ticket['estado'] === 'cancelada') {
            $errores[] = "El ticket {$folio} está cancelado en Microsip.";
        } elseif ($ticket['estado'] !== 'pagada') {
            $errores[] = "El ticket {$folio} todavía no está cobrado.";
        }

        if ($this->normalizar($ticket['articulo']) !== $this->normalizar($pedido->material)) {
            $errores[] = "El ticket es de «{$ticket['articulo']}» y el traspaso es de «{$pedido->material}». Debe ser el mismo artículo.";
        }

        // Se vende exactamente lo que se traspasó: ni de más ni de menos.
        if (($ticket['cantidad'] ?? null) !== null && abs($ticket['cantidad'] - (float) $pedido->metros_traspaso) > 0.001) {
            $errores[] = 'El ticket vende '.$this->numero($ticket['cantidad']).' y el traspaso es de '
                .$this->numero((float) $pedido->metros_traspaso).'. Deben ser la misma cantidad.';
        }

        if (config('aa.ticket_exclusivo') && $ticket['lineas_ajenas'] > 0) {
            $errores[] = 'El ticket trae productos de línea. El pedido especial debe venderse en un ticket independiente.';
        }

        // Microsip devuelve el folio completo (CA0078212) aunque se haya tecleado corto (ca78212).
        $usado = Pedido::query()->whereIn('folio_venta', array_unique([$folio, $ticket['folio']]))->whereKeyNot($pedido->getKey())->first();
        if ($usado) {
            $errores[] = "El ticket {$folio} ya está ligado al traspaso {$usado->folio}.";
        }

        return $errores;
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ','), '0'), '.');
    }

    private function normalizar(string $texto): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $texto)));
    }
}