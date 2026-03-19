{{-- resources/views/filament/pages/view-site-header.blade.php --}}
<div class="fi-header flex flex-col gap-3 px-4 py-6 sm:px-6 lg:px-8">

    {{-- Titolo e descrizione --}}
    <div>
        <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
            {{ $heading }}
        </h1>
        @if(!empty($description))
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $description }}
            </p>
        @endif
    </div>

    {{-- Pulsanti su riga separata --}}
    @if(!empty($actions))
        <div class="flex flex-wrap items-center gap-3">
            <x-filament-actions::actions :actions="$actions" />
        </div>
    @endif

</div>
