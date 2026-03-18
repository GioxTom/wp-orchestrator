<?php

namespace App\Jobs\Provisioning;

use App\Services\WpCliService;
use Illuminate\Support\Str;

class InstallWordPressJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Installazione WordPress'; }
    protected function nextJob(): ?string  { return GenerateCategoriesJob::class; }

    protected function execute(): void
    {
        $site       = $this->site->fresh();
        $connection = $site->server->connection();
        $wpCli      = new WpCliService($connection);
        $docroot    = $site->docroot;

        // Genera password admin sicura
        $adminPassword = Str::password(20);

        // 1. Scarica WordPress core con la locale del sito
        $wpCli->coreDownload($docroot, $site->locale);

        // 2. Crea wp-config.php
        $wpCli->createConfig($docroot, [
            'name'     => $site->db_name,
            'user'     => $site->db_user,
            'password' => $site->db_password,
        ]);

        // 3. Installa WordPress con URL corretto in base a www_mode
        $wwwMode = $site->www_mode ?? 'www';
        $prefix  = $wwwMode === 'www' ? 'www.' : '';
        $siteUrl = "https://{$prefix}{$site->domain}";

        $wpCli->coreInstall($docroot, [
            'domain'         => "{$prefix}{$site->domain}",
            'title'          => $site->site_name,
            'admin_password' => $adminPassword,
            'admin_email'    => $site->wp_admin_email,
        ]);

        // 4. Configura siteurl e home esplicitamente (la description non va in WP)
        $wpCli->setSiteUrl($docroot, $site->domain, $wwwMode);
        $wpCli->updateOption($docroot, 'blogname', $site->site_name);

        // 5. Imposta la lingua
        if ($site->locale !== 'en_US') {
            try {
                $wpCli->setLocale($docroot, $site->locale);
            } catch (\Throwable) {
                // Non bloccante: locale non critico
            }
        }

        // Salva la password admin (encrypted) — verrà mostrata una sola volta in Filament
        $site->update(['wp_admin_password' => $adminPassword]);
    }
}
