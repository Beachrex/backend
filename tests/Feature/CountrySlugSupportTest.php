<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountrySlugSupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed statuses
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);

        // Seed continent
        Continent::insert([
            ['code' => 'EU'],
        ]);

        // Seed continent translations
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
            ['continent_code' => 'EU', 'locale' => 'de', 'name' => 'Europa', 'slug' => 'europa'],
        ]);

        // Seed single country (Italy)
        Country::insert([
            ['code' => 'IT', 'iso3' => 'ITA', 'continent_code' => 'EU', 'latitude' => 41.8719, 'longitude' => 12.5674],
        ]);

        // Seed translations for Italy
        CountryTranslation::insert([
            ['country_code' => 'IT', 'locale' => 'en', 'name' => 'Italy', 'full_name' => 'Italian Republic', 'slug' => 'italy'],
            ['country_code' => 'IT', 'locale' => 'de', 'name' => 'Italien', 'full_name' => 'Italienische Republik', 'slug' => 'italien'],
        ]);

        // At least one active beach so country is considered to have beaches where needed
        Beach::create([
            'user_id' => null,
            'name' => 'Test Beach IT',
            'latitude' => 41.8,
            'longitude' => 12.5,
            'status_id' => 1,
            'continent_code' => 'EU',
            'country_code' => 'IT',
        ]);
    }

    public function test_show_endpoint_accepts_slug_in_default_locale(): void
    {
        $response = $this->getJson('/api/v1/countries/italy');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'IT')
            ->assertJsonPath('data.slug', 'italy');
    }

    public function test_show_endpoint_accepts_slug_with_accept_language_header(): void
    {
        // Request German locale, slug remains the same but translation switches
        $response = $this->getJson('/api/v1/countries/italy', [
            'Accept-Language' => 'de',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.code', 'IT')
            ->assertJsonPath('data.slug', 'italien')
            ->assertJsonPath('data.name', 'Italien');
    }

    public function test_show_endpoint_still_accepts_country_code_case_insensitive(): void
    {
        $responseLower = $this->getJson('/api/v1/countries/it');
        $responseUpper = $this->getJson('/api/v1/countries/IT');

        $responseLower->assertStatus(200)->assertJsonPath('data.code', 'IT');
        $responseUpper->assertStatus(200)->assertJsonPath('data.code', 'IT');
    }

    public function test_weather_endpoint_accepts_slug(): void
    {
        $response = $this->getJson('/api/v1/countries/italy/weather');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.country_slug', 'italy');

        // Latitude/longitude formatting may vary (e.g. 41.8719 vs 41.8719000),
        // so just assert presence of the keys instead of exact string match.
        $json = $response->json('data.coordinates');
        $this->assertArrayHasKey('latitude', $json);
        $this->assertArrayHasKey('longitude', $json);
    }

    public function test_weather_endpoint_accepts_country_code(): void
    {
        $response = $this->getJson('/api/v1/countries/IT/weather');

        $response
            ->assertStatus(200)
            ->assertJsonPath('data.country_slug', 'italy');
    }

    public function test_show_endpoint_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/countries/unknown-slug');

        $response
            ->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Country not found');
    }
}

