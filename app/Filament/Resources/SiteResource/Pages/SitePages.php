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

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.pages.view-site-header', [
            'heading' => $this->getTitle(),
            'actions' => $this->getCachedHeaderActions(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [

            // Credenziali WordPress
            Actions\Action::make('credentials')
                ->label('Credenziali')
                ->icon('heroicon-o-key')
                ->color('success')
                ->visible(fn () => $this->record->isActive())
                ->modalHeading(fn () => 'Credenziali — ' . $this->record->domain)
                ->modalContent(fn () => view('filament.modals.wp-credentials', ['site' => $this->record]))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Chiudi'),

            // Riprova provisioning
            Actions\Action::make('retry')
                ->label('Riprova provisioning')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'error')
                ->requiresConfirmation()
                ->modalHeading('Riprendere il provisioning?')
                ->modalDescription(fn () => 'Ripartirà dallo step fallito: ' .
                    ($this->record->provisioningLogs()->where('status', 'failed')->latest()->first()?->step_label ?? 'inizio'))
                ->modalSubmitActionLabel('Sì, riprova')
                ->action(function () {
                    $lastFailed = $this->record->provisioningLogs()
                        ->where('status', 'failed')
                        ->latest()
                        ->first();

                    $lastFailed?->delete();

                    $jobClass = $lastFailed?->job_class
                        ?? \App\Jobs\Provisioning\CreateIspConfigDomainJob::class;

                    $this->record->update(['status' => 'provisioning', 'current_step' => null]);
                    dispatch(new $jobClass($this->record->fresh()));

                    \Filament\Notifications\Notification::make()
                        ->title('Provisioning ripreso')
                        ->body('Ripartito da: ' . ($lastFailed?->step_label ?? 'inizio'))
                        ->success()->send();
                }),

            // Reset pagine blueprint
            Actions\Action::make('reset_pages')
                ->label('Reset pagine')
                ->icon('heroicon-o-document-duplicate')
                ->color('warning')
                ->visible(fn () => $this->record->isActive() && $this->record->blueprint_id)
                ->requiresConfirmation()
                ->modalHeading('Resettare le pagine del blueprint?')
                ->modalDescription('Le pagine con gli stessi slug del blueprint verranno eliminate e ricreate.')
                ->modalSubmitActionLabel('Sì, resetta pagine')
                ->action(function () {
                    $connection = $this->record->server->connection();
                    $wpCli      = new \App\Services\WpCliService($connection);
                    $service    = new \App\Services\BlueprintService($wpCli);
                    $service->resetAndApplyPages($this->record, $this->record->blueprint);

                    \Filament\Notifications\Notification::make()
                        ->title('Pagine ripristinate')->success()->send();
                }),

            // Reset widget blueprint
            Actions\Action::make('reset_widgets')
                ->label('Reset widget')
                ->icon('heroicon-o-squares-2x2')
                ->color('warning')
                ->visible(fn () => $this->record->isActive() && $this->record->blueprint_id)
                ->requiresConfirmation()
                ->modalHeading('Resettare i widget del blueprint?')
                ->modalDescription('I widget nelle sidebar del blueprint verranno rimossi e ricreati.')
                ->modalSubmitActionLabel('Sì, resetta widget')
                ->action(function () {
                    $connection = $this->record->server->connection();
                    $wpCli      = new \App\Services\WpCliService($connection);
                    $service    = new \App\Services\BlueprintService($wpCli);
                    $service->resetAndApplyWidgets($this->record, $this->record->blueprint);

                    \Filament\Notifications\Notification::make()
                        ->title('Widget ripristinati')->success()->send();
                }),

            // Genera categorie AI
            Actions\Action::make('generate_categories')
                ->label('Genera categorie')
                ->icon('heroicon-o-tag')
                ->color('info')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Generare nuove categorie?')
                ->modalDescription('L\'AI genererà nuove categorie basate sulla descrizione del sito e le aggiungerà a quelle esistenti.')
                ->modalSubmitActionLabel('Genera')
                ->action(function () {
                    $created = \App\Services\CategoryGenerationService::generate($this->record, deleteExisting: false);

                    \Filament\Notifications\Notification::make()
                        ->title(count($created) . ' categorie aggiunte')
                        ->body(implode(', ', $created))
                        ->success()
                        ->send();
                }),

            // Reset password admin
            Actions\Action::make('reset_password')
                ->label('Reset password admin')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->action(function () {
                    $newPassword = \Illuminate\Support\Str::password(20);
                    $connection  = $this->record->server->connection();
                    $wpCli       = new \App\Services\WpCliService($connection);
                    $wpCli->resetAdminPassword($this->record->docroot, $newPassword);
                    $this->record->update(['wp_admin_password' => $newPassword]);

                    \Filament\Notifications\Notification::make()
                        ->title('Password resettata')
                        ->body("Nuova password: <code>{$newPassword}</code>")
                        ->success()->persistent()->send();
                }),

            // Purge Varnish
            Actions\Action::make('purge_varnish')
                ->label('Purge Varnish')
                ->icon('heroicon-o-trash')
                ->color('gray')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->action(function () {
                    $connection = $this->record->server->connection();
                    $varnish    = new \App\Services\VarnishService($connection);
                    $varnish->ban($this->record->domain);

                    \Filament\Notifications\Notification::make()
                        ->title('Cache Varnish svuotata')->success()->send();
                }),

            // Disabilita sito
            Actions\Action::make('disable')
                ->label('Disabilita sito')
                ->icon('heroicon-o-pause-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->action(function () {
                    $ispConfig = new \App\Services\IspConfigService($this->record->server);
                    $ispConfig->disableWebDomain($this->record->ispconfig_domain_id);
                    $this->record->update(['status' => 'disabled']);

                    \Filament\Notifications\Notification::make()
                        ->title('Sito disabilitato')->success()->send();
                }),

            // Abilita sito
            Actions\Action::make('enable')
                ->label('Abilita sito')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'disabled')
                ->requiresConfirmation()
                ->action(function () {
                    $ispConfig = new \App\Services\IspConfigService($this->record->server);
                    $ispConfig->enableWebDomain($this->record->ispconfig_domain_id);
                    $this->record->update(['status' => 'active']);

                    \Filament\Notifications\Notification::make()
                        ->title('Sito abilitato')->success()->send();
                }),

            // Modifica (solo in pending/error)
            Actions\EditAction::make()
                ->visible(fn () => in_array($this->record->status, ['pending', 'error'])),
        ];
    }

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
                    Infolists\Components\TextEntry::make('description')
                        ->label('Descrizione')
                        ->placeholder('—')
                        ->columnSpanFull(),
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

    // Polling ogni 3s durante provisioning, si ferma quando completato
    public function getPollingInterval(): ?string
    {
        return $this->record->fresh()->isProvisioning() ? '3s' : null;
    }

    // Aggiorna i log ad ogni poll senza perdere la posizione nella pagina
    public function refreshLogs(): void
    {
        $this->record = $this->record->fresh()->load('provisioningLogs');
    }

    public function getTitle(): string
    {
        return "Log provisioning — {$this->record->domain}";
    }
}
