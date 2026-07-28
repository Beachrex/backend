<?php

namespace App\Filament\BrAdmin\Resources\StaticPageResource\Pages;

use App\Filament\BrAdmin\Resources\StaticPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaticPage extends EditRecord
{
    protected static string $resource = StaticPageResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
