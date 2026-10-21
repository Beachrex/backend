<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocaleFromHeader;
use App\Models\Continent;
use App\Models\Country;
use App\Models\CulinaryOffer;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Owner report 2026-10-21: filter section titles (Accommodation, Culinary Offer, Region, City, ...)
 * were English in every language, because `filter_title` is empty in the translation tables and the
 * fallback was a hardcoded English string. Fixed in Beachrex/backend#124.
 */
class FilterGroupTitleLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** Every hardcoded filter group title in FilterController. */
    private const GROUP_TITLES = [
        'Water Body', 'User Type', 'Beach Type', 'Country', 'Region', 'Island', 'City', 'Accommodation',
        'Culinary Offer', 'Infrastructure', 'Natural Conditions', 'Activities', 'Underwater', 'Transport',
        'Accessibility', 'Area Type', 'Parking Capacity', 'Beach Inclination', 'Swimming Depth', 'Occupancy',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Status::insert([['id' => 1, 'name' => 'active'], ['id' => 2, 'name' => 'inactive']]);
        Continent::create(['code' => 'EU']);
        Country::create(['code' => 'SI', 'continent_code' => 'EU']);

        $offer = CulinaryOffer::create([
            'code' => 'beach_bar', 'status_id' => 1, 'show_filter' => true, 'filter_order' => 1, 'input_type' => 'checkbox',
        ]);
        foreach (['en' => 'Beach bar', 'ua' => 'Пляжний бар', 'sl' => 'Plažni bar', 'el' => 'Μπαρ παραλίας'] as $locale => $name) {
            DB::table('culinary_offer_translation')->insert([
                'culinary_offer_id' => $offer->id, 'locale' => $locale, 'name' => $name, 'slug' => 'beach-bar',
                'filter_title' => null,
            ]);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function group_title_is_translated_when_filter_title_is_empty(): void
    {
        $expected = ['ua' => 'Харчування', 'sl' => 'Gostinska ponudba', 'el' => 'Εστίαση', 'en' => 'Culinary Offer'];

        foreach ($expected as $locale => $title) {
            $data = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => $locale])->assertOk()->json('data');
            $this->assertSame($title, $data['culinary_offer']['title'], $locale);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function empty_string_filter_title_also_falls_back_to_the_translation(): void
    {
        DB::table('culinary_offer_translation')->where('locale', 'ua')->update(['filter_title' => '']);

        $data = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'ua'])->assertOk()->json('data');
        $this->assertSame('Харчування', $data['culinary_offer']['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function a_filter_title_set_in_the_database_still_overrides_the_translation(): void
    {
        DB::table('culinary_offer_translation')->where('locale', 'sl')->update(['filter_title' => 'Hrana in pijača']);

        $data = $this->getJson('/api/v1/beaches/filters', ['Accept-Language' => 'sl'])->assertOk()->json('data');
        $this->assertSame('Hrana in pijača', $data['culinary_offer']['title']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function every_language_file_translates_every_group_title(): void
    {
        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            $texts = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            foreach (self::GROUP_TITLES as $key) {
                $this->assertNotEmpty($texts[$key] ?? null, "{$locale}.json: {$key}");
            }
        }
    }
}
