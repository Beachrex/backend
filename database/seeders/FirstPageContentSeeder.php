<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\FirstPageContent;
use App\Models\FirstPageContentTranslation;
use App\Models\FirstPageContentItem;
use App\Models\FirstPageContentItemTranslation;
use App\Models\Media;
use App\Models\MediaType;
use App\Models\MediaCollection;

class FirstPageContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding first page content...');

        // Get media type and collection IDs
        $imageType = MediaType::where('code', 'image')->first();
        $iconType = MediaType::where('code', 'icon')->first();

        // Create sections
        $this->createBanner();
        $this->createSection1();
        $this->createSection2();
        $this->createSection3();
        $this->createSection4();
        $this->createSection5();
        $this->createSection6();
        $this->createSection7();

        $this->command->info('First page content seeded successfully!');
    }

    private function createBanner(): void
    {
        $banner = FirstPageContent::create([
            'code' => 'hero_banner',
            'section_type' => 'banner',
            'order' => 1,
            'status_id' => 1,
        ]);

        // Create translations
        $translations = [
            'en' => 'All worries are smaller on the beach!',
            'sl' => 'Vse skrbi so manjše na plaži!',
        ];

        foreach ($translations as $locale => $title) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $banner->id,
                'locale' => $locale,
                'title' => $title,
            ]);
        }

        // Create media record for background image
        $this->createMediaRecord($banner, 'image', 'img/first-page/hero-bg.jpg', 'hero-bg.jpg');
    }

    private function createSection1(): void
    {
        $section = FirstPageContent::create([
            'code' => 'browse_map',
            'section_type' => 'text_image',
            'order' => 2,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Browse the map with over 1500 beaches',
                'description' => 'Discover the most beautiful beaches around the world with an interactive map containing over 1500 stunning locations. Whether you\'re looking for sandy paradise shores, hidden coves, or popular surfing destinations, you\'ll find the perfect beach for you. Check descriptions, ratings, and photos to plan your next dream seaside adventure!',
            ],
            'sl' => [
                'title' => 'Prebrskaj zemljevid z več kot 1500 plažami',
                'description' => 'Odkrijte najlepše plaže po svetu z interaktivnim zemljevidom, ki vsebuje več kot 1500 osupljivih lokacij. Ne glede na to, ali iščete peščene rajske obale, skrite uvale ali priljubljene destinacije za surfanje, boste našli popolno plažo zase. Preverite opise, ocene in fotografije ter načrtujte svojo naslednjo sanjsko pustolovščino ob morju!',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }

        $this->createMediaRecord($section, 'image', 'img/first-page/worldmap.png', 'worldmap.png');
    }

    private function createSection2(): void
    {
        $section = FirstPageContent::create([
            'code' => 'find_beach',
            'section_type' => 'text_image',
            'order' => 3,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Find a beach to your taste',
                'description' => 'Looking for the perfect beach? Use advanced filters and quickly find the ideal location! Browse by beach type, sand type, accessibility, popularity, activities, infrastructure and more. Whether you want a quiet corner to relax or a lively beach with plenty of water sports, you\'ll easily find what suits you best.',
            ],
            'sl' => [
                'title' => 'Poišči plažo po svojem okusu',
                'description' => 'Iščeš popolno plažo? Uporabi napredne filtre in hitro najdi idealno lokacijo! Brskaj glede na tip plaže, vrsto peska, dostopnost, priljubljenost, aktivnosti, infrastrukturo in še več. Ne glede na to, ali si želiš mirnega kotička za sprostitev ali živahne plaže s številnimi vodnimi športi, boš zlahka našel tisto, kar ti najbolj ustreza.',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }
    }

    private function createSection3(): void
    {
        $section = FirstPageContent::create([
            'code' => 'beach_views',
            'section_type' => 'repeater',
            'order' => 4,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'View beaches from all angles'],
            'sl' => ['title' => 'Oglej si plaže v vseh pogledih'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }

        // Create repeater items
        $items = [
            [
                'icon' => 'img/icon/gallery-icon.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'en' => 'Gallery',
                'sl' => 'Galerija',
            ],
            [
                'icon' => 'img/icon/play-button-arrowhead.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'en' => 'Video',
                'sl' => 'Video',
            ],
            [
                'icon' => 'img/icon/360-degree.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'en' => 'Spheres',
                'sl' => 'Sfere',
            ],
            [
                'icon' => 'img/icon/video-icon.png',
                'image' => 'img/first-page/beach-gallery.jpg',
                'en' => 'Live',
                'sl' => 'V živo',
            ],
        ];

        foreach ($items as $index => $itemData) {
            $item = FirstPageContentItem::create([
                'first_page_content_id' => $section->id,
                'order' => $index + 1,
                'status_id' => 1,
            ]);

            // Create translations
            FirstPageContentItemTranslation::create([
                'first_page_content_item_id' => $item->id,
                'locale' => 'en',
                'title' => $itemData['en'],
            ]);

            FirstPageContentItemTranslation::create([
                'first_page_content_item_id' => $item->id,
                'locale' => 'sl',
                'title' => $itemData['sl'],
            ]);

            // Create media records for icon and image
            $this->createMediaRecord($item, 'icon', $itemData['icon'], basename($itemData['icon']), 'icon');
            $this->createMediaRecord($item, 'image', $itemData['image'], basename($itemData['image']), 'main_image');
        }
    }

    private function createSection4(): void
    {
        $section = FirstPageContent::create([
            'code' => 'accommodation',
            'section_type' => 'text_image',
            'order' => 5,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => [
                'title' => 'Relax in comfortable beach accommodation',
                'description' => 'Experience complete relaxation in accommodation right by the sea! Choose from luxury resorts, charming bungalows or apartments with a view of the waves. Regardless of your travel style, comfort, proximity to the beach and an unforgettable experience by the sea await you.',
            ],
            'sl' => [
                'title' => 'Odpočij si v udobni plažni nastanitvi',
                'description' => 'Doživi popolno sprostitev v nastanitvi tik ob morju! Izberi med luksuznimi letovišči, prikupnimi bungalovi ali apartmaji s pogledom na valove. Ne glede na tvoj slog potovanja te čaka udobje, bližina plaže in nepozabno doživetje ob morski obali.',
            ],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
                'description' => $data['description'],
            ]);
        }
    }

    private function createSection5(): void
    {
        $section = FirstPageContent::create([
            'code' => 'coastal_destinations',
            'section_type' => 'text_only',
            'order' => 6,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'The most beautiful coastal destinations in the world'],
            'sl' => ['title' => 'Najlepše obalne destinacije sveta'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }
    }

    private function createSection6(): void
    {
        $section = FirstPageContent::create([
            'code' => 'featured_sections',
            'section_type' => 'repeater',
            'order' => 7,
            'status_id' => 1,
        ]);

        // Create repeater items
        $items = [
            [
                'image' => 'img/first-page/best.jpg',
                'en' => '100 most beautiful beaches in the world',
                'sl' => '100 najlepših plaž na svetu',
            ],
            [
                'image' => 'img/first-page/discover.jpg',
                'en' => 'Discover beach stories and tips',
                'sl' => 'Odkrij plažne zgodbe in nasvete',
            ],
            [
                'image' => 'img/first-page/relax.jpg',
                'en' => 'Relax in comfortable accommodation',
                'sl' => 'Odpočijte si v udobni nastanitvi',
            ],
            [
                'image' => 'img/first-page/taste.jpg',
                'en' => 'Discover a beach to your taste',
                'sl' => 'Odkrij plažo po svojem okusu',
            ],
        ];

        foreach ($items as $index => $itemData) {
            $item = FirstPageContentItem::create([
                'first_page_content_id' => $section->id,
                'order' => $index + 1,
                'status_id' => 1,
            ]);

            // Create translations
            FirstPageContentItemTranslation::create([
                'first_page_content_item_id' => $item->id,
                'locale' => 'en',
                'title' => $itemData['en'],
            ]);

            FirstPageContentItemTranslation::create([
                'first_page_content_item_id' => $item->id,
                'locale' => 'sl',
                'title' => $itemData['sl'],
            ]);

            // Create media record for image
            $this->createMediaRecord($item, 'image', $itemData['image'], basename($itemData['image']));
        }
    }

    private function createSection7(): void
    {
        $section = FirstPageContent::create([
            'code' => 'footer_text',
            'section_type' => 'text_only',
            'order' => 8,
            'status_id' => 1,
        ]);

        $translations = [
            'en' => ['title' => 'Explore, dream, discover paradise beaches!'],
            'sl' => ['title' => 'Raziskuj, sanjaj, odkrivaj rajske plaže!'],
        ];

        foreach ($translations as $locale => $data) {
            FirstPageContentTranslation::create([
                'first_page_content_id' => $section->id,
                'locale' => $locale,
                'title' => $data['title'],
            ]);
        }
    }

    /**
     * Create a media record for the given model
     */
    private function createMediaRecord($model, string $typeCode, string $filePath, string $fileName, string $collectionCode = null): void
    {
        $mediaType = MediaType::where('code', $typeCode)->first();

        if (!$mediaType) {
            $this->command->warn("Media type '{$typeCode}' not found. Skipping media creation.");
            return;
        }

        $mediaCollection = null;
        if ($collectionCode) {
            $mediaCollection = MediaCollection::where('code', $collectionCode)->first();
        }

        Media::create([
            'mediable_type' => get_class($model),
            'mediable_id' => $model->id,
            'media_type_id' => $mediaType->id,
            'media_collection_id' => $mediaCollection?->id,
            'user_id' => null,
            'file_name' => $fileName,
            'file_path' => $filePath,
            'disk' => 'spaces', // Using DigitalOcean Spaces
            'mime_type' => $this->getMimeType($fileName),
            'size' => 0, // Size unknown for external files
            'width' => null,
            'height' => null,
            'is_primary' => true,
            'order' => 0,
            'status_id' => 1,
        ]);
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}

