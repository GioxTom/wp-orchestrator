<?php

namespace App\Filament\Resources\BlueprintResource\Pages;

use App\Filament\Resources\BlueprintResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;

class ListBlueprints extends ListRecords
{
    protected static string $resource = BlueprintResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}

class CreateBlueprint extends CreateRecord
{
    protected static string $resource = BlueprintResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return BlueprintResource::mutateFormDataBeforeCreate($data);
    }
}

class EditBlueprint extends EditRecord
{
    protected static string $resource = BlueprintResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BlueprintResource::mutateFormDataBeforeSave($data);
    }

    /**
     * Quando si apre il form in edit, converte wp_settings da
     * {key: value} (formato DB) a [{key, value}] (formato Repeater).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['wp_settings']) && is_array($data['wp_settings'])) {
            $knownKeys = array_keys(BlueprintResource::WP_SETTINGS_OPTIONS ?? []);

            $data['wp_settings'] = collect($data['wp_settings'])
                ->map(function ($value, $key) use ($knownKeys) {
                    $isKnown = in_array($key, $knownKeys) && $key !== 'custom';
                    return [
                        'key'        => $isKnown ? $key : 'custom',
                        'custom_key' => $isKnown ? null : $key,
                        'value'      => $value,
                    ];
                })
                ->values()
                ->toArray();
        }

        return $data;
    }
}
