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
        Schema::create('calidad', function (Blueprint $table) {
            $table->id('calidad_id');
            $table->string('checklist_mes');
            $table->integer('checklist_calificacion');
            $table->string('inspeccion_mes');
            $table->integer('inspeccion_calificacion');
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
        Schema::dropIfExists('calidad');
    }
};
