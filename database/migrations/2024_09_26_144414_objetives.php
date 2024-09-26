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
        Schema::create('objetives', function (Blueprint $table) {
            $table->id('objetives_id');
            $table->string('cumplimiento');
            $table->string('eficiencia_productiva');
            $table->string('calidad');
            $table->string('desperdicio_me');
            $table->string('desperdicio_pp');
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
        Schema::dropIfExists('objetives');
    }
};
