<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un contribuyente aprovisionado por un partner le pertenece (tenancy del
 * plano on-behalf) y no necesita plan propio: consume la cuota pool del
 * partner. Las cuentas directas siguen con partner_id null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->foreignId('partner_id')
                ->nullable()
                ->after('plan_id')
                ->constrained('partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contribuyentes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('partner_id');
        });
    }
};
