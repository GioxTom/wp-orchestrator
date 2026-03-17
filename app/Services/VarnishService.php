<?php

namespace App\Services;

use App\Contracts\ServerConnection;

class VarnishService
{
    public function __construct(private readonly ServerConnection $connection)
    {
    }

    /**
     * Invalida la cache Varnish per un dominio specifico.
     */
    public function ban(string $domain): string
    {
        $cmd = "sudo varnishadm ban 'req.http.host == {$domain}'";
        return $this->connection->run($cmd);
    }

    /**
     * Verifica che il sito risponda correttamente attraverso Varnish (porta 80).
     */
    public function checkHealth(string $domain): bool
    {
        try {
            $output = $this->connection->run(
                "curl -s -o /dev/null -w '%{http_code}' http://{$domain}/"
            );
            return trim($output) === '200' || trim($output) === '301';
        } catch (\Throwable) {
            return false;
        }
    }
}
