<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;

/** Primer administrador del servidor: en producción no corren los seeders de usuarios de prueba. */
class CrearAdmin extends Command
{
    protected $signature = 'aa:crear-admin {username : Usuario para entrar} {--nombre= : Nombre completo}';

    protected $description = 'Crea un usuario administrador o le cambia la contraseña';

    public function handle(): int
    {
        $username = strtolower(trim($this->argument('username')));
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');

        if (strlen((string) $password) < 8 || $password !== $this->secret('Repite la contraseña')) {
            $this->error('La contraseña es muy corta o no coincide.');

            return self::FAILURE;
        }

        $u = User::updateOrCreate(['username' => $username], [
            'name' => $this->option('nombre') ?: (User::where('username', $username)->value('name') ?? 'Administrador'),
            'rol' => Rol::Admin,
            'sucursal' => null,
            'password' => $password,
        ]);

        $this->info(($u->wasRecentlyCreated ? 'Administrador creado: ' : 'Administrador actualizado: ').$u->username);

        return self::SUCCESS;
    }
}
