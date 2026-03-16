<?php

namespace App\Services\Connections;

use App\Contracts\ServerConnection;
use App\Exceptions\ServerCommandException;
use App\Models\Server;
use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SshConnection implements ServerConnection
{
    private ?SSH2 $ssh = null;

    public function __construct(private readonly Server $server)
    {
    }

    private function connect(): SSH2
    {
        if ($this->ssh && $this->ssh->isConnected()) {
            return $this->ssh;
        }

        $ssh = new SSH2($this->server->ip);
        $key = PublicKeyLoader::load(file_get_contents($this->server->ssh_key_path));

        if (! $ssh->login($this->server->ssh_user, $key)) {
            throw new ServerCommandException(
                'SSH login',
                'Authentication failed',
                1
            );
        }

        $this->ssh = $ssh;
        return $this->ssh;
    }

    public function run(string $command): string
    {
        $ssh    = $this->connect();
        $output = $ssh->exec($command);

        if ($ssh->getExitStatus() !== 0) {
            throw new ServerCommandException(
                $command,
                $output,
                $ssh->getExitStatus()
            );
        }

        return $output;
    }

    public function upload(string $localPath, string $remotePath): void
    {
        $ssh = $this->connect();
        $sftp = new \phpseclib3\Net\SFTP($this->server->ip);
        $key  = PublicKeyLoader::load(file_get_contents($this->server->ssh_key_path));

        $sftp->login($this->server->ssh_user, $key);

        $dir = dirname($remotePath);
        $sftp->mkdir($dir, 0755, true);

        if (! $sftp->put($remotePath, $localPath, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE)) {
            throw new ServerCommandException(
                "sftp put {$localPath} {$remotePath}",
                'SFTP upload failed',
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
