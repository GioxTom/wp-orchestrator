<div class="fi-header flex flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">

    {{-- Titolo + breadcrumbs --}}
    <div>
        <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
            {{ $heading }}
        </h1>
        @if($subheading)
            <p class="fi-header-subheading mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $subheading }}
            </p>
        @endif
    </div>

    {{-- Azioni su riga separata --}}
    @if($actions)
        <div class="flex flex-wrap items-center gap-3">
            {{ $actions }}
        </div>
    @endif

</div>
