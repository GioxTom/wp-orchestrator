<div class="space-y-4 p-1">

    @php
        $prefix  = $site->www_mode === 'www' ? 'www.' : '';
        $siteUrl = "https://{$prefix}{$site->domain}";

        $rows = [
            'URL sito' => [
                ['label' => 'URL',      'value' => $siteUrl,                'secret' => false],
                ['label' => 'WP Admin', 'value' => $siteUrl . '/wp-admin',  'secret' => false],
            ],
            'Credenziali WordPress' => [
                ['label' => 'Utente',   'value' => 'admin',                 'secret' => false],
                ['label' => 'Password', 'value' => $site->wp_admin_password, 'secret' => true],
                ['label' => 'Email',    'value' => $site->wp_admin_email,   'secret' => false],
            ],
            'Database' => [
                ['label' => 'Nome DB',     'value' => $site->db_name,      'secret' => false],
                ['label' => 'Utente DB',   'value' => $site->db_user,      'secret' => false],
                ['label' => 'Password DB', 'value' => $site->db_password,  'secret' => true],
                ['label' => 'Host',        'value' => 'localhost',          'secret' => false],
            ],
        ];
    @endphp

    @foreach($rows as $section => $fields)
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">

        <div class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 bg-gray-100 dark:bg-gray-800">
            {{ $section }}
        </div>

        @foreach($fields as $field)
        <div
            class="flex items-center gap-4 px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10 transition-colors group border-t border-gray-100 dark:border-gray-700"
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
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 shrink-0 focus:outline-none"
                        x-on:click.stop="shown = !shown"
                    >
                        <svg x-show="!shown" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                        <svg x-show="shown" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                    </button>
                @else
                    <span class="font-mono text-sm text-gray-900 dark:text-gray-100 truncate">
                        {{ $field['value'] }}
                    </span>
                @endif
            </div>

            <div class="shrink-0">
                <svg x-show="!copied" class="w-4 h-4 text-gray-300 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                <span x-show="copied" x-cloak class="text-xs font-medium text-green-500">✓</span>
            </div>
        </div>
        @endforeach

    </div>
    @endforeach

    <p class="text-xs text-center text-gray-400">Clicca su un valore per copiarlo</p>
</div>
