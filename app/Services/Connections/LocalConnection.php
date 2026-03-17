<?php

namespace App\Services\Connections;

use App\Contracts\ServerConnection;
use App\Exceptions\ServerCommandException;
use Symfony\Component\Process\Process;

class LocalConnection implements ServerConnection
{
    /**
     * Esegue un comando in locale tramite Symfony Process.
     * Ideale per fase 1: orchestrator sullo stesso server dei siti.
     */
    public function run(string $command): string
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300); // 5 minuti per operazioni lunghe (WP install)
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ServerCommandException(
                $command,
                $process->getErrorOutput() ?: $process->getOutput(),
                $process->getExitCode()
            );
        }

        return $process->getOutput();
    }

    /**
     * Copia un file locale in una path di destinazione.
     * Usa sudo cp se la destinazione non è scrivibile dall'utente corrente.
     */
    public function upload(string $localPath, string $remotePath): void
    {
        $dir = dirname($remotePath);

        // Prova prima con operazioni PHP dirette
        if (is_writable($dir) || (! is_dir($dir) && is_writable(dirname($dir)))) {
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (! copy($localPath, $remotePath)) {
                throw new ServerCommandException("copy {$localPath} {$remotePath}", 'File copy failed', 1);
            }
            return;
        }

        // Directory non scrivibile — usa sudo cp
        // Rileva l'utente proprietario della directory di destinazione
        $owner = $this->getOwner($dir);

        if ($owner && $owner !== 'root') {
            $process = Process::fromShellCommandline(
                "sudo -u {$owner} cp " . escapeshellarg($localPath) . ' ' . escapeshellarg($remotePath)
            );
        } else {
            $process = Process::fromShellCommandline(
                "sudo cp " . escapeshellarg($localPath) . ' ' . escapeshellarg($remotePath)
            );
        }

        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ServerCommandException(
                "sudo cp {$localPath} {$remotePath}",
                $process->getErrorOutput() ?: $process->getOutput(),
                $process->getExitCode()
            );
        }
    }

    private function getOwner(string $path): ?string
    {
        // Risale la gerarchia per trovare una directory esistente e leggibile
        while ($path && $path !== '/') {
            if (is_dir($path)) {
                $entries = @scandir($path);
                if ($entries !== false) {
                    foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') continue;
                        $stat = @stat($path . '/' . $entry);
            if ($stat) {
                $info = posix_getpwuid($stat['uid']);
                if ($info && $info['name'] !== 'root') {
                    return $info['name'];
                }
            }
        }
                }
            }
            $path = dirname($path);
        }

        return null;
    }

    public function test(): bool
    {
        try {
            $this->run('echo ok');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
