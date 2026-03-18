<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Site;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NanaBananaService
{
    private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

    private string $apiKey;
    private string $model;
    private string $imageSize;
    private bool   $useBatch;

    public function __construct()
    {
        $this->apiKey    = Setting::get('gemini_api_key', '');
        $this->model     = Setting::get('gemini_model', 'gemini-3.1-flash-image-preview');
        $this->imageSize = Setting::get('gemini_image_size', '1K');
        $this->useBatch  = Setting::get('gemini_use_batch', '0') === '1';
    }

    // ────────────────────────────────────────────────────────────────────────
    // GENERAZIONE SINCRONA
    // Usata nel GenerateLogoJob — risposta immediata con immagine base64
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Genera il logo per un sito tramite Gemini API.
     * Restituisce il path locale del PNG salvato, o null in caso di errore.
     */
    public function generateLogo(Site $site): ?string
    {
        if (empty($this->apiKey)) {
            Log::warning("NanaBananaService: API key Gemini non configurata");
            return null;
        }

        $prompt = $site->resolvedPrompt();

        if (! $prompt) {
            Log::warning("NanaBananaService: nessun prompt per site #{$site->id}");
            return null;
        }

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type'   => 'application/json',
            ])
            ->timeout(120)
            ->post(self::API_BASE . '/models/' . $this->model . ':generateContent', [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseModalities' => ['TEXT', 'IMAGE'],
                    'imageConfig' => [
                        'aspectRatio' => '1:1',
                        'imageSize'   => $this->imageSize,
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::error("NanaBananaService: errore API Gemini", [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            // La risposta contiene inline_data con base64 del PNG — nessuna URL
            return $this->extractAndSaveImage($response->json(), $site->id);

        } catch (\Throwable $e) {
            Log::error("NanaBananaService: eccezione", ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // GENERAZIONE BATCH
    // Più economica (risparmio ~50%), elaborazione fino a 24h
    // Ideale per generazioni massive di loghi al lancio di nuovi siti
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Invia una richiesta di logo alla Batch API di Gemini.
     * Restituisce il nome del job batch da salvare nel sito per polling successivo.
     *
     * Dopo aver chiamato questo metodo, schedula BatchLogoCheckJob ogni ora
     * finché lo stato non è SUCCEEDED o FAILED.
     */
    public function submitBatchLogoRequest(Site $site): ?string
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $prompt = $site->resolvedPrompt();
        if (! $prompt) {
            return null;
        }

        try {
            // Prepara il file JSONL con la singola richiesta
            $requestLine = json_encode([
                'key'     => "site_{$site->id}_logo",
                'request' => [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'responseModalities' => ['TEXT', 'IMAGE'],
                        'imageConfig' => ['aspectRatio' => '1:1', 'imageSize' => $this->imageSize],
                    ],
                ],
            ]);

            // Step 1: Upload del file JSONL tramite Files API
            $uploadResponse = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type'   => 'text/plain', // JSONL
            ])
            ->timeout(30)
            ->withBody($requestLine, 'text/plain')
            ->post(self::API_BASE . '/files');

            if ($uploadResponse->failed()) {
                Log::error("NanaBananaService: upload JSONL fallito", [
                    'status' => $uploadResponse->status(),
                    'body'   => $uploadResponse->body(),
                ]);
                return null;
            }

            $fileUri = $uploadResponse->json('file.uri');

            // Step 2: Crea il batch job
            $batchResponse = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type'   => 'application/json',
            ])
            ->timeout(30)
            ->post(self::API_BASE . '/batches', [
                'model'        => 'models/' . $this->model,
                'src'          => ['fileUri' => $fileUri],
                'dest'         => [],  // output gestito internamente da Gemini
            ]);

            if ($batchResponse->failed()) {
                Log::error("NanaBananaService: creazione batch fallita", [
                    'status' => $batchResponse->status(),
                    'body'   => $batchResponse->body(),
                ]);
                return null;
            }

            $jobName = $batchResponse->json('name');
            Log::info("NanaBananaService: batch job creato → {$jobName} per site #{$site->id}");

            return $jobName;

        } catch (\Throwable $e) {
            Log::error("NanaBananaService: eccezione batch submit", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Controlla lo stato di un batch job.
     * Ritorna:
     *   - string  = path PNG locale se completato con successo
     *   - null    = ancora in elaborazione (riprova dopo)
     *   - false   = job fallito
     */
    public function checkBatchJob(string $jobName, int $siteId): string|null|false
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(30)
                ->get(self::API_BASE . '/' . $jobName);

            if ($response->failed()) {
                return false;
            }

            $state = $response->json('state');

            return match ($state) {
                'JOB_STATE_SUCCEEDED' => $this->parseBatchResult($response->json(), $siteId),
                'JOB_STATE_FAILED'    => false,
                default               => null, // JOB_STATE_RUNNING, PENDING, ecc.
            };

        } catch (\Throwable $e) {
            Log::error("NanaBananaService: check batch fallito", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // APPLICAZIONE LOGO A WORDPRESS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Importa il logo PNG in WordPress e lo imposta come custom_logo del tema attivo.
     * $localPngPath = path locale temporaneo del PNG generato da Gemini.
     */
    public function applyLogoToWordPress(Site $site, string $localPngPath): bool
    {
        if (! file_exists($localPngPath)) {
            Log::error("NanaBananaService: PNG non trovato: {$localPngPath}");
            return false;
        }

        try {
            $connection = $site->server->connection();
            $docroot    = $site->docroot;

            // Copia il PNG nel server (locale: stessa cosa, SSH: upload)
            $remoteTemp = "{$docroot}/wp-content/uploads/logo-{$site->id}-" . uniqid() . '.png';
            $connection->upload($localPngPath, $remoteTemp);

            // Importa in WordPress media library
            $connection->run(
                "wp --path={$docroot} media import {$remoteTemp} " .
                "--title=" . escapeshellarg("{$site->site_name} Logo") .
                " --allow-root 2>&1"
            );

            // Recupera l'ID attachment appena importato
            $attachmentId = trim($connection->run(
                "wp --path={$docroot} post list --post_type=attachment " .
                "--posts_per_page=1 --orderby=date --order=DESC --field=ID --allow-root 2>&1"
            ));

            if (is_numeric($attachmentId)) {
                // Recupera il tema attivo e imposta custom_logo
                $themeSlug = trim($connection->run(
                    "wp --path={$docroot} theme list --status=active --field=name --allow-root 2>&1"
                ));

                $connection->run(
                    "wp --path={$docroot} option update theme_mods_{$themeSlug} " .
                    escapeshellarg(json_encode(['custom_logo' => (int) $attachmentId])) .
                    " --format=json --allow-root 2>&1"
                );

                Log::info("NanaBananaService: logo applicato (attachment #{$attachmentId}) per site #{$site->id}");
            }

            // Pulizia file temporanei
            $connection->run("rm -f {$remoteTemp}");
            @unlink($localPngPath);

            return true;

        } catch (\Throwable $e) {
            Log::error("NanaBananaService: errore applicazione logo", ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // HELPER PRIVATI
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Estrae l'immagine base64 dalla risposta Gemini e la salva come PNG locale.
     * La risposta Gemini NON restituisce URL — restituisce inline_data.data (base64).
     */
    private function extractAndSaveImage(array $responseData, int $siteId): ?string
    {
        $candidates = $responseData['candidates'] ?? [];

        foreach ($candidates as $candidate) {
            $parts = $candidate['content']['parts'] ?? [];

            foreach ($parts as $part) {
                // Cerca la parte con inline_data di tipo immagine
                if (! isset($part['inlineData'])) {
                    continue;
                }

                $mimeType = $part['inlineData']['mimeType'] ?? '';
                if (! str_starts_with($mimeType, 'image/')) {
                    continue;
                }

                // Decodifica base64 e salva localmente
                $imageData = base64_decode($part['inlineData']['data']);
                $tmpDir    = storage_path('app/tmp');

                if (! is_dir($tmpDir)) {
                    mkdir($tmpDir, 0755, true);
                }

                $filename = "logo_site_{$siteId}_" . uniqid() . '.png';
                $path     = "{$tmpDir}/{$filename}";

                file_put_contents($path, $imageData);

                Log::info("NanaBananaService: PNG estratto → {$path}");
                return $path;
            }
        }

        Log::warning("NanaBananaService: nessuna immagine nella risposta Gemini", [
            'response' => $responseData,
        ]);
        return null;
    }

    /**
     * Recupera e parsa il risultato di un batch job completato.
     */
    private function parseBatchResult(array $jobData, int $siteId): ?string
    {
        // Il batch job completato espone l'output via dest.fileUri
        // Scarica il JSONL di output e cerca l'inline_data
        $outputUri = $jobData['dest']['fileUri'] ?? null;

        if (! $outputUri) {
            Log::warning("NanaBananaService: nessun outputUri nel batch job");
            return null;
        }

        try {
            $outputResponse = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(60)
                ->get($outputUri);

            if ($outputResponse->failed()) {
                return null;
            }

            // Il JSONL di output contiene una riga per richiesta
            $lines = explode("\n", trim($outputResponse->body()));
            foreach ($lines as $line) {
                $data = json_decode($line, true);
                if (! $data) continue;

                $result = $this->extractAndSaveImage($data['response'] ?? [], $siteId);
                if ($result) {
                    return $result;
                }
            }
        } catch (\Throwable $e) {
            Log::error("NanaBananaService: parse batch result fallito", ['error' => $e->getMessage()]);
        }

        return null;
    }
}
