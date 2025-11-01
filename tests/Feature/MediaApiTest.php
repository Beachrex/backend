<?php

namespace Tests\Feature;

use App\Models\Beach;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\MediaCollection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Beach $beach;
    protected MediaType $imageType;
    protected MediaCollection $beachGallery;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed necessary data
        $this->seed([
            \Database\Seeders\StatusSeeder::class,
            \Database\Seeders\MediaTypeSeeder::class,
            \Database\Seeders\MediaCollectionSeeder::class,
        ]);

        // Create test user
        $this->user = User::factory()->create();

        // Create test beach
        $this->beach = Beach::factory()->create();

        // Get media type and collection
        $this->imageType = MediaType::where('code', 'image')->first();
        $this->beachGallery = MediaCollection::where('code', 'beach_gallery')->first();

        // Fake storage (use default disk)
        Storage::fake(config('filesystems.default'));
    }

    /** @test */
    public function can_upload_media()
    {
        $file = UploadedFile::fake()->image('beach.jpg', 1920, 1080)->size(2048);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
                'mediable_type' => 'Beach',
                'mediable_id' => $this->beach->id,
                'media_type_code' => 'image',
                'collection_code' => 'beach_gallery',
                'is_primary' => true,
                'alt_text' => 'Beautiful beach',
                'caption' => 'Main beach view',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Media uploaded successfully',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'media_type',
                    'media_collection',
                    'file_name',
                    'url',
                    'mime_type',
                    'size',
                    'is_primary',
                    'order',
                    'alt_text',
                    'caption',
                ],
                'message',
            ]);

        // Verify database
        $this->assertDatabaseHas('media', [
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'is_primary' => true,
        ]);

        // Verify file was stored
        $media = Media::first();
        Storage::disk($media->disk)->assertExists($media->file_path);
    }

    /** @test */
    public function cannot_upload_media_without_authentication()
    {
        $file = UploadedFile::fake()->image('beach.jpg');

        $response = $this->postJson('/api/v1/media/upload', [
            'file' => $file,
            'mediable_type' => 'Beach',
            'mediable_id' => $this->beach->id,
            'media_type_code' => 'image',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function validates_required_fields_on_upload()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/media/upload', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'mediable_type', 'mediable_id', 'media_type_code']);
    }

    /** @test */
    public function rejects_invalid_file_type()
    {
        // Try to upload a video as an image type
        $file = UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
                'mediable_type' => 'Beach',
                'mediable_id' => $this->beach->id,
                'media_type_code' => 'image',
                'collection_code' => 'beach_gallery',
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }

    /** @test */
    public function rejects_file_exceeding_size_limit()
    {
        // Create a file larger than 10MB (image limit)
        $file = UploadedFile::fake()->image('huge.jpg')->size(11 * 1024); // 11MB

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/media/upload', [
                'file' => $file,
                'mediable_type' => 'Beach',
                'mediable_id' => $this->beach->id,
                'media_type_code' => 'image',
            ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function can_get_media_by_id()
    {
        $media = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
            'media_collection_id' => $this->beachGallery->id,
        ]);

        $response = $this->getJson("/api/v1/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $media->id,
                ],
            ]);
    }

    /** @test */
    public function returns_404_for_nonexistent_media()
    {
        $response = $this->getJson('/api/v1/media/999999');

        $response->assertStatus(404);
    }

    /** @test */
    public function can_update_media_metadata()
    {
        $media = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/media/{$media->id}", [
                'alt_text' => 'Updated alt text',
                'caption' => 'Updated caption',
                'order' => 5,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media updated successfully',
            ]);

        $media->refresh();
        $this->assertEquals('Updated alt text', $media->alt_text);
        $this->assertEquals('Updated caption', $media->caption);
        $this->assertEquals(5, $media->order);
    }

    /** @test */
    public function can_set_media_as_primary()
    {
        // Create two media items
        $media1 = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
            'media_collection_id' => $this->beachGallery->id,
            'is_primary' => true,
        ]);

        $media2 = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
            'media_collection_id' => $this->beachGallery->id,
            'is_primary' => false,
        ]);

        // Set media2 as primary
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/media/{$media2->id}", [
                'is_primary' => true,
            ]);

        $response->assertStatus(200);

        // Verify only media2 is primary now
        $media1->refresh();
        $media2->refresh();
        $this->assertFalse($media1->is_primary);
        $this->assertTrue($media2->is_primary);
    }

    /** @test */
    public function can_delete_media()
    {
        $media = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/media/{$media->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media deleted successfully',
            ]);

        $this->assertSoftDeleted('media', ['id' => $media->id]);
    }

    /** @test */
    public function can_reorder_media()
    {
        $media1 = Media::factory()->create(['order' => 0]);
        $media2 = Media::factory()->create(['order' => 1]);
        $media3 = Media::factory()->create(['order' => 2]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/media/reorder', [
                'media_ids' => [$media3->id, $media1->id, $media2->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Media reordered successfully',
            ]);

        // Verify new order
        $this->assertEquals(0, $media3->fresh()->order);
        $this->assertEquals(1, $media1->fresh()->order);
        $this->assertEquals(2, $media2->fresh()->order);
    }

    /** @test */
    public function beach_has_media_relationship()
    {
        $media = Media::factory()->create([
            'mediable_type' => 'App\Models\Beach',
            'mediable_id' => $this->beach->id,
            'media_type_id' => $this->imageType->id,
            'media_collection_id' => $this->beachGallery->id,
        ]);

        $this->assertTrue($this->beach->hasMedia());
        $this->assertEquals(1, $this->beach->media_count);
        $this->assertEquals($media->id, $this->beach->media->first()->id);
    }
}

