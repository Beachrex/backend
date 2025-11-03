<?php

namespace App\Filament\BrAdmin\Resources\ArticleResource\Pages;

use App\Filament\BrAdmin\Resources\ArticleResource;
use App\Exports\ArticlesExport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListArticles extends ListRecords
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export')
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $query = $this->getFilteredTableQuery();
                    return Excel::download(new ArticlesExport($query), 'articles-' . now()->format('Y-m-d-His') . '.xlsx');
                }),
            Actions\CreateAction::make(),
        ];
    }
}
