<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Eliminar un dispositivo de la lista de autorizados (borrado lógico): el registro queda oculto para la bitácora y
| para reconocer su llave y bloquearla. Va aparte porque la migración anterior ya se corrió en algunas bases.
| El hasColumn cubre la base donde se corrió la versión anterior de esa migración que ya traía la columna.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dispositivos', 'deleted_at')) {
            Schema::table('dispositivos', fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dispositivos', 'deleted_at')) {
            Schema::table('dispositivos', fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
