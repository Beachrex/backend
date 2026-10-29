<?php

namespace App\Services\Webcams;

/**
 * Finds a Windy webcam id in whatever was pasted: legacy `beach.webcam` embed HTML (Windy or Lookr —
 * Lookr ids are Windy ids), a Windy iframe/player URL, a windy.com webcam page link, or a bare id.
 * Returns null when there is none (empty, "brez", other providers).
 */
class WebcamIdExtractor
{
    /** Windy ids are 10-digit numbers (e.g. 1616668973). */
    private const ID = '(\d{9,11})';

    private const PATTERNS = [
        // <a name="windy-webcam-timelapse-player" | "lkr-timelapse-player" | "lkr-timelapse" ... data-id="…">
        '/name\s*=\s*["\'](?:windy-webcam-timelapse-player|windy-webcam-player-embed|lkr-timelapse(?:-player)?)["\'][^>]*?data-id\s*=\s*["\']'.self::ID.'["\']/i',
        '/data-id\s*=\s*["\']'.self::ID.'["\'][^>]*?name\s*=\s*["\'](?:windy-webcam-timelapse-player|windy-webcam-player-embed|lkr-timelapse(?:-player)?)["\']/i',
        // player URLs: …/embed/player?webcamId=…, …/public/player?webcamId=…
        '/(?:windy\.com|lookr\.com)\/[^\s"\'<>]*[?&]webcamId=' . self::ID . '/i',
        // older iframe form: …/embed/player/{id}/day
        '/(?:windy\.com|lookr\.com)\/[^\s"\'<>]*\/embed\/player\/' . self::ID . '/i',
        // pages: windy.com/webcams/{id}, windy.com/{lang}/-…/webcams/{id}, lookr.com/{id}, lookr.com/lookout/{id}-Name
        '/windy\.com\/(?:[^\s"\'<>]*\/)?webcams\/' . self::ID . '(?!\d)/i',
        '/lookr\.com\/(?:lookout\/)?' . self::ID . '(?!\d)/i',
    ];

    public function extract(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        $text = trim(str_replace(['\\"', "\\'"], ['"', "'"], $raw));
        if ($text === '') {
            return null;
        }
        if (preg_match('/^' . self::ID . '$/', $text, $m)) {
            return (int) $m[1];
        }
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /** Legacy format label for reports. */
    public function format(?string $raw): string
    {
        $text = trim((string) $raw);
        return match (true) {
            $text === '' => 'empty',
            (bool) preg_match('/^bre[zt]?$/i', $text) => 'placeholder',
            str_contains($text, 'windy-webcam') => 'windy_embed',
            str_contains($text, 'lkr-timelapse') => 'lookr_embed',
            $this->extract($text) !== null => 'windy_url',
            default => 'unparseable',
        };
    }
}
