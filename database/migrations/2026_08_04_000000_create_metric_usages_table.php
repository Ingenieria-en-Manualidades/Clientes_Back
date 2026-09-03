<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_usages', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('module');
            $table->string('submodule')->default('General');
            $table->string('method', 10);
            $table->string('route');
            $table->string('action')->default('Consultar');
            $table->unsignedInteger('requests_count')->default(0);
            $table->unsignedInteger('errors_count')->default(0);
            $table->unsignedInteger('slow_requests_count')->default(0);
            $table->unsignedInteger('sessions_count')->default(0);
            $table->unsignedInteger('critical_actions_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['date', 'hour', 'user_id', 'cliente_id', 'role_id', 'module', 'submodule', 'method', 'route'], 'metric_usages_unique_bucket');
            $table->index(['date', 'module']);
            $table->index(['date', 'user_id']);
            $table->index(['date', 'cliente_id']);
            $table->index(['date', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_usages');
    }
};
