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
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('AI beach guide')
                    ->description('Applies to this environment only (dev, uat and prod each have their own switch).')
                    ->schema([
                        Toggle::make(SiteSetting::AI_ASSISTANT_ENABLED)
                            ->label('AI guide on')
                            ->helperText('Off: the AI guide disappears from the website menu, its pages answer 404, and the API refuses chat, trip plans and buying credits (website and mobile app). Visible within about 5 minutes.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $enabled = (bool) ($state[SiteSetting::AI_ASSISTANT_ENABLED] ?? true);

        SiteSetting::set(SiteSetting::AI_ASSISTANT_ENABLED, $enabled);

        Notification::make()
            ->title($enabled ? 'AI guide is ON' : 'AI guide is OFF')
            ->body('The website shows the change within about 5 minutes; the API immediately.')
            ->success()
            ->send();
    }
}
