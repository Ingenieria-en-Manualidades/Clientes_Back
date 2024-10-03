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
        Schema::create('produccion', function (Blueprint $table) {
            $table->id('produccion_id');
            $table->timestamp('fecha_produccion');
            $table->integer('planificada');
            $table->integer('modificada')->nullable();
            $table->integer('plan_armado')->nullable();
            $table->integer('calidad')->nullable();
            $table->integer('desperfecto_me')->nullable();
            $table->integer('desperfecto_pp')->nullable();
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
        Schema::dropIfExists('produccion');
    }
};
