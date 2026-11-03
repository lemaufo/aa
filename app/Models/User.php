<?php

namespace App\Models;

use App\Enums\Rol;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'rol',
        'sucursal',
        'microsip_usuario_id',
        'microsip_rol',
        'microsip_activo',
        'microsip_sync_at',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => Rol::class,
            'microsip_activo' => 'boolean',
            'microsip_sync_at' => 'datetime',
        ];
    }

    /** SYSDBA es el supervisor de Microsip: siempre administrador de la app. */
    public function esSysdba(): bool
    {
        return $this->username === 'sysdba';
    }

    /** Guarda lo que dice Microsip del usuario (catálogo de CONFIG.FDB). */
    public function sincronizarMicrosip(array $datos): void
    {
        $this->forceFill([
            'microsip_usuario_id' => $datos['id'],
            'microsip_rol' => $datos['rol'],
            'microsip_activo' => $datos['activo'],
            'microsip_sync_at' => now(),
        ]);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $parte) => Str::of($parte)->substr(0, 1))
            ->take(2)
            ->implode('');
    }
}