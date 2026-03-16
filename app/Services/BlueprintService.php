<?php

namespace App\Services;

use App\Models\Blueprint;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlueprintService
{
    public function __construct(private readonly WpCliService $wpCli)
    {
    }

    /**
     * Applica un blueprint completo a un sito WordPress già installato.
     * Ordine: tema parent → plugin → child theme → impostazioni WP.
     */
    public function apply(Site $site, Blueprint $blueprint): void
    {
        $docroot = $site->docroot;

        // 1. Installa il tema parent dallo ZIP
        if ($blueprint->zip_path) {
            $this->installParentTheme($docroot, $blueprint);
        }

        // 2. Installa i plugin dalla lista
        if (! empty($blueprint->plugin_list)) {
            $this->installPlugins($docroot, $blueprint->plugin_list);
        }

        // 3. Genera e attiva il child theme
        $parentSlug = $this->extractThemeSlug($blueprint->zip_path);
        $this->generateAndActivateChildTheme($docroot, $parentSlug, $site, $blueprint);

        // 4. Applica le impostazioni WordPress
        if (! empty($blueprint->wp_settings)) {
            $this->applyWpSettings($docroot, $blueprint->wp_settings);
        }
    }

    /**
     * Installa il tema parent dal file ZIP del blueprint.
     */
    private function installParentTheme(string $docroot, Blueprint $blueprint): void
    {
        $zipFullPath = Storage::disk('local')->path($blueprint->zip_path);

        if (! file_exists($zipFullPath)) {
            throw new \RuntimeException("Blueprint ZIP non trovato: {$zipFullPath}");
        }

        // Copia lo ZIP in una path temporanea accessibile al server
        $tmpPath = '/tmp/blueprint_theme_' . uniqid() . '.zip';
        copy($zipFullPath, $tmpPath);
        chmod($tmpPath, 0644);

        $this->wpCli->themeInstall($docroot, $tmpPath, false);

        @unlink($tmpPath);
    }

    /**
     * Installa tutti i plugin del blueprint.
     * Gestisce sia plugin standard (slug WP.org) che premium (ZIP caricato).
     */
    private function installPlugins(string $docroot, array $plugins): void
    {
        foreach ($plugins as $plugin) {
            $isPremium = $plugin['is_premium'] ?? false;
            $activate  = $plugin['activate'] ?? true;
            $name      = $plugin['name'] ?? 'plugin sconosciuto';

            try {
                if ($isPremium) {
                    $this->installPremiumPlugin($docroot, $plugin, $activate);
                } else {
                    $slug = $plugin['slug'] ?? null;
                    if (! $slug) {
                        \Log::warning("BlueprintService: slug mancante per plugin '{$name}', saltato");
                        continue;
                    }
                    // Aggiunge la versione se specificata (es. wordpress-seo:5.9.1)
                    $target = ($plugin['version'] ?? 'latest') !== 'latest'
                        ? "{$slug}:{$plugin['version']}"
                        : $slug;
                    $this->wpCli->pluginInstall($docroot, $target, $activate);
                }
            } catch (\Throwable $e) {
                // Non bloccante — logga e continua con gli altri plugin
                \Log::warning("BlueprintService: installazione '{$name}' fallita: " . $e->getMessage());
            }
        }
    }

    /**
     * Installa un plugin premium dal file ZIP caricato nel blueprint.
     */
    private function installPremiumPlugin(string $docroot, array $plugin, bool $activate): void
    {
        $zipPath = $plugin['zip_path'] ?? null;

        if (! $zipPath) {
            \Log::warning("BlueprintService: zip_path mancante per plugin premium '{$plugin['name']}'");
            return;
        }

        $zipFullPath = Storage::disk('local')->path($zipPath);

        if (! file_exists($zipFullPath)) {
            throw new \RuntimeException("Plugin ZIP non trovato: {$zipFullPath}");
        }

        // Copia in path temporanea accessibile
        $tmpPath = '/tmp/plugin_premium_' . uniqid() . '.zip';
        copy($zipFullPath, $tmpPath);
        chmod($tmpPath, 0644);

        $this->wpCli->pluginInstall($docroot, $tmpPath, $activate);

        @unlink($tmpPath);
    }

    /**
     * Genera il child theme a partire dallo skeleton del blueprint e lo attiva.
     */
    private function generateAndActivateChildTheme(
        string    $docroot,
        string    $parentSlug,
        Site      $site,
        Blueprint $blueprint
    ): void {
        $childSlug    = $parentSlug . '-child';
        $childDir     = "{$docroot}/wp-content/themes/{$childSlug}";

        if (! is_dir($childDir)) {
            mkdir($childDir, 0755, true);
        }

        // Genera style.css del child
        $styleCss = $this->buildChildStyleCss($parentSlug, $childSlug, $site->site_name);
        file_put_contents("{$childDir}/style.css", $styleCss);

        // Genera functions.php del child dallo skeleton o da default
        $functionsPHP = $this->buildChildFunctionsPHP($blueprint->child_skeleton, $site);
        file_put_contents("{$childDir}/functions.php", $functionsPHP);

        // Attiva il child theme
        $this->wpCli->themeActivate($docroot, $childSlug);
    }

    private function buildChildStyleCss(string $parentSlug, string $childSlug, string $siteName): string
    {
        return <<<CSS
/*
 Theme Name:   {$siteName} Child
 Theme URI:    https://example.com/{$childSlug}
 Description:  Child theme for {$siteName}
 Author:       WP Orchestrator
 Template:     {$parentSlug}
 Version:      1.0.0
 Text Domain:  {$childSlug}
*/
CSS;
    }

    private function buildChildFunctionsPHP(
        ?string $skeleton,
        Site    $site
    ): string {
        if ($skeleton) {
            // Sostituisce placeholder nel skeleton del blueprint
            return str_replace(
                ['{site_name}', '{domain}', '{locale}'],
                [$site->site_name, $site->domain, $site->locale],
                $skeleton
            );
        }

        // Default minimale
        return <<<'PHP'
<?php
/**
 * Child theme functions.
 * Generated by WP Orchestrator.
 */

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'parent-style',
        get_template_directory_uri() . '/style.css'
    );
});
PHP;
    }

    /**
     * Applica le impostazioni WordPress definite nel blueprint.
     */
    private function applyWpSettings(string $docroot, array $settings): void
    {
        foreach ($settings as $key => $value) {
            match ($key) {
                'permalink_structure' => $this->wpCli->setPermalinks($docroot, $value),
                'timezone'            => $this->wpCli->updateOption($docroot, 'timezone_string', $value),
                'date_format'         => $this->wpCli->updateOption($docroot, 'date_format', $value),
                'time_format'         => $this->wpCli->updateOption($docroot, 'time_format', $value),
                'posts_per_page'      => $this->wpCli->updateOption($docroot, 'posts_per_page', $value),
                'default_comment_status' => $this->wpCli->updateOption($docroot, 'default_comment_status', $value),
                default               => $this->wpCli->updateOption($docroot, $key, (string) $value),
            };
        }
    }

    /**
     * Estrae lo slug del tema dal path ZIP (es. /path/to/generatepress.zip → generatepress).
     */
    private function extractThemeSlug(?string $zipPath): string
    {
        if (! $zipPath) {
            return 'unknown-theme';
        }

        return Str::beforeLast(basename($zipPath), '.zip');
    }
}
