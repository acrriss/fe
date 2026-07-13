<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de vinculación (§11, 7d): un partner pide gestionar un RUC
 * que ya existe como cuenta directa; el dueño de la cuenta aprueba o
 * rechaza desde su panel. Al aprobar, el contribuyente pasa a ser
 * gestionado (partner_id) sin perder sus usuarios directos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vinculaciones', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained('partners')->cascadeOnDelete();
            $table->foreignId('contribuyente_id')->constrained('contribuyentes')->cascadeOnDelete();
            $table->string('estado')->default('pendiente')->index();
            $table->dateTime('resuelta_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vinculaciones');
    }
};
