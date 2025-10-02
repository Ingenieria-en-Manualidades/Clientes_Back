<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $campaign = 'rebranding-2025';
        $limit    = 0; // 0 = sin límite
        $tz       = config('app.timezone', 'America/Bogota');

        // Día 0 (lanzamiento) – dispara 1 sola vez en la fecha indicada
        $startDate = '2025-10-01'; // <-- ajusta
        $schedule->command("rebranding:send --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)
            ->when(fn () => now($tz)->toDateString() === $startDate);

        // Fase 2 – Día 3, 7, 10 (gracias)
        $schedule->command("survey:phase2 3 --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)->dailyAt('08:30');
        $schedule->command("survey:phase2 7 --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)->dailyAt('08:35');
        $schedule->command("survey:phase2 10 --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)->dailyAt('08:40');

        // Fase 3 – Noviembre (email + CSV WhatsApp)
        $schedule->command("survey:nudge nov --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)->cron('0 9 15 11 *');   // 15/Nov 09:00
        $schedule->command("survey:wa-nov --limit={$limit}")
            ->timezone($tz)->cron('5 9 15 11 *');   // 15/Nov 09:05

        // Fase 3 – Diciembre (WA inicio + email mitad + CSV llamadas)
        $schedule->command("survey:wa-dec-start --limit={$limit}")
            ->timezone($tz)->cron('0 9 1 12 *');    // 1/Dic 09:00
        $schedule->command("survey:nudge dec_mid --campaign={$campaign} --limit={$limit}")
            ->timezone($tz)->cron('0 9 15 12 *');   // 15/Dic 09:00
        $schedule->command("survey:calls-dec-mid --limit={$limit}")
            ->timezone($tz)->cron('5 9 15 12 *');   // 15/Dic 09:05
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php'); // si mantienes closures
    }
}
