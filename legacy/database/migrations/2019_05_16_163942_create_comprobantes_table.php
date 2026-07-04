<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateComprobantesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->bigIncrements('idcomprobantes');
            $table->string('tipo')->nullable();
            $table->string('ruc')->nullable();
            $table->string('razonSocial')->nullable();
            $table->string('importeTotal')->nullable();
            $table->string('xml')->nullable();
            $table->string('ride')->nullable();
            $table->datetime('dcompra')->nullable();
            $table->unsignedBigInteger('iduser_fk');
            $table->foreign('iduser_fk')->references('id')->on('users')->onDelete('cascade');
            // $table->unsignedInteger('iduser_fk');
            // $table->foreign('iduser_fk')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('comprobantes');

    }
}
