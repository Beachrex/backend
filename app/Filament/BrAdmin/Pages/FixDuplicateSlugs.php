<?php

namespace App\Filament\BrAdmin\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class FixDuplicateSlugs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.br-admin.pages.fix-duplicate-slugs';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Fix Duplicate Slugs';

    protected static ?int $navigationSort = 100;

    public $duplicateCount = 0;
    public $duplicates = [];
    public $isRunning = false;
    public $output = '';

    public function mount(): void
    {
        $this->checkDuplicates();
    }

    public function checkDuplicates(): void
    {
        // Check for duplicates across all locales
        $locales = ['en', 'si', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk'];

        $this->duplicates = [];
        $totalCount = 0;

        foreach ($locales as $locale) {
            $duplicatesForLocale = DB::table('beach_translation')
                ->select('slug', DB::raw('COUNT(*) as count'))
                ->where('locale', $locale)
                ->groupBy('slug')
                ->having('count', '>', 1)
                ->get();

            if ($duplicatesForLocale->isNotEmpty()) {
                $this->duplicates[$locale] = $duplicatesForLocale->toArray();
                $totalCount += $duplicatesForLocale->count();
            }
        }

        $this->duplicateCount = $totalCount;
    }

    public function runDryRun(): void
    {
        $this->isRunning = true;
        $this->output = '';

        try {
            Artisan::call('beaches:fix-duplicate-slugs', ['--dry-run' => true]);
            $this->output = Artisan::output();

            Notification::make()
                ->title('Dry Run Complete')
                ->body('Check the output below to see what would be changed.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to run dry run: ' . $e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }

    public function runFix(): void
    {
        $this->isRunning = true;
        $this->output = '';

        try {
            Artisan::call('beaches:fix-duplicate-slugs');
            $this->output = Artisan::output();

            // Clear cache after fixing
            Artisan::call('cache:clear');

            // Refresh duplicate count
            $this->checkDuplicates();

            Notification::make()
                ->title('Slugs Fixed Successfully!')
                ->body('All duplicate slugs have been fixed across all languages. Cache has been cleared.')
                ->success()
                ->duration(5000)
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Failed to fix slugs: ' . $e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isRunning = false;
        }
    }
}

