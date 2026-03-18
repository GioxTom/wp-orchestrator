<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlueprintResource\Pages;
use App\Models\Blueprint;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class BlueprintResource extends Resource
{
    protected static ?string $model           = Blueprint::class;
    protected static ?string $navigationIcon  = 'heroicon-o-puzzle-piece';
    protected static ?string $navigationGroup = 'Configurazione';
    protected static ?string $navigationLabel = 'Blueprint';
    protected static ?int    $navigationSort  = 1;

    // ── Opzioni WP standard disponibili nella dropdown ────────────────────────
    public const WP_SETTINGS_OPTIONS = [
        'permalink_structure'    => 'permalink_structure — Struttura permalink',
        'timezone_string'        => 'timezone_string — Fuso orario',
        'date_format'            => 'date_format — Formato data',
        'time_format'            => 'time_format — Formato ora',
        'posts_per_page'         => 'posts_per_page — Post per pagina',
        'posts_per_rss'          => 'posts_per_rss — Post nel feed RSS',
        'blog_public'            => 'blog_public — Visibilità motori di ricerca',
        'default_comment_status' => 'default_comment_status — Commenti (open/closed)',
        'default_ping_status'    => 'default_ping_status — Ping/Trackback (open/closed)',
        'comment_moderation'     => 'comment_moderation — Moderazione commenti (0/1)',
        'uploads_use_yearmonth'  => 'uploads_use_yearmonth — Cartelle media per data (0/1)',
        'thumbnail_size_w'       => 'thumbnail_size_w — Larghezza miniatura (px)',
        'thumbnail_size_h'       => 'thumbnail_size_h — Altezza miniatura (px)',
        'medium_size_w'          => 'medium_size_w — Larghezza immagine media (px)',
        'medium_size_h'          => 'medium_size_h — Altezza immagine media (px)',
        'large_size_w'           => 'large_size_w — Larghezza immagine grande (px)',
        'large_size_h'           => 'large_size_h — Altezza immagine grande (px)',
        'show_on_front'          => 'show_on_front — Homepage (posts/page)',
        'woocommerce_currency'   => 'woocommerce_currency — Valuta WooCommerce',
        'custom'                 => '＋ Opzione personalizzata...',
    ];

    // ── Valori suggeriti per le opzioni più comuni ────────────────────────────
    private const WP_SETTINGS_HINTS = [
        'permalink_structure'    => '/%postname%/',
        'timezone_string'        => 'Europe/Rome',
        'date_format'            => 'd/m/Y',
        'time_format'            => 'H:i',
        'posts_per_page'         => '10',
        'blog_public'            => '1  (1=visibile, 0=nascosto)',
        'default_comment_status' => 'closed',
        'default_ping_status'    => 'closed',
        'comment_moderation'     => '1',
        'uploads_use_yearmonth'  => '1',
        'show_on_front'          => 'posts',
    ];

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ── Informazioni ─────────────────────────────────────────────────
            Forms\Components\Section::make('Informazioni')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome blueprint')
                        ->required(),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->unique(ignoreRecord: true)
                        ->required(),

                    Forms\Components\TextInput::make('version')
                        ->label('Versione')
                        ->default('1.0.0'),

                    Forms\Components\Select::make('status')
                        ->label('Stato')
                        ->options(['draft' => 'Bozza', 'active' => 'Attivo', 'archived' => 'Archiviato'])
                        ->default('draft')
                        ->required(),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrizione')
                        ->columnSpanFull(),
                ])->columns(2),

            // ── Tema Parent ───────────────────────────────────────────────────
            Forms\Components\Section::make('Tema Parent')
                ->schema([
                    Forms\Components\FileUpload::make('zip_path')
                        ->label('ZIP Tema WordPress')
                        ->disk('local')
                        ->directory('blueprints')
                        ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize(51200)
                        ->helperText('Carica il file ZIP del tema parent (es. generatepress.zip). Il nome del file diventa lo slug del tema.'),
                ]),

            // ── Plugin ────────────────────────────────────────────────────────
            Forms\Components\Section::make('Plugin da installare')
                ->schema([
                    // Guida plugin collassabile
                    Forms\Components\Section::make('📖 Come aggiungere plugin')
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('plugin_guide')
                                ->label('')
                                ->content(new HtmlString('
<div class="space-y-4 text-sm">
    <div>
        <h4 class="font-semibold mb-1">Plugin da WordPress.org (gratuiti)</h4>
        <p>Usa il toggle <strong>Standard</strong> e inserisci lo slug. Lo slug è la parte finale dell\'URL della pagina del plugin:</p>
        <p class="mt-1 font-mono text-xs bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded">
            https://wordpress.org/plugins/<strong>yoast-seo</strong>/  →  slug: <strong>yoast-seo</strong>
        </p>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Plugin premium (ZIP)</h4>
        <p>Usa il toggle <strong>Premium (ZIP)</strong>, dai un nome al plugin e carica il file ZIP. Il sistema lo installerà automaticamente durante il provisioning.</p>
    </div>
    <div>
        <h4 class="font-semibold mb-2">Plugin comuni e relativi slug</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Plugin</th>
                        <th class="px-3 py-2 text-left font-mono">Slug</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr><td class="px-3 py-2">Yoast SEO</td><td class="px-3 py-2 font-mono">wordpress-seo</td></tr>
                    <tr><td class="px-3 py-2">Rank Math SEO</td><td class="px-3 py-2 font-mono">seo-by-rank-math</td></tr>
                    <tr><td class="px-3 py-2">WP Super Cache</td><td class="px-3 py-2 font-mono">wp-super-cache</td></tr>
                    <tr><td class="px-3 py-2">W3 Total Cache</td><td class="px-3 py-2 font-mono">w3-total-cache</td></tr>
                    <tr><td class="px-3 py-2">Wordfence Security</td><td class="px-3 py-2 font-mono">wordfence</td></tr>
                    <tr><td class="px-3 py-2">Really Simple SSL</td><td class="px-3 py-2 font-mono">really-simple-ssl</td></tr>
                    <tr><td class="px-3 py-2">Contact Form 7</td><td class="px-3 py-2 font-mono">contact-form-7</td></tr>
                    <tr><td class="px-3 py-2">WooCommerce</td><td class="px-3 py-2 font-mono">woocommerce</td></tr>
                    <tr><td class="px-3 py-2">Elementor</td><td class="px-3 py-2 font-mono">elementor</td></tr>
                    <tr><td class="px-3 py-2">Classic Editor</td><td class="px-3 py-2 font-mono">classic-editor</td></tr>
                    <tr><td class="px-3 py-2">Akismet Anti-Spam</td><td class="px-3 py-2 font-mono">akismet</td></tr>
                    <tr><td class="px-3 py-2">UpdraftPlus Backup</td><td class="px-3 py-2 font-mono">updraftplus</td></tr>
                    <tr><td class="px-3 py-2">Redirection</td><td class="px-3 py-2 font-mono">redirection</td></tr>
                    <tr><td class="px-3 py-2">Cookie Notice</td><td class="px-3 py-2 font-mono">cookie-notice</td></tr>
                    <tr><td class="px-3 py-2">WP Mail SMTP</td><td class="px-3 py-2 font-mono">wp-mail-smtp</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Versione specifica</h4>
        <p>Lascia <strong>latest</strong> per installare sempre l\'ultima versione stabile. Per fissare una versione, es: <span class="font-mono">5.9.1</span></p>
    </div>
</div>
                                ')),
                        ]),

                    Forms\Components\Repeater::make('plugin_list')
                        ->label('')
                        ->schema([
                            // Toggle premium/standard — determina quali campi mostrare
                            Forms\Components\Toggle::make('is_premium')
                                ->label('Premium (ZIP)')
                                ->default(false)
                                ->live()
                                ->columnSpan(1),

                            // Nome descrittivo — sempre visibile
                            Forms\Components\TextInput::make('name')
                                ->label('Nome plugin')
                                ->placeholder('es. Yoast SEO')
                                ->required()
                                ->columnSpan(2),

                            // Slug — solo per plugin standard
                            Forms\Components\TextInput::make('slug')
                                ->label('Slug (WP.org)')
                                ->placeholder('es. wordpress-seo')
                                ->visible(fn (Get $get) => ! $get('is_premium'))
                                ->requiredIf('is_premium', false)
                                ->columnSpan(2),

                            // ZIP — solo per plugin premium
                            Forms\Components\FileUpload::make('zip_path')
                                ->label('File ZIP')
                                ->disk('local')
                                ->directory('blueprints/plugins')
                                ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                                ->maxSize(102400) // 100MB
                                ->visible(fn (Get $get) => $get('is_premium'))
                                ->requiredIf('is_premium', true)
                                ->columnSpan(2),

                            // Versione — solo per standard
                            Forms\Components\TextInput::make('version')
                                ->label('Versione')
                                ->placeholder('latest')
                                ->visible(fn (Get $get) => ! $get('is_premium'))
                                ->columnSpan(1),

                            // Attiva
                            Forms\Components\Toggle::make('activate')
                                ->label('Attiva')
                                ->default(true)
                                ->columnSpan(1),
                        ])
                        ->columns(9)
                        ->addActionLabel('Aggiungi plugin')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $name    = $state['name'] ?? null;
                            $premium = ($state['is_premium'] ?? false) ? ' 💎' : '';
                            return $name ? $name . $premium : null;
                        })
                        ->rules([
                            fn () => function (string $attribute, $value, $fail) {
                                // Controlla duplicati su slug (solo plugin standard)
                                $slugs = collect($value)
                                    ->filter(fn ($p) => ! ($p['is_premium'] ?? false))
                                    ->pluck('slug')
                                    ->filter()
                                    ->values();

                                if ($slugs->count() !== $slugs->unique()->count()) {
                                    $fail('Ci sono plugin duplicati. Ogni slug deve essere unico.');
                                }

                                // Controlla duplicati su nome (tutti)
                                $names = collect($value)->pluck('name')->filter()->values();
                                if ($names->count() !== $names->unique()->count()) {
                                    $fail('Ci sono plugin con lo stesso nome. Ogni plugin deve avere un nome unico.');
                                }
                            },
                        ]),
                ]),

            // ── Impostazioni WordPress ────────────────────────────────────────
            Forms\Components\Section::make('Impostazioni WordPress')
                ->schema([
                    Forms\Components\Repeater::make('wp_settings')
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('key')
                                ->label('Opzione')
                                ->options(self::WP_SETTINGS_OPTIONS)
                                ->searchable()
                                ->live()
                                ->required()
                                ->afterStateUpdated(function ($state, Forms\Set $set) {
                                    // Precompila il valore suggerito
                                    if (isset(self::WP_SETTINGS_HINTS[$state])) {
                                        $set('value', self::WP_SETTINGS_HINTS[$state]);
                                    }
                                    // Se custom, svuota il campo chiave custom
                                    if ($state !== 'custom') {
                                        $set('custom_key', null);
                                    }
                                })
                                ->columnSpan(2),

                            // Campo visibile solo se si sceglie "custom"
                            Forms\Components\TextInput::make('custom_key')
                                ->label('Nome opzione custom')
                                ->placeholder('es. my_custom_option')
                                ->visible(fn (Get $get) => $get('key') === 'custom')
                                ->requiredIf('key', 'custom')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('value')
                                ->label('Valore')
                                ->required()
                                ->columnSpan(2),
                        ])
                        ->columns(6)
                        ->addActionLabel('Aggiungi impostazione')
                        ->reorderable(false)
                        ->collapsible()
                        ->itemLabel(function (array $state): ?string {
                            $key = $state['key'] === 'custom'
                                ? ($state['custom_key'] ?? 'custom')
                                : ($state['key'] ?? null);
                            $val = $state['value'] ?? '';
                            return $key ? "{$key} = {$val}" : null;
                        })
                        ->rules([
                            fn () => function (string $attribute, $value, $fail) {
                                $keys = collect($value)->map(function ($item) {
                                    return $item['key'] === 'custom'
                                        ? ($item['custom_key'] ?? '')
                                        : ($item['key'] ?? '');
                                })->filter()->values();

                                if ($keys->count() !== $keys->unique()->count()) {
                                    $fail('Ci sono impostazioni duplicate. Ogni opzione può apparire una sola volta.');
                                }
                            },
                        ])
                        ->helperText('Seleziona un\'opzione dalla lista o scegli "Opzione personalizzata" per inserirne una custom.'),
                ]),

            // ── Child Theme Skeleton ──────────────────────────────────────────
            Forms\Components\Section::make('Child Theme Skeleton')
                ->schema([
                    Forms\Components\Textarea::make('child_skeleton')
                        ->label('functions.php del child theme')
                        ->rows(12)
                        ->placeholder("<?php\n// Child theme functions\n// Placeholder: {site_name}, {domain}, {locale}\n")
                        ->helperText('Lascia vuoto per usare il template di default. Puoi usare {site_name}, {domain}, {locale} come placeholder.'),
                ]),

            // ── Pagine di default ─────────────────────────────────────────────
            Forms\Components\Section::make('Pagine di default')
                ->schema([
                    Forms\Components\Repeater::make('pages')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Titolo')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->placeholder('es. chi-siamo')
                                ->helperText('Lascia vuoto per generarlo dal titolo automaticamente.')
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('template')
                                ->label('Template')
                                ->placeholder('default')
                                ->helperText('Nome file del template del tema (es. full-width). Lascia vuoto per il default.')
                                ->columnSpan(2),

                            Forms\Components\RichEditor::make('content')
                                ->label('Contenuto')
                                ->columnSpanFull()
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike',
                                    'h2', 'h3',
                                    'bulletList', 'orderedList',
                                    'link', 'blockquote',
                                    'undo', 'redo',
                                ]),
                        ])
                        ->columns(6)
                        ->addActionLabel('Aggiungi pagina')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['title'] ?? 'Nuova pagina'),
                ]),

            // ── Menu di default ───────────────────────────────────────────────
            Forms\Components\Section::make('Menu di default')
                ->schema([
                    // Guida posizioni menu
                    Forms\Components\Section::make('📖 Come trovare le posizioni menu del tema')
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('menu_guide')
                                ->label('')
                                ->content(new HtmlString('
<div class="space-y-4 text-sm">
    <div>
        <h4 class="font-semibold mb-1">Dove trovare le posizioni disponibili</h4>
        <p>Ogni tema WordPress registra le proprie posizioni menu. Per trovarle:</p>
        <ol class="list-decimal ml-4 mt-2 space-y-1">
            <li>Installa il tema sul sito</li>
            <li>Vai in <strong>Aspetto → Menu</strong></li>
            <li>Scorri in basso fino alla sezione <strong>"Posizioni tema"</strong></li>
            <li>Le posizioni disponibili sono elencate lì con il loro nome visibile</li>
        </ol>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Come ottenere il nome tecnico (slug)</h4>
        <p>Il nome tecnico si trova nel file <strong>functions.php</strong> del tema, nella funzione <code>register_nav_menus()</code>:</p>
        <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded overflow-x-auto">register_nav_menus([
    \'primary\'  => \'Menu principale\',   ← usa: primary
    \'footer\'   => \'Menu footer\',       ← usa: footer
    \'social\'   => \'Link social\',       ← usa: social
]);</pre>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Posizioni comuni per tema</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Tema</th>
                        <th class="px-3 py-2 text-left">Posizioni disponibili</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr><td class="px-3 py-2">GeneratePress</td><td class="px-3 py-2 font-mono">primary, secondary, mobile-menu, footer-bar</td></tr>
                    <tr><td class="px-3 py-2">Astra</td><td class="px-3 py-2 font-mono">primary, footer-menu</td></tr>
                    <tr><td class="px-3 py-2">OceanWP</td><td class="px-3 py-2 font-mono">main_nav, mobile_nav, footer_nav, top_bar_menu</td></tr>
                    <tr><td class="px-3 py-2">Hello Elementor</td><td class="px-3 py-2 font-mono">menu-1</td></tr>
                    <tr><td class="px-3 py-2">Neve</td><td class="px-3 py-2 font-mono">primary, footer</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
                                ')),
                        ]),

                    Forms\Components\Repeater::make('menus')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('name')
                                ->label('Nome menu')
                                ->placeholder('es. Menu principale')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('location')
                                ->label('Posizione tema')
                                ->placeholder('es. primary')
                                ->helperText('Slug della posizione (vedi guida sopra)')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\Repeater::make('items')
                                ->label('Voci menu')
                                ->schema([
                                    Forms\Components\TextInput::make('label')
                                        ->label('Etichetta')
                                        ->required()
                                        ->columnSpan(2),

                                    Forms\Components\Select::make('type')
                                        ->label('Tipo')
                                        ->options([
                                            'page'   => 'Pagina blueprint',
                                            'custom' => 'URL personalizzato',
                                            'home'   => 'Homepage',
                                        ])
                                        ->default('page')
                                        ->live()
                                        ->required()
                                        ->columnSpan(1),

                                    Forms\Components\TextInput::make('page_slug')
                                        ->label('Slug pagina')
                                        ->placeholder('es. chi-siamo')
                                        ->visible(fn (Get $get) => $get('type') === 'page')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('url')
                                        ->label('URL')
                                        ->placeholder('https://')
                                        ->visible(fn (Get $get) => $get('type') === 'custom')
                                        ->columnSpan(2),

                                    Forms\Components\TextInput::make('order')
                                        ->label('Ordine')
                                        ->numeric()
                                        ->default(1)
                                        ->columnSpan(1),
                                ])
                                ->columns(6)
                                ->addActionLabel('Aggiungi voce')
                                ->collapsible()
                                ->itemLabel(fn (array $state) => $state['label'] ?? 'Voce')
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->addActionLabel('Aggiungi menu')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => ($state['name'] ?? '') . ($state['location'] ? ' → ' . $state['location'] : '')),
                ]),

            // ── Widget di default ─────────────────────────────────────────────
            Forms\Components\Section::make('Widget di default')
                ->schema([
                    // Guida widget
                    Forms\Components\Section::make('📖 Come trovare sidebar e widget disponibili')
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('widget_guide')
                                ->label('')
                                ->content(new HtmlString('
<div class="space-y-4 text-sm">
    <div>
        <h4 class="font-semibold mb-1">Trovare le sidebar disponibili</h4>
        <p>Ogni tema registra le proprie sidebar (aree widget). Per trovarle:</p>
        <ol class="list-decimal ml-4 mt-2 space-y-1">
            <li>Installa il tema sul sito</li>
            <li>Vai in <strong>Aspetto → Widget</strong></li>
            <li>Le sezioni elencate (es. "Sidebar principale", "Footer 1") sono le sidebar disponibili</li>
        </ol>
        <p class="mt-2">Il <strong>sidebar_id</strong> si trova nel functions.php del tema, nella funzione <code>register_sidebar()</code>:</p>
        <pre class="mt-1 text-xs bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded overflow-x-auto">register_sidebar([
    \'id\'   => \'sidebar-1\',    ← usa questo come sidebar_id
    \'name\' => \'Sidebar principale\',
]);</pre>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Widget WordPress standard</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Widget</th>
                        <th class="px-3 py-2 text-left">widget_type</th>
                        <th class="px-3 py-2 text-left">Impostazioni principali</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr><td class="px-3 py-2">Testo</td><td class="px-3 py-2 font-mono">text</td><td class="px-3 py-2 font-mono">title, text</td></tr>
                    <tr><td class="px-3 py-2">Post recenti</td><td class="px-3 py-2 font-mono">recent-posts</td><td class="px-3 py-2 font-mono">title, number</td></tr>
                    <tr><td class="px-3 py-2">Categorie</td><td class="px-3 py-2 font-mono">categories</td><td class="px-3 py-2 font-mono">title, count, dropdown</td></tr>
                    <tr><td class="px-3 py-2">Archivi</td><td class="px-3 py-2 font-mono">archives</td><td class="px-3 py-2 font-mono">title, count, dropdown</td></tr>
                    <tr><td class="px-3 py-2">Ricerca</td><td class="px-3 py-2 font-mono">search</td><td class="px-3 py-2 font-mono">title</td></tr>
                    <tr><td class="px-3 py-2">Tag cloud</td><td class="px-3 py-2 font-mono">tag_cloud</td><td class="px-3 py-2 font-mono">title, taxonomy</td></tr>
                    <tr><td class="px-3 py-2">Commenti recenti</td><td class="px-3 py-2 font-mono">recent-comments</td><td class="px-3 py-2 font-mono">title, number</td></tr>
                    <tr><td class="px-3 py-2">RSS</td><td class="px-3 py-2 font-mono">rss</td><td class="px-3 py-2 font-mono">title, url, items</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div>
        <h4 class="font-semibold mb-1">Sidebar comuni per tema</h4>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-xs">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Tema</th>
                        <th class="px-3 py-2 text-left">Sidebar ID</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr><td class="px-3 py-2">GeneratePress</td><td class="px-3 py-2 font-mono">sidebar, footer-1, footer-2, footer-3</td></tr>
                    <tr><td class="px-3 py-2">Astra</td><td class="px-3 py-2 font-mono">sidebar-1, sidebar-2</td></tr>
                    <tr><td class="px-3 py-2">OceanWP</td><td class="px-3 py-2 font-mono">sidebar, footer-1, footer-2, footer-3, footer-4</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
                                ')),
                        ]),

                    Forms\Components\Repeater::make('widgets')
                        ->label('')
                        ->schema([
                            Forms\Components\TextInput::make('sidebar_id')
                                ->label('Sidebar ID')
                                ->placeholder('es. sidebar-1')
                                ->helperText('Vedi guida sopra')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('widget_type')
                                ->label('Tipo widget')
                                ->placeholder('es. text')
                                ->helperText('Vedi tabella nella guida')
                                ->required()
                                ->columnSpan(2),

                            Forms\Components\KeyValue::make('settings')
                                ->label('Impostazioni')
                                ->keyLabel('Chiave')
                                ->valueLabel('Valore')
                                ->columnSpanFull(),
                        ])
                        ->columns(4)
                        ->addActionLabel('Aggiungi widget')
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(fn (array $state) => ($state['widget_type'] ?? '') . ($state['sidebar_id'] ? ' → ' . $state['sidebar_id'] : '')),
                ]),
        ]);
    }

    // ── Mutator: normalizza wp_settings prima del salvataggio ─────────────────
    // Converte {key: 'custom', custom_key: 'foo', value: 'bar'} → {foo: 'bar'}
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        return static::normalizeWpSettings($data);
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::normalizeWpSettings($data);
    }

    private static function normalizeWpSettings(array $data): array
    {
        if (empty($data['wp_settings'])) {
            return $data;
        }

        $normalized = [];
        foreach ($data['wp_settings'] as $item) {
            $key = $item['key'] === 'custom'
                ? ($item['custom_key'] ?? null)
                : ($item['key'] ?? null);

            if ($key && isset($item['value'])) {
                $normalized[$key] = $item['value'];
            }
        }

        $data['wp_settings'] = $normalized;
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
                Tables\Columns\TextColumn::make('version')->label('Ver.'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Stato')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'active',
                        'gray'    => 'archived',
                    ]),
                Tables\Columns\TextColumn::make('plugin_list')
                    ->label('Plugin')
                    ->getStateUsing(fn (Blueprint $record) => count($record->plugin_list ?? []) . ' plugin'),
                Tables\Columns\IconColumn::make('zip_path')
                    ->label('Tema ZIP')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('sites_count')
                    ->label('Siti')
                    ->counts('sites'),
                Tables\Columns\TextColumn::make('updated_at')->label('Aggiornato')->since(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Modifica'),

                Tables\Actions\Action::make('clone')
                    ->label('Clona')
                    ->icon('heroicon-o-document-duplicate')
                    ->iconButton()
                    ->tooltip('Clona blueprint con tutti i file')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Blueprint $record) => 'Clonare "' . $record->name . '"?')
                    ->modalDescription('Verrà creata una copia completa del blueprint inclusi tema ZIP e plugin premium.')
                    ->modalSubmitActionLabel('Clona')
                    ->action(function (Blueprint $record) {
                        // Clona il record
                        $clone = $record->replicate();
                        $clone->name   = $record->name . ' (copia)';
                        $clone->slug   = $record->slug . '-clone-' . substr(uniqid(), -6);
                        $clone->status = 'draft';

                        // Copia il file ZIP del tema se presente
                        if ($record->zip_path && \Storage::disk('local')->exists($record->zip_path)) {
                            $ext      = pathinfo($record->zip_path, PATHINFO_EXTENSION);
                            $newPath  = 'blueprints/clone_' . uniqid() . '.' . $ext;
                            \Storage::disk('local')->copy($record->zip_path, $newPath);
                            $clone->zip_path = $newPath;
                        }

                        // Copia i file ZIP dei plugin premium
                        $pluginList = $record->plugin_list ?? [];
                        foreach ($pluginList as &$plugin) {
                            if (! empty($plugin['is_premium']) && ! empty($plugin['zip_path'])) {
                                if (\Storage::disk('local')->exists($plugin['zip_path'])) {
                                    $ext     = pathinfo($plugin['zip_path'], PATHINFO_EXTENSION);
                                    $newPath = 'blueprints/plugins/clone_' . uniqid() . '.' . $ext;
                                    \Storage::disk('local')->copy($plugin['zip_path'], $newPath);
                                    $plugin['zip_path'] = $newPath;
                                }
                            }
                        }
                        unset($plugin);
                        $clone->plugin_list = $pluginList;

                        $clone->save();

                        \Filament\Notifications\Notification::make()
                            ->title('Blueprint clonato')
                            ->body('"' . $clone->name . '" è pronto in bozza.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBlueprints::route('/'),
            'create' => Pages\CreateBlueprint::route('/create'),
            'edit'   => Pages\EditBlueprint::route('/{record}/edit'),
        ];
    }
}
