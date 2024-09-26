<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accidentes', function (Blueprint $table) {
            $table->id('accidentes_id');
            $table->string('tipo_accidente');
            $table->integer('cantidad');
            $table->timestamp('fecha_ingreso')->nullable();
            $table->unsignedBigInteger('tablero_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('tablero_id')->references('tablero_id')->on('tablero_sae');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accidentes');
    }
};