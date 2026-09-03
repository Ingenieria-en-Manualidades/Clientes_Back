<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('metric_usages')
            ->where('module', 'Administracion')
            ->whereIn('submodule', ['Usuarios', 'Roles', 'Clientes', 'Politicas'])
            ->update([
                'module' => DB::raw('submodule'),
                'submodule' => 'Administracion',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('metric_usages')
            ->where('submodule', 'Administracion')
            ->whereIn('module', ['Usuarios', 'Roles', 'Clientes', 'Politicas'])
            ->update([
                'submodule' => DB::raw('module'),
                'module' => 'Administracion',
                'updated_at' => now(),
            ]);
    }
};
