<?php

namespace App\Services\Webcams;

use App\Models\PlaceWebcam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * TASK-006 §6.4. Scheduled daily but self-pacing: resumes an unfinished import, otherwise starts a new one
 * only when the last finished run is `refresh_days` old. After a finished import: player check, primary
 * moved away from webcams that are no longer usable, match re-run for affected places, alert on big losses.
 */
class WebcamRefresher
{
    public function __construct(
        private readonly WebcamImporter $importer,
        private readonly EmbedChecker $embed,
        private readonly WebcamMatcher $matcher,
    ) {
    }

    /** @return array<string,mixed> */
    public function run(bool $force = false): array
    {
        $countries = DB::table('beach')->whereNotNull('country_code')->distinct()->orderBy('country_code')->pluck('country_code')->all();
        $open = DB::table('windy_import_slices')->whereIn('status', ['pending', 'running'])->exists();
        $lastDone = DB::table('windy_import_slices')->max('updated_at');
        if (! $open && ! $force && $lastDone && now()->diffInDays($lastDone, true) < config('webcams.refresh.days')) {
            return ['action' => 'nothing to do', 'last_run' => $lastDone];
        }

        $visibleBefore = PlaceWebcam::query()->where('is_primary', true)->count();   // places showing a webcam
        $startedAt = now();
        $import = $this->importer->run($this->importer->runKey($countries, ! $open), $countries);
        if (! $import['finished']) {
            return ['action' => 'import paused (budget)', 'requests' => $import['requests']];
        }

        $embed = $this->embed->run();
        $demoted = $this->demote();
        $newNearby = DB::table('beach as b')->whereNotNull('b.location')
            ->whereExists(fn ($q) => $q->from('windy_webcams as w')->where('w.first_seen_at', '>=', $startedAt)
                ->whereRaw('ST_DWithin(w.location, b.location::geography, ?)', [config('webcams.matching.max_radius_m')]))
            ->pluck('b.id')->all();
        $affected = array_values(array_unique(array_merge($demoted['places'], $newNearby)));
        $match = $affected === [] ? null : $this->matcher->run(['place_ids' => $affected]);

        $lost = $demoted['lost'];
        $ratio = $visibleBefore > 0 ? $lost / $visibleBefore : 0.0;
        if ($ratio > config('webcams.refresh.alert_ratio')) {
            $this->alert($lost, $visibleBefore);
        }

        return [
            'action' => 'refreshed', 'requests' => $import['requests'], 'import' => $this->importer->stats,
            'embed_check' => $embed, 'lost' => $lost, 'shown_before' => $visibleBefore,
            'affected_places' => count($affected), 'match' => $match['summary'] ?? null,
        ];
    }

    /**
     * Shown assignments whose webcam is no longer usable stop being primary (the page hides them anyway);
     * the place's best other usable shown webcam becomes primary.
     *
     * `lost` = places whose primary webcam stopped working in this pass (counted once: it is no longer primary after).
     *
     * @return array{lost:int, places:list<int>}
     */
    public function demote(): array
    {
        $dead = PlaceWebcam::query()->with('webcam')->whereIn('review_status', PlaceWebcam::VISIBLE)->get()
            ->filter(fn (PlaceWebcam $r) => ! $r->webcam?->isUsable());
        $places = [];
        $lost = 0;
        foreach ($dead as $row) {
            $places[$row->place_id] = true;
            if ($row->is_primary) {
                $lost++;
                DB::transaction(function () use ($row) {
                    $row->update(['is_primary' => false]);
                    $next = PlaceWebcam::query()->with('webcam')->where('place_id', $row->place_id)->where('id', '!=', $row->id)
                        ->whereIn('review_status', PlaceWebcam::VISIBLE)->orderByDesc('match_score')->get()
                        ->first(fn (PlaceWebcam $r) => (bool) $r->webcam?->isUsable());
                    $next?->update(['is_primary' => true]);
                });
            }
        }

        return ['lost' => $lost, 'places' => array_keys($places)];
    }

    private function alert(int $lost, int $before): void
    {
        $text = "Windy webcam refresh: {$lost} of {$before} places lost their main webcam. Check the admin review page.";
        Log::warning($text);
        $to = config('webcams.refresh.alert_email');
        if ($to) {
            Mail::raw($text, fn ($m) => $m->to($to)->subject('Beachrex: many webcams lost'));
        }
    }
}
