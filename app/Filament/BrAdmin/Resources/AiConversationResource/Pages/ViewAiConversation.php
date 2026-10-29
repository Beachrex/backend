<?php

namespace App\Filament\BrAdmin\Resources\AiConversationResource\Pages;

use App\Filament\BrAdmin\Resources\AiConversationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAiConversation extends ViewRecord
{
    protected static string $resource = AiConversationResource::class;

    protected function getHeaderActions(): array
    {
        // One conversation at a time, with confirmation (e.g. a visitor asks for their chat to be deleted).
        // Its messages go with it (cascade); the cost rows in ai_usage stay, without the session link.
        return [
            DeleteAction::make()->label('Delete conversation')
                ->modalDescription('The whole conversation (all messages) is deleted permanently. Cost statistics stay.'),
        ];
    }
}
