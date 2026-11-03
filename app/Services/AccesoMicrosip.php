<?php

namespace App\Services;

use App\Contracts\UsuariosMicrosip;
use App\Enums\Rol;
use App\Exceptions\MicrosipNoResponde;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\Hash;

/**
 * Entrada con el usuario y la contraseña de Microsip. La contraseña la valida Firebird; la app no la conoce,
 * solo guarda cifrada la última que funcionó para dejar entrar si Firebird no contesta.
 * Para entrar, el usuario debe estar activo en Microsip y tener un rol asignado en la app (Usuarios).
 */
class AccesoMicrosip
{
    public function __construct(private UsuariosMicrosip $microsip) {}

    /**
     * El usuario que entra, o null si el usuario o la contraseña no son correctos.
     *
     * @throws DomainException con el mensaje para la pantalla (sin acceso a la app, baja en Microsip)
     */
    public function autenticar(string $usuario, string $password): ?User
    {
        $usuario = strtolower(trim($usuario));
        $local = User::where('username', $usuario)->first();

        try {
            if (! $this->microsip->validar($usuario, $password)) {
                return null;
            }

            $datos = $this->microsip->buscar($usuario);
        } catch (MicrosipNoResponde $e) {
            report($e);

            // Sin Firebird: solo quien ya entró antes con Microsip, con la última contraseña que funcionó.
            return $local?->microsip_sync_at && $local->microsip_activo && Hash::check($password, $local->password) ? $local : null;
        }

        if ($datos === null) {
            throw new DomainException('Tu usuario no tiene acceso a la empresa '.config('aa.empresa_microsip').' en Microsip.');
        }

        if (! $datos['activo']) {
            $local?->sincronizarMicrosip($datos);
            $local?->save();

            throw new DomainException('Tu usuario está dado de baja en Microsip.');
        }

        // SYSDBA entra siempre como administrador, aunque la app esté recién instalada.
        $local ??= $usuario === 'sysdba'
            ? new User(['username' => 'sysdba', 'name' => 'SYSDBA', 'rol' => Rol::Admin])
            : throw new DomainException('Tu usuario de Microsip todavía no tiene acceso a esta app. Pide al administrador que te asigne un rol en Usuarios.');

        $local->sincronizarMicrosip($datos);
        if (! $local->exists || ! Hash::check($password, $local->password)) {
            $local->password = $password; // el modelo la guarda cifrada
        }
        $local->save();

        return $local;
    }
}
