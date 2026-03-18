<?php

namespace App\Services;

use App\Contracts\ServerConnection;

class WpCliService
{
    public function __construct(private readonly ServerConnection $connection)
    {
    }

    /**
     * Esegue un comando WP-CLI nel docroot specificato.
     * Usa sudo -u con l'utente proprietario del docroot se diverso dall'utente corrente.
     */
    public function run(string $docroot, string $command): string
    {
        $owner       = $this->getDocRootOwner($docroot);
        $currentUser = get_current_user() ?: posix_getpwuid(posix_geteuid())['name'] ?? '';

        if ($owner && $owner !== 'root' && $owner !== $currentUser) {
            $cmd = "sudo -u {$owner} wp --path={$docroot} {$command} 2>&1";
        } else {
            $cmd = "wp --path={$docroot} {$command} --allow-root 2>&1";
        }

        return $this->connection->run($cmd);
    }

    /**
     * Legge il proprietario del docroot leggendo la directory /web
     * oppure la directory padre se /web non esiste ancora.
     * Usa stat() sulla parent directory che è accessibile a tutti (drwxr-xr-x).
     */
    private function getDocRootOwner(string $docroot): ?string
    {
        // Prova prima sul docroot (/web) — potrebbe non esistere ancora
        // Prova sulla cartella padre (web14) che ha permessi drwxr-xr-x (leggibile da tutti)
        $parent = dirname($docroot); // /var/www/clients/client2/web14

        // Legge le sottocartelle per trovare l'utente proprietario di cgi-bin o simili
        // che sono accessibili (drwxr-xr-x)
        foreach (['cgi-bin', 'tmp'] as $sub) {
            $path = $parent . '/' . $sub;
            if (is_dir($path)) {
                $stat = @stat($path);
                if ($stat) {
                    $info = posix_getpwuid($stat['uid']);
                    if ($info && $info['name'] !== 'root') {
                        return $info['name'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Scarica e installa il core di WordPress.
     */
    public function coreDownload(string $docroot, string $locale = 'en_US'): string
    {
        return $this->run($docroot, "core download --locale={$locale} --force");
    }

    /**
     * Genera wp-config.php.
     */
    public function createConfig(string $docroot, array $db): string
    {
        return $this->run($docroot, implode(' ', [
            'config create',
            "--dbname={$db['name']}",
            "--dbuser={$db['user']}",
            "--dbpass={$db['password']}",
            '--dbhost=localhost',
            '--dbcharset=utf8mb4',
        ]));
    }

    /**
     * Installa WordPress (crea le tabelle e l'utente admin).
     */
    public function coreInstall(string $docroot, array $params): string
    {
        return $this->run($docroot, implode(' ', [
            'core install',
            "--url=https://{$params['domain']}",
            "--title=" . escapeshellarg($params['title']),
            "--admin_user=admin",
            "--admin_password=" . escapeshellarg($params['admin_password']),
            "--admin_email=" . escapeshellarg($params['admin_email']),
            '--skip-email',
        ]));
    }

    /**
     * Installa un plugin da slug WP.org o da path ZIP locale.
     */
    public function pluginInstall(string $docroot, string $slugOrPath, bool $activate = true): string
    {
        $activateFlag = $activate ? '--activate' : '';
        return $this->run($docroot, "plugin install {$slugOrPath} --force {$activateFlag}");
    }

    /**
     * Installa un tema da slug WP.org o da path ZIP locale.
     */
    public function themeInstall(string $docroot, string $slugOrPath, bool $activate = false): string
    {
        $activateFlag = $activate ? '--activate' : '';
        return $this->run($docroot, "theme install {$slugOrPath} --force {$activateFlag}");
    }

    /**
     * Attiva un tema già installato.
     */
    public function themeActivate(string $docroot, string $slug): string
    {
        return $this->run($docroot, "theme activate {$slug}");
    }

    /**
     * Aggiorna un'opzione WordPress.
     */
    public function updateOption(string $docroot, string $option, string $value): string
    {
        return $this->run($docroot, "option update {$option} " . escapeshellarg($value));
    }

    /**
     * Aggiorna la permalink structure.
     */
    public function setPermalinks(string $docroot, string $structure = '/%postname%/'): string
    {
        return $this->run($docroot, "rewrite structure " . escapeshellarg($structure));
    }

    /**
     * Aggiorna la password dell'admin canonico.
     * Restituisce la nuova password.
     */
    public function resetAdminPassword(string $docroot, string $newPassword): string
    {
        $this->run($docroot, "user update admin --user_pass=" . escapeshellarg($newPassword));
        return $newPassword;
    }

    /**
     * Elenca gli utenti admin del sito.
     */
    public function getAdminUsers(string $docroot): array
    {
        $output = $this->run($docroot, "user list --role=administrator --fields=ID,user_login,user_email --format=json");
        return json_decode($output, true) ?? [];
    }

    /**
     * Elimina un utente WordPress by ID.
     */
    public function deleteUser(string $docroot, int $userId): string
    {
        return $this->run($docroot, "user delete {$userId} --yes");
    }

    /**
     * Copia un file nel docroot del sito (usato per MU-plugin e child theme).
     */
    public function putFile(string $localPath, string $destPath): void
    {
        $this->connection->upload($localPath, $destPath);
    }

    /**
     * Verifica se WordPress è installato nel docroot.
     */
    public function isInstalled(string $docroot): bool
    {
        try {
            $this->run($docroot, 'core is-installed');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Imposta il titolo e la descrizione del sito.
     */
    public function setSiteInfo(string $docroot, string $title, string $description): void
    {
        $this->updateOption($docroot, 'blogname', $title);
        $this->updateOption($docroot, 'blogdescription', $description);
    }

    /**
     * Imposta la lingua del sito.
     */
    public function setLocale(string $docroot, string $locale): string
    {
        return $this->run($docroot, "language core install {$locale} --activate");
    }

    /**
     * Crea una pagina WordPress.
     * Restituisce l'ID della pagina creata.
     */
    public function createPage(string $docroot, string $title, string $slug, string $content = '', string $template = ''): int
    {
        $args = implode(' ', array_filter([
            'post create',
            '--post_type=page',
            '--post_status=publish',
            '--post_title=' . escapeshellarg($title),
            '--post_name=' . escapeshellarg($slug ?: \Illuminate\Support\Str::slug($title)),
            '--post_content=' . escapeshellarg($content),
            $template ? '--meta_input=' . escapeshellarg(json_encode(['_wp_page_template' => $template])) : '',
            '--porcelain', // restituisce solo l'ID
        ]));

        return (int) trim($this->run($docroot, $args));
}

    /**
     * Crea un menu WordPress e lo assegna a una posizione del tema.
     * Restituisce l'ID del menu creato.
     */
    public function createMenu(string $docroot, string $name): int
    {
        $output = $this->run($docroot, "menu create " . escapeshellarg($name) . " --porcelain");
        return (int) trim($output);
    }

    /**
     * Assegna un menu a una posizione del tema.
     */
    public function assignMenuLocation(string $docroot, string $menuNameOrId, string $location): void
    {
        $this->run($docroot, "menu location assign " . escapeshellarg($menuNameOrId) . " " . escapeshellarg($location));
    }

    /**
     * Aggiunge una voce di tipo pagina al menu.
     */
    public function addMenuItemPage(string $docroot, string $menuNameOrId, int $pageId, string $title = '', int $position = 0): void
    {
        $args = array_filter([
            "menu item add-post",
            escapeshellarg($menuNameOrId),
            $pageId,
            $title ? '--title=' . escapeshellarg($title) : '',
            $position ? "--position={$position}" : '',
        ]);
        $this->run($docroot, implode(' ', $args));
    }

    /**
     * Aggiunge una voce di tipo URL personalizzato al menu.
     */
    public function addMenuItemCustom(string $docroot, string $menuNameOrId, string $url, string $title, int $position = 0): void
    {
        $args = array_filter([
            "menu item add-custom",
            escapeshellarg($menuNameOrId),
            escapeshellarg($title),
            escapeshellarg($url),
            $position ? "--position={$position}" : '',
        ]);
        $this->run($docroot, implode(' ', $args));
    }

    /**
     * Aggiunge un widget a una sidebar.
     */
    public function addWidget(string $docroot, string $sidebarId, string $widgetType, int $position = 1, array $settings = []): void
    {
        $settingsJson = ! empty($settings) ? escapeshellarg(json_encode($settings)) : "'{}'";
        $this->run($docroot, "widget add {$widgetType} {$sidebarId} {$position} --settings={$settingsJson}");
    }

    /**
     * Rimuove tutti i widget da una sidebar.
     */
    public function resetSidebar(string $docroot, string $sidebarId): void
    {
        try {
            $output = $this->run($docroot, "widget list {$sidebarId} --fields=id --format=ids");
            $ids    = array_filter(explode(' ', trim($output)));
            foreach ($ids as $id) {
                $this->run($docroot, "widget delete {$id}");
            }
        } catch (\Throwable) {
            // Sidebar potrebbe essere vuota
        }
    }

    /**
     * Elimina pagine per slug.
     */
    public function deletePage(string $docroot, string $slug): void
    {
        try {
            $output = $this->run($docroot, "post list --post_type=page --post_name={$slug} --fields=ID --format=ids");
            $ids    = array_filter(explode(' ', trim($output)));
            foreach ($ids as $id) {
                $this->run($docroot, "post delete {$id} --force");
            }
        } catch (\Throwable) {
            // Pagina non trovata
        }
    }

    /**
     * Configura siteurl e home in base al www_mode.
     */
    public function setSiteUrl(string $docroot, string $domain, string $wwwMode = 'www'): void
    {
        $prefix = $wwwMode === 'www' ? 'www.' : '';
        $url    = "https://{$prefix}{$domain}";
        $this->updateOption($docroot, 'siteurl', $url);
        $this->updateOption($docroot, 'home', $url);
    }

}
