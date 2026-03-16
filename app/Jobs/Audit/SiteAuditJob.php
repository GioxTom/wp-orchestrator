<?php

namespace App\Jobs\Audit;

use App\Models\Site;
use App\Models\SiteAudit;
use App\Services\WpCliService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SiteAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 60;

    public function __construct(private readonly Site $site)
    {
    }

    public function handle(): void
    {
        $site = $this->site->fresh();

        if (! $site || $site->status !== 'active') {
            return;
        }

        $connection = $site->server->connection();
        $wpCli      = new WpCliService($connection);

        $httpStatus  = $this->checkUrl("http://{$site->domain}/");
        $httpsStatus = $this->checkUrl("https://{$site->domain}/");
        $certExpiry  = $this->getCertExpiry($site->domain);
        $adminOk     = $this->checkAdminGovernance($wpCli, $site);
        $muPluginOk  = $this->checkMuPlugin($site);

        SiteAudit::create([
            'site_id'        => $site->id,
            'http_status'    => $httpStatus,
            'https_status'   => $httpsStatus,
            'cert_expiry_at' => $certExpiry,
            'admin_ok'       => $adminOk,
            'mu_plugin_ok'   => $muPluginOk,
            'checked_at'     => now(),
        ]);

        // Remediation automatica: se l'admin non è corretto, ripristina
        if (! $adminOk) {
            $this->remediateAdmins($wpCli, $site);
        }

        // Remediation automatica: se il MU-plugin manca, lo rideploya
        if (! $muPluginOk) {
            dispatch(new \App\Jobs\Provisioning\DeployMuPluginJob($site));
        }
    }

    private function checkAdminGovernance(WpCliService $wpCli, Site $site): bool
    {
        try {
            $admins = $wpCli->getAdminUsers($site->docroot);

            if (count($admins) !== 1) {
                return false;
            }

            return $admins[0]['user_email'] === $site->wp_admin_email;
        } catch (\Throwable $e) {
            Log::warning("SiteAuditJob: impossibile verificare admin per site #{$site->id}: " . $e->getMessage());
            return false;
        }
    }

    private function checkMuPlugin(Site $site): bool
    {
        $muPluginPath = "{$site->docroot}/wp-content/mu-plugins/orchestrator-governance.php";
        return file_exists($muPluginPath);
    }

    private function remediateAdmins(WpCliService $wpCli, Site $site): void
    {
        try {
            $admins = $wpCli->getAdminUsers($site->docroot);

            foreach ($admins as $admin) {
                if ($admin['user_email'] !== $site->wp_admin_email) {
                    $wpCli->deleteUser($site->docroot, (int) $admin['ID']);
                    Log::warning("SiteAuditJob: admin non autorizzato rimosso (ID {$admin['ID']}) su site #{$site->id}");
                }
            }

            // Verifica che l'admin canonico esista ancora
            $remaining = $wpCli->getAdminUsers($site->docroot);
            if (empty($remaining)) {
                // Ricrea l'admin canonico
                $wpCli->run($site->docroot, sprintf(
                    'user create admin %s --role=administrator --user_pass=%s',
                    escapeshellarg($site->wp_admin_email),
                    escapeshellarg(\Str::password(20))
                ));
                Log::warning("SiteAuditJob: admin canonico ricreato per site #{$site->id}");
            }
        } catch (\Throwable $e) {
            Log::error("SiteAuditJob: remediation fallita per site #{$site->id}: " . $e->getMessage());
        }
    }

    private function checkUrl(string $url): int
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT        => 10,
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
            $sock = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
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
