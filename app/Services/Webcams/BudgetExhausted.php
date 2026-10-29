<?php

namespace App\Services\Webcams;

use RuntimeException;

/** The daily Windy request budget is used up; the run stops cleanly and resumes from its checkpoint. */
class BudgetExhausted extends RuntimeException
{
}
