<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resolución que califica al emisor como Gran Contribuyente (ficha técnica
 * 2.34, Anexo 24). Va como campo adicional del comprobante, no como tag
 * propio; null = no calificado. Ancho 300, el tope del campo adicional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('gran_contribuyente_resolucion', 300)->nullable()->after('regimen_rimpe');
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropColumn('gran_contribuyente_resolucion');
        });
    }
};
