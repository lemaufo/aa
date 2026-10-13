<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->date('fecha_prometida')->nullable()->index()->after('forma_entrega');
            $table->timestamp('ticket_validado_at')->nullable();
            $table->timestamp('etiqueta_impresa_at')->nullable();
            $table->decimal('peso_produccion', 10, 2)->nullable();   // báscula de producción (paso 2)
            $table->string('lote_rollo', 60)->nullable();            // rollo o lote de materia prima
            $table->string('evidencia_bascula_path')->nullable();    // foto de la báscula (paso 3)
            $table->string('acuse_path')->nullable();                // foto del acuse firmado
            $table->string('venta_sync_estado', 10)->nullable();     // null | ok | error
            $table->string('venta_sync_error')->nullable();
            $table->timestamp('venta_sync_at')->nullable();
            $table->boolean('cancelado_por_expertos')->default(false);
        });

        // Lo que antes era "venta cerrada a mano" ahora es el estado de la sincronización.
        DB::table('pedidos')->where('venta_cerrada', true)->update(['venta_sync_estado' => 'ok']);

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn(['cortes_producidos', 'captura_validada_at', 'venta_cerrada']);
        });

        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_hash', 64)->nullable()->unique();
            $table->timestamp('codigo_expira_at')->nullable();
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('activado_at')->nullable();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamp('revocado_at')->nullable();
            $table->timestamps();
        });

        // Simulación de Microsip / Expertos para la demo. Con AA_ORIGEN=firebird dejan de usarse.
        Schema::create('demo_ventas', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->string('estado', 12)->default('pagada');   // pagada | pendiente | cancelada
            $table->string('articulo');
            $table->unsignedSmallInteger('lineas_ajenas')->default(0); // renglones que no son el pedido especial
            $table->boolean('entregada')->default(false);
            $table->string('entregada_por')->nullable();
            $table->timestamp('entregada_at')->nullable();
            $table->timestamps();
        });

        Schema::create('demo_traspasos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->boolean('cancelado')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_traspasos');
        Schema::dropIfExists('demo_ventas');
        Schema::dropIfExists('dispositivos');

        Schema::table('pedidos', function (Blueprint $table) {
            $table->json('cortes_producidos')->nullable();
            $table->timestamp('captura_validada_at')->nullable();
            $table->boolean('venta_cerrada')->default(false);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_prometida', 'ticket_validado_at', 'etiqueta_impresa_at', 'peso_produccion', 'lote_rollo',
                'evidencia_bascula_path', 'acuse_path', 'venta_sync_estado', 'venta_sync_error', 'venta_sync_at',
                'cancelado_por_expertos',
            ]);
        });
    }
};