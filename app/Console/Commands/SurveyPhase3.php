<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SurveyPhase3 extends Command
{
    protected $signature = 'survey:phase3 {slot : nov|dic}
                            {--campaign=rebranding-2025} {--limit=0} {--dry} {--no-wa} {--no-calls}';
    protected $description = 'Fase 3: Nov (email+WA) y Dic (WA+email+llamadas)';

    public function handle(): int
    {
        $slot     = strtolower((string)$this->argument('slot'));
        $campaign = (string)$this->option('campaign');
        $limit    = (int)$this->option('limit');
        $dry      = (bool)$this->option('dry');
        $noWa     = (bool)$this->option('no-wa');
        $noCalls  = (bool)$this->option('no-calls');

        if ($slot === 'nov') {
            $this->call('survey:nudge', ['wave'=>'nov','--campaign'=>$campaign,'--limit'=>$limit,'--dry'=>$dry]);
            if (!$noWa) $this->call('survey:wa-nov', ['--limit'=>$limit,'--dry'=>$dry]);
            return self::SUCCESS;
        }

        if ($slot === 'dic') {
            if (!$noWa) $this->call('survey:wa-dec-start', ['--limit'=>$limit,'--dry'=>$dry]);
            $this->call('survey:nudge', ['wave'=>'dec_mid','--campaign'=>$campaign,'--limit'=>$limit,'--dry'=>$dry]);
            if (!$noCalls) $this->call('survey:calls-dec-mid', ['--limit'=>$limit,'--dry'=>$dry]);
            return self::SUCCESS;
        }

        $this->error('Slot inválido. Usa: nov | dic');
        return self::INVALID;
    }
}
