<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada usuario pertenece a un contribuyente (decisión de producto:
 * relación 1:N; un usuario no administra varios contribuyentes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('contribuyente_id')
                ->nullable()
                ->after('id')
                ->constrained('contribuyentes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contribuyente_id');
        });
    }
};
