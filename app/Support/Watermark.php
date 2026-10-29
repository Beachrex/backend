<?php

namespace App\Support;

/**
 * The Beachrex logo watermark that Imagor draws on mobile-app photos when they are shown
 * (ADR 2026-10-29 "Watermark mobile-app photos on the fly"). The originals stay untouched.
 *
 * The file is versioned: a new design gets a new file name (v2, ...) and the website switches to it,
 * so a published version never changes and the bucket can be shared by every environment.
 */
final class Watermark
{
    /** The PNG in this repo (white logo + www.beachrex.com, soft shadow, transparent). */
    public const SOURCE = 'resources/watermark/beachrex-watermark-v1.png';

    /** Path as Imagor and the website see it (relative to `public/` on the bucket). */
    public const IMAGOR_PATH = 'img/watermark/beachrex-watermark-v1.png';

    /** Key on the `spaces` disk: website files live under `public/` in the bucket. */
    public static function storageKey(): string
    {
        return 'public/' . self::IMAGOR_PATH;
    }

    public static function sourcePath(): string
    {
        return base_path(self::SOURCE);
    }
}
