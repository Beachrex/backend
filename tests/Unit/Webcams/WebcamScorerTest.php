<?php

namespace Tests\Unit\Webcams;

use App\Services\Webcams\NameMatcher;
use App\Services\Webcams\WebcamScorer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** TASK-006: name normalisation, every scoring signal, thresholds and the runner-up margin. */
class WebcamScorerTest extends TestCase
{
    private function score(array $names, ?string $title, array $cats, int $m, array $towns = [], string $profile = 'beach'): array
    {
        return (new WebcamScorer())->score($names, $towns, $profile, $title, $cats, $m);
    }

    #[Test]
    public function normalises_diacritics_case_entities_and_stopwords(): void
    {
        $n = new NameMatcher();
        $stop = config('webcams.profiles.beach.stopwords');
        $this->assertSame(['zlatni', 'rat'], $n->tokens('Plaža ZLATNI Rat', $stop));
        $this->assertSame(['moscenicka', 'draga'], $n->tokens('Mošćenička Draga beach', $stop));
        $this->assertSame(['primosten'], $n->tokens('Primo&scaron;ten', $stop));
        $this->assertSame(1.0, $n->ratio('Beach Nikolina', 'Baska Voda › North-east: Plaža Nikolina - Brački kanal', $stop));
    }

    #[Test]
    public function fuzzy_ratio_boundary_is_085(): void
    {
        // "nikolina" vs "nikolino": 1 edit / 8 chars = 0.875 → match; "nikolxyz": 3/8 → 0.625 → no match.
        $hit = $this->score(['Nikolina'], 'Nikolino', [], 5000);
        $miss = $this->score(['Nikolina'], 'Nikolxyz', [], 5000);
        $this->assertSame(30, $hit['breakdown']['name']);
        $this->assertSame(0, $miss['breakdown']['name']);
    }

    #[Test]
    public function distance_bands_with_exact_limits(): void
    {
        $points = fn (int $m) => $this->score([], null, [], $m)['breakdown']['distance'];
        $this->assertSame(40, $points(0));
        $this->assertSame(40, $points(300));
        $this->assertSame(25, $points(301));
        $this->assertSame(25, $points(1000));
        $this->assertSame(10, $points(1001));
        $this->assertSame(10, $points(3000));
        $this->assertSame(0, $points(3001));
    }

    #[Test]
    public function categories_apply_best_bonus_and_worst_penalty_once(): void
    {
        $this->assertSame(25, $this->score([], null, ['beach'], 9999)['score']);
        $this->assertSame(10, $this->score([], null, ['coast'], 9999)['score']);
        $this->assertSame(25, $this->score([], null, ['beach', 'coast'], 9999)['score']);
        $this->assertSame(-20, $this->score([], null, ['city', 'traffic'], 9999)['score']);
        $this->assertSame(5, $this->score([], null, ['beach', 'city'], 9999)['score']);
        $this->assertSame(0, $this->score([], null, ['landscape'], 9999)['score']);
    }

    #[Test]
    public function town_only_match_gives_5_and_not_on_top_of_a_name_match(): void
    {
        $town = $this->score(['Beach Ponta'], 'Viganj: Camping Antony Boy', [], 9999, ['Viganj']);
        $this->assertSame(5, $town['breakdown']['town']);
        $both = $this->score(['Viganj'], 'Viganj: Camping', [], 9999, ['Viganj']);
        $this->assertSame([30, 0], [$both['breakdown']['name'], $both['breakdown']['town']]);
    }

    #[Test]
    public function pool_profile_uses_its_own_categories_and_stopwords(): void
    {
        $s = $this->score(['Terme Ptuj'], 'Ptuj: Therme Ptuj indoor', ['indoor'], 200, [], 'pool_spa');
        $this->assertSame(['distance' => 40, 'category' => 10, 'name' => 30], array_intersect_key($s['breakdown'], array_flip(['distance', 'category', 'name'])));
        $this->assertSame('pool_spa', (new WebcamScorer())->profileFor(2));
        $this->assertSame('beach', (new WebcamScorer())->profileFor(3));
        $this->assertSame('beach', (new WebcamScorer())->profileFor(null));
    }

    #[Test]
    public function thresholds_and_runner_up_margin(): void
    {
        $s = new WebcamScorer();
        $this->assertSame('none', $s->decide(39, null));
        $this->assertSame('pending', $s->decide(40, null));
        $this->assertSame('pending', $s->decide(69, null));
        $this->assertSame('auto_approved', $s->decide(70, null));
        $this->assertSame('auto_approved', $s->decide(70, 55));   // exactly +15
        $this->assertSame('pending', $s->decide(70, 56));         // +14
        $this->assertSame('pending', $s->decide(95, 90));
    }
}
