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
        Schema::create('unidades_diarias', function (Blueprint $table) {
            $table->id('unidades_diarias_id');
            $table->integer('valor');
            $table->date('fecha_programacion');
            $table->tinyInteger('actualizaciones')->nullable();
            $table->unsignedBigInteger('meta_unidades_id');
            $table->softDeletes();
            $table->timestamps();
            $table->string('usuario');
            $table->char('activo', 1)->default('s');
            $table->foreign('meta_unidades_id')->references('meta_unidades_id')->on('meta_unidades');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades_diarias');
    }
};
