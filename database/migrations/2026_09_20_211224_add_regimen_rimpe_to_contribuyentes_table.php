<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régimen RIMPE del emisor (ficha técnica 2.34, Anexo 22): clave del enum
 * RegimenRimpe (`rimpe` | `negocio_popular`), null = régimen general. El
 * pipeline imprime la leyenda literal en <contribuyenteRimpe>.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('regimen_rimpe', 20)->nullable()->after('contribuyente_especial_resolucion');
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropColumn('regimen_rimpe');
        });
    }
};
