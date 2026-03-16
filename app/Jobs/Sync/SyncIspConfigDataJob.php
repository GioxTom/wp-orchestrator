<?php

namespace App\Jobs\Sync;

use App\Models\Server;
use App\Services\IspConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncIspConfigDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;

    public function __construct(private readonly Server $server)
    {
    }

    public function handle(): void
    {
        $ispConfig = new IspConfigService($this->server);

        try {
            $clients  = $ispConfig->syncClients();
            $versions = $ispConfig->syncPhpVersions();

            Log::info("SyncIspConfigDataJob: server #{$this->server->id} — {$clients} clienti, {$versions} versioni PHP sincronizzate");

            $this->server->update(['status' => 'active']);

        } catch (\Throwable $e) {
            Log::error("SyncIspConfigDataJob: errore server #{$this->server->id}: " . $e->getMessage());
            $this->server->update(['status' => 'error']);
            throw $e;
        } finally {
            $ispConfig->disconnect();
        }
    }
}
