<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class ExportWaDecStart extends Command
{
    protected $signature = 'survey:wa-dec-start {--limit=0} {--dry}';
    protected $description = 'CSV WhatsApp – Inicio Diciembre';

    public function handle(CampaignService $svc): int
    {
        $limit = (int)$this->option('limit');
        $dry   = (bool)$this->option('dry');

        $path = $svc->exportWaDecStart($limit, $dry);
        $this->info($dry ? 'DRY RUN listo.' : "Generado: {$path}");
        return self::SUCCESS;
    }
}
