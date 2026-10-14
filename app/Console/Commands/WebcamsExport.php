<?php

namespace App\Console\Commands;

use App\Models\PlaceWebcam;
use App\Models\WindyWebcam;
use App\Services\AI\Agent\BeachTools;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * TASK-006: CSV of places with webcam assignments and their website links, for the owner to check.
 * Read-only. Default: webcams shown on the site (approved / auto-approved, usable), English links on the
 * environment's FRONTEND_URL.
 */
class WebcamsExport extends Command
{
    protected $signature = 'webcams:export
                            {--all : Every assignment (pending, rejected, unusable webcams too), not only shown ones}
                            {--country=* : Only these countries (ISO-2)}
                            {--base-url= : Website base URL for the links (default: FRONTEND_URL)}
                            {--locale=en : Website language of the links (URL code, e.g. en, si, de)}';

    protected $description = 'Export places with webcams and their website links to CSV (read-only)';

    public function handle(BeachTools $tools): int
    {
        $urlLocale = (string) $this->option('locale');
        $dbLocale = $urlLocale === 'si' ? 'sl' : $urlLocale;   // website URL code `si` = DB locale `sl`
        if ($this->option('base-url')) {
            config(['app.frontend_url' => $this->option('base-url')]);
        }

        $rows = DB::table('place_webcams as pw')
            ->join('windy_webcams as ww', 'ww.windy_id', '=', 'pw.windy_id')
            ->join('beach as b', 'b.id', '=', 'pw.place_id')
            ->leftJoin('beach_translation as bt', fn ($j) => $j->on('bt.beach_id', '=', 'b.id')->where('bt.locale', $dbLocale))
            ->leftJoin('beach_translation as bt_en', fn ($j) => $j->on('bt_en.beach_id', '=', 'b.id')->where('bt_en.locale', 'en'))
            ->leftJoin('continent_translation as cont_t', fn ($j) => $j->on('cont_t.continent_code', '=', 'b.continent_code')->where('cont_t.locale', $dbLocale))
            ->leftJoin('continent_translation as cont_en', fn ($j) => $j->on('cont_en.continent_code', '=', 'b.continent_code')->where('cont_en.locale', 'en'))
            ->leftJoin('country_translation as ct', fn ($j) => $j->on('ct.country_code', '=', 'b.country_code')->where('ct.locale', $dbLocale))
            ->leftJoin('country_translation as ct_en', fn ($j) => $j->on('ct_en.country_code', '=', 'b.country_code')->where('ct_en.locale', 'en'))
            ->leftJoin('region_translation as rt', fn ($j) => $j->on('rt.region_id', '=', 'b.region_id')->where('rt.locale', $dbLocale))
            ->leftJoin('region_translation as rt_en', fn ($j) => $j->on('rt_en.region_id', '=', 'b.region_id')->where('rt_en.locale', 'en'))
            ->leftJoin('island_translation as it', fn ($j) => $j->on('it.island_id', '=', 'b.island_id')->where('it.locale', $dbLocale))
            ->leftJoin('island_translation as it_en', fn ($j) => $j->on('it_en.island_id', '=', 'b.island_id')->where('it_en.locale', 'en'))
            ->leftJoin('city_translation as cityt', fn ($j) => $j->on('cityt.city_id', '=', 'b.city_id')->where('cityt.locale', $dbLocale))
            ->leftJoin('city_translation as city_en', fn ($j) => $j->on('city_en.city_id', '=', 'b.city_id')->where('city_en.locale', 'en'))
            ->when(! $this->option('all'), fn ($q) => $q->whereIn('pw.review_status', PlaceWebcam::VISIBLE)
                ->whereRaw(WindyWebcam::usableSql('ww')))
            ->when($this->option('country'), fn ($q) => $q->whereIn('b.country_code', array_map('strtoupper', $this->option('country'))))
            ->orderBy('b.country_code')->orderBy('b.id')->orderByDesc('pw.is_primary')
            ->get([
                'b.id', 'b.country_code', 'pw.review_status', 'pw.is_primary', 'pw.source', 'pw.match_score', 'pw.distance_m',
                'ww.windy_id', 'ww.title as webcam_title', 'ww.status as webcam_status', 'ww.embed_status',
                DB::raw('COALESCE(bt.name, bt_en.name, b.name) as name'),
                DB::raw('COALESCE(bt.slug, bt_en.slug) as slug'),
                DB::raw('COALESCE(cont_t.slug, cont_en.slug) as continent_slug'),
                DB::raw('COALESCE(ct.slug, ct_en.slug) as country_slug'),
                DB::raw('COALESCE(rt.slug, rt_en.slug) as region_slug'),
                DB::raw('COALESCE(it.slug, it_en.slug) as island_slug'),
                DB::raw('COALESCE(cityt.slug, city_en.slug) as city_slug'),
            ]);

        $header = ['place_id', 'country', 'beach', 'beach_url', 'windy_id', 'webcam_title', 'windy_url', 'status', 'primary',
            'source', 'score', 'distance_m', 'webcam_status', 'player'];
        $lines = [self::csv($header)];
        foreach ($rows as $r) {
            $lines[] = self::csv([
                $r->id, $r->country_code, $r->name, $tools->beachUrl((array) $r, $urlLocale),
                $r->windy_id, $r->webcam_title, str_replace('{id}', (string) $r->windy_id, config('webcams.public_url')),
                $r->review_status, $r->is_primary ? 'yes' : 'no', $r->source, $r->match_score, $r->distance_m,
                $r->webcam_status, $r->embed_status,
            ]);
        }

        $path = 'webcams/export-' . now()->format('Ymd-His') . '.csv';
        Storage::disk('local')->put($path, implode("\n", $lines) . "\n");

        $this->info('webcams:export — ' . ($this->option('all') ? 'all assignments' : 'webcams shown on the site'));
        $this->line('Rows: ' . $rows->count() . ' · places: ' . $rows->pluck('id')->unique()->count());
        $this->line('File: ' . Storage::disk('local')->path($path));

        return self::SUCCESS;
    }

    private static function csv(array $values): string
    {
        return implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $values));
    }
}
