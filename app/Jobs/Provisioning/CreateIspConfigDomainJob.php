<?php

namespace App\Jobs\Provisioning;

use App\Services\IspConfigService;

class CreateIspConfigDomainJob extends BaseProvisioningJob
{
    protected function stepLabel(): string
    {
        return 'Creazione vhost ISPConfig + SSL';
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

        $ispConfig = new IspConfigService($server);

        try {
            $domainId = $ispConfig->createWebDomain([
                'client_id'          => $client->ispconfig_client_id,
                'domain'             => $this->site->domain,
                'ssl'                => $this->site->ssl_enabled ? 'y' : 'n',
                'ssl_letsencrypt'    => $this->site->ssl_enabled ? 'y' : 'n',
                'php'                => 'php-fpm',
                'php_fpm_use_socket' => 'y',
                'fastcgi_php_version' => $phpVersion?->version ?? '',
            ]);

            // Salva l'ID del dominio ISPConfig per operazioni future
            $this->site->update(['ispconfig_domain_id' => $domainId]);

            // Attende che ISPConfig scriva il docroot su disco (max 30s)
            $this->waitForDocroot($this->site->domain);

            // Recupera e salva il docroot effettivo
            $docroot = $this->resolveDocroot($this->site->domain);
            $this->site->update(['docroot' => $docroot]);

        } finally {
            $ispConfig->disconnect();
        }
    }

    /**
     * Aspetta che ISPConfig crei fisicamente il docroot sul filesystem.
     */
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
        // ISPConfig standard path: /var/www/clients/client{N}/web{N}/web
        // Cerca nei path standard
        $patterns = [
            "/var/www/clients/client*/web*/web",
        ];

        foreach ($patterns as $pattern) {
            $dirs = glob($pattern . "/../settings.json") ?: [];
            foreach ($dirs as $settings) {
                $data = json_decode(file_get_contents($settings), true);
                if (($data['domain'] ?? '') === $domain) {
                    return dirname($settings) . '/web';
                }
            }
        }

        // Fallback: path derivata dal dominio
        return "/var/www/{$domain}/web";
    }
}
