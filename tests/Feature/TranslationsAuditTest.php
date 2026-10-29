<?php

namespace Tests\Feature;

use App\Models\BeachType;
use App\Models\BeachTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Owner 2026-10-12: Greek pages showed "small_stones"; Albanian showed English country names. */
class TranslationsAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_missing_and_english_copies_per_locale(): void
    {
        $this->seed([\Database\Seeders\StatusSeeder::class]);
        $type = BeachType::create(['code' => 'small_stones', 'status_id' => 1]);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'en', 'name' => 'Small Stones', 'slug' => 'small-stones']);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'sq', 'name' => 'Small Stones', 'slug' => 'small-stones']);

        Artisan::call('translations:audit', ['--table' => ['beach_type_translation'], '--examples' => 3]);
        $out = Artisan::output();

        $this->assertMatchesRegularExpression('/beach_type_translation\s*\|\s*el\s*\|\s*1\s*\|\s*1\s*\|\s*0/', $out);
        $this->assertMatchesRegularExpression('/beach_type_translation\s*\|\s*sq\s*\|\s*1\s*\|\s*0\s*\|\s*1/', $out);
        $this->assertStringContainsString('beach_type_translation sq: Small Stones', $out);
    }

    public function test_runs_over_every_translation_table(): void
    {
        $this->assertSame(0, Artisan::call('translations:audit'));
        $this->assertStringContainsString('Same as en', Artisan::output());
    }
}
