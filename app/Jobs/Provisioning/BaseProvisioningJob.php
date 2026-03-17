<?php

namespace App\Jobs\Provisioning;

use App\Models\ProvisioningLog;
use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

abstract class BaseProvisioningJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300;

    protected ProvisioningLog $log;

    public function __construct(protected Site $site)
    {
    }

    /**
     * Ogni job figlio implementa questo metodo con la propria logica.
     */
    abstract protected function execute(): void;

    /**
     * Etichetta leggibile dello step (es. "Creazione vhost ISPConfig").
     */
    abstract protected function stepLabel(): string;

    /**
     * Classe del prossimo job da lanciare in caso di successo.
     * Restituisce null se è l'ultimo step.
     */
    protected function nextJob(): ?string
    {
        return null;
    }

    public function handle(): void
    {
        // Aggiorna lo step corrente nel sito
        $this->site->update(['current_step' => $this->stepLabel()]);

        // Crea il log di provisioning per questo step
        $this->log = ProvisioningLog::create([
            'site_id'    => $this->site->id,
            'job_class'  => static::class,
            'step_label' => $this->stepLabel(),
            'status'     => 'running',
            'started_at' => now(),
        ]);

        try {
            $this->execute();

            $this->log->markSuccess();

            // Lancia il prossimo job se esiste
            if ($next = $this->nextJob()) {
                dispatch(new $next($this->site->fresh()));
            } else {
                // Pipeline completata
                $this->site->update([
                    'status'       => 'active',
                    'current_step' => null,
                ]);
            }

        } catch (\App\Exceptions\ImportDetectedException $e) {
            // Importazione rilevata — non è un errore, pipeline interrotta intenzionalmente
            $this->log->markSuccess($e->getMessage());
            // Lo stato del sito è già stato aggiornato dentro execute()

        } catch (\Throwable $e) {
            Log::error("ProvisioningJob {$this->stepLabel()} fallito per sito #{$this->site->id}: " . $e->getMessage());

            $this->log->markFailed($e->getMessage());

            $this->site->update([
                'status'       => 'error',
                'current_step' => $this->stepLabel() . ' — ERRORE',
            ]);

            // Rilancia per i retry automatici
            throw $e;
        }
    }
}
