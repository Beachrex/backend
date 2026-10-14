<?php

namespace App\Services\Webcams;

use App\Models\PlaceWebcam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Local webcam → place matching (TASK-006 §6.3). Zero API calls: reads `windy_webcams` and `beach`.
 *
 * Rules: candidates within `max_radius_m`; usable webcams only; rejected pairs never proposed; places with
 * an approved or manual webcam are left alone; reviewed rows are never changed; one webcam goes to one place
 * (highest score wins, tie → nearest; the loser falls back to its next candidate).
 */
class WebcamMatcher
{
    public function __construct(private readonly WebcamScorer $scorer = new WebcamScorer())
    {
    }

    /**
     * @param  array{countries?:list<string>, place_ids?:list<int>, only_missing?:bool}  $filters
     * @return array{summary:array<string,int>, decisions:list<array<string,mixed>>}
     */
    public function run(array $filters = [], bool $dryRun = false): array
    {
        $places = $this->places($filters);
        $summary = ['places' => $places->count(), 'skipped_reviewed' => 0, 'auto_approved' => 0, 'pending' => 0, 'none' => 0];
        if ($places->isEmpty()) {
            return ['summary' => $summary, 'decisions' => []];
        }
        $placeIds = $places->keys()->all();

        $existing = PlaceWebcam::query()->whereIn('place_id', $placeIds)->get()->groupBy('place_id');
        $skip = [];
        foreach ($existing as $placeId => $rows) {
            if ($rows->contains(fn (PlaceWebcam $r) => $r->review_status === PlaceWebcam::APPROVED || $r->source === PlaceWebcam::SOURCE_MANUAL)) {
                $skip[$placeId] = true;
            }
        }
        $summary['skipped_reviewed'] = count($skip);

        // Webcams already shown or proposed elsewhere (rows this run will not replace).
        $taken = PlaceWebcam::query()
            ->whereIn('review_status', [PlaceWebcam::AUTO_APPROVED, PlaceWebcam::APPROVED, PlaceWebcam::PENDING])
            ->where(fn ($q) => $q->whereNotIn('place_id', $placeIds)->orWhere('source', '!=', PlaceWebcam::SOURCE_MATCHER))
            ->get(['place_id', 'windy_id'])
            ->mapWithKeys(fn ($r) => [$r->windy_id => $r->place_id])->all();

        $scored = $this->score($places, $placeIds);

        // Greedy global assignment.
        usort($scored, fn ($a, $b) => [$b['score'], $a['distance_m']] <=> [$a['score'], $b['distance_m']]);
        $byPlace = [];
        foreach ($scored as $c) {
            $byPlace[$c['place_id']][] = $c['score'];
        }
        $done = [];
        $chosen = [];
        foreach ($scored as $c) {
            $pid = $c['place_id'];
            if (isset($done[$pid]) || isset($skip[$pid])) {
                continue;
            }
            if (isset($taken[$c['windy_id']]) && $taken[$c['windy_id']] !== $pid) {
                continue;
            }
            $others = $byPlace[$pid];
            unset($others[array_search($c['score'], $others, true)]);
            $runnerUp = $others === [] ? null : max($others);
            $status = $this->scorer->decide($c['score'], $runnerUp);
            $done[$pid] = true;
            if ($status === WebcamScorer::NONE) {
                continue;
            }
            $c['review_status'] = $status;
            $c['breakdown']['runner_up'] = $runnerUp;
            $chosen[$pid] = $c;
            $taken[$c['windy_id']] = $pid;
        }

        $decisions = [];
        foreach ($places as $pid => $place) {
            if (isset($skip[$pid])) {
                continue;
            }
            $c = $chosen[$pid] ?? null;
            $summary[$c ? $c['review_status'] : 'none']++;
            $decisions[] = ['place_id' => $pid, 'place' => $place->name, 'country' => $place->country_code] + ($c ?? ['review_status' => 'none']);
            if (! $dryRun) {
                $this->persist($pid, $c, $existing->get($pid, collect()));
            }
        }

        return ['summary' => $summary, 'decisions' => $decisions];
    }

    /**
     * Score one place/webcam pair with the matcher's rules but without the radius or usability filter
     * (legacy webcams, admin manual add). Null when the place has no location or the webcam is unknown.
     *
     * @return array{place_id:int, windy_id:int, title:?string, distance_m:int, score:int, breakdown:array}|null
     */
    public function scorePair(int $placeId, int $windyId): ?array
    {
        $places = $this->places(['place_ids' => [$placeId]]);
        if ($places->isEmpty()) {
            return null;
        }

        return $this->score($places, [$placeId], [$windyId])[0] ?? null;
    }

    private function places(array $filters): Collection
    {
        $q = DB::table('beach as b')->whereNotNull('b.location')
            ->select('b.id', 'b.name', 'b.country_code', 'b.city_id', 'b.waterbody_id');
        if (! empty($filters['countries'])) {
            $q->whereIn('b.country_code', array_map('strtoupper', $filters['countries']));
        }
        if (! empty($filters['place_ids'])) {
            $q->whereIn('b.id', $filters['place_ids']);
        }
        if (! empty($filters['only_missing'])) {
            $q->whereNotExists(fn ($s) => $s->from('place_webcams as pw')->whereColumn('pw.place_id', 'b.id')
                ->whereIn('pw.review_status', PlaceWebcam::VISIBLE));
        }

        return $q->orderBy('b.id')->get()->keyBy('id');
    }

    /**
     * Candidates within the radius (usable, not rejected), or — with $windyIds — exactly those pairs, unfiltered.
     *
     * @return list<array<string,mixed>>
     */
    private function score(Collection $places, array $placeIds, ?array $windyIds = null): array
    {
        $ids = '{' . implode(',', $placeIds) . '}';
        if ($windyIds !== null) {
            $rows = DB::select(
                'SELECT b.id AS place_id, w.windy_id, w.title, w.categories,
                        ROUND(ST_Distance(w.location, b.location::geography))::int AS distance_m
                   FROM beach b CROSS JOIN windy_webcams w
                  WHERE b.id = ANY(?::bigint[]) AND w.windy_id = ANY(?::bigint[])',
                [$ids, '{' . implode(',', $windyIds) . '}']
            );
        } else {
            $usable = "w.status = 'active' AND w.missing_since IS NULL"
                . (config('webcams.matching.require_embed_ok') ? " AND w.embed_status = 'ok'" : '');
            $rows = DB::select(
                "SELECT b.id AS place_id, w.windy_id, w.title, w.categories,
                        ROUND(ST_Distance(w.location, b.location::geography))::int AS distance_m
                   FROM beach b
                   JOIN windy_webcams w ON ST_DWithin(w.location, b.location::geography, ?)
                  WHERE b.id = ANY(?::bigint[]) AND {$usable}
                    AND NOT EXISTS (SELECT 1 FROM place_webcams r WHERE r.place_id = b.id AND r.windy_id = w.windy_id AND r.review_status = 'rejected')",
                [config('webcams.matching.max_radius_m'), $ids]
            );
        }

        $names = DB::table('beach_translation')->whereIn('beach_id', $placeIds)->get(['beach_id', 'name'])->groupBy('beach_id');
        $cityIds = $places->pluck('city_id')->filter()->unique()->all();
        $towns = DB::table('city_translation')->whereIn('city_id', $cityIds)->get(['city_id', 'name'])->groupBy('city_id');

        $out = [];
        foreach ($rows as $r) {
            $place = $places[$r->place_id];
            $placeNames = array_merge([$place->name], $names->get($place->id, collect())->pluck('name')->all());
            $townNames = $place->city_id ? $towns->get($place->city_id, collect())->pluck('name')->all() : [];
            $profile = $this->scorer->profileFor($place->waterbody_id !== null ? (int) $place->waterbody_id : null);
            $s = $this->scorer->score($placeNames, $townNames, $profile, $r->title, json_decode($r->categories, true) ?: [], (int) $r->distance_m);
            $out[] = ['place_id' => (int) $r->place_id, 'windy_id' => (int) $r->windy_id, 'title' => $r->title,
                'distance_m' => (int) $r->distance_m, 'score' => $s['score'], 'breakdown' => $s['breakdown']];
        }

        return $out;
    }

    private function persist(int $placeId, ?array $c, Collection $existing): void
    {
        DB::transaction(function () use ($placeId, $c, $existing) {
            // Earlier matcher proposals for this place are replaced; legacy, manual and reviewed rows stay.
            $stale = $existing->filter(fn (PlaceWebcam $r) => $r->source === PlaceWebcam::SOURCE_MATCHER && $r->isMatcherOwned()
                && (! $c || $r->windy_id !== $c['windy_id']));
            PlaceWebcam::query()->whereIn('id', $stale->pluck('id'))->delete();
            if (! $c) {
                return;
            }
            $row = $existing->firstWhere('windy_id', $c['windy_id']);
            if ($row && ! $row->isMatcherOwned()) {
                return;
            }
            $row ??= new PlaceWebcam(['place_id' => $placeId, 'windy_id' => $c['windy_id'], 'source' => PlaceWebcam::SOURCE_MATCHER]);
            $row->fill([
                'distance_m' => $c['distance_m'],
                'match_score' => $c['score'],
                'score_breakdown' => $c['breakdown'],
                'review_status' => $c['review_status'],
            ]);
            $hasPrimary = PlaceWebcam::query()->where('place_id', $placeId)->where('is_primary', true)
                ->when($row->exists, fn ($q) => $q->where('id', '!=', $row->id))->exists();
            $row->is_primary = $c['review_status'] === PlaceWebcam::AUTO_APPROVED && ! $hasPrimary;
            $row->save();
        });
    }
}
