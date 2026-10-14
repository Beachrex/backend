<?php

namespace App\Services\Webcams;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use Illuminate\Support\Facades\DB;

/**
 * TASK-006 §6.2: salvages webcam ids from `beach.webcam` (never edits that column).
 *
 * Unknown ids are looked up once via the API (`webcamIds`, 50 per call); ids Windy does not return are dead.
 * Known ids get a public player check when theirs is missing. Usable ones are scored with the matcher's
 * rules; beyond the matching radius, or when the webcam is already shown elsewhere, they are pending at best.
 */
class LegacyWebcamExtractor
{
    public const KEPT = 'kept';
    public const PENDING = 'pending';
    public const BELOW_THRESHOLD = 'below_threshold';
    public const DEAD = 'dead';
    public const UNPARSEABLE = 'unparseable';

    public function __construct(
        private readonly WebcamIdExtractor $extractor,
        private readonly WindyApiClient $api,
        private readonly WebcamImporter $importer,
        private readonly EmbedChecker $embed,
        private readonly WebcamMatcher $matcher,
        private readonly WebcamScorer $scorer,
    ) {
    }

    /**
     * @return array{counts:array<string,int>, rows:list<array<string,mixed>>, requests:int}
     */
    public function run(array $countries = [], bool $dryRun = false): array
    {
        $beaches = DB::table('beach')->whereNotNull('webcam')
            ->when($countries, fn ($q) => $q->whereIn('country_code', array_map('strtoupper', $countries)))
            ->orderBy('id')->get(['id', 'name', 'country_code', 'webcam']);

        $counts = ['empty' => 0, 'placeholder' => 0];
        $parsed = [];
        $rows = [];
        foreach ($beaches as $b) {
            $format = $this->extractor->format($b->webcam);
            if (in_array($format, ['empty', 'placeholder'], true)) {
                $counts[$format]++;
                continue;
            }
            $id = $this->extractor->extract($b->webcam);
            if ($id === null) {
                $rows[] = $this->row($b, $format, null, self::UNPARSEABLE, 'not a Windy webcam');
                continue;
            }
            $parsed[] = [$b, $format, $id];
        }

        $before = $this->api->remainingBudget();
        $ids = array_unique(array_column($parsed, 2));
        $known = WindyWebcam::query()->whereIn('windy_id', $ids)->pluck('windy_id')->map(fn ($v) => (int) $v)->all();
        $unknown = array_values(array_diff($ids, $known));
        $returned = [];
        if (! $dryRun && $unknown !== []) {
            $found = $this->api->byIds($unknown);
            $this->importer->store($found);
            $returned = array_map(fn ($w) => (int) $w['webcamId'], $found);
            $this->embed->run(array_values(array_intersect($unknown, $returned)));
        }
        if (! $dryRun) {
            $unchecked = WindyWebcam::query()->whereIn('windy_id', $known)->where('embed_status', EmbedChecker::UNKNOWN)
                ->pluck('windy_id')->map(fn ($v) => (int) $v)->all();
            $this->embed->run($unchecked);
        }

        foreach ($parsed as [$b, $format, $id]) {
            $webcam = WindyWebcam::find($id);
            if (! $webcam) {
                $rows[] = $dryRun
                    ? $this->row($b, $format, $id, 'lookup', 'not stored yet; needs one API lookup')
                    : $this->row($b, $format, $id, self::DEAD, 'not returned by Windy');
                continue;
            }
            if (! $webcam->isUsable()) {
                $reason = $webcam->missing_since ? 'missing from Windy' : ($webcam->status !== 'active' ? "status {$webcam->status}" : "player {$webcam->embed_status}");
                $rows[] = $this->row($b, $format, $id, self::DEAD, $reason);
                continue;
            }
            $rows[] = $this->assign($b, $format, $webcam, $dryRun);
        }

        foreach ($rows as $r) {
            $counts[$r['outcome']] = ($counts[$r['outcome']] ?? 0) + 1;
        }

        return ['counts' => $counts, 'rows' => $rows, 'requests' => $before - $this->api->remainingBudget()];
    }

    private function assign(object $b, string $format, WindyWebcam $webcam, bool $dryRun): array
    {
        $pair = $this->matcher->scorePair((int) $b->id, (int) $webcam->windy_id);
        if ($pair === null) {
            return $this->row($b, $format, $webcam->windy_id, self::UNPARSEABLE, 'place has no coordinates');
        }
        $status = $this->scorer->decide($pair['score'], null);
        $reasons = [];
        if ($status === WebcamScorer::NONE) {
            return $this->row($b, $format, $webcam->windy_id, self::BELOW_THRESHOLD, 'score below ' . config('webcams.matching.pending_min'), $pair);
        }
        if ($pair['distance_m'] > config('webcams.matching.max_radius_m')) {
            $status = WebcamScorer::PENDING;
            $reasons[] = 'beyond radius';
        }
        $shownElsewhere = PlaceWebcam::query()->where('windy_id', $webcam->windy_id)->where('place_id', '!=', $b->id)
            ->whereIn('review_status', PlaceWebcam::VISIBLE)->exists();
        if ($shownElsewhere) {
            $status = WebcamScorer::PENDING;
            $reasons[] = 'already shown on another place';
        }

        $existing = PlaceWebcam::query()->where(['place_id' => $b->id, 'windy_id' => $webcam->windy_id])->first();
        if ($existing) {
            return $this->row($b, $format, $webcam->windy_id, $existing->review_status === PlaceWebcam::REJECTED ? self::DEAD : self::outcome($existing->review_status),
                'already assigned (' . $existing->review_status . ')', $pair);
        }
        if (! $dryRun) {
            $hasPrimary = PlaceWebcam::query()->where('place_id', $b->id)->where('is_primary', true)->exists();
            PlaceWebcam::create([
                'place_id' => $b->id, 'windy_id' => $webcam->windy_id, 'source' => PlaceWebcam::SOURCE_LEGACY,
                'distance_m' => $pair['distance_m'], 'match_score' => $pair['score'],
                'score_breakdown' => $pair['breakdown'] + ['legacy' => true, 'notes' => $reasons],
                'review_status' => $status, 'is_primary' => $status === WebcamScorer::AUTO && ! $hasPrimary,
            ]);
        }

        return $this->row($b, $format, $webcam->windy_id, self::outcome($status), implode('; ', $reasons), $pair);
    }

    private static function outcome(string $status): string
    {
        return in_array($status, [PlaceWebcam::AUTO_APPROVED, PlaceWebcam::APPROVED], true) ? self::KEPT : self::PENDING;
    }

    private function row(object $b, string $format, ?int $id, string $outcome, string $reason = '', ?array $pair = null): array
    {
        return [
            'place_id' => $b->id, 'country' => $b->country_code, 'place' => trim((string) $b->name), 'legacy_format' => $format,
            'webcam_id' => $id, 'outcome' => $outcome, 'reason' => $reason,
            'distance_m' => $pair['distance_m'] ?? null, 'score' => $pair['score'] ?? null, 'title' => $pair['title'] ?? null,
        ];
    }
}
