<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El contribuyente es el emisor de los comprobantes: dueño del RUC, del
 * certificado de firma (cifrado en reposo) y del logo del RIDE. Tiene uno
 * o varios usuarios y pertenece a un plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribuyentes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ruc', 13)->unique();
            $table->string('razon_social');
            $table->string('nombre_comercial')->nullable();
            $table->string('dir_matriz')->nullable();
            $table->string('logo_path')->nullable();
            // certificado .p12 (base64) y su clave, cifrados con APP_KEY
            $table->text('certificado_p12')->nullable();
            $table->text('certificado_clave')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribuyentes');
    }
};
