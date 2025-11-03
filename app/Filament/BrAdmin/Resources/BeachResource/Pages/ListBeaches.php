<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Pages;

use App\Filament\BrAdmin\Resources\BeachResource;
use App\Exports\BeachesExport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListBeaches extends ListRecords
{
    protected static string $resource = BeachResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();
                    return Excel::download(new BeachesExport($query), 'beaches-' . now()->format('Y-m-d-His') . '.xlsx');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
