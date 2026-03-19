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
                                Forms\Components\Section::make('📖 Guida Nano Banana (Gemini Image)')
                                    ->description("Come ottenere l'API Key per generare immagini con Google Gemini.")
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Placeholder::make('guide')
                                            ->label('')
                                            ->content(new HtmlString('
<div class="space-y-5 text-sm">
  <div>
    <h4 class="font-semibold text-base mb-1">Cos\'è Nano Banana?</h4>
    <p>Nano Banana è il nome ufficiale della generazione immagini nativa di Google Gemini. Due modelli disponibili: <strong>Flash</strong> (veloce, economico, solo 1K) e <strong>Pro</strong> (professionale, fino a 4K).</p>
  </div>
  <div>
    <h4 class="font-semibold text-base mb-2">1. Ottieni una Google AI API Key</h4>
    <ol class="list-decimal list-inside space-y-1 ml-2">
      <li>Vai su <a href="https://aistudio.google.com/apikey" target="_blank" class="text-primary-600 underline font-medium">aistudio.google.com/apikey</a></li>
      <li>Accedi con il tuo account Google</li>
      <li>Clicca <strong>"Create API Key"</strong> e seleziona un progetto Google Cloud (o creane uno nuovo)</li>
      <li>Copia la chiave generata e incollala nel campo qui sotto</li>
    </ol>
  </div>
  <div>
    <h4 class="font-semibold text-base mb-2">2. Abilita la fatturazione (obbligatorio per le immagini)</h4>
    <p>La generazione immagini richiede un piano a pagamento. Vai su <a href="https://aistudio.google.com" target="_blank" class="text-primary-600 underline font-medium">Google AI Studio</a> → Settings → Billing e attiva il piano <strong>Pay-as-you-go</strong>.</p>
  </div>
  <div>
    <h4 class="font-semibold text-base mb-3">3. Prezzi</h4>
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
      <table class="w-full text-xs">
        <thead class="bg-gray-100 dark:bg-gray-800">
          <tr>
            <th class="px-4 py-2 text-left font-semibold">Modello</th>
            <th class="px-4 py-2 text-left font-semibold">Risoluzione</th>
            <th class="px-4 py-2 text-left font-semibold">Prezzo standard</th>
            <th class="px-4 py-2 text-left font-semibold text-green-600">Prezzo Batch (−50%)</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr><td class="px-4 py-2 font-medium">🍌 Flash</td><td class="px-4 py-2">1K</td><td class="px-4 py-2">$0.039 / img</td><td class="px-4 py-2 text-green-600 font-semibold">$0.0195 / img</td></tr>
          <tr><td class="px-4 py-2 font-medium">🍌 Pro</td><td class="px-4 py-2">1K – 2K</td><td class="px-4 py-2">$0.134 / img</td><td class="px-4 py-2 text-green-600 font-semibold">$0.067 / img</td></tr>
          <tr><td class="px-4 py-2"></td><td class="px-4 py-2">4K</td><td class="px-4 py-2">$0.24 / img</td><td class="px-4 py-2 text-green-600 font-semibold">$0.12 / img</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="flex gap-6 text-xs pt-3 border-t border-gray-200 dark:border-gray-700">
    <a href="https://ai.google.dev/gemini-api/docs/image-generation" target="_blank" class="text-primary-600 hover:underline font-medium">📖 Documentazione</a>
    <a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" class="text-primary-600 hover:underline font-medium">💰 Prezzi</a>
  </div>
</div>')),
                                    ]),

                                Forms\Components\Section::make('Configurazione API')
                                    ->schema([
                                        Forms\Components\TextInput::make('gemini_api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->placeholder('AIzaSy...')
                                            ->helperText('Ottienila su aistudio.google.com/apikey — salvata cifrata.')
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
                            ]),

                        // ── Tab: AI Contenuti ─────────────────────────────────
                        Forms\Components\Tabs\Tab::make('🤖 AI Contenuti')
                            ->schema([
                                Forms\Components\Section::make('Provider di Default')
                                    ->schema([
                                        Forms\Components\Select::make('ai_content_provider')
                                            ->label('Provider AI Predefinito')
                                            ->options([
                                                'claude' => '🟣 Claude (Anthropic)',
                                                'openai' => '🟢 ChatGPT (OpenAI)',
                                            ])
                                            ->default('claude')
                                            ->live()
                                            ->required(),
                                    ]),

                                Forms\Components\Section::make('Claude (Anthropic)')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'claude')
                                    ->schema([
                                        Forms\Components\Section::make('📖 Come ottenere la API Key di Claude')
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Placeholder::make('claude_guide')
                                                    ->label('')
                                                    ->content(new HtmlString('
<div class="text-sm space-y-2">
  <ol class="list-decimal list-inside space-y-1 ml-2">
    <li>Vai su <a href="https://console.anthropic.com/settings/keys" target="_blank" class="text-primary-600 underline font-medium">console.anthropic.com/settings/keys</a></li>
    <li>Accedi o crea un account Anthropic</li>
    <li>Clicca <strong>"Create Key"</strong>, assegna un nome e copia la chiave</li>
    <li>Ricarica credito su <a href="https://console.anthropic.com/settings/billing" target="_blank" class="text-primary-600 underline font-medium">Billing</a> per usare l\'API</li>
  </ol>
</div>')),
                                            ]),

                                        Forms\Components\TextInput::make('claude_api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->placeholder('sk-ant-...')
                                            ->helperText('Lascia vuoto per mantenere la chiave attuale.'),

                                        Forms\Components\Select::make('claude_model')
                                            ->label('Modello Predefinito')
                                            ->options([
                                                'claude-opus-4-6'           => 'Claude Opus 4.6 — massima qualità',
                                                'claude-sonnet-4-5'         => 'Claude Sonnet 4.5 — bilanciato (consigliato)',
                                                'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 — veloce, economico',
                                            ])
                                            ->default('claude-sonnet-4-5'),
                                    ])->columns(2),

                                Forms\Components\Section::make('ChatGPT (OpenAI)')
                                    ->visible(fn (Forms\Get $get) => $get('ai_content_provider') === 'openai')
                                    ->schema([
                                        Forms\Components\Section::make('📖 Come ottenere la API Key di OpenAI')
                                            ->collapsed()
                                            ->schema([
                                                Forms\Components\Placeholder::make('openai_guide')
                                                    ->label('')
                                                    ->content(new HtmlString('
<div class="text-sm space-y-2">
  <ol class="list-decimal list-inside space-y-1 ml-2">
    <li>Vai su <a href="https://platform.openai.com/api-keys" target="_blank" class="text-primary-600 underline font-medium">platform.openai.com/api-keys</a></li>
    <li>Accedi o crea un account OpenAI</li>
    <li>Clicca <strong>"Create new secret key"</strong> e copia la chiave</li>
    <li>Ricarica credito su <a href="https://platform.openai.com/settings/billing" target="_blank" class="text-primary-600 underline font-medium">Billing</a></li>
  </ol>
</div>')),
                                            ]),

                                        Forms\Components\TextInput::make('openai_api_key')
                                            ->label('API Key')
                                            ->password()
                                            ->revealable()
                                            ->placeholder('sk-...')
                                            ->helperText('Lascia vuoto per mantenere la chiave attuale.'),

                                        Forms\Components\Select::make('openai_model')
                                            ->label('Modello Predefinito')
                                            ->options([
                                                'gpt-4o'      => 'GPT-4o — massima qualità',
                                                'gpt-4o-mini' => 'GPT-4o Mini — veloce, economico (consigliato)',
                                                'gpt-4-turbo' => 'GPT-4 Turbo',
                                            ])
                                            ->default('gpt-4o-mini'),
                                    ])->columns(2),
                            ]),

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
