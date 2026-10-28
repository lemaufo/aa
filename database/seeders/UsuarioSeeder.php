<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // Usuarios de prueba con contraseñas conocidas: nunca en producción.
        if (app()->isProduction()) {
            $this->command?->warn('UsuarioSeeder no corre en producción (usuarios de prueba).');

            return;
        }

        foreach (config('aa.usuarios') as $datos) {
            User::updateOrCreate(
                ['username' => $datos['username']],
                [
                    'name' => $datos['name'],
                    'rol' => $datos['rol'],
                    'sucursal' => $datos['sucursal'],
                    'password' => $datos['password'], // el modelo la guarda cifrada
                ],
            );
        }
    }
}