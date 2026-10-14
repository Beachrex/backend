<?php

namespace App\Services\Webcams;

use Illuminate\Support\Str;

/** Normalised, fuzzy "is this place name in that webcam title" check (TASK-006 scoring). */
class NameMatcher
{
    /** @return list<string> lowercase ASCII tokens without stopwords */
    public function tokens(?string $text, array $stopwords): array
    {
        $ascii = strtolower(Str::ascii(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $parts = preg_split('/[^a-z0-9]+/', $ascii, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_flip($stopwords);

        return array_values(array_filter($parts, fn (string $t) => ! isset($stop[$t])));
    }

    /**
     * Best similarity (0–1) between the name and any run of the same number of consecutive title tokens.
     * Similarity = 1 − levenshtein / longer length, on the joined tokens.
     */
    public function ratio(?string $name, ?string $title, array $stopwords): float
    {
        $n = $this->tokens($name, $stopwords);
        $t = $this->tokens($title, $stopwords);
        if ($n === [] || $t === []) {
            return 0.0;
        }
        $needle = implode(' ', $n);
        $best = 0.0;
        $size = count($n);
        for ($i = 0; $i + $size <= count($t); $i++) {
            $best = max($best, $this->similarity($needle, implode(' ', array_slice($t, $i, $size))));
        }

        return $best;
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }
        $max = max(strlen($a), strlen($b));

        return $max === 0 ? 0.0 : 1 - levenshtein($a, $b) / $max;
    }
}
