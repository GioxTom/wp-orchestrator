{{-- resources/views/filament/pages/settings.blade.php --}}
<x-filament-panels::page>
    <div x-data="{ tab: 'nana-banana' }">

        {{-- ── Tab Nav ──────────────────────────────────────────────────── --}}
        <nav class="fi-tabs flex max-w-full gap-x-1 overflow-x-auto mx-auto rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
             role="tablist">

            {{-- Tab: Nano Banana --}}
            <button type="button" role="tab"
                    x-on:click="tab = 'nana-banana'"
                    x-bind:class="{
                        'fi-active fi-tabs-item-active bg-gray-50 dark:bg-white/5': tab === 'nana-banana',
                        'hover:bg-gray-50 dark:hover:bg-white/5': tab !== 'nana-banana',
                    }"
                    class="fi-tabs-item group flex items-center justify-center gap-x-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75">
                <svg x-bind:class="tab === 'nana-banana' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500'"
                     class="fi-tabs-item-icon h-5 w-5 shrink-0 transition duration-75"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                <span x-bind:class="tab === 'nana-banana' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200'"
                      class="fi-tabs-item-label transition duration-75">
                    🍌 Nano Banana
                </span>
            </button>

            {{-- Tab: Logo --}}
            <button type="button" role="tab"
                    x-on:click="tab = 'logo'"
                    x-bind:class="{
                        'fi-active fi-tabs-item-active bg-gray-50 dark:bg-white/5': tab === 'logo',
                        'hover:bg-gray-50 dark:hover:bg-white/5': tab !== 'logo',
                    }"
                    class="fi-tabs-item group flex items-center justify-center gap-x-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75">
                <svg x-bind:class="tab === 'logo' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500'"
                     class="fi-tabs-item-icon h-5 w-5 shrink-0 transition duration-75"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
                </svg>
                <span x-bind:class="tab === 'logo' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200'"
                      class="fi-tabs-item-label transition duration-75">
                    Logo
                </span>
            </button>

            {{-- Tab: AI Contenuti --}}
            <button type="button" role="tab"
                    x-on:click="tab = 'ai'"
                    x-bind:class="{
                        'fi-active fi-tabs-item-active bg-gray-50 dark:bg-white/5': tab === 'ai',
                        'hover:bg-gray-50 dark:hover:bg-white/5': tab !== 'ai',
                    }"
                    class="fi-tabs-item group flex items-center justify-center gap-x-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75">
                <svg x-bind:class="tab === 'ai' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500'"
                     class="fi-tabs-item-icon h-5 w-5 shrink-0 transition duration-75"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 0 0 2.25-2.25V6.75a2.25 2.25 0 0 0-2.25-2.25H6.75A2.25 2.25 0 0 0 4.5 6.75v10.5a2.25 2.25 0 0 0 2.25 2.25Zm.75-12h9v9h-9v-9Z" />
                </svg>
                <span x-bind:class="tab === 'ai' ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200'"
                      class="fi-tabs-item-label transition duration-75">
                    🤖 AI Contenuti
                </span>
            </button>

        </nav>

        {{-- ── Tab Panels ───────────────────────────────────────────────── --}}
        <div class="mt-6">

            {{-- Panel: Nano Banana --}}
            <div x-show="tab === 'nana-banana'" x-cloak>
                <form wire:submit="salvaNanaBanana">
                    {{ $this->nanaBananaForm }}
                    <div class="mt-6 flex items-center justify-end gap-x-3">
                        <x-filament::button type="submit" color="primary" icon="heroicon-m-check">
                            Salva impostazioni Nano Banana
                        </x-filament::button>
                    </div>
                </form>
            </div>

            {{-- Panel: Logo --}}
            <div x-show="tab === 'logo'" x-cloak>
                <form wire:submit="salvaLogo">
                    {{ $this->logoForm }}
                    <div class="mt-6 flex items-center justify-end gap-x-3">
                        <x-filament::button type="submit" color="primary" icon="heroicon-m-check">
                            Salva impostazioni Logo
                        </x-filament::button>
                    </div>
                </form>
            </div>

            {{-- Panel: AI Contenuti --}}
            <div x-show="tab === 'ai'" x-cloak>
                <form wire:submit="salvaAi">
                    {{ $this->aiForm }}
                    <div class="mt-6 flex items-center justify-end gap-x-3">
                        <x-filament::button type="submit" color="primary" icon="heroicon-m-check">
                            Salva impostazioni AI
                        </x-filament::button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
