<?php

namespace App\Console\Commands;

use App\Services\Webcams\EmbedChecker;
use App\Services\Webcams\PlaceWebcamReview;
use App\Services\Webcams\WebcamRefresher;
use Illuminate\Console\Command;

/** TASK-006: check that webcams near our places render in Windy's public player (no API key, ~1 request/s). */
class WebcamsEmbedCheck extends Command
{
    protected $signature = 'webcams:embed-check
                            {--id=* : Only these webcam ids}
                            {--shown : Only webcams shown on a place, however recently checked (daily from the scheduler)}
                            {--limit= : At most this many webcams}
                            {--dry-run : Count what would be checked, change nothing}';

    protected $description = 'Check which stored webcams render in the public Windy player';

    public function handle(EmbedChecker $checker, WebcamRefresher $refresher, PlaceWebcamReview $review): int
    {
        $ids = $this->option('id') ? array_map('intval', $this->option('id')) : null;
        if ($ids === null && $this->option('shown')) {
            $ids = $checker->shownIds();
        }
        $dryRun = (bool) $this->option('dry-run');
        $result = $checker->run($ids, $this->option('limit') ? (int) $this->option('limit') : null, $dryRun);
        $this->info(($dryRun ? '[dry-run] ' : '') . 'webcams:embed-check');
        $this->table(['result', 'count'], collect($result)->map(fn ($v, $k) => [$k, $v])->values()->all());

        if (! $dryRun && $checker->changedPlaces !== []) {
            // A shown webcam that stopped rendering loses primary; its place's page is rebuilt without it.
            $demoted = $refresher->demote();
            $places = array_values(array_unique(array_merge($checker->changedPlaces, $demoted['places'])));
            foreach ($places as $placeId) {
                $review->forgetPlace($placeId);
            }
            $this->info('Places whose shown webcam changed: ' . implode(', ', $checker->changedPlaces)
                . " (cache cleared; {$demoted['lost']} lost their main webcam)");
        }

        return self::SUCCESS;
    }
}
