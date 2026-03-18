<?php

namespace App\Jobs\Provisioning;

use App\Models\Prompt;
use App\Services\AiContentService;
use App\Services\WpCliService;

class GenerateCategoriesJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Generazione categorie AI'; }
    protected function nextJob(): ?string  { return ApplyBlueprintJob::class; }

    protected function execute(): void
    {
        $site = $this->site->fresh();

        // Skip se disabilitato
        if (! $site->auto_categories) {
            return;
        }

        $count   = max(1, (int) ($site->categories_count ?? 4));
        $locale  = $site->locale ?? 'it_IT';
        $lang    = str_contains($locale, 'it') ? 'italiano' : 'English';

        // Recupera il prompt — usa quello del sito o il default di sistema
        $prompt = $site->categoriesPrompt
            ?? Prompt::where('action', 'category_generation')->where('type', 'system')->first();

        if (! $prompt) {
            \Log::warning("GenerateCategoriesJob: nessun prompt per category_generation — step saltato");
            return;
        }

        // Sostituisce i placeholder nel prompt
        $promptText = str_replace(
            ['{description}', '{count}', '{locale}', '{lang}'],
            [$site->description ?? $site->site_name, $count, $locale, $lang],
            $prompt->content
        );

        // Chiama l'AI
        $ai       = AiContentService::forSite($site);
        $response = $ai->complete($promptText, 300);

        if (! $response) {
            \Log::warning("GenerateCategoriesJob: risposta AI vuota per site #{$site->id}");
            return;
        }

        // Parsa la risposta — si aspetta un JSON array di stringhe
        $categories = $this->parseCategories($response);

        if (empty($categories)) {
            \Log::warning("GenerateCategoriesJob: nessuna categoria parsata", ['response' => $response]);
            return;
        }

        // Elimina la categoria default "Uncategorized" e crea le nuove
        $connection = $site->server->connection();
        $wpCli      = new WpCliService($connection);
        $docroot    = $site->docroot;

        // Prima elimina tutte le categorie esistenti (tranne quella con ID=1 che WP richiede)
        try {
            $ids = trim($wpCli->run($docroot, "term list category --fields=term_id --format=ids"));
            foreach (array_filter(explode(' ', $ids)) as $id) {
                if ((int) $id !== 1) {
                    $wpCli->run($docroot, "term delete category {$id}");
                }
            }
            // Rinomina la categoria default
            $wpCli->run($docroot, "term update category 1 --name=" . escapeshellarg($categories[0]));
            array_shift($categories);
        } catch (\Throwable) {
            // Non bloccante
        }

        // Crea le categorie restanti
        foreach ($categories as $name) {
            try {
                $wpCli->run($docroot, "term create category " . escapeshellarg($name) . " --porcelain");
            } catch (\Throwable $e) {
                \Log::warning("GenerateCategoriesJob: errore creazione categoria '{$name}': " . $e->getMessage());
            }
        }

        \Log::info("GenerateCategoriesJob: " . count($categories) . " categorie create per site #{$site->id}", [
            'provider' => $ai->getProvider(),
            'model'    => $ai->getModel(),
            'categories' => $categories,
        ]);
    }

    private function parseCategories(string $response): array
    {
        // Prova a decodificare JSON
        $response = trim($response);

        // Rimuove eventuali backtick markdown
        $response = preg_replace('/^```json?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        // Fallback: una categoria per riga
        $lines = array_filter(array_map('trim', explode("\n", $response)));
        return array_values(array_map(
            fn ($line) => preg_replace('/^[\d\.\-\*\s]+/', '', $line),
            $lines
        ));
    }
}
