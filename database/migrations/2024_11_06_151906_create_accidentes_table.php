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
            $table->unsignedBigInteger('objetivos_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('objetivos_id')->references('objetivos_id')->on('objetivos');
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
