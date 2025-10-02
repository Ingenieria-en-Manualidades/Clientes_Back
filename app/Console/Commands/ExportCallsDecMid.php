<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class ExportCallsDecMid extends Command
{
    protected $signature = 'survey:calls-dec-mid {--limit=0} {--dry}';
    protected $description = 'CSV Guion de Llamadas – Mitad Diciembre';

    public function handle(CampaignService $svc): int
    {
        $limit = (int)$this->option('limit');
        $dry   = (bool)$this->option('dry');

        $path = $svc->exportCallsDecMid($limit, $dry);
        $this->info($dry ? 'DRY RUN listo.' : "Generado: {$path}");
        return self::SUCCESS;
    }
}
