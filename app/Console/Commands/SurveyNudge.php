<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class SurveyNudge extends Command
{
    protected $signature = 'survey:nudge {wave : day3|day7|thanks|day14|nov|dec_mid}
                            {--campaign=rebranding-2025} {--limit=0} {--dry} {--op=}';
    protected $description = 'Envía el recordatorio por wave';

    public function handle(CampaignService $svc): int
    {
        $wave     = strtolower((string)$this->argument('wave'));
        if (!in_array($wave, ['day3','day7','thanks','day14','nov','dec_mid'], true)) {
            $this->error("Wave inválida: {$wave}");
            return self::INVALID;
        }

        $campaign = (string)$this->option('campaign');
        $limit    = (int)$this->option('limit');
        $dry      = (bool)$this->option('dry');
        $forceOp  = $this->option('op') ? (string)$this->option('op') : null;

        $r = $svc->sendNudge($wave, $campaign, $limit, $dry, $forceOp);
        $this->info("Wave {$wave}: enviados {$r['sent']}, omitidos {$r['skipped']} de {$r['total']}".($dry ? ' (DRY)' : ''));

        return self::SUCCESS;
    }
}
