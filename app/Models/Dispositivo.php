<?php

namespace App\Models;

use App\Enums\EstatusDispositivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Dispositivo autorizado (PC, tablet, celular). El administrador lo da de alta con su nombre y MAC y le genera
 * un código de un solo uso; al escribirlo en el dispositivo se guarda una llave en una cookie (aquí solo vive su
 * hash). Solo opera con estatus Activo; Suspendido se reactiva, Baja es definitiva. Eliminar lo quita de la lista
 * (borrado lógico): no vuelve a operar, pero su llave se sigue reconociendo para bloquearla y anotarlo.
 */
class Dispositivo extends Model
{
    use SoftDeletes;

    protected $table = 'dispositivos';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'estatus' => EstatusDispositivo::class,
            'codigo_expira_at' => 'datetime',
            'activado_at' => 'datetime',
            'ultimo_uso_at' => 'datetime',
            'revocado_at' => 'datetime',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(DispositivoEvento::class);
    }

    /**
     * MAC en formato AA:BB:CC:DD:EE:FF. Acepta separadores ":", "-", "." o ninguno (como la muestran Windows,
     * Linux o un switch). Null si no es una MAC de 12 dígitos hexadecimales o es la nula / la de difusión.
     */
    public static function normalizarMac(?string $mac): ?string
    {
        $hex = strtoupper(preg_replace('/[\s:.\-]/', '', (string) $mac));

        if (! preg_match('/^[0-9A-F]{12}$/', $hex) || in_array($hex, ['000000000000', 'FFFFFFFFFFFF'], true)) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    public function operable(): bool
    {
        return $this->estatus === EstatusDispositivo::Activo && ! $this->trashed();
    }

    /** Si ya se activó en el dispositivo o espera su código. */
    public function conexion(): string
    {
        return match (true) {
            $this->activado_at !== null => 'Vinculado',
            $this->codigo_hash !== null && $this->codigo_expira_at?->isPast() => 'Código vencido',
            $this->codigo_hash !== null => 'Esperando activación',
            default => 'Sin vincular',
        };
    }

    /**
     * Código nuevo para (re)vincular el dispositivo, p. ej. si se borró la cookie del navegador.
     * La llave anterior deja de servir.
     */
    public function nuevoCodigo(): string
    {
        $codigo = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        $this->update([
            'codigo_hash' => hash('sha256', $codigo),
            'codigo_expira_at' => now()->addHours((int) config('aa.dispositivos.codigo_vigencia_horas', 24)),
            'token_hash' => null,
            'activado_at' => null,
        ]);

        return $codigo;
    }

    /** Canjea el código de un dispositivo activo. Devuelve la llave en claro para la cookie, o null si no sirve. */
    public static function activar(string $codigo): ?string
    {
        $codigo = strtoupper(trim($codigo));

        $dispositivo = static::query()
            ->where('codigo_hash', hash('sha256', $codigo))
            ->where('estatus', EstatusDispositivo::Activo)
            ->whereNull('activado_at')
            ->where('codigo_expira_at', '>', now())
            ->first();

        if (! $dispositivo) {
            return null;
        }

        $token = Str::random(64);

        $dispositivo->update([
            'token_hash' => hash('sha256', $token),
            'codigo_hash' => null,
            'activado_at' => now(),
            'ultimo_uso_at' => now(),
        ]);

        DispositivoEvento::anotar($dispositivo, auth()->user(), 'Dispositivo vinculado', 'Se capturó el código en el dispositivo');

        return $token;
    }

    /** Mensaje para el dispositivo que no puede operar. */
    public function avisoBloqueo(): string
    {
        return $this->trashed()
            ? 'Este dispositivo fue eliminado de la lista de autorizados y ya no puede usar el sistema.'
            : $this->estatus->avisoBloqueo();
    }

    /** Cómo se anota en la bitácora un intento de uso bloqueado. */
    public function motivoBloqueo(): string
    {
        return 'Intento de uso con dispositivo '.($this->trashed() ? 'eliminado' : mb_strtolower($this->estatus->label()));
    }

    /** La MAC ya está en otro dispositivo que puede operar (activo o suspendido, sin eliminar). */
    public static function macOcupada(string $mac, ?int $salvo = null): bool
    {
        return static::query()->where('mac', $mac)->where('estatus', '!=', EstatusDispositivo::Baja->value)
            ->when($salvo, fn ($q) => $q->whereKeyNot($salvo))->exists();
    }

    /**
     * El dispositivo de esa llave, con cualquier estatus e incluso eliminado (quien llama decide si opera).
     * Null si la llave no existe.
     */
    public static function porLlave(?string $token): ?self
    {
        if (! $token || strlen($token) !== 64) {
            return null;
        }

        return static::withTrashed()->where('token_hash', hash('sha256', $token))->first();
    }

    public function marcarUso(): void
    {
        if ($this->ultimo_uso_at === null || $this->ultimo_uso_at->diffInMinutes(now()) >= 5) {
            $this->forceFill(['ultimo_uso_at' => now()])->save();
        }
    }
}
