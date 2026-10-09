<?php

namespace App\Support;

/**
 * Copyright metadata written into uploaded JPEG photos (owner, 2026-10-08).
 *
 * Photos on Beachrex are works of MOJA PLAŽA, d.o.o. (terms of use §6). The IPTC block names the
 * company as creator and copyright holder, so a downloaded original shows its owner. Uses PHP's
 * built-in iptcembed(): no image library, the pixels are not re-encoded. Other formats (PNG, WebP)
 * are left as they are.
 *
 * Note: resized and WebP copies are made on the fly by Imagor (s3.beachrex.com); whether those
 * keep the metadata depends on Imagor's settings. The originals on Spaces keep it.
 */
final class ImageCopyright
{
    public const HOLDER = 'MOJA PLAŽA, d.o.o.';

    public const CREDIT = 'Beachrex';

    /** IPTC dataset 2:116 Copyright notice. */
    public static function notice(?int $year = null): string
    {
        return '© ' . ($year ?? (int) date('Y')) . ' ' . self::HOLDER;
    }

    /** True for JPEG bytes (the only format iptcembed supports). */
    public static function isJpeg(string $bytes): bool
    {
        return str_starts_with($bytes, "\xFF\xD8\xFF");
    }

    /** True when the JPEG already carries our copyright notice (any year). */
    public static function hasNotice(string $bytes): bool
    {
        $iptc = self::readIptc($bytes);
        $notice = $iptc['2#116'][0] ?? '';

        return str_contains($notice, self::HOLDER);
    }

    /**
     * The JPEG with the copyright IPTC block, or null when the bytes are not a JPEG or embedding fails.
     * An existing IPTC block is replaced (iptcembed writes one APP13 segment).
     */
    public static function embed(string $bytes, ?int $year = null): ?string
    {
        if (!self::isJpeg($bytes)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'br_iptc_');
        try {
            file_put_contents($tmp, $bytes);
            $result = @iptcembed(self::iptcBlock($year), $tmp);

            return is_string($result) && self::isJpeg($result) ? $result : null;
        } finally {
            @unlink($tmp);
        }
    }

    /** @return array<string, array<int, string>> */
    public static function readIptc(string $bytes): array
    {
        if (!self::isJpeg($bytes)) {
            return [];
        }
        $info = [];
        @getimagesizefromstring($bytes, $info);
        if (!isset($info['APP13'])) {
            return [];
        }
        $parsed = @iptcparse($info['APP13']);

        return is_array($parsed) ? $parsed : [];
    }

    private static function iptcBlock(?int $year): string
    {
        $fields = [
            '1#090' => "\x1B%G", // coded character set: UTF-8
            '2#080' => self::HOLDER, // By-line (creator)
            '2#110' => self::CREDIT, // Credit line
            '2#115' => self::CREDIT, // Source
            '2#116' => self::notice($year), // Copyright notice
        ];

        $block = '';
        foreach ($fields as $tag => $value) {
            [$record, $dataset] = array_map('intval', explode('#', $tag));
            $block .= self::dataset($record, $dataset, $value);
        }

        return $block;
    }

    /** One IPTC-IIM dataset: 0x1C, record, dataset, 2-byte length, value. */
    private static function dataset(int $record, int $dataset, string $value): string
    {
        return chr(0x1C) . chr($record) . chr($dataset) . pack('n', strlen($value)) . $value;
    }
}
