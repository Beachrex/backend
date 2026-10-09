<?php

namespace App\Services\AI\Itinerary;

use RuntimeException;

class NotEnoughBeachesException extends RuntimeException
{
    public function __construct(public readonly int $found, public readonly int $needed)
    {
        parent::__construct("Only {$found} beach(es) match the request; at least {$needed} are needed.");
    }
}
