<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sublímite mensual dentro de la cuota pool del partner (§11, 7d): tope
 * de emisiones de UN contribuyente gestionado, para que un cliente no
 * agote el pool de todos. Null = sin sublímite (solo aplica el pool).
 * En cuentas directas se ignora (rige el plan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->unsignedInteger('limite_mensual')->nullable()->after('partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropColumn('limite_mensual');
        });
    }
};
