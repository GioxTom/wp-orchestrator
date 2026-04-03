<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Jobs\Sync\SyncIspConfigDataJob;
use App\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ServerResource extends Resource
{
    protected static ?string $model           = Server::class;
    protected static ?string $navigationIcon  = 'heroicon-o-server';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Server';
    protected static ?int    $navigationSort  = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Guida introduttiva ────────────────────────────────────────────
            Forms\Components\Section::make('📖 Prima di aggiungere un server')
                ->collapsed()
                ->schema([
                    Forms\Components\Placeholder::make('server_guide')
                        ->label('')
                        ->content(new HtmlString('
<div class="space-y-4 text-sm">

    <div>
        <h4 class="font-semibold mb-2">Stesso server o server remoto?</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left">Scenario</th>
                        <th class="px-4 py-2 text-left">Tipo connessione</th>
                        <th class="px-4 py-2 text-left">IP da usare</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr>
                        <td class="px-4 py-2">Orchestrator e siti sullo <strong>stesso server</strong></td>
                        <td class="px-4 py-2"><span class="font-mono">Locale</span></td>
                        <td class="px-4 py-2 font-mono">127.0.0.1</td>
                    </tr>
                    <tr>
                        <td class="px-4 py-2">Orchestrator su server A, siti su server B</td>
                        <td class="px-4 py-2"><span class="font-mono">SSH</span></td>
                        <td class="px-4 py-2 font-mono">IP pubblico di B</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h4 class="font-semibold mb-2">Prerequisiti sul server target</h4>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><strong>ISPConfig 3.3</strong> installato e operativo</li>
            <li><strong>WP-CLI</strong> installato globalmente: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">which wp</span> deve rispondere</li>
            <li><strong>PHP 8.3</strong> con estensione <span class="font-mono">soap</span> abilitata</li>
            <li>Utente <span class="font-mono">orchestrator</span> con permessi di scrittura sui docroot</li>
        </ul>
    </div>

    <div>
        <h4 class="font-semibold mb-2">Come abilitare le API Remote di ISPConfig</h4>
        <ol class="list-decimal list-inside space-y-1 ml-2">
            <li>Accedi al pannello ISPConfig come admin</li>
            <li>Vai su <strong>System → Remote Users</strong></li>
            <li>Crea un nuovo utente remoto con i permessi: <em>Sites, Server, Client</em></li>
            <li>L\'URL da inserire è solo il base: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">https://tuoserver:8080</span></li>
        </ol>
        <p class="mt-2 text-xs italic">Se ISPConfig usa una porta diversa dalla 8080, adatta l\'URL di conseguenza.</p>
    </div>

    <div>
        <h4 class="font-semibold mb-2">Connessione SSH (solo per server remoti)</h4>
        <p class="mb-2">L\'orchestrator usa una chiave SSH dedicata per eseguire comandi remoti (WP-CLI, operazioni file). Per generarla:</p>
        <div class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded space-y-1">
            <p>ssh-keygen -t ed25519 -f /home/orchestrator/.ssh/id_orchestrator -N ""</p>
            <p>ssh-copy-id -i /home/orchestrator/.ssh/id_orchestrator.pub utente@server-remoto</p>
        </div>
        <p class="mt-2 text-xs">Inserisci il path della chiave privata nel campo <strong>Path chiave SSH</strong>: <span class="font-mono">/home/orchestrator/.ssh/id_orchestrator</span></p>
    </div>

</div>
                        ')),
                ]),

            // ── Configurazione server ─────────────────────────────────────────
            Forms\Components\Section::make('Configurazione Server')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome server')
                        ->placeholder('es. Server principale')
                        ->helperText('Nome descrittivo per identificare il server nel pannello.')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('hostname')
                        ->label('Hostname')
                        ->placeholder('es. server1.tuodominio.com')
                        ->helperText('Nome DNS del server. Usato solo come riferimento descrittivo.')
                        ->required(),

                    Forms\Components\Select::make('connection_type')
                        ->label('Tipo connessione')
                        ->options([
                            'local' => 'Locale — orchestrator sullo stesso server',
                            'ssh'   => 'SSH — server remoto',
                        ])
                        ->default('local')
                        ->live()
                        ->required()
                        ->helperText('Scegli "Locale" se questo pannello gira sullo stesso server dei siti WordPress.'),

                    Forms\Components\TextInput::make('ip')
                        ->label('Indirizzo IP')
                        ->required()
                        ->default(fn (Get $get) => $get('connection_type') === 'local' ? '127.0.0.1' : '')
                        ->placeholder(fn (Get $get) => $get('connection_type') === 'local' ? '127.0.0.1' : 'es. 1.2.3.4')
                        ->helperText(fn (Get $get) => $get('connection_type') === 'local'
                            ? '✅ Stesso server: usa 127.0.0.1 (loopback). Non serve l\'IP pubblico.'
                            : 'Server remoto: inserisci l\'IP pubblico o privato raggiungibile dall\'orchestrator.')
                        ->live(),

                    // Campi SSH — visibili solo per connessione remota
                    Forms\Components\TextInput::make('ssh_user')
                        ->label('Utente SSH')
                        ->placeholder('es. root oppure orchestrator')
                        ->helperText('Utente con cui connettersi via SSH. Deve avere accesso a WP-CLI e ai docroot.')
                        ->visible(fn (Get $get) => $get('connection_type') === 'ssh'),

                    Forms\Components\TextInput::make('ssh_key_path')
                        ->label('Path chiave SSH privata')
                        ->placeholder('/home/orchestrator/.ssh/id_orchestrator')
                        ->helperText('Path assoluto alla chiave privata sul server dove gira l\'orchestrator. La chiave pubblica deve essere già autorizzata sul server remoto.')
                        ->visible(fn (Get $get) => $get('connection_type') === 'ssh'),

                ])->columns(2),

            // ── ISPConfig API ─────────────────────────────────────────────────
            Forms\Components\Section::make('ISPConfig API')
                ->schema([
                    Forms\Components\TextInput::make('ispconfig_api_url')
                        ->label('URL ISPConfig')
                        ->placeholder('https://127.0.0.1:8080')
                        ->helperText('Inserisci solo il base URL (es. https://127.0.0.1:8080). Il percorso /remote/json.php viene aggiunto automaticamente.')
                        ->required()
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('ispconfig_user')
                        ->label('Utente API ISPConfig')
                        ->helperText('Utente creato in ISPConfig sotto System → Remote Users.')
                        ->required(),

                    Forms\Components\TextInput::make('ispconfig_password')
                        ->label('Password API ISPConfig')
                        ->password()
                        ->revealable()
                        ->helperText('Lascia vuoto per mantenere la password attuale.')
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn ($state) => filled($state)),

                ])->columns(2),

            // ── Porte Apache ──────────────────────────────────────────────────
            Forms\Components\Section::make('Porte Apache')
                ->description('Porta interna su cui Apache risponde. Nel tuo setup è 8082 (Nginx fa da proxy davanti).')
                ->schema([
                    Forms\Components\TextInput::make('apache_http_port')
                        ->label('Porta HTTP Apache')
                        ->numeric()
                        ->default(8082)
                        ->required()
                        ->helperText('Usata da ISPConfig come http_port nel vhost (es. 8082).'),

                    Forms\Components\TextInput::make('apache_https_port')
                        ->label('Porta HTTPS Apache')
                        ->numeric()
                        ->default(8082)
                        ->required()
                        ->helperText('Solitamente uguale alla HTTP in setup con Nginx+Varnish.'),
                ])->columns(2),

            // ── PHP-FPM Defaults ──────────────────────────────────────────────
            Forms\Components\Section::make('PHP-FPM — Valori di default')
                ->description('Usati per tutti i siti su questo server, salvo override nel singolo sito.')
                ->schema([
                    Forms\Components\Select::make('default_pm')
                        ->label('Process Manager (pm)')
                        ->options([
                            'ondemand' => 'ondemand — avvia worker su richiesta (consigliato)',
                            'dynamic'  => 'dynamic — pool variabile',
                            'static'   => 'static — numero fisso di worker',
                        ])
                        ->default('ondemand')
                        ->required(),

                    Forms\Components\TextInput::make('default_pm_max_children')
                        ->label('pm.max_children')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->required(),

                    Forms\Components\TextInput::make('default_pm_process_idle_timeout')
                        ->label('pm.process_idle_timeout (s)')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->required()
                        ->helperText('Solo per ondemand: secondi prima di terminare worker inattivi.'),

                    Forms\Components\TextInput::make('default_pm_max_requests')
                        ->label('pm.max_requests')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->required()
                        ->helperText('0 = illimitato. Utile per gestire memory leak.'),

                    Forms\Components\TextInput::make('default_hd_quota')
                        ->label('Quota disco default (MB)')
                        ->numeric()
                        ->default(-1)
                        ->required()
                        ->helperText('-1 = illimitato.'),

                ])->columns(2),

            // ── Impostazioni predefinite ──────────────────────────────────────
            Forms\Components\Section::make('Impostazioni predefinite')
                ->schema([
                    Forms\Components\Select::make('default_php_version_id')
                        ->label('Versione PHP predefinita')
                        ->options(fn (Get $get, $record) => $record
                            ? \App\Models\IspConfigPhpVersion::where('server_id', $record->id)
                                ->pluck('label', 'id')
                            : [])
                        ->placeholder('Seleziona dopo la prima sync')
                        ->helperText('Verrà preselezionata automaticamente durante la creazione di un nuovo sito su questo server.')
                        ->searchable(),
                ]),

            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->placeholder('Informazioni aggiuntive sul server, versione OS, ecc.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->description(fn (Server $record) => $record->hostname),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP'),
                Tables\Columns\BadgeColumn::make('connection_type')
                    ->label('Connessione')
                    ->colors(['success' => 'local', 'info' => 'ssh'])
                    ->formatStateUsing(fn ($state) => $state === 'local' ? 'Locale' : 'SSH'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors(['success' => 'active', 'danger' => 'error', 'warning' => 'inactive']),
                Tables\Columns\TextColumn::make('apache_http_port')
                    ->label('Porta Apache'),
                Tables\Columns\TextColumn::make('default_pm')
                    ->label('PM default'),
                Tables\Columns\TextColumn::make('ispConfigClients_count')
                    ->label('Clienti')
                    ->counts('ispConfigClients'),
                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Siti')
                    ->counts('sites'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Aggiornato')
                    ->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('sync')
                    ->label('Sincronizza ISPConfig')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (Server $record) {
                        dispatch(new SyncIspConfigDataJob($record));
                        Notification::make()
                            ->title('Sincronizzazione avviata')
                            ->body('Clienti e versioni PHP verranno aggiornati a breve.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('test')
                    ->label('Test connessione')
                    ->icon('heroicon-o-signal')
                    ->color('warning')
                    ->action(function (Server $record) {
                        $results = [];
                        $allOk   = true;

                        // Test 1 — connessione shell (locale o SSH)
                        $shellOk = $record->connection()->test();
                        $results[] = ($shellOk ? '✅' : '❌') . ' Connessione shell: ' .
                            ($shellOk ? 'OK' : 'FALLITA — verifica IP e tipo connessione');
                        if (! $shellOk) $allOk = false;

                        // Test 2 — credenziali ISPConfig SOAP
                        try {
                            $ispConfig = new \App\Services\IspConfigService($record);
                            $ispConfig->testConnection();
                            $results[] = '✅ Credenziali ISPConfig: OK';
                        } catch (\Throwable $e) {
                            $results[] = '❌ Credenziali ISPConfig: FALLITE — ' . $e->getMessage();
                            $allOk = false;
                        }

                        // Test 3 — WP-CLI disponibile
                        try {
                            $record->connection()->run('wp --info --allow-root 2>&1 | head -1');
                            $results[] = '✅ WP-CLI: trovato';
                        } catch (\Throwable) {
                            $results[] = '⚠️ WP-CLI: non trovato (necessario per il provisioning)';
                        }

                        Notification::make()
                            ->title($allOk ? 'Tutti i test superati' : 'Alcuni test falliti')
                            ->body(implode("\n", $results))
                            ->status($allOk ? 'success' : 'danger')
                            ->persistent(! $allOk)
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListServers::route('/'),
            'create' => Pages\CreateServer::route('/create'),
            'edit'   => Pages\EditServer::route('/{record}/edit'),
        ];
    }
}
