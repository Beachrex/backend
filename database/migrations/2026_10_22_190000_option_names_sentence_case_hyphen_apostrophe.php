<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Follow-up to 2026_10_22_180000_option_names_sentence_case: names whose Title Case word follows a hyphen or an
 * apostrophe ("Maître-Nageur", "Scivolo d'Acqua"), found on dev after the first fix. German "FKK-freundlich"
 * is an adjective, so it is lowercase after the hyphen. Same rules: exact match, idempotent, reversible, slugs kept.
 */
return new class extends Migration
{
    /** [table, locale, current name, new name] */
    public const FIXES = [
        ['accommodation_around_translation', 'fr', "Complexe d'Appartements", "Complexe d'appartements"],
        ['location_infrastructure_translation', 'fr', 'Maître-Nageur', 'Maître-nageur'],
        ['location_infrastructure_translation', 'fr', "Escaliers dans l'Eau", "Escaliers dans l'eau"],
        ['sporting_activities_translation', 'fr', "Sauter dans l'Eau", "Sauter dans l'eau"],
        ['recommended_transport_type_translation', 'fr', 'Camping-Car', 'Camping-car'],
        ['location_infrastructure_translation', 'it', "Scivolo d'Acqua", "Scivolo d'acqua"],
        ['location_infrastructure_translation', 'de', 'FKK-Freundlich', 'FKK-freundlich'],
    ];

    public function up(): void
    {
        foreach (self::FIXES as [$table, $locale, $old, $new]) {
            DB::table($table)->where('locale', $locale)->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::FIXES as [$table, $locale, $old, $new]) {
            DB::table($table)->where('locale', $locale)->where('name', $new)->update(['name' => $old]);
        }
    }
};
