<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Owner 2026-10-23: the Greek (and da, nl, pt, sq) home page showed most blocks in English. */
class FirstPageContentMissingLocalesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function missing_languages_get_translations_and_human_edits_stay(): void
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        $map = DB::table('first_page_content')->insertGetId(['code' => 'browse_map', 'section_type' => 'text_image', 'order' => 2, 'status_id' => 1]);
        $views = DB::table('first_page_content')->insertGetId(['code' => 'beach_views', 'section_type' => 'repeater', 'order' => 3, 'status_id' => 1]);
        $item = DB::table('first_page_content_items')->insertGetId(['first_page_content_id' => $views, 'order' => 1, 'status_id' => 1]);

        DB::table('first_page_content_translation')->insert([
            ['first_page_content_id' => $map, 'locale' => 'en', 'title' => 'Browse the map with over 1500 beaches', 'description' => 'English text', 'metadata' => json_encode(['button' => 'Map'])],
            ['first_page_content_id' => $map, 'locale' => 'nl', 'title' => 'Browse the map with over 1500 beaches', 'description' => 'English text', 'metadata' => null],
            ['first_page_content_id' => $map, 'locale' => 'pt', 'title' => 'Escrito por uma pessoa', 'description' => 'Texto humano', 'metadata' => null],
        ]);
        DB::table('first_page_content_item_translation')->insert([
            ['first_page_content_item_id' => $item, 'locale' => 'en', 'title' => 'Gallery', 'description' => null, 'metadata' => null],
        ]);

        $migration = require database_path('migrations/2026_10_23_100000_add_first_page_content_missing_locales.php');
        $migration->up();
        $migration->up(); // idempotent

        $section = fn ($l) => DB::table('first_page_content_translation')->where('first_page_content_id', $map)->where('locale', $l)->first();
        $this->assertSame('Εξερευνήστε τον χάρτη με πάνω από 1500 παραλίες', $section('el')->title);
        $this->assertStringStartsWith('Ανακαλύψτε', $section('el')->description);
        $this->assertSame(['button' => 'Map'], json_decode($section('da')->metadata, true));
        $this->assertSame('Verken de kaart met meer dan 1500 stranden', $section('nl')->title, 'an English copy is replaced');
        $this->assertStringStartsWith('Ontdek', $section('nl')->description);
        $this->assertSame('Escrito por uma pessoa', $section('pt')->title, 'a human translation stays');
        $this->assertSame('Texto humano', $section('pt')->description);
        $this->assertSame(1, DB::table('first_page_content_translation')->where('first_page_content_id', $map)->where('locale', 'sq')->count());

        $this->assertSame('Συλλογή', DB::table('first_page_content_item_translation')->where('first_page_content_item_id', $item)->where('locale', 'el')->value('title'));

        $this->getJson('/api/v1/first-page-content', ['Accept-Language' => 'el'])
            ->assertOk()
            ->assertJsonFragment(['title' => 'Εξερευνήστε τον χάρτη με πάνω από 1500 παραλίες']);
    }
}
