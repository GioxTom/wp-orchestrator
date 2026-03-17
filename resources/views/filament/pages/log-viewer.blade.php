<x-filament-panels::page>

    {{-- Toolbar: selezione file + righe --}}
    <div class="flex flex-wrap items-end gap-4 mb-4">

        {{-- Selettore file --}}
        <div class="flex-1 min-w-48">
            <label class="block text-sm font-medium mb-1">File di log</label>
            <select
                wire:model.live="selectedFile"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
            >
                @forelse($this->getLogFiles() as $path => $label)
                    <option value="{{ $path }}">{{ $label }}</option>
                @empty
                    <option value="">Nessun file di log trovato</option>
                @endforelse
            </select>
        </div>

        {{-- Numero righe --}}
        <div class="w-36">
            <label class="block text-sm font-medium mb-1">Ultime righe</label>
            <select
                wire:model.live="lines"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm"
            >
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="250">250</option>
                <option value="500">500</option>
                <option value="1000">1000</option>
            </select>
        </div>

    </div>

    {{-- Contenuto log --}}
    <x-filament::section>
        <div
            class="overflow-x-auto overflow-y-auto max-h-[70vh] rounded-lg bg-gray-950 p-4"
            x-data
            x-init="$el.scrollTop = $el.scrollHeight"
        >
            <pre class="text-xs text-green-400 whitespace-pre-wrap break-all leading-5 font-mono">{{ $this->getLogContent() }}</pre>
        </div>
    </x-filament::section>

</x-filament-panels::page>
