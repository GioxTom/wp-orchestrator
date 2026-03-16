<?php

namespace App\Filament\Resources\PromptResource\Pages;

use App\Filament\Resources\PromptResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListPrompts extends ListRecords
{
    protected static string $resource = PromptResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nuovo prompt utente')];
    }
}

class CreatePrompt extends CreateRecord
{
    protected static string $resource = PromptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['type'] = 'user'; // I prompt creati dal pannello sono sempre 'user'
        return $data;
    }
}

class EditPrompt extends EditRecord
{
    protected static string $resource = PromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDeletable()),
        ];
    }
}
