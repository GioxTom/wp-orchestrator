<?php

namespace App\Jobs\Provisioning;

use App\Services\CategoryGenerationService;

class GenerateCategoriesJob extends BaseProvisioningJob
{
    protected function stepLabel(): string { return 'Generazione categorie AI'; }
    protected function nextJob(): ?string  { return ApplyBlueprintJob::class; }

    protected function execute(): void
    {
        $site = $this->site->fresh();

        if (! $site->auto_categories) {
            return;
        }

        // Durante il provisioning elimina le esistenti e ricrea
        CategoryGenerationService::generate($site, deleteExisting: true);
        }
        }
