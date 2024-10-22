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
        Schema::create('tablero_sae', function (Blueprint $table) {
            $table->id('tablero_sae_id');
            $table->timestamp('fecha');
            $table->unsignedBigInteger('meta_id');
            $table->unsignedBigInteger('cliente_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('meta_id')->references('meta_id')->on('meta');
            $table->foreign('cliente_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tablero_sae');
    }
};
