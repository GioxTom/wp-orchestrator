<?php

namespace App\Jobs\Provisioning;

use App\Services\VarnishService;

class PurgeVarnishJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Purge Varnish cache'; }
    protected function nextJob(): ?string  { return null; } // ultimo step bloccante

    protected function execute(): void
    {
        $site       = $this->site->fresh();
        $connection = $site->server->connection();
        $varnish    = new VarnishService($connection);

        $varnish->ban($site->domain);

        // Il sito è ora ACTIVE — il parent lo gestisce dopo nextJob() = null
        // Dispatch asincrono del logo (non bloccante)
        dispatch(new GenerateLogoJob($site))->delay(now()->addSeconds(10));
    }
}
