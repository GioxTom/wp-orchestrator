<?php

namespace App\Jobs\Provisioning;

use App\Services\IspConfigService;
use App\Services\WpCliService;

class CreateIspConfigDomainJob extends BaseProvisioningJob
{
    protected function stepLabel(): string
    {
        return 'Verifica e creazione vhost ISPConfig';
    }

    protected function nextJob(): ?string
    {
        return CreateIspConfigDatabaseJob::class;
    }

    protected function execute(): void
    {
        $server     = $this->site->server;
        $client     = $this->site->ispConfigClient;
        $phpVersion = $this->site->phpVersion;
        $ispConfig  = new IspConfigService($server);

        try {
            // Verifica se il dominio esiste già in ISPConfig
            $existingDomainId = $ispConfig->findDomainByName($this->site->domain);

            if ($existingDomainId) {
                // ── Dominio già esistente → importa ──────────────────────────
                $this->log->update(['output' => "Dominio già presente in ISPConfig (ID: {$existingDomainId}). Avvio importazione."]);

                $docroot = $ispConfig->getDomainDocroot($existingDomainId);

                $this->site->update([
                    'ispconfig_domain_id' => $existingDomainId,
                    'docroot'             => $docroot,
                ]);

                // Verifica se WordPress è già installato
                $connection  = $server->connection();
                $wpCli       = new WpCliService($connection);
                $wpInstalled = $wpCli->isInstalled($docroot);

                if ($wpInstalled) {
                    // WP esiste → importa e vai ACTIVE, salta resto pipeline
                    $this->log->update(['output' => $this->log->output . "\nWordPress trovato — avvio importazione."]);
                    $this->handleWpImport();
                } else {
                    // WP non esiste → salta solo questo step, prosegui pipeline
                    $this->log->update(['output' => $this->log->output . "\nWordPress non trovato — proseguo con installazione."]);
                    // nextJob() → CreateIspConfigDatabaseJob si occuperà del DB
                }

            } else {
                // ── Dominio non esiste → crea normalmente ────────────────────
                $domainId = $ispConfig->createWebDomain([
                    'client_id'           => $client->ispconfig_client_id,
                    'domain'              => $this->site->domain,
                    'ssl'                 => $this->site->ssl_enabled ? 'y' : 'n',
                    'ssl_letsencrypt'     => $this->site->ssl_enabled ? 'y' : 'n',
                    'php'                 => 'php-fpm',
                    'php_fpm_use_socket'  => 'y',
                    'fastcgi_php_version' => $phpVersion?->version ?? '',
                ]);

                $this->site->update(['ispconfig_domain_id' => $domainId]);

                $this->waitForDocroot($this->site->domain);

                $docroot = $this->resolveDocroot($this->site->domain);
                $this->site->update(['docroot' => $docroot]);
            }

        } finally {
            $ispConfig->disconnect();
        }
    }

    /**
     * WordPress già installato → importa e interrompi la pipeline normale.
     * Verifica se c'è un blueprint selezionato e gestisce di conseguenza.
     */
    private function handleWpImport(): void
    {
        $site = $this->site->fresh();

        if ($site->blueprint_id) {
            // C'è un blueprint selezionato → segna che serve conferma
            // Il blueprint NON viene applicato automaticamente
            $site->update([
                'status'       => 'import_blueprint_pending',
                'current_step' => 'In attesa di conferma applicazione blueprint',
            ]);

            // Il log lo gestirà il pannello con l'azione di conferma
            // Non dispatchiamo ImportWordPressJob qui — aspettiamo conferma utente
        } else {
            // Nessun blueprint → importa direttamente
            $site->update(['status' => 'provisioning']);
            dispatch(new ImportWordPressJob($site));
        }

        // In entrambi i casi interrompiamo la pipeline normale
        // sovrascrivo nextJob per questo run impostando il sito
        // (BaseProvisioningJob lancia nextJob solo se il site è ancora in provisioning)
        throw new \App\Exceptions\ImportDetectedException(
            "Dominio esistente rilevato — avviata procedura di importazione."
        );
    }

    private function waitForDocroot(string $domain): void
    {
        $docroot  = $this->resolveDocroot($domain);
        $attempts = 0;

        while (! is_dir($docroot) && $attempts < 15) {
            sleep(2);
            $attempts++;
        }

        if (! is_dir($docroot)) {
            throw new \RuntimeException("Docroot {$docroot} non creato da ISPConfig dopo 30 secondi");
        }
    }

    private function resolveDocroot(string $domain): string
    {
        foreach (glob('/var/www/clients/client*/web*/web') ?: [] as $dir) {
            $settingsFile = dirname($dir) . '/domain';
            if (file_exists($settingsFile) && trim(file_get_contents($settingsFile)) === $domain) {
                return $dir;
            }
        }

        return "/var/www/{$domain}/web";
    }
}
