<?php

namespace App\Filament\BrAdmin\Pages;

use App\Models\SiteSetting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * TASK-009: per-environment site switches. Guide (Slovenian): docs/guides/admin/nastavitve-strani.md.
 */
class SiteSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string $view = 'filament.br-admin.pages.site-settings';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            SiteSetting::AI_ASSISTANT_ENABLED => SiteSetting::aiAssistantEnabled(),
            SiteSetting::AI_TRIP_PLANNER_ENABLED => SiteSetting::aiTripPlannerEnabled(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI beach guide')
                    ->description('Two independent switches. Each applies to this environment only (dev, uat and prod each have their own).')
                    ->schema([
                        Toggle::make(SiteSetting::AI_ASSISTANT_ENABLED)
                            ->label('AI chat')
                            ->helperText('Off: the AI beach chat (chat page and the chat button on every page) disappears from the website, and the API refuses chat questions (website and mobile app). The trip planner is not affected.'),
                        Toggle::make(SiteSetting::AI_TRIP_PLANNER_ENABLED)
                            ->label('AI trip planner (paid)')
                            ->helperText('Off: the AI trip planner disappears from the website menu and links, its pages and the payment pages answer 404, and the API refuses trip plans, credit prices and buying credits (website and mobile app). Nothing is deleted: plans and credits already bought come back when it is switched on again. The chat is not affected.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $chat = (bool) ($state[SiteSetting::AI_ASSISTANT_ENABLED] ?? true);
        $planner = (bool) ($state[SiteSetting::AI_TRIP_PLANNER_ENABLED] ?? true);

        SiteSetting::set(SiteSetting::AI_ASSISTANT_ENABLED, $chat);
        SiteSetting::set(SiteSetting::AI_TRIP_PLANNER_ENABLED, $planner);

        Notification::make()
            ->title('AI chat is '.($chat ? 'ON' : 'OFF').', AI trip planner is '.($planner ? 'ON' : 'OFF'))
            ->body('The website shows the change within about 5 minutes; the API immediately.')
            ->success()
            ->send();
    }
}
