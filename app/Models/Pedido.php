<?php

namespace App\Models;

use App\Enums\Estatus;
use App\Enums\FormaEntrega;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un pedido es un traspaso de Expertos: un folio, un cliente, un artículo.
 * En esta fase vive en MySQL; después el traspaso se leerá (solo lectura) de Microsip.
 */
class Pedido extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estatus' => Estatus::class,
            'forma_entrega' => FormaEntrega::class,
            'cortes_solicitados' => 'array',
            'cortes_producidos' => 'array',
            'captura_validada_at' => 'datetime',
            'venta_cerrada' => 'boolean',
            'metros_traspaso' => 'float',
            'peso_por_metro' => 'float',
            'peso_teorico' => 'float',
            'peso_real' => 'float',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(PedidoEvento::class);
    }

    /** Cajero y Sucursal solo ven su sucursal; el resto ve todas. */
    public function scopeVisiblePara(Builder $query, User $user): Builder
    {
        return $user->rol->limitadoASuSucursal()
            ? $query->where('sucursal', $user->sucursal)
            : $query;
    }

    public function esVisiblePara(User $user): bool
    {
        return ! $user->rol->limitadoASuSucursal() || $this->sucursal === $user->sucursal;
    }

    /** Peso teórico en kg: metros del traspaso por peso por metro lineal del artículo. */
    public function pesoTeorico(): float
    {
        return round($this->metros_traspaso * $this->peso_por_metro, 2);
    }

    /** Diferencia porcentual del peso real contra el teórico (positivo si pesa de más). */
    public function desviacion(float $pesoReal): float
    {
        $teorico = $this->pesoTeorico();

        return $teorico > 0 ? round((($pesoReal - $teorico) / $teorico) * 100, 2) : 0.0;
    }

    public function dentroDeTolerancia(float $pesoReal): bool
    {
        return abs($this->desviacion($pesoReal)) <= (float) config('aa.tolerancia_peso');
    }
}