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

    /** Movimientos de la bitácora que el cliente no debe ver al rastrear su pedido. */
    public const EVENTOS_INTERNOS = [
        'Etiqueta impresa',
        'Peso de producción validado',
        'Datos de entrega modificados',
        'Venta actualizada en Microsip',
        'Error al actualizar Microsip',
        'Reintento de actualización de venta',
    ];

    /** Columna donde vive cada tipo de evidencia (disco privado). */
    public const EVIDENCIAS = [
        'producto' => 'evidencia_path',
        'bascula' => 'evidencia_bascula_path',
        'acuse' => 'acuse_path',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => Estatus::class,
            'forma_entrega' => FormaEntrega::class,
            'cortes_solicitados' => 'array',
            'fecha_prometida' => 'date',
            'ticket_validado_at' => 'datetime',
            'etiqueta_impresa_at' => 'datetime',
            'venta_sync_at' => 'datetime',
            'cancelado_por_expertos' => 'boolean',
            'metros_traspaso' => 'float',
            'peso_por_metro' => 'float',
            'peso_teorico' => 'float',
            'peso_real' => 'float',
            'peso_produccion' => 'float',
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

    /** Abiertos primero, y entre ellos el que se promete antes. Sin fecha al final. */
    public function scopePrioritarios(Builder $query): Builder
    {
        return $query
            ->orderByRaw('case when estatus in (?, ?) then 1 else 0 end', [Estatus::Entregado->value, Estatus::Cancelado->value])
            ->orderByRaw('fecha_prometida is null')
            ->orderBy('fecha_prometida')
            ->orderByDesc('id');
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

    /** Diferencia porcentual de un peso medido contra el teórico (positivo si pesa de más). */
    public function desviacion(float $pesoReal): float
    {
        $teorico = $this->pesoTeorico();

        return $teorico > 0 ? round((($pesoReal - $teorico) / $teorico) * 100, 2) : 0.0;
    }

    public function dentroDeTolerancia(float $pesoReal): bool
    {
        return abs($this->desviacion($pesoReal)) <= (float) config('aa.tolerancia_peso');
    }

    /**
     * Urgencia según la fecha prometida: nivel (vencido, hoy, pronto, normal), días y texto.
     * Null si no hay fecha o el pedido ya terminó.
     *
     * @return array{nivel: string, dias: int, texto: string}|null
     */
    public function prioridad(): ?array
    {
        if ($this->fecha_prometida === null || $this->estatus->esFinal()) {
            return null;
        }

        $dias = (int) now()->startOfDay()->diffInDays($this->fecha_prometida->copy()->startOfDay(), false);

        return match (true) {
            $dias < 0 => ['nivel' => 'vencido', 'dias' => $dias, 'texto' => 'Vencido hace '.abs($dias).($dias === -1 ? ' día' : ' días')],
            $dias === 0 => ['nivel' => 'hoy', 'dias' => 0, 'texto' => 'Se promete para hoy'],
            $dias <= 2 => ['nivel' => 'pronto', 'dias' => $dias, 'texto' => $dias === 1 ? 'Se promete para mañana' : 'Se promete en 2 días'],
            default => ['nivel' => 'normal', 'dias' => $dias, 'texto' => 'En '.$dias.' días'],
        };
    }

    /** Teléfono en 10 dígitos más lada de México, listo para wa.me. Null si no es válido. */
    public function telefonoWhatsApp(): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $this->telefono);

        if (strlen($digitos) === 10) {
            return '52'.$digitos;
        }

        return strlen($digitos) >= 12 && strlen($digitos) <= 13 ? $digitos : null;
    }

    /** Enlace que abre WhatsApp con el mensaje ya escrito. Null si el pedido no tiene un teléfono válido. */
    public function urlWhatsApp(string $mensaje): ?string
    {
        $telefono = $this->telefonoWhatsApp();

        return $telefono ? 'https://wa.me/'.$telefono.'?text='.rawurlencode($mensaje) : null;
    }

    /** Mensaje para avisar al cliente que su pedido ya está listo para recoger en CEDIS. */
    public function mensajeListoEnCedis(): string
    {
        return 'Hola, te escribimos de Aceros el Águila. Tu pedido '.$this->folio
            .' ya está listo para recoger en CEDIS. Lleva una identificación o tu ticket de venta'
            .($this->folio_venta ? ' '.$this->folio_venta : '').'.';
    }

    /** Quién elaboró: el usuario de producción que imprimió la etiqueta (orden de producción). */
    public function elaboro(): ?string
    {
        return $this->eventos()->with('user')->where('accion', 'Etiqueta impresa')->latest('id')->first()?->user?->name;
    }

    /** Quién autorizó: el usuario que ligó el traspaso con la venta cobrada. */
    public function autorizo(): ?string
    {
        return $this->eventos()->with('user')->where('accion', 'Traspaso ligado a la venta')->latest('id')->first()?->user?->name;
    }

    public function totalPiezas(): int
    {
        return (int) collect($this->cortes_solicitados ?? [])->sum('piezas');
    }

    /**
     * Bitácora para el cliente que rastrea: sin nombres del personal ni datos internos
     * (pesos, ventas, evidencias). Cada movimiento sale con el nombre público del estatus.
     */
    public function eventosPublicos()
    {
        return $this->eventos()
            ->whereNotIn('accion', self::EVENTOS_INTERNOS)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PedidoEvento $e) => ['titulo' => $e->estatus->tituloPublico(), 'fecha' => $e->created_at])
            ->reverse()
            ->values();
    }
}