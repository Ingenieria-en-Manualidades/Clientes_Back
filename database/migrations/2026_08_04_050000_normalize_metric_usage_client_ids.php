<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            with mapped as (
                select
                    metric_usages.id,
                    clientes.id as normalized_cliente_id
                from metric_usages
                join clientes on metric_usages.cliente_id = clientes.cliente_endpoint_id
                where metric_usages.cliente_id <> clientes.id
            ), duplicate_groups as (
                select
                    min(metric_usages.id) as keep_id,
                    array_remove(array_agg(metric_usages.id), min(metric_usages.id)) as remove_ids,
                    sum(metric_usages.requests_count) as requests_count,
                    sum(metric_usages.errors_count) as errors_count,
                    sum(metric_usages.slow_requests_count) as slow_requests_count,
                    sum(metric_usages.sessions_count) as sessions_count,
                    sum(metric_usages.critical_actions_count) as critical_actions_count,
                    max(metric_usages.last_activity_at) as last_activity_at,
                    max(metric_usages.updated_at) as updated_at
                from metric_usages
                left join mapped on mapped.id = metric_usages.id
                group by
                    metric_usages.date,
                    metric_usages.hour,
                    metric_usages.user_id,
                    coalesce(mapped.normalized_cliente_id, metric_usages.cliente_id),
                    metric_usages.role_id,
                    metric_usages.module,
                    metric_usages.submodule,
                    metric_usages.method,
                    metric_usages.route
                having count(*) > 1
            ), updated_keep_rows as (
                update metric_usages
                set requests_count = duplicate_groups.requests_count,
                    errors_count = duplicate_groups.errors_count,
                    slow_requests_count = duplicate_groups.slow_requests_count,
                    sessions_count = duplicate_groups.sessions_count,
                    critical_actions_count = duplicate_groups.critical_actions_count,
                    last_activity_at = duplicate_groups.last_activity_at,
                    updated_at = duplicate_groups.updated_at
                from duplicate_groups
                where metric_usages.id = duplicate_groups.keep_id
                returning metric_usages.id
            )
            delete from metric_usages
            using duplicate_groups
            where metric_usages.id = any(duplicate_groups.remove_ids)
        SQL);

        DB::statement(<<<'SQL'
            update metric_usages
            set cliente_id = clientes.id,
                updated_at = now()
            from clientes
            where metric_usages.cliente_id = clientes.cliente_endpoint_id
              and metric_usages.cliente_id <> clientes.id
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            update metric_usages
            set cliente_id = clientes.cliente_endpoint_id,
                updated_at = now()
            from clientes
            where metric_usages.cliente_id = clientes.id
              and clientes.cliente_endpoint_id is not null
        SQL);
    }
};
