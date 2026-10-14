<?php

namespace App\Services\Webcams;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks whether a webcam renders in Windy's public embed player (no API key, no API quota).
 * Windy answers 200 for every id; the body tells the cases apart (checked 2026-10-14):
 * "…currently not available" (dead), "…not publicly available" (embedding disabled), or the player page.
 */
class EmbedChecker
{
    public const OK = 'ok';
    public const NOT_PUBLIC = 'not_public';
    public const UNAVAILABLE = 'unavailable';
    public const UNKNOWN = 'unknown';

    public function __construct(private readonly ?\Closure $sleep = null)
    {
    }

    public function check(int $windyId): string
    {
        try {
            $response = Http::timeout(15)->get(config('webcams.player_base_url'), ['webcamId' => $windyId, 'playerType' => 'day']);
        } catch (Throwable) {
            return self::UNKNOWN;
        }
        if (! $response->successful()) {
            return self::UNKNOWN;
        }

        return self::classify($response->body());
    }

    public static function classify(string $body): string
    {
        return match (true) {
            str_contains($body, 'not publicly available') => self::NOT_PUBLIC,
            str_contains($body, 'currently not available') => self::UNAVAILABLE,
            (bool) preg_match('/<title>\s*Webcam player\b/i', $body) => self::OK,
            default => self::UNKNOWN,
        };
    }

    /**
     * Checks the given ids (or, when null, active webcams within the matching radius of a place whose
     * check is missing or older than `embed_check_stale_days`) and stores the result.
     *
     * @return array<string,int> count per result
     */
    public function run(?array $ids = null, ?int $limit = null, bool $dryRun = false): array
    {
        $ids ??= $this->due($limit);
        $counts = ['checked' => 0, self::OK => 0, self::NOT_PUBLIC => 0, self::UNAVAILABLE => 0, self::UNKNOWN => 0];
        if ($dryRun) {
            return ['would_check' => count($ids)] + $counts;
        }
        $delay = (int) config('webcams.import.embed_check_delay_ms');
        foreach (array_values($ids) as $i => $id) {
            if ($i > 0 && $delay > 0) {
                ($this->sleep ?? fn (int $ms) => usleep($ms * 1000))($delay);
            }
            $result = $this->check((int) $id);
            DB::table('windy_webcams')->where('windy_id', $id)->update(['embed_status' => $result, 'embed_checked_at' => now()]);
            $counts['checked']++;
            $counts[$result]++;
        }

        return $counts;
    }

    /** @return list<int> */
    private function due(?int $limit): array
    {
        $stale = now()->subDays((int) config('webcams.import.embed_check_stale_days'));
        $q = DB::table('windy_webcams as w')
            ->where('w.status', 'active')->whereNull('w.missing_since')
            ->where(fn ($q) => $q->whereNull('w.embed_checked_at')->orWhere('w.embed_checked_at', '<', $stale))
            ->whereExists(fn ($s) => $s->from('beach as b')->whereNotNull('b.location')
                ->whereRaw('ST_DWithin(w.location, b.location::geography, ?)', [config('webcams.matching.max_radius_m')]))
            ->orderBy('w.windy_id');
        if ($limit) {
            $q->limit($limit);
        }

        return $q->pluck('w.windy_id')->map(fn ($v) => (int) $v)->all();
    }
}
