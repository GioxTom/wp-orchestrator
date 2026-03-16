<?php

namespace App\Jobs\Provisioning;

use App\Models\Setting;
use App\Models\Site;
use App\Services\NanaBananaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job asincrono, non bloccante.
 * Dispatchato DOPO che il sito è già ACTIVE.
 *
 * Sceglie automaticamente tra:
 * - Generazione SINCRONA: risposta immediata, logo pronto in ~30-60s
 * - Generazione BATCH: invia la richiesta a Gemini e termina.
 *   Il polling è gestito da BatchLogoCheckJob (schedulato ogni 5 min).
 */
class GenerateLogoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(private readonly Site $site)
    {
    }

    public function handle(): void
    {
        $site = $this->site->fresh();

        if (! $site || $site->status !== 'active') {
            return;
        }

        // Segna subito come pending per evitare doppi dispatch
        $site->update(['logo_status' => 'pending']);

        $nanaBanana = new NanaBananaService();
        $useBatch   = Setting::get('gemini_use_batch', '0') === '1';

        if ($useBatch) {
            $this->handleBatch($site, $nanaBanana);
        } else {
            $this->handleSync($site, $nanaBanana);
        }
    }

    // ── Generazione sincrona ──────────────────────────────────────────────────

    private function handleSync(Site $site, NanaBananaService $nanaBanana): void
    {
        $localPngPath = $nanaBanana->generateLogo($site);

        if (! $localPngPath) {
            Log::warning("GenerateLogoJob [sync]: nessun logo generato per site #{$site->id}");
            $site->update(['logo_status' => 'failed']);
            return;
        }

        $applied = $nanaBanana->applyLogoToWordPress($site, $localPngPath);

        $site->update([
            'logo_url'          => $applied ? 'wp-content/uploads/' . basename($localPngPath) : null,
            'logo_generated_at' => now(),
            'logo_status'       => $applied ? 'done' : 'failed',
            'logo_batch_job'    => null,
        ]);

        Log::info("GenerateLogoJob [sync]: " . ($applied ? 'done' : 'failed') . " per site #{$site->id}");
    }

    // ── Generazione batch ─────────────────────────────────────────────────────

    private function handleBatch(Site $site, NanaBananaService $nanaBanana): void
    {
        $jobName = $nanaBanana->submitBatchLogoRequest($site);

        if (! $jobName) {
            Log::warning("GenerateLogoJob [batch]: submit fallito per site #{$site->id}, fallback sincrono");

            // Fallback automatico al sincrono se il batch fallisce all'invio
            $this->handleSync($site, $nanaBanana);
            return;
        }

        // Salva il job name — il polling lo gestisce BatchLogoCheckJob
        $site->update([
            'logo_batch_job' => $jobName,
            'logo_status'    => 'batch_pending',
        ]);

        Log::info("GenerateLogoJob [batch]: job inviato → {$jobName} per site #{$site->id}");
    }
}
