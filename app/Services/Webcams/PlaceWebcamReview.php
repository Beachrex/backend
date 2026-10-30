<?php

namespace App\Services\Webcams;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Owner review of webcam assignments (TASK-006 §7). Used by the admin page; every change clears the place's
 * cached beach details so the website shows it on the next render.
 */
class PlaceWebcamReview
{
    public function __construct(
        private readonly WebcamIdExtractor $extractor,
        private readonly WebcamMatcher $matcher,
        private readonly WindyApiClient $api,
        private readonly WebcamImporter $importer,
        private readonly EmbedChecker $embed,
    ) {
    }

    public function approve(PlaceWebcam $row, int $userId): void
    {
        $this->assertNotShownElsewhere($row);
        DB::transaction(function () use ($row, $userId) {
            $row->update(['review_status' => PlaceWebcam::APPROVED, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
            if (! PlaceWebcam::query()->where('place_id', $row->place_id)->where('is_primary', true)->exists()) {
                $row->update(['is_primary' => true]);
            }
        });
        $this->forgetPlace($row->place_id);
    }

    public function reject(PlaceWebcam $row, int $userId): void
    {
        DB::transaction(function () use ($row, $userId) {
            $wasPrimary = $row->is_primary;
            $row->update(['review_status' => PlaceWebcam::REJECTED, 'is_primary' => false, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
            if ($wasPrimary) {
                PlaceWebcam::query()->where('place_id', $row->place_id)->whereIn('review_status', PlaceWebcam::VISIBLE)
                    ->orderByDesc('match_score')->first()?->update(['is_primary' => true]);
            }
        });
        $this->forgetPlace($row->place_id);
    }

    public function makePrimary(PlaceWebcam $row, int $userId): void
    {
        if (! in_array($row->review_status, PlaceWebcam::VISIBLE, true)) {
            throw new InvalidArgumentException('Only an approved webcam can be primary.');
        }
        DB::transaction(function () use ($row, $userId) {
            PlaceWebcam::query()->where('place_id', $row->place_id)->where('id', '!=', $row->id)->update(['is_primary' => false]);
            $row->update(['is_primary' => true, 'reviewed_by' => $userId, 'reviewed_at' => now()]);
        });
        $this->forgetPlace($row->place_id);
    }

    /**
     * Owner pastes an id, a Windy URL or embed code. Unknown ids cost one API call (verify); the player
     * check is the public page. The row is manual + approved; the matcher never touches it.
     */
    public function addManual(int $placeId, string $input, int $userId): PlaceWebcam
    {
        $id = $this->extractor->extract($input);
        if ($id === null) {
            throw new InvalidArgumentException('No Windy webcam id found in that text.');
        }
        if (! WindyWebcam::query()->whereKey($id)->exists()) {
            $found = $this->api->byIds([$id]);
            if ($found === []) {
                throw new InvalidArgumentException("Windy does not know webcam {$id}.");
            }
            $this->importer->store($found);
        }
        $webcam = WindyWebcam::findOrFail($id);
        if ($webcam->embed_status === EmbedChecker::UNKNOWN) {
            $this->embed->run([$id]);
            $webcam->refresh();
        }
        if (! $webcam->isUsable()) {
            throw new InvalidArgumentException("Webcam {$id} cannot be shown (status {$webcam->status}, player {$webcam->embed_status}).");
        }
        $pair = $this->matcher->scorePair($placeId, $id);
        $row = PlaceWebcam::query()->firstOrNew(['place_id' => $placeId, 'windy_id' => $id]);
        $row->fill([
            'source' => PlaceWebcam::SOURCE_MANUAL, 'distance_m' => $pair['distance_m'] ?? null, 'match_score' => $pair['score'] ?? 0,
            'score_breakdown' => $pair['breakdown'] ?? null, 'review_status' => PlaceWebcam::PENDING,
        ]);
        $row->save();
        $this->approve($row, $userId);

        return $row->refresh();
    }

    private function assertNotShownElsewhere(PlaceWebcam $row): void
    {
        $other = PlaceWebcam::query()->where('windy_id', $row->windy_id)->where('place_id', '!=', $row->place_id)
            ->whereIn('review_status', PlaceWebcam::VISIBLE)->value('place_id');
        if ($other) {
            throw new InvalidArgumentException("Webcam {$row->windy_id} is already shown on place {$other}. Reject it there first.");
        }
    }

    /** Beach details show the webcam: clear that beach's cache. */
    public function forgetPlace(int $placeId): void
    {
        \App\Support\BeachDetailsCache::forget($placeId);
    }
}
