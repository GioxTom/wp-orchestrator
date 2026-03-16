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
     * Copia un file locale in un'altra path locale.
     */
    public function upload(string $localPath, string $remotePath): void
    {
        $dir = dirname($remotePath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! copy($localPath, $remotePath)) {
            throw new ServerCommandException(
                "copy {$localPath} {$remotePath}",
                'File copy failed',
                1
            );
        }
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
