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

        // Genera credenziali DB sicure e univoche
        $dbName     = 'wp_' . Str::slug(Str::limit($this->site->domain, 20), '_') . '_' . Str::random(4);
        $dbUser     = 'u_' . Str::random(10);
        $dbPassword = Str::random(24);

        $ispConfig = new IspConfigService($server);

        try {
            $dbId = $ispConfig->createDatabase([
                'client_id'   => $client->ispconfig_client_id,
                'db_name'     => $dbName,
                'db_user'     => $dbUser,
                'db_password' => $dbPassword,
            ]);

            $this->site->update([
                'ispconfig_db_id' => $dbId,
                'db_name'         => $dbName,
                'db_user'         => $dbUser,
                'db_password'     => $dbPassword, // cast encrypted
            ]);

        } finally {
            $ispConfig->disconnect();
        }
    }
}
