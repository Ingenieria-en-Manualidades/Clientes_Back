<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_usage_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('fecha_registro');
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('module');
            $table->string('submodule')->default('General');
            $table->string('action')->default('Consultar');
            $table->string('method', 10);
            $table->string('route');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('is_error')->default(false);
            $table->boolean('is_slow')->default(false);
            $table->boolean('is_critical')->default(false);
            $table->timestamps();

            $table->index(['date', 'module']);
            $table->index(['date', 'user_id']);
            $table->index(['date', 'cliente_id']);
            $table->index(['fecha_registro']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_usage_events');
    }
};
