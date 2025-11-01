<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaCollectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collections = [
            // Beach-related collections
            [
                'code' => 'beach_gallery',
                'name' => 'Beach Gallery',
                'description' => 'Main photo gallery for beaches',
                'allowed_media_types' => json_encode(['image']),
                'max_items' => null, // unlimited
                'status_id' => 1,
            ],
            [
                'code' => 'beach_videos',
                'name' => 'Beach Videos',
                'description' => 'Video content for beaches',
                'allowed_media_types' => json_encode(['video']),
                'max_items' => 10,
                'status_id' => 1,
            ],
            [
                'code' => 'beach_360_sphere',
                'name' => 'Beach 360° Spheres',
                'description' => '360-degree panoramic images of beaches',
                'allowed_media_types' => json_encode(['sphere_360']),
                'max_items' => 5,
                'status_id' => 1,
            ],
            [
                'code' => 'parking_images',
                'name' => 'Parking Images',
                'description' => 'Photos showing parking area and directions',
                'allowed_media_types' => json_encode(['image']),
                'max_items' => 10,
                'status_id' => 1,
            ],
            
            // Region/Location collections
            [
                'code' => 'region_hero',
                'name' => 'Region Hero Image',
                'description' => 'Main hero/banner image for regions',
                'allowed_media_types' => json_encode(['banner', 'image']),
                'max_items' => 1,
                'status_id' => 1,
            ],
            [
                'code' => 'city_hero',
                'name' => 'City Hero Image',
                'description' => 'Main hero/banner image for cities',
                'allowed_media_types' => json_encode(['banner', 'image']),
                'max_items' => 1,
                'status_id' => 1,
            ],
            [
                'code' => 'country_hero',
                'name' => 'Country Hero Image',
                'description' => 'Main hero/banner image for countries',
                'allowed_media_types' => json_encode(['banner', 'image']),
                'max_items' => 1,
                'status_id' => 1,
            ],
            [
                'code' => 'continent_hero',
                'name' => 'Continent Hero Image',
                'description' => 'Main hero/banner image for continents',
                'allowed_media_types' => json_encode(['banner', 'image']),
                'max_items' => 1,
                'status_id' => 1,
            ],
            
            // Promotional/Marketing
            [
                'code' => 'promotional_banner',
                'name' => 'Promotional Banners',
                'description' => 'Marketing and promotional banner images',
                'allowed_media_types' => json_encode(['banner']),
                'max_items' => null,
                'status_id' => 1,
            ],
            
            // Icons and assets
            [
                'code' => 'feature_icons',
                'name' => 'Feature Icons',
                'description' => 'Icons for features and amenities',
                'allowed_media_types' => json_encode(['icon']),
                'max_items' => null,
                'status_id' => 1,
            ],
            
            // Documentation
            [
                'code' => 'beach_documents',
                'name' => 'Beach Documents',
                'description' => 'PDF documents, maps, and other files related to beaches',
                'allowed_media_types' => json_encode(['document']),
                'max_items' => 20,
                'status_id' => 1,
            ],
        ];

        foreach ($collections as $collection) {
            DB::table('media_collections')->updateOrInsert(
                ['code' => $collection['code']],
                array_merge($collection, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}

