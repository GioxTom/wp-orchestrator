<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Jobs\Provisioning\CreateIspConfigDomainJob;
use App\Models\Site;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\ViewRecord;

// ── Lista siti ────────────────────────────────────────────────────────────────
class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nuovo sito WordPress')];
    }
}

// ── Creazione sito (avvia provisioning) ───────────────────────────────────────
class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function afterCreate(): void
    {
        $this->record->update(['status' => 'provisioning']);

        // Avvia la pipeline di provisioning
        dispatch(new CreateIspConfigDomainJob($this->record));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}

// ── Vista dettaglio sito ──────────────────────────────────────────────────────
class ViewSite extends ViewRecord
{
    protected static string $resource = SiteResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Informazioni sito')
                ->schema([
                    Infolists\Components\TextEntry::make('domain')->label('Dominio'),
                    Infolists\Components\TextEntry::make('site_name')->label('Nome'),
                    Infolists\Components\TextEntry::make('locale')->label('Lingua'),
                    Infolists\Components\TextEntry::make('status')->label('Stato')->badge(),
                    Infolists\Components\TextEntry::make('wp_admin_email')->label('Email admin'),
                    Infolists\Components\TextEntry::make('blueprint.name')->label('Blueprint'),
                ])->columns(3),

            Infolists\Components\Section::make('Provisioning')
                ->schema([
                    Infolists\Components\TextEntry::make('current_step')
                        ->label('Step corrente')
                        ->placeholder('Completato'),

                    Infolists\Components\RepeatableEntry::make('provisioningLogs')
                        ->label('Pipeline')
                        ->schema([
                            Infolists\Components\TextEntry::make('step_label')->label('Step'),
                            Infolists\Components\TextEntry::make('status')->label('Stato')->badge(),
                            Infolists\Components\TextEntry::make('started_at')->label('Inizio')->since(),
                            Infolists\Components\TextEntry::make('finished_at')->label('Fine')->since(),
                        ])
                        ->columns(4),
                ]),

            Infolists\Components\Section::make('Ultimo audit')
                ->schema([
                    Infolists\Components\TextEntry::make('latestAudit.http_status')->label('HTTP'),
                    Infolists\Components\TextEntry::make('latestAudit.https_status')->label('HTTPS'),
                    Infolists\Components\TextEntry::make('latestAudit.cert_expiry_at')->label('Scadenza SSL')->date(),
                    Infolists\Components\IconEntry::make('latestAudit.admin_ok')->label('Admin OK')->boolean(),
                    Infolists\Components\IconEntry::make('latestAudit.mu_plugin_ok')->label('MU-Plugin OK')->boolean(),
                    Infolists\Components\TextEntry::make('latestAudit.checked_at')->label('Verificato')->since(),
                ])->columns(3),

            Infolists\Components\Section::make('Logo')
                ->schema([
                    Infolists\Components\ImageEntry::make('logo_url')->label('Logo generato')->height(80),
                    Infolists\Components\TextEntry::make('logo_generated_at')->label('Generato il')->dateTime(),
                ])->columns(2),
        ]);
    }
}

// ── Log provisioning ──────────────────────────────────────────────────────────
class SiteProvisioningLogs extends Page
{
    protected static string $resource = SiteResource::class;
    protected static string $view     = 'filament.resources.site-resource.pages.provisioning-logs';

    public Site $record;

    public function mount(Site $record): void
    {
        $this->record = $record->load('provisioningLogs');
    }

    public function getTitle(): string
    {
        return "Log provisioning — {$this->record->domain}";
    }
}
