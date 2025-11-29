<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeographicEntityMainImageSeeder extends Seeder
{
    /**
     * Run the database seeder.
     *
     * Populates main_image for continents, countries, regions, islands, and cities
     * by selecting the image from the most viewed beach in each geographic entity.
     * Only updates entities that have beaches with status_id = 1.
     */
    public function run(): void
    {
        $this->command->info('Starting to populate geographic entity main images from most viewed beaches...');

        // Populate city main images
        $this->populateCityMainImages();

        // Populate island main images
        $this->populateIslandMainImages();

        // Populate region main images
        $this->populateRegionMainImages();

        // Populate country main images
        $this->populateCountryMainImages();

        // Populate continent main images
        $this->populateContinentMainImages();

        $this->command->info('✅ Geographic entity main images populated successfully!');
    }

    /**
     * Populate main_image for cities from most viewed beach in each city
     */
    private function populateCityMainImages(): void
    {
        $this->command->info('Populating city main images...');

        // Get the most viewed beach for each city
        // First try to get primary image (is_primary = 1), if not available use first image (is_primary = 0)
        $results = DB::select("
            WITH beach_media AS (
                SELECT
                    b.city_id,
                    b.id as beach_id,
                    b.beach_views,
                    m.file_path,
                    m.is_primary,
                    m.order,
                    m.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY b.id
                        ORDER BY m.is_primary DESC, m.order ASC, m.created_at ASC
                    ) as media_rn
                FROM beach b
                INNER JOIN media m ON m.mediable_id = b.id
                    AND m.mediable_type = 'App\\Models\\Beach'
                    AND m.status_id = 1
                    AND m.media_type_id = 1
                    AND m.media_collection_id = 1
                WHERE b.status_id = 1
                    AND b.city_id IS NOT NULL
                    AND b.folder_name IS NOT NULL
                    AND m.file_path IS NOT NULL
            ),
            ranked_beaches AS (
                SELECT
                    city_id,
                    beach_id,
                    file_path,
                    beach_views,
                    ROW_NUMBER() OVER (
                        PARTITION BY city_id
                        ORDER BY beach_views DESC, beach_id DESC
                    ) as beach_rn
                FROM beach_media
                WHERE media_rn = 1
            )
            SELECT
                city_id,
                file_path as main_image
            FROM ranked_beaches
            WHERE beach_rn = 1
        ");

        $updated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            $affected = DB::table('city')
                ->where('id', $result->city_id)
                ->update(['main_image' => $result->main_image]);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("  ✓ Cities: {$updated} updated, {$skipped} skipped (no change)");
    }

    /**
     * Populate main_image for islands from most viewed beach on each island
     */
    private function populateIslandMainImages(): void
    {
        $this->command->info('Populating island main images...');

        // Get the most viewed beach for each island
        // First try to get primary image (is_primary = 1), if not available use first image (is_primary = 0)
        $results = DB::select("
            WITH beach_media AS (
                SELECT
                    b.island_id,
                    b.id as beach_id,
                    b.beach_views,
                    m.file_path,
                    m.is_primary,
                    m.order,
                    m.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY b.id
                        ORDER BY m.is_primary DESC, m.order ASC, m.created_at ASC
                    ) as media_rn
                FROM beach b
                INNER JOIN media m ON m.mediable_id = b.id
                    AND m.mediable_type = 'App\\Models\\Beach'
                    AND m.status_id = 1
                    AND m.media_type_id = 1
                    AND m.media_collection_id = 1
                WHERE b.status_id = 1
                    AND b.island_id IS NOT NULL
                    AND b.folder_name IS NOT NULL
                    AND m.file_path IS NOT NULL
            ),
            ranked_beaches AS (
                SELECT
                    island_id,
                    beach_id,
                    file_path,
                    beach_views,
                    ROW_NUMBER() OVER (
                        PARTITION BY island_id
                        ORDER BY beach_views DESC, beach_id DESC
                    ) as beach_rn
                FROM beach_media
                WHERE media_rn = 1
            )
            SELECT
                island_id,
                file_path as main_image
            FROM ranked_beaches
            WHERE beach_rn = 1
        ");

        $updated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            $affected = DB::table('island')
                ->where('id', $result->island_id)
                ->update(['main_image' => $result->main_image]);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("  ✓ Islands: {$updated} updated, {$skipped} skipped (no change)");
    }

    /**
     * Populate main_image for regions from most viewed beach in each region
     */
    private function populateRegionMainImages(): void
    {
        $this->command->info('Populating region main images...');

        // Get the most viewed beach for each region
        // First try to get primary image (is_primary = 1), if not available use first image (is_primary = 0)
        $results = DB::select("
            WITH beach_media AS (
                SELECT
                    b.region_id,
                    b.id as beach_id,
                    b.beach_views,
                    m.file_path,
                    m.is_primary,
                    m.order,
                    m.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY b.id
                        ORDER BY m.is_primary DESC, m.order ASC, m.created_at ASC
                    ) as media_rn
                FROM beach b
                INNER JOIN media m ON m.mediable_id = b.id
                    AND m.mediable_type = 'App\\Models\\Beach'
                    AND m.status_id = 1
                    AND m.media_type_id = 1
                    AND m.media_collection_id = 1
                WHERE b.status_id = 1
                    AND b.region_id IS NOT NULL
                    AND b.folder_name IS NOT NULL
                    AND m.file_path IS NOT NULL
            ),
            ranked_beaches AS (
                SELECT
                    region_id,
                    beach_id,
                    file_path,
                    beach_views,
                    ROW_NUMBER() OVER (
                        PARTITION BY region_id
                        ORDER BY beach_views DESC, beach_id DESC
                    ) as beach_rn
                FROM beach_media
                WHERE media_rn = 1
            )
            SELECT
                region_id,
                file_path as main_image
            FROM ranked_beaches
            WHERE beach_rn = 1
        ");

        $updated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            $affected = DB::table('region')
                ->where('id', $result->region_id)
                ->update(['main_image' => $result->main_image]);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("  ✓ Regions: {$updated} updated, {$skipped} skipped (no change)");
    }

    /**
     * Populate main_image for countries from most viewed beach in each country
     */
    private function populateCountryMainImages(): void
    {
        $this->command->info('Populating country main images...');

        // Get the most viewed beach for each country
        // First try to get primary image (is_primary = 1), if not available use first image (is_primary = 0)
        $results = DB::select("
            WITH beach_media AS (
                SELECT
                    b.country_code,
                    b.id as beach_id,
                    b.beach_views,
                    m.file_path,
                    m.is_primary,
                    m.order,
                    m.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY b.id
                        ORDER BY m.is_primary DESC, m.order ASC, m.created_at ASC
                    ) as media_rn
                FROM beach b
                INNER JOIN media m ON m.mediable_id = b.id
                    AND m.mediable_type = 'App\\Models\\Beach'
                    AND m.status_id = 1
                    AND m.media_type_id = 1
                    AND m.media_collection_id = 1
                WHERE b.status_id = 1
                    AND b.country_code IS NOT NULL
                    AND b.folder_name IS NOT NULL
                    AND m.file_path IS NOT NULL
            ),
            ranked_beaches AS (
                SELECT
                    country_code,
                    beach_id,
                    file_path,
                    beach_views,
                    ROW_NUMBER() OVER (
                        PARTITION BY country_code
                        ORDER BY beach_views DESC, beach_id DESC
                    ) as beach_rn
                FROM beach_media
                WHERE media_rn = 1
            )
            SELECT
                country_code,
                file_path as main_image
            FROM ranked_beaches
            WHERE beach_rn = 1
        ");

        $updated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            // Countries use image_path field instead of main_image
            $affected = DB::table('countries')
                ->where('code', $result->country_code)
                ->update(['image_path' => $result->main_image]);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("  ✓ Countries: {$updated} updated, {$skipped} skipped (no change)");
    }

    /**
     * Populate main_image for continents from most viewed beach in each continent
     */
    private function populateContinentMainImages(): void
    {
        $this->command->info('Populating continent main images...');

        // Get the most viewed beach for each continent
        // First try to get primary image (is_primary = 1), if not available use first image (is_primary = 0)
        $results = DB::select("
            WITH beach_media AS (
                SELECT
                    b.continent_code,
                    b.id as beach_id,
                    b.beach_views,
                    m.file_path,
                    m.is_primary,
                    m.order,
                    m.created_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY b.id
                        ORDER BY m.is_primary DESC, m.order ASC, m.created_at ASC
                    ) as media_rn
                FROM beach b
                INNER JOIN media m ON m.mediable_id = b.id
                    AND m.mediable_type = 'App\\Models\\Beach'
                    AND m.status_id = 1
                    AND m.media_type_id = 1
                    AND m.media_collection_id = 1
                WHERE b.status_id = 1
                    AND b.continent_code IS NOT NULL
                    AND b.folder_name IS NOT NULL
                    AND m.file_path IS NOT NULL
            ),
            ranked_beaches AS (
                SELECT
                    continent_code,
                    beach_id,
                    file_path,
                    beach_views,
                    ROW_NUMBER() OVER (
                        PARTITION BY continent_code
                        ORDER BY beach_views DESC, beach_id DESC
                    ) as beach_rn
                FROM beach_media
                WHERE media_rn = 1
            )
            SELECT
                continent_code,
                file_path as main_image
            FROM ranked_beaches
            WHERE beach_rn = 1
        ");

        $updated = 0;
        $skipped = 0;

        foreach ($results as $result) {
            $affected = DB::table('continents')
                ->where('code', $result->continent_code)
                ->update(['main_image' => $result->main_image]);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped++;
            }
        }

        $this->command->info("  ✓ Continents: {$updated} updated, {$skipped} skipped (no change)");
    }
}

