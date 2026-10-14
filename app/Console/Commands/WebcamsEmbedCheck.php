<?php

namespace App\Console\Commands;

use App\Services\Webcams\EmbedChecker;
use Illuminate\Console\Command;

/** TASK-006: check that webcams near our places render in Windy's public player (no API key, ~1 request/s). */
class WebcamsEmbedCheck extends Command
{
    protected $signature = 'webcams:embed-check
                            {--id=* : Only these webcam ids}
                            {--limit= : At most this many webcams}
                            {--dry-run : Count what would be checked, change nothing}';

    protected $description = 'Check which stored webcams render in the public Windy player';

    public function handle(EmbedChecker $checker): int
    {
        $ids = $this->option('id') ? array_map('intval', $this->option('id')) : null;
        $result = $checker->run($ids, $this->option('limit') ? (int) $this->option('limit') : null, (bool) $this->option('dry-run'));
        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . 'webcams:embed-check');
        $this->table(['result', 'count'], collect($result)->map(fn ($v, $k) => [$k, $v])->values()->all());

        return self::SUCCESS;
    }
}
