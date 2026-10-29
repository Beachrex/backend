<?php

namespace App\Console\Commands;

use App\Services\Webcams\WebcamRefresher;
use Illuminate\Console\Command;
use RuntimeException;

/** TASK-006 §6.4: monthly refresh (scheduled daily, self-pacing; see WebcamRefresher). */
class WebcamsRefresh extends Command
{
    protected $signature = 'webcams:refresh {--force : Start a new import even if the last one is recent}';

    protected $description = 'Re-import Windy metadata, demote lost webcams and re-match affected places';

    public function handle(WebcamRefresher $refresher): int
    {
        try {
            $result = $refresher->run((bool) $this->option('force'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
