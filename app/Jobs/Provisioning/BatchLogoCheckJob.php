<?php

namespace App\Jobs\Provisioning;

use App\Models\Site;
use App\Services\NanaBananaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job di polling schedulato ogni 5 minuti dallo Scheduler.
 *
 * Controlla tutti i siti con logo_status = 'batch_pending'
 * e per ognuno verifica se il batch job Gemini è completato.
 *
 * Flusso:
 *   - SUCCEEDED → scarica il PNG, applica a WordPress, aggiorna logo_status = 'done'
 *   - FAILED    → aggiorna logo_status = 'failed', logga errore
 *   - in corso  → non fa nulla, riproverà al prossimo ciclo
 *
 * Timeout batch Gemini: max 24h. Dopo 25h consideriamo il job scaduto.
 */
class BatchLogoCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 120;

    public function handle(): void
    {
        $sites = Site::where('logo_status', 'batch_pending')
            ->whereNotNull('logo_batch_job')
            ->get();

        if ($sites->isEmpty()) {
            return;
        }

        Log::info("BatchLogoCheckJob: controllo {$sites->count()} siti in batch_pending");

        $nanaBanana = new NanaBananaService();

        foreach ($sites as $site) {
            $this->checkSite($site, $nanaBanana);
        }
    }

    private function checkSite(Site $site, NanaBananaService $nanaBanana): void
    {
        // Scadenza: se il job è in pending da più di 25h, marcalo come fallito
        if ($site->updated_at->diffInHours(now()) > 25) {
            Log::warning("BatchLogoCheckJob: job scaduto (>25h) per site #{$site->id} → {$site->logo_batch_job}");
            $site->update([
                'logo_status'    => 'failed',
                'logo_batch_job' => null,
            ]);
            return;
        }

        $result = $nanaBanana->checkBatchJob($site->logo_batch_job, $site->id);

        if ($result === null) {
            // Ancora in corso — riprova al prossimo ciclo (5 min)
            Log::debug("BatchLogoCheckJob: site #{$site->id} ancora in elaborazione");
            return;
        }

        if ($result === false) {
            // Job fallito da Gemini
            Log::error("BatchLogoCheckJob: batch job fallito per site #{$site->id}");
            $site->update([
                'logo_status'    => 'failed',
                'logo_batch_job' => null,
            ]);
            return;
        }

        // $result = path locale del PNG — applica a WordPress
        $applied = $nanaBanana->applyLogoToWordPress($site, $result);

        $site->update([
            'logo_url'          => $applied ? 'wp-content/uploads/' . basename($result) : null,
            'logo_generated_at' => now(),
            'logo_status'       => $applied ? 'done' : 'failed',
            'logo_batch_job'    => null,
        ]);

        Log::info("BatchLogoCheckJob: site #{$site->id} → " . ($applied ? 'logo applicato ✓' : 'applicazione fallita ✗'));
    }
}
