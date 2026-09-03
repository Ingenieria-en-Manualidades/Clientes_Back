<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table metric_usages drop constraint if exists metric_usages_unique_bucket');
        DB::statement('drop index if exists metric_usages_unique_bucket');
        DB::statement('create unique index metric_usages_unique_bucket on metric_usages (date, hour, user_id, cliente_id, role_id, module, submodule, method, route)');
    }

    public function down(): void
    {
        DB::statement('alter table metric_usages drop constraint if exists metric_usages_unique_bucket');
        DB::statement('drop index if exists metric_usages_unique_bucket');
        DB::statement('create unique index metric_usages_unique_bucket on metric_usages (date, hour, user_id, cliente_id, role_id, module, method, route)');
    }
};
