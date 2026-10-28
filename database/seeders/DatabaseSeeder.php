<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(UsuarioSeeder::class);

        // Con Microsip conectado los pedidos salen de traspasos reales; los demo tienen folios inventados
        // que podrían coincidir con traspasos viejos de Expertos.
        if (config('aa.origen') === 'demo') {
            $this->call(PedidoSeeder::class);
        }
    }
}