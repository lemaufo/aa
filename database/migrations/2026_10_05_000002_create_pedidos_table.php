<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();            // folio del traspaso en Expertos
            $table->string('folio_venta')->nullable();    // ticket de la venta de mostrador (Microsip)
            $table->string('estatus')->index();
            $table->string('sucursal')->index();
            $table->string('cliente');
            $table->string('material');
            $table->decimal('metros_traspaso', 10, 2);
            $table->decimal('peso_por_metro', 8, 3);      // kg por metro lineal, del catálogo del artículo
            $table->text('instrucciones')->nullable();    // lo que el cajero escribió en la descripción de Expertos
            $table->string('forma_entrega')->nullable();  // cedis | cliente | sucursal
            $table->string('telefono')->nullable();         // teléfono del cliente, capturado en caja
            $table->string('ubicacion_cedis')->nullable();  // rack o zona donde queda el material en CEDIS
            $table->json('cortes_solicitados')->nullable();
            $table->json('cortes_producidos')->nullable();
            $table->timestamp('captura_validada_at')->nullable();
            $table->string('direccion_entrega')->nullable();
            $table->string('gps_link')->nullable();
            $table->decimal('peso_teorico', 10, 2)->nullable();
            $table->decimal('peso_real', 10, 2)->nullable();
            $table->string('evidencia_path')->nullable();
            $table->string('camion')->nullable();
            $table->string('repartidor')->nullable();
            $table->string('documento_tipo')->nullable();
            $table->string('documento_numero')->nullable();
            $table->string('recibe_nombre')->nullable();
            $table->string('motivo_cancelacion')->nullable();
            $table->boolean('venta_cerrada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};