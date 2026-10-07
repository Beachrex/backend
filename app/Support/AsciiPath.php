<?php

namespace App\Support;

use Illuminate\Support\Str;
use Normalizer;

/**
 * Turns a storage path (Spaces key, DB path, folder or file name) into an ASCII-only path.
 *
 * Rule (TASK-002): normalise to NFC, transliterate (č→c, ć→c, š→s, ž→z, đ→dj; other scripts
 * via Str::ascii), then per segment: whitespace and anything outside [A-Za-z0-9._-] become "-",
 * repeated "-" collapse. Case and "/" separators are kept. The function is idempotent.
 */
final class AsciiPath
{
    /** Owner rule: čšžć → cszc; đ → dj. Applied before Str::ascii so it wins over its defaults. */
    private const MAP = [
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
        'Č' => 'C', 'Ć' => 'C', 'Š' => 'S', 'Ž' => 'Z', 'Đ' => 'Dj',
    ];

    public static function fromPath(string $path): string
    {
        return implode('/', array_map(self::segment(...), explode('/', $path)));
    }

    /**
     * Storage file name for an admin upload: a ULID plus the ASCII, lowercase extension of the
     * original name. Same as Filament's default naming, but the extension is guaranteed ASCII.
     */
    public static function uploadFileName(string $originalName): string
    {
        $extension = self::fromPath(strtolower(pathinfo($originalName, PATHINFO_EXTENSION)));

        return Str::ulid().($extension === '' ? '' : '.'.$extension);
    }

    public static function isAscii(string $value): bool
    {
        return ! preg_match('/[^\x00-\x7F]/', $value);
    }

    /** "NFC", "NFD", "mixed" (non-ASCII but neither normal form) or "ascii". */
    public static function unicodeForm(string $value): string
    {
        if (self::isAscii($value)) {
            return 'ascii';
        }
        if (Normalizer::isNormalized($value, Normalizer::FORM_C)) {
            return 'NFC';
        }
        if (Normalizer::isNormalized($value, Normalizer::FORM_D)) {
            return 'NFD';
        }

        return 'mixed';
    }

    private static function segment(string $segment): string
    {
        if ($segment === '') {
            return '';
        }

        $nfc = Normalizer::normalize($segment, Normalizer::FORM_C);
        $ascii = Str::ascii(strtr($nfc === false ? $segment : $nfc, self::MAP));
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '-', $ascii);

        return preg_replace('/-{2,}/', '-', $ascii);
    }
}
