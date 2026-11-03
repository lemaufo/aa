<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Copia de lo que dice Microsip (System\CONFIG.FDB, USUARIOS) al asignar el usuario y en cada acceso.
            // SYSDBA no está en USUARIOS: queda con microsip_usuario_id en null.
            $table->unsignedInteger('microsip_usuario_id')->nullable()->unique()->after('sucursal');
            $table->string('microsip_rol')->nullable()->after('microsip_usuario_id');
            $table->boolean('microsip_activo')->nullable()->after('microsip_rol');
            $table->timestamp('microsip_sync_at')->nullable()->after('microsip_activo');
        });

        // El administrador de la app pasa a ser el SYSDBA de Microsip.
        if (! DB::table('users')->where('username', 'sysdba')->exists()) {
            DB::table('users')->where('username', 'admin')->update(['username' => 'sysdba', 'name' => 'SYSDBA']);
        }
    }

    public function down(): void
    {
        if (! DB::table('users')->where('username', 'admin')->exists()) {
            DB::table('users')->where('username', 'sysdba')->update(['username' => 'admin', 'name' => 'Administrador']);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['microsip_usuario_id']);
            $table->dropColumn(['microsip_usuario_id', 'microsip_rol', 'microsip_activo', 'microsip_sync_at']);
        });
    }
};
