<?php

namespace App\Jobs\Provisioning;

use App\Services\IspConfigService;
use Illuminate\Support\Str;

class CreateIspConfigDatabaseJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Creazione database MariaDB'; }
    protected function nextJob(): ?string  { return InstallWordPressJob::class; }

    protected function execute(): void
    {
        $server = $this->site->server;
        $client = $this->site->ispConfigClient;

        // Se il DB è già stato creato in un tentativo precedente, non ricreare
        if ($this->site->db_name && $this->site->ispconfig_db_id) {
            $this->log->update(['output' => "DB già presente ({$this->site->db_name}), skip creazione."]);
            return;
        }

        // Genera nomi leggibili e correlati tra DB e utente
        $suffix  = Str::random(4);
        $base    = 'wp_' . Str::slug(Str::limit($this->site->domain, 15), '_');
        $dbName  = $base . '_' . $suffix;
        $dbUser  = $base . '_usr';          // stesso prefisso del DB
        $dbPassword = Str::random(24);

        $ispConfig = new IspConfigService($server);

        try {
            $dbId = $ispConfig->createDatabase([
                'client_id'   => $client->ispconfig_client_id,
                'domain_id'   => $this->site->ispconfig_domain_id,
                'db_name'     => $dbName,
                'db_user'     => $dbUser,
                'db_password' => $dbPassword,
            ]);

            $this->site->update([
                'ispconfig_db_id' => $dbId,
                'db_name'         => $dbName,
                'db_user'         => $dbUser,
                'db_password'     => $dbPassword,
            ]);

        } finally {
            $ispConfig->disconnect();
        }
    }
}
