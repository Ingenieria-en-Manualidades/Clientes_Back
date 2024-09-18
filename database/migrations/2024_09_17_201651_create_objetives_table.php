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
            $table->id();
            $table->string('cumplimiento');
            $table->string('eficiencia_productiva');
            $table->string('calidad');
            $table->string('desperdicio_me');
            $table->string('desperdicio_pp');
            $table->timestamps();
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
