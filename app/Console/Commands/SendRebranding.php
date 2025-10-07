<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class SendRebranding extends Command
{
    protected $signature = 'rebranding:send {--campaign=rebranding-2025} {--limit=0} {--dry}';
    protected $description = 'Enviar correo de lanzamiento (Día 0)';

    public function handle(CampaignService $svc)
    {
        $campaign = (string)$this->argument('campaign');
        $limit    = (int)$this->option('limit');
        $dry      = (bool)$this->option('dry');

        $res = $svc->sendRebranding($campaign, $limit, $dry);
        $this->info("Enviados: {$res['sent']} / Total: {$res['total']} " . ($dry ? '(dry-run)' : ''));
        return self::SUCCESS;
    }
}
