<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            // Clave del artículo en Microsip: con ella se vuelve a leer el peso por metro del catálogo al imprimir.
            $table->string('articulo_clave')->nullable()->after('material');

            // Diseño de referencia que adjunta la caja (canalón y otras piezas sin clave propia).
            $table->string('diseno_path')->nullable()->after('acuse_path');

            // Peso fuera de la tolerancia liberado por un supervisor, con su nota.
            $table->boolean('peso_fuera_tolerancia')->default(false)->after('peso_produccion');
            $table->string('peso_nota')->nullable()->after('peso_fuera_tolerancia');
            $table->string('peso_autorizado_por')->nullable()->after('peso_nota');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['articulo_clave', 'diseno_path', 'peso_fuera_tolerancia', 'peso_nota', 'peso_autorizado_por']);
        });
    }
};
