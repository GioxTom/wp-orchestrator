<?php

declare(strict_types=1);

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
    protected static ?string $navigationLabel = 'Impostazioni';
    protected static ?string $title           = 'Impostazioni';
    protected static ?string $slug            = 'settings';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?int    $navigationSort  = 20;
    protected static string  $view            = 'filament.pages.settings';

    public ?array $nanaBananaData = [];
    public ?array $logoData       = [];
    public ?array $aiData         = [];

    // ─── Mount ────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->nanaBananaForm->fill([
            'gemini_api_key'    => Setting::get('gemini_api_key') ? '••••••••' : '',
            'gemini_model'      => Setting::get('gemini_model', 'gemini-3.1-flash-image-preview'),
            'gemini_image_size' => Setting::get('gemini_image_size', '1K'),
            'gemini_use_batch'  => Setting::get('gemini_use_batch', '0') === '1',
        ]);

        $this->logoForm->fill([
            'gemini_logo_aspect' => Setting::get('gemini_logo_aspect', '1:1'),
        ]);

        $this->aiForm->fill([
            'ai_content_provider' => Setting::get('ai_content_provider', 'claude'),
            'claude_api_key'      => Setting::get('claude_api_key') ? '••••••••' : '',
            'claude_model'        => Setting::get('claude_model', 'claude-sonnet-4-5'),
            'openai_api_key'      => Setting::get('openai_api_key') ? '••••••••' : '',
            'openai_model'        => Setting::get('openai_model', 'gpt-4o-mini'),
        ]);
    }

    // ─── Nano Banana Form ─────────────────────────────────────────────────

    protected function getNanaBananaFormSchema(): array
    {
        return [
            Forms\Components\Section::make('📖 Guida')
                ->schema([
                    Forms\Components\Placeholder::make('guide_nb')
                        ->label('')
                        ->content(new HtmlString('
                            <div class="space-y-4 text-sm">
                                <p><strong>Nano Banana</strong> usa Google Gemini per generare immagini.
                                Due modelli: <strong>Flash</strong> (veloce, 1K) e <strong>Pro</strong> (alta qualità, fino a 4K).</p>
                                <ol class="list-decimal list-inside space-y-1 ml-2">
                                    <li>Vai su <a href="https://aistudio.google.com/apikey" target="_blank" class="text-primary-600 underline">aistudio.google.com/apikey</a> e crea una API Key</li>
                                    <li>Abilita la fatturazione su Google AI Studio → Billing</li>
                                    <li>Incolla la chiave nel campo qui sotto</li>
                                </ol>
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 mt-2">
                                    <table class="w-full text-xs">
                                        <thead class="bg-gray-100 dark:bg-gray-800">
                                            <tr>
                                                <th class="px-3 py-2 text-left">Modello</th>
                                                <th class="px-3 py-2 text-left">Risoluzione</th>
                                                <th class="px-3 py-2 text-right">Standard</th>
                                                <th class="px-3 py-2 text-right text-green-600">Batch −50%</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            <tr>
                                                <td class="px-3 py-2">🍌 Flash</td>
                                                <td class="px-3 py-2">1K</td>
                                                <td class="px-3 py-2 text-right">$0.039</td>
                                                <td class="px-3 py-2 text-right text-green-600">$0.0195</td>
                                            </tr>
                                            <tr>
                                                <td class="px-3 py-2">🍌 Pro</td>
                                                <td class="px-3 py-2">1K – 4K</td>
                                                <td class="px-3 py-2 text-right">$0.134 – $0.24</td>
                                                <td class="px-3 py-2 text-right text-green-600">$0.067 – $0.12</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="flex gap-4 text-xs">
                                    <a href="https://ai.google.dev/gemini-api/docs/image-generation" target="_blank" class="text-primary-600 underline">📖 Documentazione</a>
                                    <a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" class="text-primary-600 underline">💰 Prezzi</a>
                                </div>
                            </div>
                        ')),
                ])
                ->collapsible()
                ->collapsed(),

            Forms\Components\Section::make('🔑 Credenziali')
                ->schema([
                    Forms\Components\TextInput::make('gemini_api_key')
                        ->label('API Key Gemini')
                        ->password()
                        ->revealable()
                        ->placeholder(Setting::get('gemini_api_key') ? '••••••••' : 'Incolla la tua API Key...')
                        ->helperText('Lascia vuoto per mantenere la chiave attuale.'),
                ]),

            Forms\Components\Section::make('⚙️ Modello e Risoluzione')
                ->schema([
                    Forms\Components\Select::make('gemini_model')
                        ->label('Modello Gemini')
                        ->options([
                            'gemini-3.1-flash-image-preview' => '🍌 Flash — veloce, economico ($0.039/img)',
                            'gemini-3.1-pro-image-preview'   => '🍌 Pro — alta qualità, fino a 4K ($0.134–$0.24/img)',
                        ])
                        ->default('gemini-3.1-flash-image-preview')
                        ->reactive()
                        ->afterStateUpdated(fn (callable $set) => $set('gemini_image_size', '1K')),

                    Forms\Components\Select::make('gemini_image_size')
                        ->label('Risoluzione')
                        ->options(function (callable $get) {
                            if (str_contains($get('gemini_model') ?? '', 'pro')) {
                                return [
                                    '1K' => '1K (1024×1024) — $0.134/img',
                                    '2K' => '2K (2048×2048) — $0.134/img',
                                    '4K' => '4K (4096×4096) — $0.24/img',
                                ];
                            }
                            return ['1K' => '1K (1024×1024) — $0.039/img'];
                        })
                        ->default('1K'),
                ])
                ->columns(2),

            Forms\Components\Section::make('⚡ Batch API')
                ->schema([
                    Forms\Components\Toggle::make('gemini_use_batch')
                        ->label('Usa Batch API (−50% sul costo)')
                        ->helperText('Invia le richieste in modalità asincrona a metà prezzo. Turnaround tipico: 2–10 minuti, massimo 24h.')
                        ->default(false),
                ]),
        ];
    }

    public function nanaBananaForm(Form $form): Form
    {
        return $form->schema($this->getNanaBananaFormSchema())->statePath('nanaBananaData');
    }

    public function salvaNanaBanana(): void
    {
        $data = $this->nanaBananaForm->getState();

        $key = trim($data['gemini_api_key'] ?? '');
        if ($key && $key !== '••••••••') {
            Setting::set('gemini_api_key', $key);
        }

        Setting::set('gemini_model',      $data['gemini_model']      ?? 'gemini-3.1-flash-image-preview');
        Setting::set('gemini_image_size', $data['gemini_image_size'] ?? '1K');
        Setting::set('gemini_use_batch',  ($data['gemini_use_batch'] ?? false) ? '1' : '0');

        Notification::make()->title('Impostazioni Nano Banana salvate')->success()->send();
    }

    // ─── Logo Form ────────────────────────────────────────────────────────

    protected function getLogoFormSchema(): array
    {
        return [
            Forms\Components\Section::make('🖼️ Formato Logo')
                ->schema([
                    Forms\Components\Select::make('gemini_logo_aspect')
                        ->label('Aspect Ratio')
                        ->options([
                            '1:1'  => '1:1 — Quadrato',
                            '3:4'  => '3:4 — Verticale',
                            '4:3'  => '4:3 — Orizzontale',
                            '16:9' => '16:9 — Widescreen',
                            '9:16' => '9:16 — Portrait',
                        ])
                        ->default('1:1')
                        ->helperText('Proporzioni dell\'immagine generata per il logo.'),
                ]),
        ];
    }

    public function logoForm(Form $form): Form
    {
        return $form->schema($this->getLogoFormSchema())->statePath('logoData');
    }

    public function salvaLogo(): void
    {
        $data = $this->logoForm->getState();

        Setting::set('gemini_logo_aspect', $data['gemini_logo_aspect'] ?? '1:1');

        Notification::make()->title('Impostazioni Logo salvate')->success()->send();
    }

    // ─── AI Form ──────────────────────────────────────────────────────────

    protected function getAiFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Provider di Default')
                ->schema([
                    Forms\Components\Select::make('ai_content_provider')
                        ->label('Provider AI Predefinito')
                        ->options([
                            'claude' => 'Claude (Anthropic)',
                            'openai' => 'OpenAI (ChatGPT)',
                        ])
                        ->required(),
                ]),

            Forms\Components\Section::make('Claude (Anthropic)')
                ->schema([
                    Forms\Components\Placeholder::make('guide_claude')
                        ->label('')
                        ->content(new HtmlString('
                            <details style="margin-bottom:12px;">
                                <summary style="cursor:pointer;font-weight:600;color:#4f46e5;">📖 Come ottenere la API Key di Claude</summary>
                                <div style="font-size:14px;line-height:1.8;padding:12px 0 0 8px;">
                                    <ol style="margin:0 0 0 20px;">
                                        <li>Vai su <a href="https://console.anthropic.com/settings/keys" target="_blank" style="color:#4f46e5;text-decoration:underline;">console.anthropic.com/settings/keys</a></li>
                                        <li>Clicca <strong>"Create Key"</strong> e copia la chiave (inizia con <code>sk-ant-api03-...</code>)</li>
                                        <li>Aggiungi un metodo di pagamento in <a href="https://console.anthropic.com/settings/billing" target="_blank" style="color:#4f46e5;text-decoration:underline;">Settings → Billing</a></li>
                                    </ol>
                                    <p style="margin-top:8px;font-size:12px;color:#6b7280;">Prezzi: <a href="https://www.anthropic.com/pricing" target="_blank" style="color:#4f46e5;">anthropic.com/pricing</a></p>
                                </div>
                            </details>
                        ')),
                    Forms\Components\TextInput::make('claude_api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->placeholder(Setting::get('claude_api_key') ? '••• configurata •••' : 'sk-ant-api03-...')
                        ->helperText('Lascia vuoto per mantenere la chiave attuale.'),
                    Forms\Components\Select::make('claude_model')
                        ->label('Modello Predefinito')
                        ->options([
                            'claude-opus-4-5'   => 'Claude Opus 4.5 — massima qualità',
                            'claude-sonnet-4-5' => 'Claude Sonnet 4.5 — bilanciato (consigliato)',
                            'claude-haiku-4-5'  => 'Claude Haiku 4.5 — veloce ed economico',
                        ])
                        ->default('claude-sonnet-4-5'),
                ]),

            Forms\Components\Section::make('OpenAI (ChatGPT)')
                ->schema([
                    Forms\Components\Placeholder::make('guide_openai')
                        ->label('')
                        ->content(new HtmlString('
                            <details style="margin-bottom:12px;">
                                <summary style="cursor:pointer;font-weight:600;color:#4f46e5;">📖 Come ottenere la API Key di OpenAI</summary>
                                <div style="font-size:14px;line-height:1.8;padding:12px 0 0 8px;">
                                    <ol style="margin:0 0 0 20px;">
                                        <li>Vai su <a href="https://platform.openai.com/api-keys" target="_blank" style="color:#4f46e5;text-decoration:underline;">platform.openai.com/api-keys</a></li>
                                        <li>Clicca <strong>"Create new secret key"</strong> e copia la chiave (inizia con <code>sk-proj-...</code>)</li>
                                        <li>Carica credito su <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" style="color:#4f46e5;text-decoration:underline;">Settings → Billing</a> (minimo $5)</li>
                                    </ol>
                                    <p style="margin-top:8px;font-size:12px;color:#6b7280;">⚠️ L\'account API è separato dall\'abbonamento ChatGPT Plus.</p>
                                </div>
                            </details>
                        ')),
                    Forms\Components\TextInput::make('openai_api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable()
                        ->placeholder(Setting::get('openai_api_key') ? '••• configurata •••' : 'sk-proj-...')
                        ->helperText('Lascia vuoto per mantenere la chiave attuale.'),
                    Forms\Components\Select::make('openai_model')
                        ->label('Modello Predefinito')
                        ->options([
                            'gpt-4o'      => 'GPT-4o — massima qualità',
                            'gpt-4o-mini' => 'GPT-4o Mini — veloce ed economico (consigliato)',
                        ])
                        ->default('gpt-4o-mini'),
                ]),
        ];
    }

    public function aiForm(Form $form): Form
    {
        return $form->schema($this->getAiFormSchema())->statePath('aiData');
    }

    public function salvaAi(): void
    {
        $data = $this->aiForm->getState();

        Setting::set('ai_content_provider', $data['ai_content_provider'] ?? 'claude');
        Setting::set('claude_model',        $data['claude_model'] ?? 'claude-sonnet-4-5');
        Setting::set('openai_model',        $data['openai_model'] ?? 'gpt-4o-mini');

        $claudeKey = trim($data['claude_api_key'] ?? '');
        if ($claudeKey && $claudeKey !== '••••••••') {
            Setting::set('claude_api_key', $claudeKey);
        }

        $openaiKey = trim($data['openai_api_key'] ?? '');
        if ($openaiKey && $openaiKey !== '••••••••') {
            Setting::set('openai_api_key', $openaiKey);
        }

        Notification::make()->title('Impostazioni AI salvate')->success()->send();
    }

    // ─── Forms Registration ───────────────────────────────────────────────

    protected function getForms(): array
    {
        return ['nanaBananaForm', 'logoForm', 'aiForm'];
    }
}
