<?php

namespace App\Services;

use App\Models\IspConfigClient;
use App\Models\IspConfigPhpVersion;
use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IspConfigService
{
    private ?string $sessionId  = null;
    private string  $baseUrl;

    public function __construct(private readonly Server $server)
    {
        // Normalizza l'URL: rimuove /index.php o /json.php se presenti,
        // poi aggiunge /remote/json.php
        $url = rtrim($this->server->ispconfig_api_url, '/');

        // Rimuovi endpoint specifici se l'utente li ha inclusi
        $url = preg_replace('#/remote/(json|index)\.php$#', '', $url);
        $url = rtrim($url, '/');

        $this->baseUrl = $url . '/remote/json.php';
    }

    // ────────────────────────────────────────────────────────────────────────
    // Autenticazione
    // ────────────────────────────────────────────────────────────────────────

    private function connect(): void
    {
        if ($this->sessionId) {
            return;
        }

        $response = $this->post('login', [
            'username'     => $this->server->ispconfig_user,
            'password'     => $this->server->ispconfig_password,
            'client_login' => false,
        ]);

        if (empty($response['response']) || $response['response'] === false) {
            throw new \RuntimeException(
                'ISPConfig login fallito — verifica utente e password Remote User. ' .
                'Risposta: ' . json_encode($response)
            );
        }

        $this->sessionId = $response['response'];
    }

    public function disconnect(): void
    {
        if (! $this->sessionId) {
            return;
        }

        try {
            $this->post('logout', ['session_id' => $this->sessionId]);
        } catch (\Throwable) {
            // ignora errori di logout
        }

        $this->sessionId = null;
    }

    public function testConnection(): bool
    {
        $this->connect();
        $this->disconnect();
        return true;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Sync clienti e versioni PHP
    // ────────────────────────────────────────────────────────────────────────

    public function syncClients(): int
    {
        $this->connect();

        $response = $this->post('client_get_all', [
            'session_id' => $this->sessionId,
        ]);

        $clients = $response['response'] ?? [];

        // ISPConfig può restituire false o "" se non ci sono clienti
        if (! is_array($clients)) {
            Log::info("IspConfigService::syncClients — nessun cliente trovato (response: " . json_encode($clients) . ")");
            return 0;
        }

        $count = 0;

        foreach ($clients as $client) {
            // Salta se il record non è un array (protezione extra)
            if (! is_array($client)) {
                continue;
            }

            IspConfigClient::updateOrCreate(
                [
                    'server_id'           => $this->server->id,
                    'ispconfig_client_id' => $client['client_id'],
                ],
                [
                    'company_name' => $client['company_name'] ?? null,
                    'contact_name' => trim(($client['firstname'] ?? '') . ' ' . ($client['name'] ?? '')),
                    'email'        => $client['email'] ?? null,
                    'username'     => $client['username'] ?? null,
                    'synced_at'    => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    public function syncPhpVersions(): int
    {
        $this->connect();

        $phpVersions = $this->detectPhpVersions();
        $count       = 0;

        foreach ($phpVersions as $version) {
            IspConfigPhpVersion::updateOrCreate(
                [
                    'server_id' => $this->server->id,
                    'version'   => $version['version'],
                ],
                [
                    'label'           => $version['label'],
                    'fpm_config_path' => $version['fpm_config_path'],
                ]
            );
            $count++;
        }

        // Se non ne trovate nessuna, inserisci almeno la versione di default
        if ($count === 0) {
            $defaultVersion = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;
            IspConfigPhpVersion::updateOrCreate(
                ['server_id' => $this->server->id, 'version' => $defaultVersion],
                [
                    'label'           => "PHP {$defaultVersion} (FPM)",
                    'fpm_config_path' => "/etc/php/{$defaultVersion}/fpm/php.ini",
                ]
            );
            $count = 1;
        }

        return $count;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Gestione domini web
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Cerca un dominio in ISPConfig per nome.
     * Restituisce l'ID del dominio se trovato, null altrimenti.
     */
    public function findDomainByName(string $domain): ?int
    {
        $this->connect();

        $response = $this->post('sites_web_domain_get_by_domain', [
            'session_id' => $this->sessionId,
            'domain'     => $domain,
        ]);

        $result = $response['response'] ?? null;

        // Può restituire array con domain_id, oppure false/null se non trovato
        if (is_array($result) && ! empty($result['domain_id'])) {
            return (int) $result['domain_id'];
        }

        // Fallback: cerca nella lista completa
        // (alcuni ISPConfig non hanno sites_web_domain_get_by_domain)
        try {
            $allResponse = $this->post('sites_web_domain_get_by_domain', [
                'session_id' => $this->sessionId,
                'domain'     => $domain,
            ]);
        } catch (\Throwable) {
            // Metodo non disponibile — cerca manualmente
            return $this->findDomainByNameFallback($domain);
        }

        return null;
    }

    /**
     * Fallback: scorre tutti i domini e cerca per nome.
     * Usato se sites_web_domain_get_by_domain non è disponibile.
     */
    private function findDomainByNameFallback(string $domain): ?int
    {
        try {
            $response = $this->post('sites_web_domain_get_all_by_user', [
                'session_id' => $this->sessionId,
                'client_id'  => 0, // 0 = tutti
            ]);

            $domains = $response['response'] ?? [];
            if (! is_array($domains)) return null;

            foreach ($domains as $d) {
                if (($d['domain'] ?? '') === $domain) {
                    return (int) $d['domain_id'];
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Recupera il docroot di un dominio ISPConfig dato il suo ID.
     */
    public function getDomainDocroot(int $domainId): string
    {
        $this->connect();

        $response = $this->post('sites_web_domain_get', [
            'session_id' => $this->sessionId,
            'domain_id'  => $domainId,
        ]);

        $domain  = $response['response'] ?? [];
        $webRoot = $domain['document_root'] ?? null;

        if ($webRoot && is_dir($webRoot)) {
            return rtrim($webRoot, '/');
        }

        // Fallback standard ISPConfig path
        // /var/www/clients/client{N}/web{N}/web
        return "/var/www/clients/client{$domain['sys_userid']}/web{$domainId}/web";
    }

    public function createWebDomain(array $params): int
    {
        $this->connect();

        $defaults = [
            'server_id'              => 1,
            'ip_address'             => '*',
            'ipv6_address'           => '',
            'domain'                 => '',
            'hd_quota'               => -1,
            'traffic_quota'          => -1,
            'cgi'                    => 'n',
            'ssi'                    => 'n',
            'suexec'                 => 'y',
            'errordocs'              => 1,
            'is_subdomainwww'        => 1,
            'subdomain'              => 'www',
            'php'                    => 'php-fpm',
            'php_fpm_use_socket'     => 'y',
            'ruby'                   => 'n',
            'redirect_type'          => '',
            'redirect_path'          => '',
            'ssl'                    => 'n',
            'ssl_letsencrypt'        => 'n',
            'stats_password'         => '',
            'allow_override'         => 'All',
            'apache_directives'      => '',
            'nginx_directives'       => '',
            'active'                 => 'y',
        ];

        $merged    = array_merge($defaults, $params);
        $clientId  = $params['client_id'];

        $response = $this->post('sites_web_domain_add', [
            'session_id' => $this->sessionId,
            'client_id'  => $clientId,
            'params'     => $merged,
        ]);

        $domainId = $response['response'] ?? null;

        if (! $domainId) {
            throw new \RuntimeException(
                "ISPConfig: impossibile creare il dominio {$params['domain']}. " .
                'Risposta: ' . json_encode($response)
            );
        }

        return (int) $domainId;
    }

    public function createDatabase(array $params): int
    {
        $this->connect();

        $response = $this->post('sites_database_add', [
            'session_id' => $this->sessionId,
            'client_id'  => $params['client_id'],
            'params'     => [
                'server_id'         => 1,
                'type'              => 'mysql',
                'database_name'     => $params['db_name'],
                'database_user_id'  => 0,
                'database_quota'    => 0,
                'database_charset'  => 'utf8mb4',
                'remote_access'     => 'n',
                'active'            => 'y',
            ],
        ]);

        // Crea anche l'utente DB
        $userResponse = $this->post('sites_database_user_add', [
            'session_id' => $this->sessionId,
            'client_id'  => $params['client_id'],
            'params'     => [
                'server_id'             => 1,
                'database_user'         => $params['db_user'],
                'database_password'     => $params['db_password'],
            ],
        ]);

        $dbId = $response['response'] ?? null;

        if (! $dbId) {
            throw new \RuntimeException(
                "ISPConfig: impossibile creare il database {$params['db_name']}. " .
                'Risposta: ' . json_encode($response)
            );
        }

        return (int) $dbId;
    }

    public function disableWebDomain(int $domainId): void
    {
        $this->connect();

        // Prima recupera il dominio
        $response = $this->post('sites_web_domain_get', [
            'session_id' => $this->sessionId,
            'domain_id'  => $domainId,
        ]);

        $domain           = $response['response'] ?? [];
        $domain['active'] = 'n';

        $this->post('sites_web_domain_update', [
            'session_id' => $this->sessionId,
            'client_id'  => $domain['sys_userid'] ?? 0,
            'domain_id'  => $domainId,
            'params'     => $domain,
        ]);
    }

    public function enableWebDomain(int $domainId): void
    {
        $this->connect();

        $response = $this->post('sites_web_domain_get', [
            'session_id' => $this->sessionId,
            'domain_id'  => $domainId,
        ]);

        $domain           = $response['response'] ?? [];
        $domain['active'] = 'y';

        $this->post('sites_web_domain_update', [
            'session_id' => $this->sessionId,
            'client_id'  => $domain['sys_userid'] ?? 0,
            'domain_id'  => $domainId,
            'params'     => $domain,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // HTTP helper
    // ────────────────────────────────────────────────────────────────────────

    private function post(string $method, array $data): array
    {
        $url = $this->baseUrl . '?' . $method;

        $response = Http::withoutVerifying()  // SSL self-signed
            ->timeout(30)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $data);

        if ($response->failed()) {
            throw new \RuntimeException(
                "ISPConfig API error [{$method}]: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $decoded = $response->json();

        if ($decoded === null) {
            throw new \RuntimeException(
                "ISPConfig API [{$method}]: risposta non JSON — {$response->body()}"
            );
        }

        // ISPConfig restituisce {"code": "ok", "message": "...", "response": ...}
        if (isset($decoded['code']) && $decoded['code'] !== 'ok') {
            throw new \RuntimeException(
                "ISPConfig API [{$method}]: {$decoded['message']} (code: {$decoded['code']})"
            );
        }

        return $decoded;
    }

    // ────────────────────────────────────────────────────────────────────────
    // Rilevamento versioni PHP installate sul server
    // ────────────────────────────────────────────────────────────────────────

    private function detectPhpVersions(): array
    {
        $versions = [];

        // Cerca le versioni PHP-FPM installate tramite i socket disponibili
        $sockets = glob('/run/php/php*-fpm.sock') ?: [];

        foreach ($sockets as $socket) {
            if (preg_match('#php(\d+\.\d+)-fpm\.sock#', $socket, $m)) {
                $v = $m[1];
                // Escludi il pool orchestrator
                if (str_contains($socket, 'orchestrator')) continue;
                $versions[] = [
                    'version'         => $v,
                    'label'           => "PHP {$v} (FPM)",
                    'fpm_config_path' => "/etc/php/{$v}/fpm/php.ini",
                ];
            }
        }

        // Fallback: cerca negli ini path standard
        if (empty($versions)) {
            foreach (glob('/etc/php/*/fpm/php.ini') ?: [] as $ini) {
                if (preg_match('#/etc/php/(\d+\.\d+)/fpm#', $ini, $m)) {
                    $v = $m[1];
                    $versions[] = [
                        'version'         => $v,
                        'label'           => "PHP {$v} (FPM)",
                        'fpm_config_path' => $ini,
                    ];
                }
            }
        }

        return $versions;
    }
}
