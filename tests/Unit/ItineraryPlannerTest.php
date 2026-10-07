<?php

namespace Tests\Unit;

use App\Services\AI\Itinerary\ItineraryPlanner;
use App\Services\AI\Itinerary\NotEnoughBeachesException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ItineraryPlannerTest extends TestCase
{
    /** Three real Croatian areas ~150-400 km apart, 4 beaches each (a few km apart). */
    private function croatia(): array
    {
        $areas = [
            'istria' => [45.08, 13.64],   // Rovinj
            'split' => [43.51, 16.44],
            'dubrovnik' => [42.65, 18.09],
        ];
        $beaches = [];
        $id = 1;
        foreach ($areas as $area => [$lat, $lon]) {
            foreach ([[0, 0], [0.02, 0.01], [-0.015, 0.02], [0.01, -0.025]] as $i => [$dLat, $dLon]) {
                $beaches[] = ['id' => $id++, 'area' => $area, 'latitude' => $lat + $dLat, 'longitude' => $lon + $dLon, 'score' => 10 - $i];
            }
        }

        return $beaches;
    }

    private function areaOf(array $beaches, int $id): string
    {
        return collect($beaches)->firstWhere('id', $id)['area'];
    }

    #[Test]
    public function each_day_stays_in_one_area_and_areas_are_not_mixed(): void
    {
        $beaches = $this->croatia();
        $plan = (new ItineraryPlanner())->plan($beaches, 3, 'balanced', 80);

        $this->assertCount(3, $plan);
        $areasPerDay = array_map(
            fn ($day) => array_unique(array_map(fn ($id) => $this->areaOf($beaches, $id), $day['beach_ids'])),
            $plan
        );
        foreach ($areasPerDay as $areas) {
            $this->assertCount(1, $areas, 'a day must not mix distant areas');
        }
        $this->assertEqualsCanonicalizing(['istria', 'split', 'dubrovnik'], array_merge(...$areasPerDay));
    }

    #[Test]
    public function days_follow_a_geographic_route_west_to_east(): void
    {
        $beaches = $this->croatia();
        $plan = (new ItineraryPlanner())->plan($beaches, 3);

        $route = array_map(fn ($d) => $this->areaOf($beaches, $d['beach_ids'][0]), $plan);
        $this->assertSame(['istria', 'split', 'dubrovnik'], $route);
    }

    #[Test]
    public function pace_sets_beaches_per_day(): void
    {
        $beaches = $this->croatia();
        foreach (['relaxed' => 2, 'balanced' => 3, 'packed' => 4] as $pace => $expected) {
            $plan = (new ItineraryPlanner())->plan($beaches, 3, $pace, 80);
            foreach ($plan as $day) {
                $this->assertCount($expected, $day['beach_ids'], "pace {$pace}");
            }
        }
    }

    #[Test]
    public function legs_and_totals_are_real_distances(): void
    {
        $plan = (new ItineraryPlanner())->plan($this->croatia(), 3);

        foreach ($plan as $day) {
            $this->assertNull($day['legs_km'][0], 'first stop has no leg');
            $this->assertEqualsWithDelta(array_sum(array_filter($day['legs_km'])), $day['total_km'], 0.11);
            $this->assertLessThan(15, $day['total_km'], 'beaches of one area are a few km apart');
        }
        // Formula sanity check, Split -> Dubrovnik straight line. Independent equirectangular estimate:
        // dLat 0.86 deg = 95.6 km, dLon 1.65 deg * cos(43.08) = 134.2 km, sqrt = ~164.8 km
        $this->assertEqualsWithDelta(164.8, ItineraryPlanner::haversineKm(43.51, 16.44, 42.65, 18.09), 1.0);
    }

    #[Test]
    public function max_travel_per_day_drops_far_stops(): void
    {
        // 1 day, beaches in Split and one in Dubrovnik: 80 km limit must drop Dubrovnik
        $beaches = [
            ['id' => 1, 'latitude' => 43.51, 'longitude' => 16.44, 'score' => 10],
            ['id' => 2, 'latitude' => 43.52, 'longitude' => 16.45, 'score' => 9],
            ['id' => 3, 'latitude' => 42.65, 'longitude' => 18.09, 'score' => 8],
        ];
        $plan = (new ItineraryPlanner())->plan($beaches, 1, 'balanced', 80);

        $this->assertSame([1, 2], $plan[0]['beach_ids']);
        $this->assertLessThanOrEqual(80, $plan[0]['total_km']);
    }

    #[Test]
    public function it_is_deterministic(): void
    {
        $beaches = $this->croatia();
        $planner = new ItineraryPlanner();
        $this->assertSame($planner->plan($beaches, 3), $planner->plan(array_reverse($beaches), 3));
    }

    #[Test]
    public function fewer_beaches_than_days_throws(): void
    {
        $this->expectException(NotEnoughBeachesException::class);
        (new ItineraryPlanner())->plan(array_slice($this->croatia(), 0, 2), 3);
    }

    #[Test]
    public function every_day_gets_at_least_one_beach_when_exactly_enough(): void
    {
        // 7 days, 7 beaches all in one town: each day one distinct beach
        $beaches = array_map(fn ($i) => ['id' => $i, 'latitude' => 43.5 + $i * 0.001, 'longitude' => 16.4, 'score' => $i], range(1, 7));
        $plan = (new ItineraryPlanner())->plan($beaches, 7);

        $this->assertCount(7, $plan);
        $ids = array_merge(...array_column($plan, 'beach_ids'));
        $this->assertCount(7, $ids);
        $this->assertCount(7, array_unique($ids), 'no beach is used twice');
    }
}
