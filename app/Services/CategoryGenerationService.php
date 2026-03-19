<?php

namespace App\Services;

use App\Models\Prompt;
use App\Models\Site;
use Illuminate\Support\Facades\Log;

class CategoryGenerationService
{
    /**
     * Genera e aggiunge categorie WordPress via AI.
     * Se $deleteExisting = false, aggiunge senza toccare le esistenti.
     */
    public static function generate(Site $site, bool $deleteExisting = false): array
    {
        $count  = max(1, (int) ($site->categories_count ?? 4));
        $locale = $site->locale ?? 'it_IT';
        $lang   = str_contains($locale, 'it') ? 'italiano' : 'English';

        $prompt = $site->categoriesPrompt
            ?? Prompt::where('action', 'category_generation')->where('type', 'system')->first();

        if (! $prompt) {
            Log::warning("CategoryGenerationService: nessun prompt category_generation per site #{$site->id}");
            return [];
        }

        $promptText = str_replace(
            ['{description}', '{count}', '{locale}', '{lang}', '{site_name}'],
            [$site->description ?? $site->site_name, $count, $locale, $lang, $site->site_name],
            $prompt->content
        );

        $ai       = AiContentService::forSite($site);
        $response = $ai->complete($promptText, 300);

        if (! $response) {
            Log::warning("CategoryGenerationService: risposta AI vuota per site #{$site->id}");
            return [];
        }

        $categories = self::parseCategories($response);

        if (empty($categories)) {
            Log::warning("CategoryGenerationService: nessuna categoria parsata", ['response' => $response]);
            return [];
        }

        $connection = $site->server->connection();
        $wpCli      = new WpCliService($connection);
        $docroot    = $site->docroot;

        if ($deleteExisting) {
            // Elimina tutte le esistenti e rinomina la default
            try {
                $ids = trim($wpCli->run($docroot, "term list category --fields=term_id --format=ids"));
                foreach (array_filter(explode(' ', $ids)) as $id) {
                    if ((int) $id !== 1) {
                        $wpCli->run($docroot, "term delete category {$id}");
                    }
                }
                $wpCli->run($docroot, "term update category 1 --name=" . escapeshellarg($categories[0]));
                array_shift($categories);
            } catch (\Throwable $e) {
                Log::warning("CategoryGenerationService: errore reset categorie — " . $e->getMessage());
            }
        }

        // Aggiunge le categorie
        $created = [];
        foreach ($categories as $name) {
            try {
                $wpCli->run($docroot, "term create category " . escapeshellarg($name) . " --porcelain");
                $created[] = $name;
            } catch (\Throwable $e) {
                Log::warning("CategoryGenerationService: errore creazione '{$name}': " . $e->getMessage());
            }
        }

        Log::info("CategoryGenerationService: " . count($created) . " categorie create per site #{$site->id}", [
            'provider'   => $ai->getProvider(),
            'model'      => $ai->getModel(),
            'categories' => $created,
        ]);

        return $created;
    }

    private static function parseCategories(string $response): array
    {
        $response = trim($response);
        $response = preg_replace('/^```json?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded)));
        }

        $lines = array_filter(array_map('trim', explode("\n", $response)));
        return array_values(array_map(
            fn ($line) => preg_replace('/^[\d\.\-\*\s]+/', '', $line),
            $lines
        ));
    }
}
