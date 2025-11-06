<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            if (DB::table('media_types')->count() > 0) {
                $this->command->info('Media types table already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        $mediaTypes = [
            [
                'code' => 'image',
                'name' => 'Image',
                'mime_types' => json_encode(['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif']),
                'max_file_size' => 10485760, // 10MB
                'status_id' => 1,
            ],
            [
                'code' => 'video',
                'name' => 'Video',
                'mime_types' => json_encode(['video/mp4', 'video/mpeg', 'video/quicktime', 'video/webm']),
                'max_file_size' => 104857600, // 100MB
                'status_id' => 1,
            ],
            [
                'code' => 'sphere_360',
                'name' => '360° Sphere',
                'mime_types' => json_encode(['image/jpeg', 'image/jpg', 'image/png']),
                'max_file_size' => 20971520, // 20MB
                'status_id' => 1,
            ],
            [
                'code' => 'icon',
                'name' => 'Icon',
                'mime_types' => json_encode(['image/svg+xml', 'image/png', 'image/webp']),
                'max_file_size' => 1048576, // 1MB
                'status_id' => 1,
            ],
            [
                'code' => 'banner',
                'name' => 'Banner',
                'mime_types' => json_encode(['image/jpeg', 'image/jpg', 'image/png', 'image/webp']),
                'max_file_size' => 5242880, // 5MB
                'status_id' => 1,
            ],
            [
                'code' => 'document',
                'name' => 'Document',
                'mime_types' => json_encode(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
                'max_file_size' => 10485760, // 10MB
                'status_id' => 1,
            ],
        ];

        foreach ($mediaTypes as $mediaType) {
            DB::table('media_types')->updateOrInsert(
                ['code' => $mediaType['code']],
                array_merge($mediaType, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}

