<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobile report 2026-10-09: `parking` and `has_swimming_pool` in /beaches/filters had a fixed English
 * title ("Parking", "Swimming Pool") and label "Yes", and no `code`, so the website dropped the selection.
 */
class BooleanFiltersLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'SI', 'continent_code' => 'EU']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function parking_and_pool_filters_are_translated_and_have_a_code(): void
    {
        $sl = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'sl'])->assertOk()->json('data');

        $this->assertSame('Parkirišče', $sl['parking']['title']);
        $this->assertSame('Bazen', $sl['has_swimming_pool']['title']);
        foreach (['parking', 'has_swimming_pool'] as $key) {
            $this->assertSame([['code' => '1', 'value' => '1', 'label' => 'Da', 'name' => 'Da', 'icon' => null]], $sl[$key]['options']);
        }

        $de = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'de'])->assertOk()->json('data');
        $this->assertSame('Parkplatz', $de['parking']['title']);
        $this->assertSame('Ja', $de['has_swimming_pool']['options'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function every_language_file_has_the_filter_texts(): void
    {
        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            $texts = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            foreach (['Parking', 'Swimming pool', 'Yes'] as $key) {
                $this->assertNotEmpty($texts[$key] ?? null, "{$locale}.json: {$key}");
            }
        }
    }
}
