<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Builds the old-site beach URL map from the old database dump (TASK-004 step 5b).
 *
 * The old beachrex.com stored one English "nice URL" per beach (`beach_nice_url`, e.g.
 * `beach-trstenik-1`); other languages swapped the first word (`plaza-trstenik-1`,
 * `strand-trstenik-1`). Beach IDs were kept by the migration, so nice URL → id is all the
 * redirect resolver needs. The output is committed, so every environment has it without a
 * server step.
 */
class BuildLegacyUrlMap extends Command
{
    protected $signature = 'legacy-urls:build
                            {--source=database/sql/beachrex_old.sql : Old database dump}
                            {--output=database/legacy/old-beach-urls.json : Where to write the map}';

    protected $description = 'Build the old beachrex.com beach URL map (nice URL → beach id) from the old dump';

    public function handle(): int
    {
        $source = base_path($this->option('source'));
        if (!is_file($source)) {
            $this->error("Dump not found: {$source}");
            return self::FAILURE;
        }

        $map = self::parseNiceUrls((string) file_get_contents($source));
        if ($map === []) {
            $this->error('No beach_nice_url rows found.');
            return self::FAILURE;
        }

        $output = base_path($this->option('output'));
        if (!is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }
        ksort($map);
        file_put_contents($output, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->info(sprintf('Wrote %d beach nice URLs to %s', count($map), $this->option('output')));
        return self::SUCCESS;
    }

    /**
     * Parse `beach_nice_url` INSERT rows: (id, 'nice_url', 'language', beach_id).
     * Only English rows hold real nice URLs; the others are junk test rows in the dump.
     *
     * @return array<string,int> nice_url => beach_id
     */
    public static function parseNiceUrls(string $sql): array
    {
        $map = [];
        if (!preg_match_all('/INSERT INTO `beach_nice_url` \([^)]*\) VALUES\s*(.*?);\s*$/ms', $sql, $blocks)) {
            return $map;
        }
        foreach ($blocks[1] as $block) {
            preg_match_all("/\(\s*\d+\s*,\s*'((?:[^'\\\\]|\\\\.)*)'\s*,\s*'(\w+)'\s*,\s*(\d+)\s*\)/", $block, $rows, PREG_SET_ORDER);
            foreach ($rows as [, $niceUrl, $language, $beachId]) {
                if ($language !== 'en' || $niceUrl === '') {
                    continue;
                }
                $map[strtolower(stripslashes($niceUrl))] = (int) $beachId;
            }
        }
        return $map;
    }
}
