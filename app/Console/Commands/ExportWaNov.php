<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class ExportWaNov extends Command
{
    protected $signature = 'survey:wa-nov {--limit=0} {--dry}';
    protected $description = 'CSV WhatsApp – Noviembre';

    public function handle(CampaignService $svc): int
    {
        $limit = (int)$this->option('limit');
        $dry   = (bool)$this->option('dry');

        $path = $svc->exportWaNov($limit, $dry);
        $this->info($dry ? 'DRY RUN listo.' : "Generado: {$path}");
        return self::SUCCESS;
    }
}
