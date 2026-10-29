<?php

namespace App\Filament\BrAdmin\Resources\AiConversationResource\Pages;

use App\Filament\BrAdmin\Resources\AiConversationResource;
use Filament\Resources\Pages\ViewRecord;

/** Read-only transcript of one chat conversation (no header actions: nothing can be changed or deleted here). */
class ViewAiConversation extends ViewRecord
{
    protected static string $resource = AiConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
