<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port modernizado de la tabla `comprobantes` del legado
 * (legacy/database/migrations/2019_05_16_163942_create_comprobantes_table.php).
 *
 * Cambios respecto al legado:
 *  - PK estándar `id` (antes `idcomprobantes`).
 *  - `user_id` con convención Laravel (antes `iduser_fk`).
 *  - `importe_total` decimal (antes string).
 *  - snake_case en columnas (antes camelCase).
 *  - columnas nuevas: `clave_acceso` (única) y `estado`, que el rediseño
 *    necesita para el flujo asíncrono (pendiente → recibido → autorizado…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->string('clave_acceso', 49)->unique()->nullable();
            $table->string('estado')->default('pendiente');
            $table->string('ruc', 13);
            $table->string('razon_social');
            $table->decimal('importe_total', 12, 2)->nullable();
            $table->string('xml_path')->nullable();
            $table->string('ride_path')->nullable();
            $table->dateTime('emitido_en')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
