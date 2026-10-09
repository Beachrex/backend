<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Scalability audit 2026-10-09: slug and country lookups on hot public endpoints had no index.
 */
class LookupIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_indexes_exist(): void
    {
        $expected = [
            'beach_translation' => 'beach_translation_slug_index',
            'city_translation' => 'city_translation_slug_index',
            'region_translation' => 'region_translation_slug_index',
            'beach' => 'beach_country_code_index',
        ];

        foreach ($expected as $table => $index) {
            $found = DB::selectOne('select 1 as ok from pg_indexes where tablename = ? and indexname = ?', [$table, $index]);
            $this->assertNotNull($found, "{$index} on {$table}");
        }

        $this->assertNotNull(
            DB::selectOne("select 1 as ok from pg_indexes where tablename = 'beach' and indexname = 'beach_continent_code_index'")
        );
    }
}
