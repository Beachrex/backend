<?php

namespace Database\Seeders;

use App\Models\StaticPage;
use App\Models\StaticPageTranslation;
use Illuminate\Database\Seeder;

class StaticPageSeeder extends Seeder
{
    /**
     * Seed the static_pages and static_page_translation tables with the
     * default footer pages (English translations only — other locales are
     * added via the Filament admin TranslationsRelationManager).
     */
    public function run(): void
    {
        if (StaticPage::count() > 0) {
            $this->command->info('StaticPage table already seeded. Skipping.');
            return;
        }

        $pages = [
            [
                'code' => 'about-us',
                'sort_order' => 1,
                'is_in_footer' => true,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'About Us',
                        'slug' => 'about-us',
                        'content' => '<p>Beachrex is the world\'s largest beach discovery platform...</p>',
                        'meta_title' => 'About Beachrex',
                        'meta_description' => 'Learn about Beachrex, the beach discovery platform.',
                    ],
                ],
            ],
            [
                'code' => 'privacy-policy',
                'sort_order' => 2,
                'is_in_footer' => true,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Privacy Policy',
                        'slug' => 'privacy-policy',
                        'content' => '<p>Placeholder privacy policy content. Edit in admin.</p>',
                        'meta_title' => 'Privacy Policy | Beachrex',
                        'meta_description' => 'How Beachrex handles your data and privacy.',
                    ],
                ],
            ],
            [
                'code' => 'terms-of-use',
                'sort_order' => 3,
                'is_in_footer' => true,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Terms of Use',
                        'slug' => 'terms-of-use',
                        'content' => '<p>Placeholder terms of use. Edit in admin.</p>',
                        'meta_title' => 'Terms of Use | Beachrex',
                        'meta_description' => 'Terms governing use of Beachrex.',
                    ],
                ],
            ],
            [
                'code' => 'contribution-rules',
                'sort_order' => 4,
                'is_in_footer' => true,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Contribution Rules',
                        'slug' => 'contribution-rules',
                        'content' => '<p>Placeholder contribution rules. Edit in admin.</p>',
                        'meta_title' => 'Contribution Rules | Beachrex',
                        'meta_description' => 'Rules for contributing beach content.',
                    ],
                ],
            ],
            [
                'code' => 'contact',
                'sort_order' => 5,
                'is_in_footer' => true,
                'status_id' => 1,
                'translations' => [
                    'en' => [
                        'title' => 'Contact',
                        'slug' => 'contact',
                        'content' => '<p>Placeholder contact content. Edit in admin.</p>',
                        'meta_title' => 'Contact Beachrex',
                        'meta_description' => 'Get in touch with the Beachrex team.',
                    ],
                ],
            ],
        ];

        foreach ($pages as $pageData) {
            $translations = $pageData['translations'];
            unset($pageData['translations']);

            $page = StaticPage::create($pageData);

            foreach ($translations as $locale => $tr) {
                StaticPageTranslation::create([
                    'static_page_id' => $page->id,
                    'locale' => $locale,
                    'title' => $tr['title'],
                    'slug' => $tr['slug'],
                    'content' => $tr['content'],
                    'meta_title' => $tr['meta_title'] ?? null,
                    'meta_description' => $tr['meta_description'] ?? null,
                ]);
            }
        }

        $this->command->info('Static pages seeded: ' . StaticPage::count() . ' pages.');
    }
}
