<?php

namespace App\Filament\BrAdmin\Resources\BeachResource\Pages;

use App\Filament\BrAdmin\Resources\BeachResource;
use App\Filament\BrAdmin\Resources\BeachResource\Widgets\BeachMapWidget;
use App\Models\BeachTranslation;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class EditBeach extends EditRecord
{
    protected static string $resource = BeachResource::class;

    protected function getHeaderActions(): array
    {
        $beach = $this->getRecord();
        $translationsCount = $beach->translations()->count();
        $missingCount = 14 - $translationsCount;

        return [
            Actions\Action::make('translate')
                ->label('Translate Beach')
                ->icon('heroicon-o-language')
                ->color($translationsCount === 14 ? 'success' : 'warning')
                ->badge($translationsCount === 14 ? 'Complete' : $missingCount . ' missing')
                ->requiresConfirmation()
                ->modalHeading('Translate Beach to All Languages')
                ->modalDescription(function () use ($beach, $translationsCount, $missingCount) {
                    $beachName = $beach->translations()->where('locale', 'en')->first()?->name
                        ?? $beach->translations()->first()?->name
                        ?? 'Beach #' . $beach->id;

                    if ($translationsCount === 14) {
                        return "Beach \"{$beachName}\" already has all 14 translations. This will skip translation.";
                    }

                    return "This will translate \"{$beachName}\" to {$missingCount} missing language(s) using AI. This may take a few seconds.";
                })
                ->modalSubmitActionLabel('Translate Now')
                ->action(function () use ($beach) {
                    try {
                        // Run the translation command for this specific beach
                        Artisan::call('beaches:translate', [
                            '--beach-id' => $beach->id,
                        ]);

                        // Refresh the record to get updated translations
                        $this->refreshFormData([
                            'translations',
                        ]);

                        $newTranslationsCount = $beach->fresh()->translations()->count();
                        $translatedCount = $newTranslationsCount - $beach->translations()->count();

                        if ($translatedCount > 0) {
                            Notification::make()
                                ->success()
                                ->title('Translation Completed!')
                                ->body("Successfully translated beach to {$translatedCount} new language(s). Total: {$newTranslationsCount}/14")
                                ->send();
                        } else {
                            Notification::make()
                                ->info()
                                ->title('No Translation Needed')
                                ->body('This beach already has all translations.')
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Translation Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        $beach = $this->getRecord();
        $beachName = $beach->translations()->where('locale', 'en')->first()?->name
            ?? $beach->translations()->first()?->name
            ?? 'Beach #' . $beach->id;

        return 'Edit Beach: ' . $beachName;
    }

    protected function getFooterWidgets(): array
    {
        return [
            BeachMapWidget::class,
        ];
    }
}
