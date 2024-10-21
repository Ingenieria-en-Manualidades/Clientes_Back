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
            $table->integer('checklist');
            $table->integer('inspeccion');
            $table->unsignedBigInteger('meta_id');
            $table->softDeletes();
            $table->timestamps();
            $table->foreign('meta_id')->references('meta_id')->on('meta');
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
