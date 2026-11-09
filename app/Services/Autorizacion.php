<?php

namespace App\Services;

use App\Contracts\UsuariosMicrosip;
use App\Enums\Rol;
use App\Exceptions\MicrosipNoResponde;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Autorización de un supervisor en el equipo del operador (reimprimir etiqueta, liberar un peso fuera de
 * tolerancia). El supervisor escribe su usuario y contraseña; no cambia la sesión de quien está trabajando.
 * Quién autoriza: los roles de aa.roles_autorizan. La contraseña se valida igual que al entrar a la app.
 */
class Autorizacion
{
    public function __construct(private UsuariosMicrosip $microsip) {}

    /** El rol de este usuario le permite autorizar (sin pedirle contraseña cuando es él quien opera). */
    public static function puedeAutorizar(User $user): bool
    {
        return in_array($user->rol->value, config('aa.roles_autorizan', ['admin']), true);
    }

    /**
     * El supervisor que autoriza. $operador es quien pide la autorización (para limitar los intentos).
     *
     * @throws DomainException con el mensaje para la pantalla
     */
    public function autorizar(User $operador, string $usuario, string $password): User
    {
        $llave = 'autorizacion:'.$operador->getKey();
        if (RateLimiter::tooManyAttempts($llave, 5)) {
            throw new DomainException('Demasiados intentos. Espera '.RateLimiter::availableIn($llave).' segundos.');
        }

        $supervisor = User::where('username', strtolower(trim($usuario)))->first();

        if ($supervisor === null || ! $this->contrasenaValida($supervisor, $password)) {
            RateLimiter::hit($llave, 60);

            throw new DomainException('Usuario o contraseña incorrectos.');
        }

        if (! self::puedeAutorizar($supervisor) || $supervisor->microsip_activo === false) {
            RateLimiter::hit($llave, 60);

            throw new DomainException("{$supervisor->name} no puede autorizar. Pide la autorización a un ".self::quienes().'.');
        }

        RateLimiter::clear($llave);

        return $supervisor;
    }

    /** Roles que autorizan, para los mensajes ("administrador o CEDIS"). */
    public static function quienes(): string
    {
        $nombres = array_map(
            fn (string $rol) => mb_strtolower(Rol::tryFrom($rol)?->label() ?? $rol),
            config('aa.roles_autorizan', ['admin'])
        );

        return implode(' o ', $nombres) ?: 'administrador';
    }

    private function contrasenaValida(User $supervisor, string $password): bool
    {
        if (! config('aa.login_microsip')) {
            return Hash::check($password, $supervisor->password);
        }

        try {
            return $this->microsip->validar($supervisor->username, $password);
        } catch (MicrosipNoResponde $e) {
            report($e);

            // Sin Firebird: la última contraseña con la que entró a la app, igual que en el acceso.
            return $supervisor->microsip_sync_at !== null && Hash::check($password, $supervisor->password);
        }
    }
}
