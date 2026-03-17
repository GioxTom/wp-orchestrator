<?php

namespace App\Jobs\Provisioning;

use App\Models\Site;
use App\Services\WpCliService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Importa un'installazione WordPress già esistente nel docroot.
 * Viene dispatchato da CreateIspConfigDomainJob quando rileva
 * che il dominio esiste già in ISPConfig.
 *
 * Cosa fa:
 * - Legge credenziali DB da wp-config.php
 * - Legge titolo, descrizione, email admin, tema attivo via WP-CLI
 * - Salva tutto nel record Site
 * - Porta il sito in stato ACTIVE
 * - NON tocca WordPress — lo lascia esattamente com'è
 */
class ImportWordPressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(private readonly Site $site)
    {
    }

    public function handle(): void
    {
        $site       = $this->site->fresh();
        $docroot    = $site->docroot;
        $connection = $site->server->connection();
        $wpCli      = new WpCliService($connection);

        Log::info("ImportWordPressJob: inizio importazione WP per site #{$site->id} ({$site->domain})");

        try {
            // 1. Leggi credenziali DB da wp-config.php
            $this->importDbCredentials($site, $docroot, $connection);

            // 2. Leggi dati WordPress via WP-CLI
            $this->importWpData($site, $docroot, $wpCli);

            // 3. Applica blueprint se presente (solo se confermato)
            if ($site->blueprint_id && $site->blueprint) {
                try {
                    $wpCli            = new WpCliService($connection);
                    $blueprintService = new \App\Services\BlueprintService($wpCli);
                    $blueprintService->apply($site, $site->blueprint);
                    Log::info("ImportWordPressJob: blueprint '{$site->blueprint->name}' applicato per site #{$site->id}");
                } catch (\Throwable $e) {
                    Log::warning("ImportWordPressJob: blueprint fallito per site #{$site->id}: " . $e->getMessage());
                    // Non bloccante
                }
            }

            // 4. Deploiamo il MU-plugin di governance (necessario anche sui siti importati)
            dispatch(new DeployMuPluginJob($site->fresh()));

            // 4. Porta il sito in ACTIVE con avviso
            $site->update([
                'status'       => 'active',
                'current_step' => null,
                // Flag che indica importazione — usato nel pannello per mostrare l'avviso
                'notes'        => ($site->notes ? $site->notes . "\n" : '') .
                    '[IMPORTATO] WordPress preesistente — password admin non modificata. ' .
                    'Usa "Reset password admin" se vuoi impostarla dal pannello.',
            ]);

            Log::info("ImportWordPressJob: importazione completata per site #{$site->id}");

        } catch (\Throwable $e) {
            Log::error("ImportWordPressJob: errore per site #{$site->id}: " . $e->getMessage());

            $site->update([
                'status'       => 'error',
                'current_step' => 'Importazione WordPress — ERRORE: ' . $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Legge DB_NAME, DB_USER, DB_PASSWORD, DB_HOST da wp-config.php
     * usando WP-CLI (più affidabile del parsing manuale).
     */
    private function importDbCredentials(Site $site, string $docroot, $connection): void
    {
        try {
            $dbName = trim($connection->run(
                "wp --path={$docroot} config get DB_NAME --allow-root 2>&1"
            ));
            $dbUser = trim($connection->run(
                "wp --path={$docroot} config get DB_USER --allow-root 2>&1"
            ));
            $dbPassword = trim($connection->run(
                "wp --path={$docroot} config get DB_PASSWORD --allow-root 2>&1"
            ));

            $site->update([
                'db_name'     => $dbName     ?: null,
                'db_user'     => $dbUser     ?: null,
                'db_password' => $dbPassword ?: null, // cast encrypted
            ]);

            Log::info("ImportWordPressJob: credenziali DB importate per site #{$site->id} (db: {$dbName})");

        } catch (\Throwable $e) {
            Log::warning("ImportWordPressJob: impossibile leggere credenziali DB per site #{$site->id}: " . $e->getMessage());
        }
    }

    /**
     * Importa titolo, descrizione, email admin, tema attivo via WP-CLI.
     */
    private function importWpData(Site $site, string $docroot, WpCliService $wpCli): void
    {
        try {
            // Titolo e descrizione del sito
            $blogname = trim($wpCli->run($docroot, "option get blogname 2>&1"));
            $blogdesc = trim($wpCli->run($docroot, "option get blogdescription 2>&1"));

            // Email admin (primo utente administrator)
            $admins    = $wpCli->getAdminUsers($docroot);
            $adminEmail = $admins[0]['user_email'] ?? $site->wp_admin_email;

            // Tema attivo
            $activeTheme = trim($wpCli->run(
                $docroot,
                "theme list --status=active --field=name --allow-root 2>&1"
            ));

            // Locale
            $locale = trim($wpCli->run($docroot, "option get WPLANG 2>&1")) ?: 'en_US';

            $site->update([
                'site_name'      => $blogname    ?: $site->site_name,
                'description'    => $blogdesc    ?: $site->description,
                'wp_admin_email' => $adminEmail,
                'locale'         => $locale,
            ]);

            Log::info("ImportWordPressJob: dati WP importati — titolo: '{$blogname}', tema: '{$activeTheme}', admin: '{$adminEmail}'");

        } catch (\Throwable $e) {
            Log::warning("ImportWordPressJob: impossibile leggere dati WP per site #{$site->id}: " . $e->getMessage());
        }
    }
}
