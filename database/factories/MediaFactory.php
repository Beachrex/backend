<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\MediaType;
use App\Models\MediaCollection;
use App\Models\Beach;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mediaType = MediaType::where('code', 'image')->first() ?? MediaType::factory()->create(['code' => 'image']);
        
        return [
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => Beach::factory(),
            'media_type_id' => $mediaType->id,
            'media_collection_id' => null,
            'user_id' => User::factory(),
            'file_name' => fake()->word() . '.jpg',
            'file_path' => 'Beach/1/beach_gallery/' . fake()->uuid() . '.jpg',
            'disk' => 'public',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(100000, 5000000),
            'width' => 1920,
            'height' => 1080,
            'duration' => null,
            'is_primary' => false,
            'order' => 0,
            'metadata' => [
                'alt_text' => fake()->sentence(),
                'caption' => fake()->paragraph(),
            ],
            'status_id' => 1,
        ];
    }

    /**
     * Indicate that the media is primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }

    /**
     * Indicate that the media is a video.
     */
    public function video(): static
    {
        $mediaType = MediaType::where('code', 'video')->first() ?? MediaType::factory()->create(['code' => 'video']);
        
        return $this->state(fn (array $attributes) => [
            'media_type_id' => $mediaType->id,
            'file_name' => fake()->word() . '.mp4',
            'file_path' => 'Beach/1/beach_videos/' . fake()->uuid() . '.mp4',
            'mime_type' => 'video/mp4',
            'duration' => fake()->numberBetween(10, 300),
        ]);
    }

    /**
     * Indicate that the media is a 360 sphere.
     */
    public function sphere360(): static
    {
        $mediaType = MediaType::where('code', 'sphere_360')->first() ?? MediaType::factory()->create(['code' => 'sphere_360']);
        
        return $this->state(fn (array $attributes) => [
            'media_type_id' => $mediaType->id,
            'file_name' => fake()->word() . '_360.jpg',
            'file_path' => 'Beach/1/beach_360_sphere/' . fake()->uuid() . '.jpg',
        ]);
    }
}

