<div class="space-y-4 p-2" x-data>

    {{-- URL Sito --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            URL Sito
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            @php
                $prefix = $site->www_mode === 'www' ? 'www.' : '';
                $siteUrl = "https://{$prefix}{$site->domain}";
                $adminUrl = "{$siteUrl}/wp-admin";
            @endphp

            <x-filament.modals.credential-row label="URL" :value="$siteUrl" />
            <x-filament.modals.credential-row label="WP Admin" :value="$adminUrl" />
        </div>
    </div>

    {{-- Credenziali WordPress --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Credenziali WordPress
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <x-filament.modals.credential-row label="Utente" value="admin" />
            <x-filament.modals.credential-row label="Password" :value="$site->wp_admin_password" :secret="true" />
            <x-filament.modals.credential-row label="Email" :value="$site->wp_admin_email" />
        </div>
    </div>

    {{-- Credenziali Database --}}
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-800 px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            Database
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-700">
            <x-filament.modals.credential-row label="Nome DB" :value="$site->db_name" />
            <x-filament.modals.credential-row label="Utente DB" :value="$site->db_user" />
            <x-filament.modals.credential-row label="Password DB" :value="$site->db_password" :secret="true" />
            <x-filament.modals.credential-row label="Host" value="localhost" />
        </div>
    </div>

    <p class="text-xs text-gray-400 text-center">
        Clicca su un valore per copiarlo negli appunti
    </p>
</div>
