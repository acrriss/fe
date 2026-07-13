<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Endpoints de webhook (§11): URLs a las que el servicio notifica eventos
 * (comprobante autorizado/devuelto/…, certificado por vencer). El
 * suscriptor es polimórfico: un Partner (recibe los eventos de TODOS sus
 * contribuyentes gestionados) o un Contribuyente (solo los suyos). El
 * secreto firma cada entrega (HMAC-SHA256) y se guarda cifrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->morphs('suscriptor');
            $table->string('url');
            $table->text('secreto');
            $table->json('eventos');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
