<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Designaciones del emisor que el SRI obliga a imprimir como leyenda en
 * cada comprobante (ficha técnica 2.34): número de resolución de agente de
 * retención (Anexo 21) y de contribuyente especial (Tabla 11, fila 8).
 * Null = no designado; el pipeline las inyecta en cada emisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('agente_retencion_resolucion', 8)->nullable()->after('dir_matriz');
            $table->string('contribuyente_especial_resolucion', 13)->nullable()->after('agente_retencion_resolucion');
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropColumn(['agente_retencion_resolucion', 'contribuyente_especial_resolucion']);
        });
    }
};
