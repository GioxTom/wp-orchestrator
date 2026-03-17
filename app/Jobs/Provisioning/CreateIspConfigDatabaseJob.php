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

            // Attende che ISPConfig propaghi l'utente a MariaDB
            // ISPConfig usa un job queue interno — tipicamente 5-15 secondi
            $this->waitForDbAccess($dbUser, $dbPassword);

        } finally {
            $ispConfig->disconnect();
        }
    }

    /**
     * Attende che ISPConfig propaghi l'utente DB a MariaDB.
     * ISPConfig usa un job queue interno — ci vogliono alcuni secondi.
     */
    private function waitForDbAccess(string $dbUser, string $dbPassword, int $maxAttempts = 12): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                new \PDO(
                    'mysql:host=localhost;charset=utf8mb4',
                    $dbUser,
                    $dbPassword,
                    [\PDO::ATTR_TIMEOUT => 3]
                );
                return; // Connessione riuscita
            } catch (\PDOException) {
                sleep(5);
}
        }

        throw new \RuntimeException(
            "Utente DB '{$dbUser}' non propagato da ISPConfig dopo " .
            ($maxAttempts * 5) . " secondi. Verifica il job queue di ISPConfig."
        );
    }
}
