<?php

namespace App\Console;

use App\Jobs\Audit\SiteAuditJob;
use App\Jobs\Provisioning\BatchLogoCheckJob;
use App\Jobs\Sync\SyncIspConfigDataJob;
use App\Models\Server;
use App\Models\Site;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Sync dati ISPConfig ogni ora per tutti i server attivi
        $schedule->call(function () {
            Server::where('status', 'active')->each(function (Server $server) {
                dispatch(new SyncIspConfigDataJob($server));
            });
        })->hourly()->name('sync-ispconfig')->withoutOverlapping();

        // Polling batch logo ogni 5 minuti (solo se ci sono siti in batch_pending)
        $schedule->job(new BatchLogoCheckJob())
            ->everyFiveMinutes()
            ->name('batch-logo-check')
            ->withoutOverlapping();

        // Audit siti ogni 30 minuti per i siti attivi
        $schedule->call(function () {
            Site::where('status', 'active')->each(function (Site $site) {
                dispatch(new SiteAuditJob($site));
            });
        })->everyThirtyMinutes()->name('audit-sites')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
