{{-- resources/views/filament/resources/site-resource/pages/provisioning-logs.blade.php --}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="space-y-3">
            @forelse($this->record->provisioningLogs as $log)
                <div class="flex items-start gap-4 p-4 rounded-lg border
                    @if($log->status === 'success') border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950
                    @elseif($log->status === 'failed') border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950
                    @elseif($log->status === 'running') border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-950
                    @else border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900
                    @endif">

                    {{-- Icona stato --}}
                    <div class="mt-0.5 shrink-0">
                        @if($log->status === 'success')
                            <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                        @elseif($log->status === 'failed')
                            <x-heroicon-o-x-circle class="w-5 h-5 text-red-600" />
                        @elseif($log->status === 'running')
                            <x-heroicon-o-arrow-path class="w-5 h-5 text-yellow-600 animate-spin" />
                        @else
                            <x-heroicon-o-clock class="w-5 h-5 text-gray-400" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <p class="font-medium text-sm">{{ $log->step_label }}</p>
                            <div class="flex items-center gap-2 text-xs text-gray-500 shrink-0">
                                @if($log->started_at)
                                    <span>{{ $log->started_at->format('H:i:s') }}</span>
                                @endif
                                @if($log->finished_at && $log->started_at)
                                    <span class="text-gray-400">
                                        ({{ $log->started_at->diffInSeconds($log->finished_at) }}s)
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if($log->output)
                            <details class="mt-2">
                                <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                                    Mostra output
                                </summary>
                                <pre class="mt-2 text-xs bg-black text-green-400 p-3 rounded overflow-x-auto max-h-48">{{ $log->output }}</pre>
                            </details>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-gray-500 text-sm text-center py-8">
                    Nessun log disponibile per questo sito.
                </p>
            @endforelse
        </div>
    </x-filament::section>
</x-filament-panels::page>
