<?php

namespace App\Filament\BrAdmin\Resources\AiConversationResource\Pages;

use App\Filament\BrAdmin\Resources\AiConversationResource;
use App\Filament\BrAdmin\Resources\AiConversationResource\Widgets\AiConversationStats;
use Filament\Resources\Pages\ListRecords;

class ListAiConversations extends ListRecords
{
    protected static string $resource = AiConversationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AiConversationStats::class];
    }
}
