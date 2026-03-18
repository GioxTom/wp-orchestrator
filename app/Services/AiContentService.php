<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiContentService
{
    private string $provider;
    private string $apiKey;
    private string $model;

    public function __construct(?string $provider = null, ?string $model = null)
    {
        $this->provider = $provider ?? Setting::get('ai_content_provider', 'claude');

        if ($this->provider === 'openai') {
            $this->apiKey = Setting::get('openai_api_key', '');
            $this->model  = $model ?? Setting::get('openai_model', 'gpt-4o-mini');
        } else {
            $this->apiKey = Setting::get('claude_api_key', '');
            $this->model  = $model ?? Setting::get('claude_model', 'claude-sonnet-4-5');
        }
    }

    /**
     * Costruisce un AiContentService con le impostazioni specifiche del sito.
     * Se il sito ha un override, usa quello; altrimenti usa il default globale.
     */
    public static function forSite(Site $site, string $context = 'categories'): self
    {
        $provider = $site->{"categories_ai_provider"} ?? null;
        $model    = $site->{"categories_ai_model"}    ?? null;

        return new self($provider, $model);
    }

    /**
     * Invia un prompt e restituisce la risposta testuale.
     */
    public function complete(string $prompt, int $maxTokens = 500): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning("AiContentService: API key mancante per provider {$this->provider}");
            return null;
        }

        try {
            return $this->provider === 'openai'
                ? $this->completeOpenAi($prompt, $maxTokens)
                : $this->completeClaude($prompt, $maxTokens);
        } catch (\Throwable $e) {
            Log::error("AiContentService: errore completamento", [
                'provider' => $this->provider,
                'error'    => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function completeClaude(string $prompt, int $maxTokens): ?string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])
        ->timeout(30)
        ->post('https://api.anthropic.com/v1/messages', [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error("AiContentService: Claude error", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json('content.0.text');
    }

    private function completeOpenAi(string $prompt, int $maxTokens): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])
        ->timeout(30)
        ->post('https://api.openai.com/v1/chat/completions', [
            'model'      => $this->model,
            'max_tokens' => $maxTokens,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if ($response->failed()) {
            Log::error("AiContentService: OpenAI error", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    public function getProvider(): string { return $this->provider; }
    public function getModel(): string    { return $this->model; }
}
