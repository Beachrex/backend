<?php

namespace App\Services\AI\Itinerary;

use InvalidArgumentException;

/**
 * Builds the geographic skeleton of a multi-day itinerary.
 *
 * Pure logic (no DB, no LLM): candidates in → ordered days out.
 *  1. Keep the best-scored candidates (a pool ~3x what the trip needs).
 *  2. k-means (k = days) on projected coordinates, deterministic farthest-point init.
 *  3. Per day: the top beaches of its cluster by score, ordered by nearest neighbour.
 *  4. Days ordered by nearest neighbour over cluster centroids.
 *  5. A day longer than $maxKmPerDay drops its farthest stops (keeps at least one).
 *
 * Distances are straight-line (haversine) — not road or ferry distances.
 */
class ItineraryPlanner
{
    public const BEACHES_PER_DAY = ['relaxed' => 2, 'balanced' => 3, 'packed' => 4];

    private const KMEANS_ITERATIONS = 30;

    /**
     * @param array<int, array{id:int, latitude:float, longitude:float, score:float}> $candidates
     * @return array<int, array{beach_ids:int[], legs_km:array<int, float|null>, total_km:float, centroid:array{0:float,1:float}}>
     */
    public function plan(array $candidates, int $days, string $pace = 'balanced', int $maxKmPerDay = 80): array
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Days must be at least 1.');
        }
        if (count($candidates) < $days) {
            throw new NotEnoughBeachesException(count($candidates), $days);
        }

        $perDay = self::BEACHES_PER_DAY[$pace] ?? self::BEACHES_PER_DAY['balanced'];
        $perDay = max(1, min($perDay, intdiv(count($candidates), $days)));

        // Best candidates first; ties broken by id for determinism.
        usort($candidates, fn ($a, $b) => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);
        $pool = array_slice($candidates, 0, max($days * $perDay * 3, $days));

        $clusters = $this->kMeans($pool, $days);

        $result = [];
        foreach ($clusters as $cluster) {
            usort($cluster['members'], fn ($a, $b) => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);
            $stops = $this->orderByNearestNeighbour(array_slice($cluster['members'], 0, $perDay));
            $stops = $this->enforceMaxDistance($stops, $maxKmPerDay);

            $result[] = [
                'beach_ids' => array_column($stops, 'id'),
                'legs_km' => $this->legs($stops),
                'total_km' => round(array_sum(array_filter($this->legs($stops))), 1),
                'centroid' => $cluster['centroid'],
            ];
        }

        return $this->orderDays($result);
    }

    public static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 2 * $r * asin(min(1.0, sqrt($a)));
    }

    /**
     * @return array<int, array{centroid:array{0:float,1:float}, members:array}>
     */
    private function kMeans(array $points, int $k): array
    {
        // Seeds: best-scored point, then repeatedly the point farthest from all seeds.
        $seeds = [$points[0]];
        while (count($seeds) < $k) {
            $best = null;
            $bestDist = -1.0;
            foreach ($points as $p) {
                $d = min(array_map(fn ($s) => $this->dist($p, $s), $seeds));
                if ($d > $bestDist) {
                    $bestDist = $d;
                    $best = $p;
                }
            }
            $seeds[] = $best;
        }
        $centroids = array_map(fn ($s) => [(float) $s['latitude'], (float) $s['longitude']], $seeds);

        $assignment = [];
        for ($i = 0; $i < self::KMEANS_ITERATIONS; $i++) {
            $newAssignment = [];
            foreach ($points as $idx => $p) {
                $newAssignment[$idx] = $this->nearestCentroid($p, $centroids);
            }

            // Re-seed empty clusters with the point farthest from its centroid.
            foreach (range(0, $k - 1) as $c) {
                if (!in_array($c, $newAssignment, true)) {
                    $far = $this->farthestFromOwnCentroid($points, $newAssignment, $centroids);
                    $newAssignment[$far] = $c;
                }
            }

            if ($newAssignment === $assignment) {
                break;
            }
            $assignment = $newAssignment;

            foreach (range(0, $k - 1) as $c) {
                $members = array_keys(array_filter($assignment, fn ($a) => $a === $c));
                $centroids[$c] = [
                    array_sum(array_map(fn ($m) => (float) $points[$m]['latitude'], $members)) / count($members),
                    array_sum(array_map(fn ($m) => (float) $points[$m]['longitude'], $members)) / count($members),
                ];
            }
        }

        $clusters = [];
        foreach (range(0, $k - 1) as $c) {
            $clusters[] = [
                'centroid' => $centroids[$c],
                'members' => array_values(array_map(
                    fn ($idx) => $points[$idx],
                    array_keys(array_filter($assignment, fn ($a) => $a === $c))
                )),
            ];
        }

        return $clusters;
    }

    private function nearestCentroid(array $p, array $centroids): int
    {
        $best = 0;
        $bestDist = INF;
        foreach ($centroids as $c => $centroid) {
            $d = self::haversineKm((float) $p['latitude'], (float) $p['longitude'], $centroid[0], $centroid[1]);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best = $c;
            }
        }

        return $best;
    }

    private function farthestFromOwnCentroid(array $points, array $assignment, array $centroids): int
    {
        $far = 0;
        $farDist = -1.0;
        // Only points whose cluster has more than one member may move.
        $counts = array_count_values($assignment);
        foreach ($points as $idx => $p) {
            if (($counts[$assignment[$idx]] ?? 0) < 2) {
                continue;
            }
            $c = $centroids[$assignment[$idx]];
            $d = self::haversineKm((float) $p['latitude'], (float) $p['longitude'], $c[0], $c[1]);
            if ($d > $farDist) {
                $farDist = $d;
                $far = $idx;
            }
        }

        return $far;
    }

    private function orderByNearestNeighbour(array $stops): array
    {
        if (count($stops) < 2) {
            return $stops;
        }

        $ordered = [array_shift($stops)]; // start at the best-scored stop
        while ($stops) {
            $last = end($ordered);
            usort($stops, fn ($a, $b) => $this->dist($last, $a) <=> $this->dist($last, $b));
            $ordered[] = array_shift($stops);
        }

        return $ordered;
    }

    private function enforceMaxDistance(array $stops, int $maxKm): array
    {
        while (count($stops) > 1 && array_sum(array_filter($this->legs($stops))) > $maxKm) {
            // Drop the stop farthest from the day's first (best-scored) stop.
            $first = $stops[0];
            $farIdx = 1;
            foreach ($stops as $i => $s) {
                if ($i > 0 && $this->dist($first, $s) >= $this->dist($first, $stops[$farIdx])) {
                    $farIdx = $i;
                }
            }
            array_splice($stops, $farIdx, 1);
            $stops = $this->orderByNearestNeighbour($stops);
        }

        return $stops;
    }

    /** @return array<int, float|null> distance from the previous stop (null for the first) */
    private function legs(array $stops): array
    {
        $legs = [];
        foreach ($stops as $i => $s) {
            $legs[] = $i === 0 ? null : round($this->dist($stops[$i - 1], $s), 1);
        }

        return $legs;
    }

    private function orderDays(array $days): array
    {
        // Start with the westernmost day, then always continue with the nearest unvisited one.
        usort($days, fn ($a, $b) => $a['centroid'][1] <=> $b['centroid'][1]);
        $ordered = [array_shift($days)];
        while ($days) {
            $last = end($ordered)['centroid'];
            usort($days, fn ($a, $b) => self::haversineKm($last[0], $last[1], ...$a['centroid'])
                <=> self::haversineKm($last[0], $last[1], ...$b['centroid']));
            $ordered[] = array_shift($days);
        }

        return $ordered;
    }

    private function dist(array $a, array $b): float
    {
        return self::haversineKm((float) $a['latitude'], (float) $a['longitude'], (float) $b['latitude'], (float) $b['longitude']);
    }
}
