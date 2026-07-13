<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Claves de idempotencia (§11): permiten al integrador reintentar una
 * emisión (timeout, corte de red) sin riesgo de duplicarla. La clave es
 * única por contribuyente; guarda la huella del request original y la
 * respuesta final para reproducirla en los reintentos. `respuesta` null
 * significa "petición en curso". Expiran a las 24 h (prunable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claves_idempotencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contribuyente_id')->constrained('contribuyentes')->cascadeOnDelete();
            $table->string('clave');
            $table->string('huella', 64);
            $table->unsignedSmallInteger('codigo_http')->nullable();
            $table->longText('respuesta')->nullable();
            $table->timestamps();

            $table->unique(['contribuyente_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claves_idempotencia');
    }
};
