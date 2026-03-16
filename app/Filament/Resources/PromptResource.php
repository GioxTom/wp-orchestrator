<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromptResource\Pages;
use App\Models\Prompt;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PromptResource extends Resource
{
    protected static ?string $model           = Prompt::class;
    protected static ?string $navigationIcon  = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'Configurazione';
    protected static ?string $navigationLabel = 'Prompt';
    protected static ?int    $navigationSort  = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Informazioni')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome prompt')
                        ->required()
                        ->disabled(fn ($record) => $record?->isSystem()),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->unique(ignoreRecord: true)
                        ->required()
                        ->disabled(fn ($record) => $record?->isSystem()),

                    Forms\Components\Select::make('action')
                        ->label('Azione')
                        ->options([
                            'logo_generation'    => 'Generazione Logo',
                            'content_generation' => 'Generazione Contenuto',
                            'meta_description'   => 'Meta Description',
                        ])
                        ->required()
                        ->disabled(fn ($record) => $record?->isSystem()),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(['system' => 'Sistema (read-only)', 'user' => 'Utente'])
                        ->disabled()
                        ->dehydrated(false),
                ])->columns(2),

            Forms\Components\Section::make('Contenuto')
                ->schema([
                    Forms\Components\Textarea::make('content')
                        ->label('Testo del prompt')
                        ->rows(10)
                        ->required()
                        ->disabled(fn ($record) => $record?->isSystem())
                        ->helperText('Placeholder disponibili: {site_name}, {site_description}, {locale}'),
                ]),

            Forms\Components\Section::make('Placeholder disponibili')
                ->schema([
                    Forms\Components\Placeholder::make('placeholder_info')
                        ->label('')
                        ->content(view('filament.prompt-placeholders')),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\BadgeColumn::make('action')
                    ->label('Azione')
                    ->colors([
                        'info'    => 'logo_generation',
                        'success' => 'content_generation',
                        'warning' => 'meta_description',
                    ]),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->colors(['gray' => 'system', 'primary' => 'user']),
                Tables\Columns\TextColumn::make('content')
                    ->label('Anteprima')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->content),
                Tables\Columns\TextColumn::make('updated_at')->label('Aggiornato')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Azione')
                    ->options([
                        'logo_generation'    => 'Generazione Logo',
                        'content_generation' => 'Generazione Contenuto',
                        'meta_description'   => 'Meta Description',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(['system' => 'Sistema', 'user' => 'Utente']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Prompt $record) => $record->isEditable()),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplica come override')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->form([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome del nuovo prompt')
                            ->required(),
                    ])
                    ->action(function (Prompt $record, array $data) {
                        $record->duplicate($data['name']);
                        Notification::make()
                            ->title('Prompt duplicato')
                            ->body('Puoi ora modificare il tuo override.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Prompt $record) => $record->isDeletable()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrompts::route('/'),
            'create' => Pages\CreatePrompt::route('/create'),
            'edit'   => Pages\EditPrompt::route('/{record}/edit'),
        ];
    }
}
