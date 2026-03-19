<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Impostazioni';
    protected static ?int    $navigationSort  = 20;
    protected static string  $view            = 'filament.pages.settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'gemini_api_key'         => Setting::get('gemini_api_key') ? '••••••••' : '',
            'gemini_model'           => Setting::get('gemini_model', 'gemini-3.1-flash-image-preview'),
            'gemini_image_size'      => Setting::get('gemini_image_size', '1K'),
            'gemini_logo_aspect'     => Setting::get('gemini_logo_aspect', '1:1'),
            'gemini_use_batch'       => Setting::get('gemini_use_batch', '0') === '1',
            // AI contenuti
            'ai_content_provider'   => Setting::get('ai_content_provider', 'claude'),
            'claude_api_key'        => Setting::get('claude_api_key') ? '••••••••' : '',
            'claude_model'          => Setting::get('claude_model', 'claude-sonnet-4-5'),
            'openai_api_key'        => Setting::get('openai_api_key') ? '••••••••' : '',
            'openai_model'          => Setting::get('openai_model', 'gpt-4o-mini'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('settings')
                    ->tabs([

                        // ── Tab: Generazione Immagini ─────────────────────────
                        Forms\Components\Tabs\Tab::make('🍌 Logo (Nano Banana)')
                            ->schema([
                                Forms\Components\Section::make('📖 Guida')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Placeholder::make('guide')
                                            ->label('')
                                            ->content(new HtmlString('
<div class="space-y-4 text-sm">
  <p>Nano Banana usa Google Gemini per generare i loghi. Due modelli: <strong>Flash</strong> (veloce, 1K) e <strong>Pro</strong> (alta qualità, fino a 4K).</p>
  <ol class="list-decimal list-inside space-y-1 ml-2">
    <li>Vai su <a href="https://aistudio.google.com/apikey" target="_blank" class="text-primary-600 underline">aistudio.google.com/apikey</a> e crea una API Key</li>
    <li>Abilita la fatturazione su Google AI Studio → Billing</li>
    <li>Incolla la chiave nel campo qui sotto</li>
  </ol>
  <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 mt-2">
    <table class="w-full text-xs"><thead class="bg-gray-100 dark:bg-gray-800"><tr><th class="px-3 py-2 text-left">Modello</th><th class="px-3 py-2 text-left">Risoluzione</th><th class="px-3 py-2 text-left">Standard</th><th class="px-3 py-2 text-left text-green-600">Batch −50%</th></tr></thead>
    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
      <tr><td class="px-3 py-2">🍌 Flash</td><td class="px-3 py-2">1K</td><td class="px-3 py-2">$0.039</td><td class="px-3 py-2 text-green-600">$0.0195</td></tr>
      <tr><td class="px-3 py-2">🍌 Pro</td><td class="px-3 py-2">1K–4K</td><td class="px-3 py-2">$0.134–$0.24</td><td class="px-3 py-2 text-green-600">$0.067–$0.12</td></tr>
    </tbody></table>
  </div>
  <div class="flex gap-4 text-xs"><a href="https://ai.google.dev/gemini-api/docs/image-generation" target="_blank" class="text-primary-600 underline">📖 Documentazione</a><a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" class="text-primary-600 underline">💰 Prezzi</a></div>
</div>')),
                                    ]),

                                Forms\Components\TextInput::make('gemini_api_key')
                                    ->label('API Key Gemini')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('AIzaSy...')
                                    ->helperText('Salvata cifrata. Lascia vuoto per mantenere la chiave attuale.')
                                    ->columnSpanFull(),

                                Forms\Components\Select::make('gemini_model')
                                    ->label('Modello')
                                    ->options([
                                        'gemini-3.1-flash-image-preview' => '🍌 Flash — veloce, economico (1K max)',
                                        'gemini-3-pro-image-preview'     => '🍌 Pro — alta qualità (fino a 4K)',
                                    ])
                                    ->default('gemini-3.1-flash-image-preview')
                                    ->live(),

                                Forms\Components\Select::make('gemini_image_size')
                                    ->label('Risoluzione')
                                    ->options(fn (Forms\Get $get) => $get('gemini_model') === 'gemini-3.1-flash-image-preview'
                                        ? ['1K' => '1K — 1024×1024 px']
                                        : ['1K' => '1K', '2K' => '2K', '4K' => '4K'])
                                    ->default('1K'),

                                Forms\Components\Select::make('gemini_logo_aspect')
                                    ->label('Aspect ratio logo (default)')
                                    ->options([
                                        '1:1'  => '1:1 — Quadrato',
                                        '3:4'  => '3:4 — Verticale',
                                        '4:3'  => '4:3 — Orizzontale',
                                        '16:9' => '16:9 — Widescreen',
                                        '9:16' => '9:16 — Verticale largo',
                                    ])
                                    ->default('1:1')
                                    ->helperText('Sovrascrivibile per ogni sito.'),

                                Forms\Components\Toggle::make('gemini_use_batch')
                                    ->label('Usa Batch API (−50% costo, asincrona)')
                                    ->helperText('Turnaround tipico 2–10 minuti, max 24h.')
                                    ->default(false)
                                    ->columnSpanFull(),
                            ])->columns(2),

                        // ── Tab: AI Contenuti ─────────────────────────────────
                        Forms\Components\Tabs\Tab::make('🤖 AI Contenuti')
                            ->schema([
                                Forms\Components\Select::make('ai_content_provider')
                                    ->label('Provider predefinito')
                                    ->options([
                                        'claude' => '🟣 Claude (Anthropic)',
                                        'openai' => '🟢 ChatGPT (OpenAI)',
                                    ])
                                    ->default('claude')
                                    ->live()
                                    ->required()
                                    ->columnSpanFull(),

                                // Claude
                                Forms\Components\TextInput::make('claude_api_key')
                                    ->label('API Key Claude')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('sk-ant-...')
                                    ->helperText('console.anthropic.com → API Keys. Lascia vuoto per mantenere la chiave attuale.')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'claude'),

                                Forms\Components\Select::make('claude_model')
                                    ->label('Modello Claude')
                                    ->options([
                                        'claude-opus-4-6'           => 'Claude Opus 4.6 — massima qualità',
                                        'claude-sonnet-4-5'         => 'Claude Sonnet 4.5 — bilanciato (consigliato)',
                                        'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — veloce, economico',
                                    ])
                                    ->default('claude-sonnet-4-5')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'claude'),

                                // OpenAI
                                Forms\Components\TextInput::make('openai_api_key')
                                    ->label('API Key OpenAI')
                                    ->password()
                                    ->revealable()
                                    ->placeholder('sk-...')
                                    ->helperText('platform.openai.com → API Keys. Lascia vuoto per mantenere la chiave attuale.')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'openai'),

                                Forms\Components\Select::make('openai_model')
                                    ->label('Modello OpenAI')
                                    ->options([
                                        'gpt-4o'      => 'GPT-4o — massima qualità',
                                        'gpt-4o-mini' => 'GPT-4o Mini — veloce, economico (consigliato)',
                                        'gpt-4-turbo' => 'GPT-4 Turbo',
                                    ])
                                    ->default('gpt-4o-mini')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'openai'),
                            ])->columns(2),

                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! empty($data['gemini_api_key']) && $data['gemini_api_key'] !== '••••••••') {
            Setting::set('gemini_api_key', $data['gemini_api_key'], true);
        }

        Setting::set('gemini_model',        $data['gemini_model']        ?? 'gemini-3.1-flash-image-preview');
        Setting::set('gemini_image_size',    $data['gemini_image_size']    ?? '1K');
        Setting::set('gemini_logo_aspect',   $data['gemini_logo_aspect']   ?? '1:1');
        Setting::set('gemini_use_batch',     ($data['gemini_use_batch']    ?? false) ? '1' : '0');

        // AI contenuti
        Setting::set('ai_content_provider', $data['ai_content_provider'] ?? 'claude');
        Setting::set('claude_model',        $data['claude_model']        ?? 'claude-sonnet-4-5');
        Setting::set('openai_model',        $data['openai_model']        ?? 'gpt-4o-mini');

        if (! empty($data['claude_api_key']) && $data['claude_api_key'] !== '••••••••') {
            Setting::set('claude_api_key', $data['claude_api_key'], true);
        }
        if (! empty($data['openai_api_key']) && $data['openai_api_key'] !== '••••••••') {
            Setting::set('openai_api_key', $data['openai_api_key'], true);
        }

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }
}
