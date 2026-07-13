<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de entregas de webhook (§11): cada evento publicado a cada
 * endpoint deja una entrega consultable con su resultado (estado, código
 * HTTP, error, intentos). El job de envío la actualiza en cada intento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_entregas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('webhook_endpoint_id')->constrained('webhook_endpoints')->cascadeOnDelete();
            $table->string('evento')->index();
            $table->json('payload');
            $table->string('estado')->default('pendiente')->index();
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->unsignedSmallInteger('codigo_http')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('entregado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_entregas');
    }
};
