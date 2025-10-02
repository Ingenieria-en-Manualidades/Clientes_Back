<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class SendRebranding extends Command
{
    protected $signature = 'rebranding:send {--campaign=rebranding-2025} {--limit=0} {--dry}';
    protected $description = 'Enviar correo de lanzamiento (Día 0)';

    public function handle(CampaignService $svc): int
    {
        $campaign = (string)$this->option('campaign');
        $limit    = (int)$this->option('limit');
        $dry      = (bool)$this->option('dry');

        $result = $svc->sendRebranding($campaign, $limit, $dry);
        $this->info("Día 0: procesados {$result['sent']} / {$result['total']}".($dry ? ' (DRY)' : ''));

        return self::SUCCESS;
    }
}
