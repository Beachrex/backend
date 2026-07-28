<?php

namespace App\Filament\BrAdmin\Resources\NewsletterSubscriberResource\Pages;

use App\Filament\BrAdmin\Resources\NewsletterSubscriberResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    protected function getHeaderActions(): array
    {
        // No CreateAction — subscribers come in via the public API endpoint.
        return [];
    }
}
