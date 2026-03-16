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
            'gemini_api_key'    => Setting::get('gemini_api_key') ? '••••••••' : '',
            'gemini_model'      => Setting::get('gemini_model', 'gemini-3.1-flash-image-preview'),
            'gemini_image_size' => Setting::get('gemini_image_size', '1K'),
            'gemini_use_batch'  => Setting::get('gemini_use_batch', '0') === '1',
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                // Guida collassabile
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
    <p class="mt-2 text-xs italic">La stessa API Key funziona sia per la generazione immagini che per i modelli di testo Gemini.</p>
  </div>
  <div>
    <h4 class="font-semibold text-base mb-2">2. Abilita la fatturazione (obbligatorio per le immagini)</h4>
    <p>La generazione immagini richiede un piano a pagamento. Vai su <a href="https://aistudio.google.com" target="_blank" class="text-primary-600 underline font-medium">Google AI Studio</a> → Settings → Billing e attiva il piano <strong>Pay-as-you-go</strong>.</p>
  </div>
  <div>
    <h4 class="font-semibold text-base mb-3">3. Scegli il modello e la risoluzione</h4>
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
          <tr>
            <td class="px-4 py-2 font-medium">🍌 Flash</td>
            <td class="px-4 py-2">1K (1024×1024)</td>
            <td class="px-4 py-2">$0.039 / img</td>
            <td class="px-4 py-2 text-green-600 font-semibold">$0.0195 / img</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-medium">🍌 Pro</td>
            <td class="px-4 py-2">1K – 2K (fino a 2048×2048)</td>
            <td class="px-4 py-2">$0.134 / img</td>
            <td class="px-4 py-2 text-green-600 font-semibold">$0.067 / img</td>
          </tr>
          <tr>
            <td class="px-4 py-2 font-medium"></td>
            <td class="px-4 py-2">4K (4096×4096)</td>
            <td class="px-4 py-2">$0.24 / img</td>
            <td class="px-4 py-2 text-green-600 font-semibold">$0.12 / img</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="mt-2 text-xs">La <strong>Batch API</strong> costa il 50% in meno ma è asincrona (turnaround tipico: 2–10 minuti, max 24h).</p>
  </div>
  <div class="flex gap-6 text-xs pt-3 border-t border-gray-200 dark:border-gray-700">
    <a href="https://ai.google.dev/gemini-api/docs/image-generation" target="_blank" class="text-primary-600 hover:underline font-medium">📖 Documentazione ufficiale</a>
    <a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" class="text-primary-600 hover:underline font-medium">💰 Prezzi completi</a>
  </div>
</div>
                            ')),
                    ]),

                // Configurazione API
                Forms\Components\Section::make('Gemini API Key 🍌')
                    ->description('Configurazione Nano Banana per la generazione automatica dei loghi.')
                    ->schema([
                        Forms\Components\TextInput::make('gemini_api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('AIzaSy...')
                            ->helperText('Ottienila su aistudio.google.com/apikey — salvata cifrata nel database.')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('gemini_model')
                            ->label('Modello Nano Banana')
                            ->options([
                                'gemini-3.1-flash-image-preview' => '🍌 Flash — veloce, economico (1K max)',
                                'gemini-3-pro-image-preview'     => '🍌 Pro — alta qualità (fino a 4K)',
                            ])
                            ->default('gemini-3.1-flash-image-preview')
                            ->live()
                            ->helperText('Flash consigliato per la maggior parte dei casi.'),

                        Forms\Components\Select::make('gemini_image_size')
                            ->label('Risoluzione immagini')
                            ->options(fn ($get) => $get('gemini_model') === 'gemini-3.1-flash-image-preview'
                                ? ['1K' => '1K — 1024×1024 px']
                                : [
                                    '1K' => '1K — 1024×1024 px',
                                    '2K' => '2K — 2048×2048 px',
                                    '4K' => '4K — 4096×4096 px',
                                ])
                            ->default('1K')
                            ->helperText('Flash supporta solo 1K. Pro supporta fino a 4K.'),

                        Forms\Components\Toggle::make('gemini_use_batch')
                            ->label('Usa Batch API (−50% costo, asincrona)')
                            ->helperText('Turnaround tipico 2–10 minuti, max 24h.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])->columns(2),

            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! empty($data['gemini_api_key']) && $data['gemini_api_key'] !== '••••••••') {
            Setting::set('gemini_api_key', $data['gemini_api_key'], true);
        }

        Setting::set('gemini_model',      $data['gemini_model']      ?? 'gemini-3.1-flash-image-preview');
        Setting::set('gemini_image_size', $data['gemini_image_size'] ?? '1K');
        Setting::set('gemini_use_batch',  ($data['gemini_use_batch'] ?? false) ? '1' : '0');

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }
}
