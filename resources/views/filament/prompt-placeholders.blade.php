{{-- resources/views/filament/prompt-placeholders.blade.php --}}
<div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
    <p class="font-medium mb-2">Placeholder utilizzabili nel testo del prompt:</p>
    <div class="grid grid-cols-2 gap-2">
        <div class="font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs">
            <span class="text-primary-600">{site_name}</span>
            <span class="text-gray-500 ml-2">→ Nome del sito</span>
        </div>
        <div class="font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs">
            <span class="text-primary-600">{site_description}</span>
            <span class="text-gray-500 ml-2">→ Descrizione del sito</span>
        </div>
        <div class="font-mono bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded text-xs">
            <span class="text-primary-600">{locale}</span>
            <span class="text-gray-500 ml-2">→ Lingua (es. it_IT)</span>
        </div>
    </div>
</div>
