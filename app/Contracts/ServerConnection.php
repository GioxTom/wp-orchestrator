<?php

namespace App\Contracts;

interface ServerConnection
{
    /**
     * Esegue un comando sul server e restituisce l'output.
     *
     * @throws \App\Exceptions\ServerCommandException
     */
    public function run(string $command): string;

    /**
     * Carica un file locale sul server.
     *
     * @throws \App\Exceptions\ServerCommandException
     */
    public function upload(string $localPath, string $remotePath): void;

    /**
     * Verifica che la connessione sia funzionante.
     */
    public function test(): bool;
}
