<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\File;

class LogViewerPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Log';
    protected static ?int    $navigationSort  = 30;
    protected static string  $view            = 'filament.pages.log-viewer';

    // File di log selezionato
    public string $selectedFile = '';

    // Numero di righe da mostrare (dal fondo)
    public int $lines = 100;

    public function mount(): void
    {
        $files = $this->getLogFiles();
        if (! empty($files)) {
            $this->selectedFile = array_key_first($files);
        }
    }

    /**
     * Restituisce la lista dei file di log disponibili.
     */
    public function getLogFiles(): array
    {
        $logPath = storage_path('logs');
        $files   = File::glob("{$logPath}/*.log");

        if (empty($files)) {
            return [];
        }

        // Ordina per data modifica — il più recente prima
        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        $result = [];
        foreach ($files as $file) {
            $name          = basename($file);
            $size          = $this->formatSize(filesize($file));
            $modified      = date('d/m/Y H:i', filemtime($file));
            $result[$file] = "{$name} — {$size} — {$modified}";
        }

        return $result;
    }

    /**
     * Legge le ultime N righe del file selezionato.
     */
    public function getLogContent(): string
    {
        if (! $this->selectedFile || ! file_exists($this->selectedFile)) {
            return 'Nessun file selezionato o file non trovato.';
        }

        // Legge le ultime N righe in modo efficiente
        $content = $this->tailFile($this->selectedFile, $this->lines);

        if (empty(trim($content))) {
            return '(file vuoto)';
        }

        return $content;
    }

    /**
     * Svuota il file di log selezionato.
     */
    public function clearLog(): void
    {
        if (! $this->selectedFile || ! file_exists($this->selectedFile)) {
            Notification::make()->title('File non trovato')->danger()->send();
            return;
        }

        file_put_contents($this->selectedFile, '');

        Notification::make()
            ->title('Log svuotato')
            ->body(basename($this->selectedFile) . ' è stato pulito.')
            ->success()
            ->send();
    }

    /**
     * Elimina il file di log selezionato.
     */
    public function deleteLog(): void
    {
        if (! $this->selectedFile || ! file_exists($this->selectedFile)) {
            Notification::make()->title('File non trovato')->danger()->send();
            return;
        }

        $name = basename($this->selectedFile);
        unlink($this->selectedFile);

        // Seleziona il prossimo file disponibile
        $files = $this->getLogFiles();
        $this->selectedFile = ! empty($files) ? array_key_first($files) : '';

        Notification::make()
            ->title('File eliminato')
            ->body("{$name} è stato eliminato.")
            ->success()
            ->send();
    }

    /**
     * Legge le ultime N righe di un file in modo efficiente
     * senza caricare tutto il file in memoria.
     */
    private function tailFile(string $path, int $lines): string
    {
        $fp      = fopen($path, 'r');
        $buffer  = '';
        $found   = 0;
        $pos     = -1;
        $size    = filesize($path);

        if ($size === 0) {
            fclose($fp);
            return '';
        }

        // Legge il file al contrario a blocchi da 4KB
        while ($found <= $lines && abs($pos) < $size) {
            $seek = max(-$size, $pos * 4096);
            fseek($fp, $seek, SEEK_END);
            $chunk  = fread($fp, abs($seek) - strlen($buffer));
            $buffer = $chunk . $buffer;
            $found  = substr_count($buffer, "\n");
            $pos--;
        }

        fclose($fp);

        // Prende solo le ultime N righe
        $allLines = explode("\n", $buffer);
        $tail     = array_slice($allLines, -($lines + 1));

        return implode("\n", $tail);
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024)        return "{$bytes} B";
        if ($bytes < 1048576)     return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => null), // Livewire re-render automatico

            Action::make('clear')
                ->label('Svuota log')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Svuotare il log?')
                ->modalDescription(fn () => 'Il contenuto di ' . basename($this->selectedFile) . ' verrà cancellato. Il file rimarrà.')
                ->modalSubmitActionLabel('Svuota')
                ->action(fn () => $this->clearLog()),

            Action::make('delete')
                ->label('Elimina file')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminare il file di log?')
                ->modalDescription(fn () => basename($this->selectedFile) . ' verrà eliminato definitivamente.')
                ->modalSubmitActionLabel('Elimina')
                ->action(fn () => $this->deleteLog()),
        ];
    }
}
