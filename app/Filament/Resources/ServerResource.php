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
            <li>L\'URL API sarà: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">https://tuoserver:8080/remote/json.php</span></li>
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
                        ->label('URL API ISPConfig')
                        ->placeholder('https://127.0.0.1:8080/remote/json.php')
                        ->helperText('Percorso completo all\'endpoint SOAP di ISPConfig. Di solito porta 8080. Deve essere raggiungibile dall\'orchestrator.')
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
                        ->helperText('Salvata cifrata nel database.')
                        ->required()
                        ->dehydrated(fn ($state) => filled($state)),

                ])->columns(2),

            Forms\Components\Textarea::make('notes')
                ->label('Note')
                ->placeholder('Informazioni aggiuntive sul server, versione OS, ecc.')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                        $ok = $record->connection()->test();
                        Notification::make()
                            ->title($ok ? '✅ Connessione OK' : '❌ Connessione fallita')
                            ->body($ok
                                ? 'Il server risponde correttamente.'
                                : 'Impossibile connettersi. Verifica IP, tipo connessione e credenziali.')
                            ->status($ok ? 'success' : 'danger')
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
