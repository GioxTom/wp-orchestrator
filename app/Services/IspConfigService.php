<?php

namespace App\Services;

use App\Models\IspConfigClient;
use App\Models\IspConfigPhpVersion;
use App\Models\Server;
use SoapClient;
use SoapFault;

class IspConfigService
{
    private ?SoapClient $client = null;
    private ?string $sessionId  = null;

    public function __construct(private readonly Server $server)
    {
    }

    /**
     * Apre la sessione SOAP con ISPConfig.
     */
    private function connect(): void
    {
        if ($this->sessionId) {
            return;
        }

        $this->client = new SoapClient(null, [
            'location'   => rtrim($this->server->ispconfig_api_url, '/') . '/index.php',
            'uri'        => 'urn:ispconfig',
            'trace'      => true,
            'exceptions' => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                ],
            ]),
        ]);

        $this->sessionId = $this->client->login(
            $this->server->ispconfig_user,
            $this->server->ispconfig_password
        );
    }

    /**
     * Chiude la sessione SOAP.
     */
    public function disconnect(): void
    {
        if ($this->client && $this->sessionId) {
            try {
                $this->client->logout($this->sessionId);
            } catch (\Throwable) {
                // ignora errori di logout
            }
            $this->sessionId = null;
        }
    }

    /**
     * Recupera tutti i clienti da ISPConfig e li sincronizza nel DB locale.
     */
    public function syncClients(): int
    {
        $this->connect();

        $remoteClients = $this->client->client_get_all($this->sessionId);
        $count         = 0;

        foreach ($remoteClients as $client) {
            IspConfigClient::updateOrCreate(
                [
                    'server_id'          => $this->server->id,
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

    /**
     * Recupera le versioni PHP disponibili su ISPConfig.
     */
    public function syncPhpVersions(): int
    {
        $this->connect();

        $versions = $this->client->server_php_get_all($this->sessionId) ?? [];
        $count    = 0;

        foreach ($versions as $v) {
            IspConfigPhpVersion::updateOrCreate(
                [
                    'server_id' => $this->server->id,
                    'version'   => $v['php_version'] ?? $v['name'] ?? 'unknown',
                ],
                [
                    'label'           => $v['name'] ?? $v['php_version'],
                    'fpm_config_path' => $v['php_fpm_ini_dir'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Crea un vhost (web domain) su ISPConfig.
     * Restituisce l'ID del dominio creato.
     */
    public function createWebDomain(array $params): int
    {
        $this->connect();

        $defaults = [
            'server_id'           => 1,
            'ip_address'          => '*',
            'ipv6_address'        => '',
            'domain'              => '',
            'hd_quota'            => -1,
            'traffic_quota'       => -1,
            'cgi'                 => 'n',
            'ssi'                 => 'n',
            'suexec'              => 'y',
            'errordocs'           => 1,
            'is_subdomainwww'     => 1,
            'subdomain'           => 'www',
            'php'                 => 'php-fpm',
            'php_fpm_use_socket'  => 'y',
            'ruby'                => 'n',
            'redirect_type'       => '',
            'redirect_path'       => '',
            'ssl'                 => 'n',
            'ssl_letsencrypt'     => 'n',
            'stats_password'      => '',
            'allow_override'      => 'All',
            'apache_directives'   => '',
            'nginx_directives'    => '',
            'active'              => 'y',
        ];

        $merged = array_merge($defaults, $params);

        $domainId = $this->client->sites_web_domain_add(
            $this->sessionId,
            $params['client_id'],
            $merged,
            'sites'
        );

        if (! $domainId) {
            throw new \RuntimeException("ISPConfig: impossibile creare il dominio {$params['domain']}");
        }

        return (int) $domainId;
    }

    /**
     * Crea un database MySQL su ISPConfig per il sito WordPress.
     * Restituisce l'ID del database creato.
     */
    public function createDatabase(array $params): int
    {
        $this->connect();

        $dbId = $this->client->sites_database_add(
            $this->sessionId,
            $params['client_id'],
            [
                'server_id'       => 1,
                'type'            => 'mysql',
                'database_name'   => $params['db_name'],
                'database_user'   => $params['db_user'],
                'database_password' => $params['db_password'],
                'database_charset'  => 'utf8mb4',
                'remote_access'     => 'n',
                'active'            => 'y',
            ],
            'sites'
        );

        if (! $dbId) {
            throw new \RuntimeException("ISPConfig: impossibile creare il database {$params['db_name']}");
        }

        return (int) $dbId;
    }

    /**
     * Disabilita un vhost su ISPConfig.
     */
    public function disableWebDomain(int $domainId): void
    {
        $this->connect();

        $domain = $this->client->sites_web_domain_get($this->sessionId, $domainId);
        $domain['active'] = 'n';

        $this->client->sites_web_domain_update(
            $this->sessionId,
            $domain['sys_userid'] ?? 1,
            $domainId,
            $domain,
            'sites'
        );
    }

    /**
     * Abilita un vhost su ISPConfig.
     */
    public function enableWebDomain(int $domainId): void
    {
        $this->connect();

        $domain = $this->client->sites_web_domain_get($this->sessionId, $domainId);
        $domain['active'] = 'y';

        $this->client->sites_web_domain_update(
            $this->sessionId,
            $domain['sys_userid'] ?? 1,
            $domainId,
            $domain,
            'sites'
        );
    }
}
