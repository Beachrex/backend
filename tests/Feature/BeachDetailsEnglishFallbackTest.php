<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\BeachType;
use App\Models\BeachTypeTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression (owner 2026-10-12, Greek beach page): attributes without a Greek translation showed the raw
 * code ("small_stones"). The beach page now falls back to the English name, like the list resources.
 */
class BeachDetailsEnglishFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_translation_falls_back_to_english_not_the_code(): void
    {
        $this->seed([\Database\Seeders\StatusSeeder::class]);
        $type = BeachType::create(['code' => 'small_stones', 'status_id' => 1]);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'en', 'name' => 'Small Stones', 'slug' => 'small-stones']);
        $beach = Beach::factory()->create(['status_id' => 1, 'beach_type_id' => $type->id]);
        BeachTranslation::create(['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Mesečev zaliv', 'slug' => 'beach-mesecev-zaliv']);

        $this->getJson("/api/v1/beach-details/{$beach->id}", ['Accept-Language' => 'el'])
            ->assertOk()
            ->assertJsonPath('data.characteristics.beach_type.name', 'Small Stones');
    }

    public function test_own_translation_still_wins(): void
    {
        $this->seed([\Database\Seeders\StatusSeeder::class]);
        $type = BeachType::create(['code' => 'small_stones', 'status_id' => 1]);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'en', 'name' => 'Small Stones', 'slug' => 'small-stones']);
        BeachTypeTranslation::create(['beach_type_id' => $type->id, 'locale' => 'el', 'name' => 'Μικρά βότσαλα', 'slug' => 'small-stones']);
        $beach = Beach::factory()->create(['status_id' => 1, 'beach_type_id' => $type->id]);
        BeachTranslation::create(['beach_id' => $beach->id, 'locale' => 'en', 'name' => 'Mesečev zaliv', 'slug' => 'beach-mesecev-zaliv']);

        $this->getJson("/api/v1/beach-details/{$beach->id}", ['Accept-Language' => 'el'])
            ->assertOk()
            ->assertJsonPath('data.characteristics.beach_type.name', 'Μικρά βότσαλα');
    }
}
