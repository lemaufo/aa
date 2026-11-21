<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Código inicial (AA_DISPOSITIVOS_CODIGO_INICIAL): SYSDBA autoriza la primera máquina sin consola. El dispositivo
| que se crea guarda el hash del código; así se sabe que ya se usó (sirve una sola vez) sin guardarlo en claro.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->string('codigo_inicial_hash', 64)->nullable()->unique()->after('codigo_expira_at');
        });
    }

    public function down(): void
    {
        Schema::table('dispositivos', function (Blueprint $table) {
            $table->dropUnique(['codigo_inicial_hash']);
            $table->dropColumn('codigo_inicial_hash');
        });
    }
};
