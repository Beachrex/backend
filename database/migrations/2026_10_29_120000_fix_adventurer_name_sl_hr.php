<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Owner, 2026-10-29: the user type "Adventurer" was "Pustolov" in Slovenian and Croatian. Correct forms:
 * Slovenian "Pustolovec", Croatian "Pustolovac" (capitalised like the other user-type labels).
 *
 * The slug follows the name. User-type slugs are not part of any URL or filter (filters use `code`/`id`),
 * so changing them breaks nothing.
 *
 * Idempotent and safe on every environment: each column changes only while it still holds the exact old
 * value, so a name or slug a person edited in the admin stays as it is.
 */
return new class extends Migration
{
    /** locale => [old name, new name, old slug, new slug] */
    public const FIXES = [
        'sl' => ['Pustolov', 'Pustolovec', 'pustolov', 'pustolovec'],
        'hr' => ['Pustolov', 'Pustolovac', 'pustolov', 'pustolovac'],
    ];

    public function up(): void
    {
        $this->apply(fn (array $f) => [$f[0], $f[1], $f[2], $f[3]]);
    }

    public function down(): void
    {
        $this->apply(fn (array $f) => [$f[1], $f[0], $f[3], $f[2]]);
    }

    private function apply(callable $direction): void
    {
        $adventurer = DB::table('user_type')->where('code', 'adventurer')->value('id');
        if (! $adventurer) {
            return;
        }

        foreach (self::FIXES as $locale => $fix) {
            [$fromName, $toName, $fromSlug, $toSlug] = $direction($fix);
            $row = fn () => DB::table('user_type_translation')->where('user_type_id', $adventurer)->where('locale', $locale);

            $row()->where('name', $fromName)->update(['name' => $toName, 'updated_at' => now()]);
            $row()->where('slug', $fromSlug)->update(['slug' => $toSlug, 'updated_at' => now()]);
        }
    }
};
