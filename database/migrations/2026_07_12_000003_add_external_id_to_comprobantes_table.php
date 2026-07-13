<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trazabilidad del sistema origen (§11): el integrador adjunta su propio
 * identificador (y metadatos libres) a cada emisión para reconciliar los
 * comprobantes contra sus registros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('ride_path');
            $table->json('metadata')->nullable()->after('external_id');
            $table->index(['contribuyente_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex(['contribuyente_id', 'external_id']);
            $table->dropColumn(['external_id', 'metadata']);
        });
    }
};
