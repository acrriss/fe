<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de emisiones de comprobantes electrónicos.
 *
 * Reemplaza a la tabla homónima del legado. Notas de diseño:
 *  - `uuid` es el identificador público de la API (el id autoincremental
 *    nunca se expone).
 *  - los comprobantes pertenecen al contribuyente emisor (dueño del RUC).
 *  - `clave_acceso` es única: la ficha del SRI (§5.10) exige reutilizar la
 *    misma clave al reintentar un comprobante rechazado, y además es el
 *    número de autorización (§5.9).
 *  - `mensajes` guarda las advertencias/errores que devuelve el SRI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tipo');
            $table->string('estado')->default('pendiente')->index();
            $table->string('ambiente', 1);
            $table->string('clave_acceso', 49)->unique()->nullable();
            $table->string('ruc', 13)->index();
            $table->string('razon_social');
            $table->string('secuencial', 9);
            $table->decimal('importe_total', 12, 2)->nullable();
            $table->string('numero_autorizacion', 49)->nullable();
            $table->dateTime('autorizado_en')->nullable();
            $table->json('mensajes')->nullable();
            $table->string('xml_path')->nullable();
            $table->string('ride_path')->nullable();
            $table->date('emitido_en')->nullable();
            $table->foreignId('contribuyente_id')->nullable()->constrained('contribuyentes')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
