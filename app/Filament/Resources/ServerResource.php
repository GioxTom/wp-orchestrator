<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServerResource\Pages;
use App\Jobs\Sync\SyncIspConfigDataJob;
use App\Models\Server;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServerResource extends Resource
{
    protected static ?string $model            = Server::class;
    protected static ?string $navigationIcon   = 'heroicon-o-server';
    protected static ?string $navigationGroup  = 'Sistema';
    protected static ?string $navigationLabel  = 'Server';
    protected static ?int    $navigationSort   = 10;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Configurazione Server')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')->required()->maxLength(255),

                    Forms\Components\TextInput::make('hostname')
                        ->label('Hostname')->required(),

                    Forms\Components\TextInput::make('ip')
                        ->label('Indirizzo IP')->required(),

                    Forms\Components\Select::make('connection_type')
                        ->label('Tipo connessione')
                        ->options(['local' => 'Locale (stesso server)', 'ssh' => 'SSH (remoto)'])
                        ->default('local')
                        ->live()
                        ->required(),

                    Forms\Components\TextInput::make('ssh_user')
                        ->label('Utente SSH')
                        ->visible(fn ($get) => $get('connection_type') === 'ssh'),

                    Forms\Components\TextInput::make('ssh_key_path')
                        ->label('Path chiave SSH')
                        ->placeholder('/home/orchestrator/.ssh/id_rsa')
                        ->visible(fn ($get) => $get('connection_type') === 'ssh'),
                ])->columns(2),

            Forms\Components\Section::make('ISPConfig API')
                ->schema([
                    Forms\Components\TextInput::make('ispconfig_api_url')
                        ->label('URL API ISPConfig')
                        ->placeholder('https://tuoserver.com:8080/remote/json.php')
                        ->required(),

                    Forms\Components\TextInput::make('ispconfig_user')
                        ->label('Utente ISPConfig')
                        ->required(),

                    Forms\Components\TextInput::make('ispconfig_password')
                        ->label('Password ISPConfig')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(fn ($state) => filled($state)),
                ])->columns(2),

            Forms\Components\Textarea::make('notes')
                ->label('Note')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('ip')->label('IP'),
                Tables\Columns\BadgeColumn::make('connection_type')
                    ->label('Connessione')
                    ->colors(['success' => 'local', 'info' => 'ssh']),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors(['success' => 'active', 'danger' => 'error', 'warning' => 'inactive']),
                Tables\Columns\TextColumn::make('ispConfigClients_count')
                    ->label('Clienti')
                    ->counts('ispConfigClients'),
                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Siti')
                    ->counts('sites'),
                Tables\Columns\TextColumn::make('updated_at')->label('Aggiornato')->since(),
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
                            ->body('I dati ISPConfig verranno aggiornati a breve.')
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
                            ->title($ok ? 'Connessione OK' : 'Connessione fallita')
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
