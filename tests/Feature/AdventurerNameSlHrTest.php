<?php

namespace Tests\Feature;

use Database\Seeders\UserTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Owner, 2026-10-29: "Adventurer" is "Pustolovec" in Slovenian and "Pustolovac" in Croatian, not "Pustolov". */
class AdventurerNameSlHrTest extends TestCase
{
    use RefreshDatabase;

    private function adventurerTranslation(string $locale): object
    {
        $id = DB::table('user_type')->where('code', 'adventurer')->value('id');

        return DB::table('user_type_translation')->where('user_type_id', $id)->where('locale', $locale)->first();
    }

    #[Test]
    public function the_migration_fixes_the_old_names_and_slugs_and_keeps_edited_values(): void
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        $adventurer = DB::table('user_type')->insertGetId(['code' => 'adventurer', 'status_id' => 1]);
        $family = DB::table('user_type')->insertGetId(['code' => 'family', 'status_id' => 1]);
        DB::table('user_type_translation')->insert([
            ['user_type_id' => $adventurer, 'locale' => 'sl', 'name' => 'Pustolov', 'slug' => 'pustolov'],
            ['user_type_id' => $adventurer, 'locale' => 'hr', 'name' => 'Pustolov', 'slug' => 'pustolov'],
            ['user_type_id' => $adventurer, 'locale' => 'en', 'name' => 'Adventurer', 'slug' => 'adventurer'],
            // a row of another user type with the same text is not touched
            ['user_type_id' => $family, 'locale' => 'sl', 'name' => 'Pustolov', 'slug' => 'pustolov'],
        ]);

        $migration = require database_path('migrations/2026_10_29_120000_fix_adventurer_name_sl_hr.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame(['Pustolovec', 'pustolovec'], [$this->adventurerTranslation('sl')->name, $this->adventurerTranslation('sl')->slug]);
        $this->assertSame(['Pustolovac', 'pustolovac'], [$this->adventurerTranslation('hr')->name, $this->adventurerTranslation('hr')->slug]);
        $this->assertSame('Adventurer', $this->adventurerTranslation('en')->name);
        $this->assertSame('Pustolov', DB::table('user_type_translation')->where('user_type_id', $family)->where('locale', 'sl')->value('name'));

        // A value a person edited in the admin is not overwritten.
        DB::table('user_type_translation')->where('user_type_id', $adventurer)->where('locale', 'hr')
            ->update(['name' => 'Pustolov', 'slug' => 'avanturist']);
        DB::table('user_type_translation')->where('user_type_id', $adventurer)->where('locale', 'sl')
            ->update(['name' => 'Avanturist']);
        $migration->up();
        $this->assertSame(['Pustolovac', 'avanturist'], [$this->adventurerTranslation('hr')->name, $this->adventurerTranslation('hr')->slug]);
        $this->assertSame('Avanturist', $this->adventurerTranslation('sl')->name);
    }

    #[Test]
    public function a_fresh_seed_has_the_correct_names(): void
    {
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        $this->seed(UserTypeSeeder::class);

        $this->assertSame(['Pustolovec', 'pustolovec'], [$this->adventurerTranslation('sl')->name, $this->adventurerTranslation('sl')->slug]);
        $this->assertSame(['Pustolovac', 'pustolovac'], [$this->adventurerTranslation('hr')->name, $this->adventurerTranslation('hr')->slug]);
    }
}
