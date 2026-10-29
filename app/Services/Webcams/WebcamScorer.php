<?php

namespace App\Services\Webcams;

/**
 * Scores one webcam candidate for one place with the weights in config/webcams.php.
 * Pure: no database, no API. Returns the score and a breakdown that explains it.
 */
class WebcamScorer
{
    public const AUTO = 'auto_approved';
    public const PENDING = 'pending';
    public const NONE = 'none';

    public function __construct(private readonly NameMatcher $names = new NameMatcher())
    {
    }

    /** Profile key for a place by its waterbody id. */
    public function profileFor(?int $waterbodyId): string
    {
        $default = 'beach';
        foreach (config('webcams.profiles') as $key => $profile) {
            if (! empty($profile['default'])) {
                $default = $key;
            }
            if ($waterbodyId !== null && in_array($waterbodyId, $profile['waterbody_ids'] ?? [], true)) {
                return $key;
            }
        }

        return $default;
    }

    /**
     * @param  list<string>  $placeNames  the place's names (all locales)
     * @param  list<string>  $townNames  the place's town names (all locales)
     * @param  list<string>  $categories  Windy category ids
     * @return array{score:int, breakdown:array<string,mixed>}
     */
    public function score(array $placeNames, array $townNames, string $profile, ?string $title, array $categories, int $distanceM): array
    {
        $cfg = config('webcams.matching');
        $prof = config("webcams.profiles.{$profile}");
        $breakdown = ['profile' => $profile, 'distance_m' => $distanceM];
        $score = 0;

        $distancePoints = 0;
        foreach ($cfg['distance_points'] as $limit => $points) {
            if ($distanceM <= $limit) {
                $distancePoints = $points;
                break;
            }
        }
        $score += $distancePoints;
        $breakdown['distance'] = $distancePoints;

        $plus = 0;
        $minus = 0;
        foreach ($categories as $category) {
            $points = $prof['category_points'][$category] ?? 0;
            $plus = max($plus, $points);
            $minus = min($minus, $points);
        }
        $score += $plus + $minus;
        $breakdown['categories'] = array_values($categories);
        $breakdown['category'] = $plus;
        $breakdown['category_penalty'] = $minus;

        $ratio = 0.0;
        foreach (array_unique(array_filter($placeNames)) as $name) {
            $ratio = max($ratio, $this->names->ratio($name, $title, $prof['stopwords']));
        }
        $breakdown['name_ratio'] = round($ratio, 2);
        $nameHit = $ratio >= $cfg['name_fuzzy_ratio'];
        $breakdown['name'] = $nameHit ? $cfg['name_match_points'] : 0;
        $score += $breakdown['name'];

        $town = 0;
        if (! $nameHit) {
            foreach (array_unique(array_filter($townNames)) as $townName) {
                if ($this->names->ratio($townName, $title, []) >= $cfg['name_fuzzy_ratio']) {
                    $town = $cfg['town_match_points'];
                    break;
                }
            }
        }
        $breakdown['town'] = $town;
        $score += $town;

        $breakdown['score'] = $score;

        return ['score' => $score, 'breakdown' => $breakdown];
    }

    /** Review status for a candidate given the best other candidate's score for the same place. */
    public function decide(int $score, ?int $runnerUpScore): string
    {
        $cfg = config('webcams.matching');
        if ($score < $cfg['pending_min']) {
            return self::NONE;
        }
        $clear = $runnerUpScore === null || $score - $runnerUpScore >= $cfg['auto_approve_margin'];

        return $score >= $cfg['auto_approve_min'] && $clear ? self::AUTO : self::PENDING;
    }
}
