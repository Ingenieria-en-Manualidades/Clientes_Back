<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SurveyPhase2 extends Command
{
    protected $signature = 'survey:phase2 {day : 3|7|10} {--campaign=rebranding-2025} {--limit=0} {--dry}';
    protected $description = 'Fase 2: envíos día 3, 7 y 10 (gracias)';

    public function handle(): int
    {
        $day = (string)$this->argument('day');
        $map = ['3'=>'day3','7'=>'day7','10'=>'thanks'];
        if (!isset($map[$day])) {
            $this->error("Día inválido ({$day}). Usa 3, 7 o 10.");
            return self::INVALID;
        }

        $args = [
            'wave' => $map[$day],
            '--campaign' => (string)$this->option('campaign'),
            '--limit' => (int)$this->option('limit'),
            '--dry' => (bool)$this->option('dry'),
        ];

        $this->call('survey:nudge', $args);
        return self::SUCCESS;
    }
}
