<?php

namespace App\Jobs\Provisioning;

use App\Services\BlueprintService;
use App\Services\WpCliService;

class ApplyBlueprintJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Applicazione Blueprint'; }
    protected function nextJob(): ?string  { return DeployMuPluginJob::class; }

    protected function execute(): void
    {
        $site      = $this->site->fresh();
        $blueprint = $site->blueprint;

        if (! $blueprint) {
            // Nessun blueprint selezionato: step saltato silenziosamente
            return;
        }

        $connection     = $site->server->connection();
        $wpCli          = new WpCliService($connection);
        $blueprintService = new BlueprintService($wpCli);

        $blueprintService->apply($site, $blueprint);
    }
}
