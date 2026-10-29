<?php

namespace Tests\Feature;

use App\Services\AI\Content\BeachContentAi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Owner 2026-10-23: the Russian name of Tratica (Pašman) was "335". */
class BeachNameWithoutLettersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('status')->insertOrIgnore([['id' => 1, 'name' => 'active']]);
        DB::table('countries')->insertOrIgnore(['code' => 'HR']);
    }

    /** @param array<string, string> $names */
    private function beach(array $names): int
    {
        $id = DB::table('beach')->insertGetId(['name' => 'x', 'country_code' => 'HR', 'status_id' => 1, 'latitude' => 43.9, 'longitude' => 15.4]);
        foreach ($names as $locale => $name) {
            DB::table('beach_translation')->insert(['beach_id' => $id, 'locale' => $locale, 'name' => $name, 'slug' => 'beach-tratica-kraj']);
        }

        return $id;
    }

    private function nameOf(int $id, string $locale): ?string
    {
        return DB::table('beach_translation')->where('beach_id', $id)->where('locale', $locale)->value('name');
    }

    #[Test]
    public function the_migration_replaces_a_number_with_the_nearest_real_name(): void
    {
        $tratica = $this->beach(['en' => 'Tratica', 'ru' => '335', 'bg' => 'Тратица', 'ua' => 'Тратіца', 'de' => '12']);
        $other = $this->beach(['en' => 'Plaža 7', 'ru' => 'Пляж 7']);

        $migration = require database_path('migrations/2026_10_23_120000_fix_beach_names_without_letters.php');
        $migration->up();
        $migration->up(); // idempotent

        $this->assertSame('Тратица', $this->nameOf($tratica, 'ru'), 'Cyrillic takes its Cyrillic neighbour');
        $this->assertSame('Tratica', $this->nameOf($tratica, 'de'), 'others take English');
        $this->assertSame('Тратіца', $this->nameOf($tratica, 'ua'));
        $this->assertSame('Пляж 7', $this->nameOf($other, 'ru'), 'a name with a number and letters stays');
    }

    #[Test]
    public function the_ai_never_writes_a_name_without_letters(): void
    {
        $id = $this->beach(['en' => 'Beach Tratica']);
        $answer = json_encode(['local_name' => 'Tratica', 'ua' => 'Тратіца', 'ru' => '335', 'bg' => 'Тратица', 'el' => 'Τράτιτσα']);

        app(BeachContentAi::class)->apply("names-{$id}", $answer, dryRun: false);

        $this->assertSame('Tratica', $this->nameOf($id, 'ru'), 'a bare number falls back to the local name');
        $this->assertSame('Тратица', $this->nameOf($id, 'bg'));

        $bad = json_encode(['local_name' => '335', 'ua' => 'x', 'ru' => 'x', 'bg' => 'x', 'el' => 'x']);
        $this->assertSame('invalid', app(BeachContentAi::class)->apply("names-{$id}", $bad, dryRun: false)['status']);
    }
}
