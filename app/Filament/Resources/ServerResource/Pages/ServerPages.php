<?php

namespace App\Filament\Resources\ServerResource\Pages;

use App\Filament\Resources\ServerResource;
use App\Jobs\Sync\SyncIspConfigDataJob;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListServers extends ListRecords
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    protected function afterCreate(): void
    {
        // Avvia sync automatico dopo la creazione
        dispatch(new SyncIspConfigDataJob($this->record));
    }
}

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
