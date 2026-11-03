<?php

namespace App\Services\Firebird;

use App\Contracts\UsuariosMicrosip;
use App\Exceptions\MicrosipNoResponde;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Throwable;

/**
 * Microsip guarda a sus usuarios en dos lugares:
 *  - El servidor Firebird (SEC$USERS): usuario y contraseña. Microsip entra conectándose con ellos.
 *  - System\CONFIG.FDB, tabla USUARIOS: TIPO U = usuario, R = rol; USAR_ROL/ROL_ID = rol que usa;
 *    ESTATUS A = activo; ACCESO_EMPRESAS T = todas, si no, las de EMPRESAS_USUARIOS.
 * SYSDBA no está en USUARIOS: es el supervisor de Microsip y en la app es el administrador.
 */
class FirebirdUsuarios implements UsuariosMicrosip
{
    /** Firebird: "Your user name and password are not defined". Cualquier otro error es que no contestó. */
    private const CREDENCIALES_INVALIDAS = 335544472;

    public function listar(): array
    {
        return [self::sysdba(), ...$this->consultar()];
    }

    public function buscar(string $usuario): ?array
    {
        $usuario = strtoupper(trim($usuario));

        return $usuario === 'SYSDBA' ? self::sysdba() : ($this->consultar($usuario)[0] ?? null);
    }

    public function validar(string $usuario, string $password): bool
    {
        $c = config('database.connections.firebird');

        try {
            // Una conexión de un solo uso con las credenciales del usuario: si Firebird la acepta, la contraseña es buena.
            $pdo = new PDO("firebird:dbname={$c['host']}/{$c['port']}:{$c['database']};charset={$c['charset']}",
                strtoupper(trim($usuario)), $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo = null;

            return true;
        } catch (PDOException $e) {
            if ((int) $e->getCode() === self::CREDENCIALES_INVALIDAS) {
                return false;
            }

            throw new MicrosipNoResponde('No contesta el servidor Firebird de Microsip: '.$e->getMessage(), previous: $e);
        }
    }

    /** @return list<array{id: ?int, usuario: string, rol: ?string, activo: bool}> */
    private function consultar(?string $usuario = null): array
    {
        try {
            $filas = DB::connection('microsip_config')->select(
                "select u.usuario_id, u.nombre, u.estatus, r.nombre as rol
                   from usuarios u
                   left join usuarios r on r.usuario_id = u.rol_id and u.usar_rol = 'S'
                  where u.tipo = 'U'".($usuario === null ? '' : ' and u.nombre = ?')."
                    and (u.acceso_empresas = 'T' or exists (
                         select 1 from empresas_usuarios eu join empresas e on e.empresa_id = eu.empresa_id
                          where eu.usuario_id = u.usuario_id and e.nombre_corto = ?))
                  order by u.nombre",
                [...($usuario === null ? [] : [$usuario]), config('aa.empresa_microsip')]
            );
        } catch (Throwable $e) {
            throw new MicrosipNoResponde('No se pudo leer el catálogo de usuarios de Microsip: '.$e->getMessage(), previous: $e);
        }

        return array_map(function ($f) {
            $f = Microsip::fila($f);

            return ['id' => (int) $f['usuario_id'], 'usuario' => $f['nombre'], 'rol' => $f['rol'] ?: null, 'activo' => $f['estatus'] === 'A'];
        }, $filas);
    }

    private static function sysdba(): array
    {
        return ['id' => null, 'usuario' => 'SYSDBA', 'rol' => null, 'activo' => true];
    }
}
