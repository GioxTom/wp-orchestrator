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

        // Rileva l'utente proprietario del docroot
        $owner = $this->getDocRootOwner($docroot);
        $sudo  = $owner ? "sudo -u {$owner}" : "sudo";

        // Crea la directory mu-plugins se non esiste
        $connection->run("{$sudo} mkdir -p {$muPluginDir}");

        // Genera il contenuto del MU-plugin
        $muPluginContent = $this->generateMuPlugin($site->wp_admin_email);

        // Scrive il file in /tmp (accessibile da tutti) poi lo copia con sudo
        $tmpPath = '/tmp/mu_plugin_' . uniqid() . '.php';
        file_put_contents($tmpPath, $muPluginContent);
        chmod($tmpPath, 0644);

        $connection->run("{$sudo} cp " . escapeshellarg($tmpPath) . " " . escapeshellarg($muPluginDest));
        $connection->run("{$sudo} chmod 644 " . escapeshellarg($muPluginDest));

        @unlink($tmpPath);
    }

    private function getDocRootOwner(string $docroot): ?string
    {
        // Legge il proprietario da cgi-bin che è drwxr-xr-x (accessibile da tutti)
        $parent = dirname($docroot);
        foreach (['cgi-bin', 'tmp'] as $sub) {
            $stat = @stat($parent . '/' . $sub);
            if ($stat) {
                $info = posix_getpwuid($stat['uid']);
                if ($info && $info['name'] !== 'root') {
                    return $info['name'];
                }
            }
        }
        return null;
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
