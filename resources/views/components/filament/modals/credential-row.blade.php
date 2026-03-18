@props(['label', 'value', 'secret' => false])

<div
    class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 group transition-colors"
    x-data="{ copied: false, shown: false }"
    x-on:click="
        navigator.clipboard.writeText('{{ addslashes($value ?? '') }}');
        copied = true;
        setTimeout(() => copied = false, 2000);
    "
    title="Clicca per copiare"
>
    <span class="text-sm font-medium text-gray-500 dark:text-gray-400 w-28 shrink-0">
        {{ $label }}
    </span>

    <div class="flex items-center gap-2 flex-1 min-w-0">
        @if($secret)
            <span
                class="font-mono text-sm text-gray-900 dark:text-gray-100 truncate"
                x-show="shown"
                x-cloak
            >{{ $value }}</span>
            <span
                class="font-mono text-sm text-gray-400 tracking-widest"
                x-show="!shown"
            >••••••••••••</span>
            <button
                type="button"
                class="ml-1 text-gray-400 hover:text-gray-600 shrink-0"
                x-on:click.stop="shown = !shown"
                :title="shown ? 'Nascondi' : 'Mostra'"
            >
                <x-heroicon-o-eye class="w-4 h-4" x-show="!shown" />
                <x-heroicon-o-eye-slash class="w-4 h-4" x-show="shown" x-cloak />
            </button>
        @else
            <span class="font-mono text-sm text-gray-900 dark:text-gray-100 truncate">
                {{ $value }}
            </span>
        @endif
    </div>

    <div class="ml-3 shrink-0">
        <span x-show="!copied" class="text-gray-300 group-hover:text-gray-400 transition-colors">
            <x-heroicon-o-clipboard class="w-4 h-4" />
        </span>
        <span x-show="copied" x-cloak class="text-green-500 text-xs font-medium">
            ✓ Copiato
        </span>
    </div>
</div>
