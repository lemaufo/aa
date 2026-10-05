<?php

namespace App\Models;

use App\Enums\Estatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Una línea de la bitácora: quién hizo qué, sobre qué pedido y cuándo. */
class PedidoEvento extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['estatus' => Estatus::class];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}