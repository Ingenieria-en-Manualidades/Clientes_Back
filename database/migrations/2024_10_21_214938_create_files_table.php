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
        Schema::create('files', function (Blueprint $table) {
            $table->id('files_id');
            $table->string('ruta');
            $table->string('tipo');
            $table->unsignedBigInteger('tablero_sae_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('tablero_sae_id')->references('tablero_sae_id')->on('tablero_sae');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
