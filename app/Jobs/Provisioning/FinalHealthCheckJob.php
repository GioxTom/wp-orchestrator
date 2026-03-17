<?php

namespace App\Jobs\Provisioning;

use App\Models\SiteAudit;

class FinalHealthCheckJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Health check finale'; }
    protected function nextJob(): ?string  { return PurgeVarnishJob::class; }

    protected function execute(): void
    {
        $site   = $this->site->fresh();
        $domain = $site->domain;

        $httpStatus  = $this->checkUrl("http://{$domain}/");
        $httpsStatus = $this->checkUrl("https://{$domain}/");
        $certExpiry  = $this->getCertExpiry($domain);

        // Salva l'audit
        SiteAudit::create([
            'site_id'        => $site->id,
            'http_status'    => $httpStatus,
            'https_status'   => $httpsStatus,
            'cert_expiry_at' => $certExpiry,
            'admin_ok'       => true,
            'mu_plugin_ok'   => true,
            'checked_at'     => now(),
        ]);

        // Fallisce solo se HTTP è completamente irraggiungibile (0)
        // HTTPS è opzionale — dipende dalla configurazione SSL del sito
        if ($httpStatus === 0) {
            throw new \RuntimeException(
                "Health check fallito: {$domain} non raggiungibile via HTTP. " .
                "Verifica che il DNS punti all'IP corretto e che il sito sia attivo in ISPConfig."
            );
        }
    }

    private function checkUrl(string $url): int
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY         => true,
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getCertExpiry(string $domain): ?\DateTime
    {
        try {
            $ctx  = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);
            $sock = stream_socket_client(
                "ssl://{$domain}:443",
                $errno, $errstr, 10,
                STREAM_CLIENT_CONNECT, $ctx
            );
            if (! $sock) return null;

            $params = stream_context_get_params($sock);
            $cert   = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            fclose($sock);

            return $cert ? (new \DateTime())->setTimestamp($cert['validTo_time_t']) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
