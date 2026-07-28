<?php

namespace App\Filament\BrAdmin\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\BrAdmin\Resources\NewsletterSubscriberResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsletterSubscriber extends EditRecord
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
