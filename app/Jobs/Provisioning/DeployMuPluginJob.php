<?php

namespace App\Jobs\Provisioning;

class DeployMuPluginJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Deploy MU-Plugin governance'; }
    protected function nextJob(): ?string  { return FinalHealthCheckJob::class; }

    protected function execute(): void
    {
        $site       = $this->site->fresh();
        $docroot    = $site->docroot;
        $connection = $site->server->connection();

        $muPluginDir  = "{$docroot}/wp-content/mu-plugins";
        $muPluginDest = "{$muPluginDir}/orchestrator-governance.php";

        // Crea la directory mu-plugins se non esiste
        $connection->run("mkdir -p {$muPluginDir}");

        // Genera il contenuto del MU-plugin con l'email canonico del sito
        $muPluginContent = $this->generateMuPlugin($site->wp_admin_email);

        // Scrive il file direttamente (locale) o via upload (SSH)
        $tmpPath = tempnam(sys_get_temp_dir(), 'mu_plugin_');
        file_put_contents($tmpPath, $muPluginContent);

        $connection->upload($tmpPath, $muPluginDest);

        @unlink($tmpPath);
    }

    private function generateMuPlugin(string $canonicalEmail): string
    {
        $email = addslashes($canonicalEmail);

        return <<<PHP
<?php
/**
 * Plugin Name: Orchestrator Governance
 * Description: Applica le policy di sicurezza gestite dall'orchestrator.
 *              NON rimuovere o disattivare questo file.
 * Version:     1.0.0
 */

defined('ABSPATH') || exit;

const ORCHESTRATOR_CANONICAL_EMAIL = '{$email}';

/**
 * Blocca la promozione a admin non autorizzata.
 */
add_filter('user_has_cap', function (\$allcaps, \$cap, \$args, \$user) {
    if (in_array('promote_users', \$cap) || in_array('edit_users', \$cap)) {
        if (\$user->user_email !== ORCHESTRATOR_CANONICAL_EMAIL) {
            \$allcaps['promote_users'] = false;
            \$allcaps['edit_users']    = false;
        }
    }
    return \$allcaps;
}, 10, 4);

/**
 * Blocca la creazione di nuovi admin non autorizzati.
 */
add_action('user_register', function (\$userId) {
    \$user = get_userdata(\$userId);
    if (in_array('administrator', \$user->roles) && \$user->user_email !== ORCHESTRATOR_CANONICAL_EMAIL) {
        \$user->set_role('subscriber');
    }
});

/**
 * Blocca la modifica di email e username dell'admin canonico dal backend WP.
 */
add_action('personal_options_update', function (\$userId) {
    \$user = get_userdata(\$userId);
    if (\$user->user_email === ORCHESTRATOR_CANONICAL_EMAIL) {
        if (isset(\$_POST['email']) && \$_POST['email'] !== ORCHESTRATOR_CANONICAL_EMAIL) {
            \$_POST['email'] = ORCHESTRATOR_CANONICAL_EMAIL;
        }
    }
});
PHP;
    }
}
