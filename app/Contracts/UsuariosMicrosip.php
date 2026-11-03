<?php

namespace App\Contracts;

use App\Exceptions\MicrosipNoResponde;

/** Usuarios de Microsip: el catálogo (System\CONFIG.FDB) y la contraseña (servidor Firebird). Solo lectura. */
interface UsuariosMicrosip
{
    /**
     * Usuarios con acceso a la empresa, SYSDBA primero. rol = rol de Microsip que usa el usuario, si tiene.
     *
     * @return list<array{id: ?int, usuario: string, rol: ?string, activo: bool}>
     *
     * @throws MicrosipNoResponde
     */
    public function listar(): array;

    /**
     * El usuario en el catálogo de Microsip, o null si no existe o no tiene acceso a la empresa.
     *
     * @return array{id: ?int, usuario: string, rol: ?string, activo: bool}|null
     *
     * @throws MicrosipNoResponde
     */
    public function buscar(string $usuario): ?array;

    /**
     * True si Firebird acepta ese usuario y contraseña, igual que al entrar a Microsip.
     *
     * @throws MicrosipNoResponde si el servidor no contesta (no es lo mismo que una contraseña mala)
     */
    public function validar(string $usuario, string $password): bool;
}
