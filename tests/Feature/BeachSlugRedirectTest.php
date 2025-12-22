<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\BeachTranslation;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Continent;
use App\Models\ContinentTranslation;
use App\Models\Country;
use App\Models\CountryTranslation;
use App\Models\Region;
use App\Models\RegionTranslation;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BeachSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the beach_slug_redirects migration
        Artisan::call('migrate', ['--path' => 'database/migrations/2025_12_22_000000_create_beach_slug_redirects_table.php']);

        // Seed statuses
        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);

        // Seed continents
        Continent::create(['code' => 'EU']);
        ContinentTranslation::insert([
            ['continent_code' => 'EU', 'locale' => 'en', 'name' => 'Europe', 'slug' => 'europe'],
        ]);

        // Seed countries
        Country::create(['code' => 'HR', 'continent_code' => 'EU']);
        CountryTranslation::insert([
            ['country_code' => 'HR', 'locale' => 'en', 'name' => 'Croatia', 'slug' => 'croatia'],
        ]);

        // Seed region
        Region::insert([
            ['id' => 1, 'code' => 'KV', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        RegionTranslation::insert([
            ['region_id' => 1, 'locale' => 'en', 'name' => 'Kvarner', 'slug' => 'kvarner'],
        ]);

        // Seed city
        City::insert([
            ['id' => 1, 'code' => 'CRS', 'country_code' => 'HR', 'created_at' => now(), 'updated_at' => now()],
        ]);
        CityTranslation::insert([
            ['city_id' => 1, 'locale' => 'en', 'name' => 'Cres', 'slug' => 'cres'],
        ]);
    }

    public function test_beach_details_works_with_new_slug()
    {
        // Create a beach with new unique slug
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'city_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Test Beach',
            'slug' => 'test-beach-cres', // New unique slug with city
        ]);

        $response = $this->getJson('/api/v1/beach-details/test-beach-cres', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'slug' => 'test-beach-cres',
                        'name' => 'Test Beach',
                    ],
                ],
            ]);
    }

    public function test_beach_details_redirects_from_old_slug()
    {
        // Create a beach with new slug
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'city_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::create([
            'beach_id' => $beach->id,
            'locale' => 'en',
            'name' => 'Test Beach',
            'slug' => 'test-beach-cres', // New slug
        ]);

        // Create a redirect record from old slug to new slug
        DB::table('beach_slug_redirects')->insert([
            'old_slug' => 'test-beach',
            'new_slug' => 'test-beach-cres',
            'locale' => 'en',
            'beach_id' => $beach->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Request with old slug should still work
        $response = $this->getJson('/api/v1/beach-details/test-beach', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'id' => $beach->id,
                        'name' => 'Test Beach',
                        'slug' => 'test-beach-cres', // Returns new slug
                    ],
                ],
            ]);
    }

    public function test_redirect_respects_locale()
    {
        // Create a beach with Croatian translation
        $beach = Beach::create([
            'latitude' => '44.9522',
            'longitude' => '14.4606',
            'country_code' => 'HR',
            'region_id' => 1,
            'city_id' => 1,
            'status_id' => 1,
            'folder_name' => 'test-beach',
            'mobile_upload' => false,
        ]);

        BeachTranslation::insert([
            [
                'beach_id' => $beach->id,
                'locale' => 'en',
                'name' => 'Test Beach',
                'slug' => 'test-beach-cres',
            ],
            [
                'beach_id' => $beach->id,
                'locale' => 'hr',
                'name' => 'Testna Plaža',
                'slug' => 'testna-plaza-cres',
            ],
        ]);

        // Create redirect for Croatian locale
        DB::table('beach_slug_redirects')->insert([
            'old_slug' => 'testna-plaza',
            'new_slug' => 'testna-plaza-cres',
            'locale' => 'hr',
            'beach_id' => $beach->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Request with old Croatian slug
        $response = $this->getJson('/api/v1/beach-details/testna-plaza', [
            'Accept-Language' => 'hr',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'basic_info' => [
                        'name' => 'Testna Plaža',
                        'slug' => 'testna-plaza-cres',
                    ],
                ],
            ]);
    }

    public function test_returns_404_when_no_redirect_exists()
    {
        // Request with non-existent slug (no redirect)
        $response = $this->getJson('/api/v1/beach-details/nonexistent-beach', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Beach not found',
            ]);
    }
}

