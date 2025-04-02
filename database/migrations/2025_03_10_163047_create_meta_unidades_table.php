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
        Schema::create('meta_unidades', function (Blueprint $table) {
            $table->id('meta_unidades_id');
            $table->integer('valor');
            $table->date('fecha_meta');
            $table->tinyInteger('actualizaciones')->nullable();
            $table->unsignedBigInteger('clientes_id');
            $table->softDeletes();
            $table->timestamps();
            $table->string('usuario');
            $table->char('activo', 1)->default('s');
            $table->foreign('clientes_id')->references('id')->on('clientes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_unidades');
    }
};
