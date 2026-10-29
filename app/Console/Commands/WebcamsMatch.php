<?php

namespace App\Console\Commands;

use App\Services\Webcams\WebcamMatcher;
use Illuminate\Console\Command;

/**
 * TASK-006 §6.3: assign stored Windy webcams to places, locally (no Windy API calls).
 * Weights and thresholds: config/webcams.php. Safe to re-run: reviewed and manual rows are never changed.
 */
class WebcamsMatch extends Command
{
    protected $signature = 'webcams:match
                            {--dry-run : Show the decisions, change nothing}
                            {--country=* : Only places in these countries (ISO-2)}
                            {--place=* : Only these place ids}
                            {--only-missing : Only places without a shown webcam}
                            {--show=20 : How many decisions to print}';

    protected $description = 'Match stored Windy webcams to beaches/places locally (zero API calls)';

    public function handle(WebcamMatcher $matcher): int
    {
        $dry = (bool) $this->option('dry-run');
        $result = $matcher->run([
            'countries' => $this->option('country'),
            'place_ids' => array_map('intval', $this->option('place')),
            'only_missing' => (bool) $this->option('only-missing'),
        ], $dry);

        $this->info(($dry ? '[dry-run] ' : '') . 'webcams:match — radius ' . config('webcams.matching.max_radius_m') . ' m');
        $this->table(['metric', 'count'], collect($result['summary'])->map(fn ($v, $k) => [$k, $v])->values()->all());

        $buckets = ['<40' => 0, '40-69' => 0, '70-84' => 0, '85+' => 0];
        foreach ($result['decisions'] as $d) {
            if (! isset($d['score'])) {
                continue;
            }
            $buckets[match (true) { $d['score'] >= 85 => '85+', $d['score'] >= 70 => '70-84', $d['score'] >= 40 => '40-69', default => '<40' }]++;
        }
        $this->line('Score distribution of assigned webcams: ' . json_encode($buckets));

        $shown = collect($result['decisions'])->filter(fn ($d) => isset($d['score']))->sortByDesc('score')->take((int) $this->option('show'));
        $this->table(['place', 'country', 'windy_id', 'title', 'm', 'score', 'status', 'breakdown'], $shown->map(fn ($d) => [
            "{$d['place_id']} {$d['place']}", $d['country'], $d['windy_id'], mb_strimwidth((string) $d['title'], 0, 50, '…'),
            $d['distance_m'], $d['score'], $d['review_status'],
            json_encode(array_intersect_key($d['breakdown'], array_flip(['distance', 'category', 'category_penalty', 'name', 'name_ratio', 'town', 'runner_up']))),
        ])->all());

        return self::SUCCESS;
    }
}
