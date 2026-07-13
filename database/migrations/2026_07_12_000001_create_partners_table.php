<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner/plataforma integradora (§11 del plan): un sistema tercero (POS,
 * ERP…) que aprovisiona contribuyentes y emite en su nombre con una sola
 * credencial (token Sanctum sobre este modelo). El partner es el cliente
 * de pago: la cuota mensual es un pool compartido por sus contribuyentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nombre');
            $table->string('slug')->unique();
            // null = sin cuota (ilimitado)
            $table->unsignedInteger('cuota_mensual')->nullable();
            $table->unsignedInteger('limite_por_minuto')->default(60);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
