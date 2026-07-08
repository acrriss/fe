<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadatos del certificado de firma, extraídos al validarlo en la carga:
 * permiten mostrar titular/vigencia en el panel y avisar del vencimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->string('certificado_titular')->nullable()->after('certificado_clave');
            $table->string('certificado_emisor')->nullable()->after('certificado_titular');
            $table->timestamp('certificado_valido_hasta')->nullable()->after('certificado_emisor');
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropColumn(['certificado_titular', 'certificado_emisor', 'certificado_valido_hasta']);
        });
    }
};
