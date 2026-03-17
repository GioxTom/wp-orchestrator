<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IspConfigClientResource\Pages;
use App\Models\IspConfigClient;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Form;

class IspConfigClientResource extends Resource
{
    protected static ?string $model           = IspConfigClient::class;
    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Clienti ISPConfig';
    protected static ?int    $navigationSort  = 15;

    // Read-only: i clienti vengono solo sincronizzati, non creati da qui
    public static function canCreate(): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // non usato
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ispconfig_client_id')
                    ->label('ID ISPConfig')
                    ->sortable(),
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Azienda')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contatto')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
                Tables\Columns\TextColumn::make('server.name')
                    ->label('Server'),
                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Siti')
                    ->counts('sites'),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Ultima sync')
                    ->since(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('server_id')
                    ->label('Server')
                    ->relationship('server', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('set_default')
                    ->label(fn (IspConfigClient $record) => $record->is_default ? '⭐ Default' : 'Imposta default')
                    ->icon('heroicon-o-star')
                    ->color(fn (IspConfigClient $record) => $record->is_default ? 'warning' : 'gray')
                    ->disabled(fn (IspConfigClient $record) => $record->is_default)
                    ->action(function (IspConfigClient $record) {
                        $record->setAsDefault();
                        \Filament\Notifications\Notification::make()
                            ->title('Cliente default impostato')
                            ->body("{$record->display_name} è ora il cliente default per il server {$record->server->name}.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIspConfigClients::route('/'),
        ];
    }
}
