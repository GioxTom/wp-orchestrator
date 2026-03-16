<?php

namespace App\Filament\Resources\IspConfigClientResource\Pages;

use App\Filament\Resources\IspConfigClientResource;
use App\Jobs\Sync\SyncIspConfigDataJob;
use App\Models\Server;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListIspConfigClients extends ListRecords
{
    protected static string $resource = IspConfigClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('sync_all')
                ->label('Sincronizza tutti i server')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function () {
                    Server::where('status', 'active')->each(
                        fn (Server $s) => dispatch(new SyncIspConfigDataJob($s))
                    );
                    Notification::make()
                        ->title('Sincronizzazione avviata')
                        ->body('I clienti ISPConfig verranno aggiornati a breve.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
