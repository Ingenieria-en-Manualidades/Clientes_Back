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
        Schema::table('users', function (Blueprint $table) {
            $table->date('reset_password')->nullable();
            $table->unsignedBigInteger('empleado_id')->nullable();
            $table->foreign('empleado_id')->references('empleado_id')->on('public.empleado')->onUpdate('cascade')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['empleado_id']);
            $table->dropColumn(['empleado_id', 'reset_password']);
        });
    }
};
