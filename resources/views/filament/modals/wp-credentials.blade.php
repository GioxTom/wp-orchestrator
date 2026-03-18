<div class="space-y-4 p-2" x-data>

    @php
        $prefix  = $site->www_mode === 'www' ? 'www.' : '';
        $siteUrl = "https://{$prefix}{$site->domain}";

        $rows = [
            'URL sito' => [
                ['label' => 'URL',      'value' => $siteUrl,               'secret' => false],
                ['label' => 'WP Admin', 'value' => $siteUrl . '/wp-admin', 'secret' => false],
            ],
            'Credenziali WordPress' => [
                ['label' => 'Utente',   'value' => 'admin',                'secret' => false],
                ['label' => 'Password', 'value' => $site->wp_admin_password, 'secret' => true],
                ['label' => 'Email',    'value' => $site->wp_admin_email,  'secret' => false],
            ],
            'Database' => [
                ['label' => 'Nome DB',     'value' => $site->db_name,     'secret' => false],
                ['label' => 'Utente DB',   'value' => $site->db_user,     'secret' => false],
                ['label' => 'Password DB', 'value' => $site->db_password, 'secret' => true],
                ['label' => 'Host',        'value' => 'localhost',         'secret' => false],
            ],
        ];
    @endphp

    @foreach($rows as $section => $fields)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            {{ $section }}
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @foreach($fields as $field)
            <div
                class="flex items-center gap-4 px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors group"
                x-data="{ copied: false, shown: false }"
                x-on:click="navigator.clipboard.writeText($el.dataset.value); copied = true; setTimeout(() => copied = false, 2000)"
                data-value="{{ $field['value'] ?? '' }}"
            >
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400 w-28 shrink-0">
                    {{ $field['label'] }}
                </span>

                <div class="flex items-center gap-2 flex-1 min-w-0">
                    @if($field['secret'])
                        <span class="font-mono text-sm text-gray-900 dark:text-gray-100 truncate" x-show="shown" x-cloak>
                            {{ $field['value'] }}
                        </span>
                        <span class="font-mono text-sm text-gray-400 tracking-widest" x-show="!shown">
                            ••••••••••••
                        </span>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0"
                            x-on:click.stop="shown = !shown"
                        >
                            <x-heroicon-o-eye class="w-4 h-4" x-show="!shown" />
                            <x-heroicon-o-eye-slash class="w-4 h-4" x-show="shown" x-cloak />
                        </button>
                    @else
                        <span class="font-mono text-sm text-gray-900 dark:text-gray-100 truncate">
                            {{ $field['value'] }}
                        </span>
                    @endif
                </div>

                <div class="shrink-0">
                    <span x-show="!copied" class="text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-300 transition-colors">
                        <x-heroicon-o-clipboard class="w-4 h-4" />
                    </span>
                    <span x-show="copied" x-cloak class="text-green-500 text-xs font-medium">✓ Copiato</span>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    <p class="text-xs text-gray-400 text-center">Clicca su un valore per copiarlo negli appunti</p>
</div>
