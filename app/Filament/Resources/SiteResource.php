<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Jobs\Provisioning\CreateIspConfigDomainJob;
use App\Models\Blueprint;
use App\Models\IspConfigClient;
use App\Models\IspConfigPhpVersion;
use App\Models\Prompt;
use App\Models\Server;
use App\Models\Setting;
use App\Models\Site;
use App\Services\IspConfigService;
use App\Services\VarnishService;
use App\Services\WpCliService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class SiteResource extends Resource
{
    protected static ?string $model           = Site::class;
    protected static ?string $navigationIcon  = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Siti';
    protected static ?string $navigationLabel = 'Siti WordPress';
    protected static ?int    $navigationSort  = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── STEP 1: Server + Cliente ISPConfig ──────────────────────────
            Forms\Components\Section::make('Step 1 — Server e cliente ISPConfig')
                ->schema([
                    Forms\Components\Select::make('server_id')
                        ->label('Server')
                        ->options(Server::where('status', 'active')->pluck('name', 'id'))
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $set('ispconfig_client_id', null);
                            // Precompila la versione PHP con il default del server
                            if ($state) {
                                $server = Server::find($state);
                                $set('php_version_id', $server?->default_php_version_id);
                            } else {
                                $set('php_version_id', null);
                            }
                        }),

                    Forms\Components\Select::make('ispconfig_client_id')
                        ->label('Cliente ISPConfig')
                        ->options(fn ($get) => IspConfigClient::where('server_id', $get('server_id'))
                            ->get()
                            ->pluck('display_name', 'id'))
                        ->default(fn ($get) => IspConfigClient::getDefault(
                            $get('server_id') ?? \App\Models\Server::where('status', 'active')->value('id')
                        )?->id)
                        ->required()
                        ->searchable()
                        ->disabled(fn ($get) => ! $get('server_id')),

                    Forms\Components\Select::make('php_version_id')
                        ->label('Versione PHP')
                        ->options(fn ($get) => IspConfigPhpVersion::where('server_id', $get('server_id'))
                            ->pluck('label', 'id'))
                        ->required()
                        ->disabled(fn ($get) => ! $get('server_id')),

                    Forms\Components\Toggle::make('ssl_enabled')
                        ->label('SSL / Let\'s Encrypt')
                        ->default(true)
                        ->helperText('Abilita HTTPS con certificato Let\'s Encrypt via ISPConfig'),
                ])->columns(2),

            // ── STEP 2: Dati sito ────────────────────────────────────────────
            Forms\Components\Section::make('Step 2 — Dati del sito')
                ->schema([
                    Forms\Components\TextInput::make('domain')
                        ->label('Dominio')
                        ->placeholder('esempio.com')
                        ->helperText('Inserisci il dominio senza www')
                        ->required()
                        ->unique(ignoreRecord: true),

                    Forms\Components\Radio::make('www_mode')
                        ->label('URL principale')
                        ->options([
                            'www'    => 'www.esempio.com  (consigliato)',
                            'no-www' => 'esempio.com',
                        ])
                        ->default('www')
                        ->inline()
                        ->columnSpanFull()
                        ->required(),

                    Forms\Components\TextInput::make('site_name')
                        ->label('Nome del sito')
                        ->required(),

                    Forms\Components\Select::make('locale')
                        ->label('Lingua WordPress')
                        ->options([
                            'en_US' => '🇺🇸 English (US)',
                            'it_IT' => '🇮🇹 Italiano',
                            'de_DE' => '🇩🇪 Deutsch',
                            'fr_FR' => '🇫🇷 Français',
                            'es_ES' => '🇪🇸 Español',
                            'pt_PT' => '🇵🇹 Português',
                            'nl_NL' => '🇳🇱 Nederlands',
                        ])
                        ->default('en_US')
                        ->required(),

                    Forms\Components\TextInput::make('wp_admin_email')
                        ->label('Email admin WordPress')
                        ->email()
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione del sito')
                        ->rows(3)
                        ->helperText('Usata per generare il logo via NanaBanana. Descrivi brevemente il sito e il suo target.')
                        ->columnSpanFull(),
                ])->columns(2),

            // ── STEP 3: Blueprint + Prompt ───────────────────────────────────
            Forms\Components\Section::make('Step 3 — Blueprint e prompt logo')
                ->schema([
                    Forms\Components\Select::make('blueprint_id')
                        ->label('Blueprint')
                        ->options(Blueprint::where('status', 'active')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Seleziona il blueprint da applicare. Contiene tema, plugin e impostazioni WP.'),

                    Forms\Components\Select::make('prompt_id')
                        ->label('Prompt generazione logo')
                        ->options(fn () => Prompt::where('action', 'logo_generation')
                            ->orderBy('type')
                            ->get()
                            ->mapWithKeys(fn ($p) => [
                                $p->id => ($p->type === 'system' ? '⚙️ ' : '✏️ ') . $p->name,
                            ]))
                        ->searchable()
                        ->helperText('⚙️ = prompt di sistema (default)  |  ✏️ = tuo override'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label('Dominio')
                    ->searchable()
                    ->url(fn (Site $record) => "https://{$record->domain}", true),

                Tables\Columns\TextColumn::make('site_name')
                    ->label('Nome')
                    ->searchable(),

                Tables\Columns\TextColumn::make('locale')
                    ->label('Lingua')
                    ->badge(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'gray'    => 'pending',
                        'warning' => 'provisioning',
                        'success' => 'active',
                        'danger'  => 'error',
                        'gray'    => 'disabled',
                        'info'    => 'import_blueprint_pending',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'                   => 'In attesa',
                        'provisioning'              => 'Provisioning',
                        'active'                    => 'Attivo',
                        'disabled'                  => 'Disabilitato',
                        'error'                     => 'Errore',
                        'import_blueprint_pending'  => '⚠️ Conferma blueprint',
                        default                     => $state,
                    }),

                Tables\Columns\TextColumn::make('current_step')
                    ->label('Step corrente')
                    ->visible(fn () => true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('blueprint.name')
                    ->label('Blueprint')
                    ->placeholder('—'),

                Tables\Columns\BadgeColumn::make('logo_status')
                    ->label('Logo')
                    ->colors([
                        'gray'    => 'none',
                        'warning' => fn ($state) => in_array($state, ['pending', 'batch_pending']),
                        'success' => 'done',
                        'danger'  => 'failed',
                    ])
                    ->icons([
                        'heroicon-o-clock'        => fn ($state) => in_array($state, ['pending', 'batch_pending']),
                        'heroicon-o-check-circle' => 'done',
                        'heroicon-o-x-circle'     => 'failed',
                        'heroicon-o-minus-circle' => 'none',
                    ])
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'none'          => 'Nessuno',
                        'pending'       => 'In coda',
                        'batch_pending' => 'Batch in corso',
                        'done'          => 'Generato',
                        'failed'        => 'Fallito',
                        default         => $state,
                    }),

                Tables\Columns\TextColumn::make('latestAudit.checked_at')
                    ->label('Ultimo audit')
                    ->since()
                    ->placeholder('Mai'),

                Tables\Columns\TextColumn::make('created_at')->label('Creato')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending'      => 'In attesa',
                        'provisioning' => 'Provisioning',
                        'active'       => 'Attivo',
                        'error'        => 'Errore',
                        'disabled'     => 'Disabilitato',
                    ]),
                Tables\Filters\SelectFilter::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name'),
            ])
            ->actions([
                // Mostra credenziali WordPress
                Tables\Actions\Action::make('credentials')
                    ->icon('heroicon-o-key')
                    ->iconButton()
                    ->tooltip('Credenziali WordPress')
                    ->color('success')
                    ->visible(fn (Site $record) => $record->isActive())
                    ->modalHeading(fn (Site $record) => 'Credenziali — ' . $record->domain)
                    ->modalContent(fn (Site $record) => view('filament.modals.wp-credentials', ['site' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),

                // Riprova provisioning dopo un errore
                Tables\Actions\Action::make('retry')
                    ->label('Riprova')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->color('warning')
                    ->visible(fn (Site $record) => $record->status === 'error')
                    ->requiresConfirmation()
                    ->modalHeading('Riprendere il provisioning?')
                    ->modalDescription(fn (Site $record) => 'Ripartirà dallo step fallito: ' .
                        ($record->provisioningLogs()->where('status', 'failed')->latest()->first()?->step_label ?? 'inizio'))
                    ->modalSubmitActionLabel('Sì, riprova')
                    ->action(function (Site $record) {
                        // Trova l'ultimo job fallito
                        $lastFailed = $record->provisioningLogs()
                            ->where('status', 'failed')
                            ->latest()
                            ->first();

                        // Elimina solo il log fallito per permettere il retry
                        $lastFailed?->delete();

                        // Riparte dal job fallito, non dall'inizio
                        $jobClass = $lastFailed?->job_class
                            ?? \App\Jobs\Provisioning\CreateIspConfigDomainJob::class;

                        $record->update([
                            'status'       => 'provisioning',
                            'current_step' => null,
                        ]);

                        dispatch(new $jobClass($record->fresh()));

                        Notification::make()
                            ->title('Provisioning ripreso')
                            ->body('Ripartito da: ' . ($lastFailed?->step_label ?? 'inizio'))
                            ->success()
                            ->send();
                    }),

                // Reset e riapplica pagine blueprint
                Tables\Actions\Action::make('reset_pages')
                    ->label('Reset pagine')
                    ->icon('heroicon-o-document-duplicate')
                    ->iconButton()
                    ->color('warning')
                    ->visible(fn (Site $record) => $record->isActive() && $record->blueprint_id)
                    ->requiresConfirmation()
                    ->modalHeading('Resettare le pagine del blueprint?')
                    ->modalDescription('Le pagine con gli stessi slug del blueprint verranno eliminate e ricreate.')
                    ->modalSubmitActionLabel('Sì, resetta pagine')
                    ->action(function (Site $record) {
                        $connection = $record->server->connection();
                        $wpCli      = new \App\Services\WpCliService($connection);
                        $service    = new \App\Services\BlueprintService($wpCli);
                        $service->resetAndApplyPages($record, $record->blueprint);

                        Notification::make()
                            ->title('Pagine ripristinate')
                            ->success()
                            ->send();
                    }),

                // Reset e riapplica widget blueprint
                Tables\Actions\Action::make('reset_widgets')
                    ->label('Reset widget')
                    ->icon('heroicon-o-squares-2x2')
                    ->iconButton()
                    ->color('warning')
                    ->visible(fn (Site $record) => $record->isActive() && $record->blueprint_id)
                    ->requiresConfirmation()
                    ->modalHeading('Resettare i widget del blueprint?')
                    ->modalDescription('I widget nelle sidebar del blueprint verranno rimossi e ricreati.')
                    ->modalSubmitActionLabel('Sì, resetta widget')
                    ->action(function (Site $record) {
                        $connection = $record->server->connection();
                        $wpCli      = new \App\Services\WpCliService($connection);
                        $service    = new \App\Services\BlueprintService($wpCli);
                        $service->resetAndApplyWidgets($record, $record->blueprint);

                        Notification::make()
                            ->title('Widget ripristinati')
                            ->success()
                            ->send();
                    }),

                // Visualizza log provisioning
                Tables\Actions\Action::make('logs')
                    ->label('Log')
                    ->icon('heroicon-o-document-text')
                    ->iconButton()
                    ->color('gray')
                    ->url(fn (Site $record) => route('filament.admin.resources.sites.logs', $record)),

                // Reset password admin
                Tables\Actions\Action::make('reset_password')
                    ->label('Reset password admin')
                    ->icon('heroicon-o-key')
                    ->iconButton()
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Site $record) => $record->isActive())
                    ->action(function (Site $record) {
                        $newPassword = Str::password(20);
                        $connection  = $record->server->connection();
                        $wpCli       = new WpCliService($connection);
                        $wpCli->resetAdminPassword($record->docroot, $newPassword);

                        // Salva encrypted
                        $record->update(['wp_admin_password' => $newPassword]);

                        Notification::make()
                            ->title('Password resettata')
                            ->body("Nuova password: <code>{$newPassword}</code><br>Copiala ora — non verrà mostrata di nuovo.")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                // Conferma applicazione blueprint (sito importato con WP già installato)
                Tables\Actions\Action::make('confirm_blueprint')
                    ->label('Applica blueprint')
                    ->icon('heroicon-o-puzzle-piece')
                    ->iconButton()
                    ->color('warning')
                    ->visible(fn (Site $record) => $record->status === 'import_blueprint_pending')
                    ->requiresConfirmation()
                    ->modalHeading('Applicare il blueprint al sito importato?')
                    ->modalDescription(fn (Site $record) =>
                        "Il sito {$record->domain} è stato importato con WordPress già installato. " .
                        "Il blueprint \"{$record->blueprint?->name}\" verrà applicato: " .
                        "tema e plugin verranno installati/sostituiti. " .
                        "I contenuti esistenti NON verranno toccati."
                    )
                    ->modalSubmitActionLabel('Sì, applica blueprint')
                    ->action(function (Site $record) {
                        $record->update([
                            'status'       => 'provisioning',
                            'current_step' => 'Importazione WordPress',
                        ]);
                        dispatch(new \App\Jobs\Provisioning\ImportWordPressJob($record));
                        Notification::make()
                            ->title('Blueprint in applicazione')
                            ->body('Il sito verrà aggiornato a breve.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('skip_blueprint')
                    ->label('Salta blueprint')
                    ->icon('heroicon-o-forward')
                    ->iconButton()
                    ->color('gray')
                    ->visible(fn (Site $record) => $record->status === 'import_blueprint_pending')
                    ->requiresConfirmation()
                    ->modalHeading('Saltare il blueprint?')
                    ->modalDescription('Il sito verrà importato così com\'è, senza applicare il blueprint.')
                    ->modalSubmitActionLabel('Sì, salta')
                    ->action(function (Site $record) {
                        $record->update(['blueprint_id' => null]);
                        dispatch(new \App\Jobs\Provisioning\ImportWordPressJob($record));
                        Notification::make()
                            ->title('Importazione avviata senza blueprint')
                            ->success()
                            ->send();
                    }),

                // Rigenera logo
                Tables\Actions\Action::make('regenerate_logo')
                    ->label('Rigenera logo')
                    ->icon('heroicon-o-sparkles')
                    ->iconButton()
                    ->color('info')
                    ->visible(fn (Site $record) => $record->isActive())
                    ->action(function (Site $record) {
                        $record->update([
                            'logo_status'    => 'none',
                            'logo_batch_job' => null,
                        ]);
                        dispatch(new \App\Jobs\Provisioning\GenerateLogoJob($record));
                        Notification::make()
                            ->title('Rigenerazione logo avviata')
                            ->body(Setting::get('gemini_use_batch', '0') === '1'
                                ? 'Modalità batch — pronto in 2–10 minuti.'
                                : 'Modalità sincrona — pronto in ~60 secondi.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('purge_varnish')
                    ->label('Purge Varnish')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->color('info')
                    ->visible(fn (Site $record) => $record->isActive())
                    ->action(function (Site $record) {
                        $connection = $record->server->connection();
                        $varnish    = new VarnishService($connection);
                        $varnish->ban($record->domain);

                        Notification::make()
                            ->title('Cache Varnish invalidata')
                            ->success()->send();
                    }),

                // Enable/Disable sito
                Tables\Actions\Action::make('disable')
                    ->label('Disabilita')
                    ->icon('heroicon-o-pause-circle')
                    ->iconButton()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Site $record) => $record->status === 'active')
                    ->action(function (Site $record) {
                        $ispConfig = new IspConfigService($record->server);
                        $ispConfig->disableWebDomain($record->ispconfig_domain_id);
                        $ispConfig->disconnect();
                        $record->update(['status' => 'disabled']);
                        Notification::make()->title('Sito disabilitato')->warning()->send();
                    }),

                Tables\Actions\Action::make('enable')
                    ->label('Abilita')
                    ->icon('heroicon-o-play-circle')
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (Site $record) => $record->status === 'disabled')
                    ->action(function (Site $record) {
                        $ispConfig = new IspConfigService($record->server);
                        $ispConfig->enableWebDomain($record->ispconfig_domain_id);
                        $ispConfig->disconnect();
                        $record->update(['status' => 'active']);
                        Notification::make()->title('Sito abilitato')->success()->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn (Site $record) => in_array($record->status, ['pending', 'error'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'view'   => Pages\ViewSite::route('/{record}'),
            'logs'   => Pages\SiteProvisioningLogs::route('/{record}/logs'),
        ];
    }
}
