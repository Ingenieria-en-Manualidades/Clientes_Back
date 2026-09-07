<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('metric_usages')
            ->where('module', 'Tablero Sae')
            ->whereIn('submodule', ['Metas', 'Cumplimiento Mensual', 'Unidades programadas', 'Cumplimiento Diarios'])
            ->update([
                'module' => DB::raw('submodule'),
                'submodule' => 'Tablero Sae',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('metric_usages')
            ->where('submodule', 'Tablero Sae')
            ->whereIn('module', ['Metas', 'Cumplimiento Mensual', 'Unidades programadas', 'Cumplimiento Diarios'])
            ->update([
                'submodule' => DB::raw('module'),
                'module' => 'Tablero Sae',
                'updated_at' => now(),
            ]);
    }
};
