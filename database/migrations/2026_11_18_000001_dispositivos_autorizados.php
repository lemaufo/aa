<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| "Equipos autorizados" pasa a "Dispositivos autorizados": cada dispositivo tiene MAC y un estatus
| activo | suspendido | baja (eliminar de la lista va en la migración siguiente). Los equipos revocados de antes
| quedan en baja. La activación por código y cookie
| se conserva. Los movimientos van a una bitácora propia (no a la de pedidos).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            // Normalizada AA:BB:CC:DD:EE:FF. Nullable solo para los equipos dados de alta antes de este cambio.
            // No es índice único: una MAC en Baja se puede volver a dar de alta. La app impide duplicados
            // entre los dispositivos activos y suspendidos.
            $table->string('mac', 17)->nullable()->index()->after('nombre');
            $table->string('estatus', 12)->default('activo')->index()->after('mac');
        });

        DB::table('dispositivos')->whereNotNull('revocado_at')->update(['estatus' => 'baja']);

        Schema::create('dispositivo_eventos', function (Blueprint $table) {
            $table->id();
            // Sin dispositivo: intentos de entrar al apartado sin permiso.
            $table->foreignId('dispositivo_id')->nullable()->constrained('dispositivos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('accion');
            $table->string('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['dispositivo_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivo_eventos');

        DB::table('dispositivos')->where('estatus', 'baja')->whereNull('revocado_at')->update(['revocado_at' => now()]);

        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropIndex(['mac']);
            $table->dropIndex(['estatus']);
            $table->dropColumn(['mac', 'estatus']);
        });
    }
};
