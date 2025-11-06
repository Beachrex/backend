<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        // Check if already populated
        try {
            if (DB::table('countries')->count() > 0) {
                $this->command->info('Countries already seeded. Skipping.');
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist yet, continue with seeding
        }

        // Parse the SQL file to extract country data
        $path = database_path('sql/countries.sql');

        if (!File::exists($path)) {
            $this->command->error("SQL file not found: $path");
            return;
        }

        // Read and parse the SQL file
        $sql = File::get($path);

        // Extract INSERT statement
        preg_match('/INSERT INTO.*?VALUES\s*(.*?);/s', $sql, $matches);

        if (empty($matches[1])) {
            $this->command->error('Could not parse INSERT statement from SQL file.');
            return;
        }

        // Parse country data from SQL
        $countries = $this->parseCountriesFromSQL($matches[1]);

        // Insert countries (without name and full_name)
        $countryRecords = [];
        foreach ($countries as $country) {
            $countryRecords[] = [
                'code' => $country['code'],
                'iso3' => $country['iso3'],
                'number' => $country['number'],
                'continent_code' => $country['continent_code'],
            ];
        }

        DB::table('countries')->insert($countryRecords);
        $this->command->info('Inserted ' . count($countryRecords) . ' countries.');

        // Generate and insert translations
        $translations = $this->generateTranslations($countries);

        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($translations, 500);
        foreach ($chunks as $chunk) {
            DB::table('country_translation')->insert($chunk);
        }

        $this->command->info('Inserted ' . count($translations) . ' country translations.');
    }

    /**
     * Parse countries from SQL INSERT statement
     */
    private function parseCountriesFromSQL(string $valuesString): array
    {
        $countries = [];

        // Match each row: ('XX', 'Name', 'Full Name', 'XXX', number, 'XX')
        preg_match_all("/\('([^']+)',\s*'([^']+)',\s*'([^']+)',\s*'([^']+)',\s*(\d+),\s*'([^']+)'\)/", $valuesString, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $countries[] = [
                'code' => $match[1],
                'name' => str_replace("''", "'", $match[2]),  // Fix escaped quotes
                'full_name' => str_replace("''", "'", $match[3]),
                'iso3' => $match[4],
                'number' => str_pad($match[5], 3, '0', STR_PAD_LEFT),
                'continent_code' => $match[6],
            ];
        }

        return $countries;
    }

    /**
     * Generate translations for all countries in all supported languages
     */
    private function generateTranslations(array $countries): array
    {
        $translations = [];
        $locales = ['en', 'sl', 'de', 'hr', 'fr', 'es', 'it', 'ua', 'ru', 'pl', 'cs', 'hu', 'bg', 'sk'];

        // Get specific translations for countries that have different names
        $specificTranslations = $this->getSpecificTranslations();

        foreach ($countries as $country) {
            $code = $country['code'];

            foreach ($locales as $locale) {
                // Check if we have specific translation for this country and locale
                if (isset($specificTranslations[$code][$locale])) {
                    $name = $specificTranslations[$code][$locale]['name'];
                    $fullName = $specificTranslations[$code][$locale]['full_name'] ?? null;
                } else {
                    // Use English name as fallback
                    $name = $country['name'];
                    $fullName = $country['full_name'];
                }

                $translations[] = [
                    'country_code' => $code,
                    'locale' => $locale,
                    'name' => $name,
                    'full_name' => $fullName,
                    'slug' => Str::slug($name),
                ];
            }
        }

        return $translations;
    }

    /**
     * Get specific translations for ALL countries in all supported languages
     * This method will be replaced with a call to load translations from a JSON file
     */
    private function getSpecificTranslations(): array
    {
        // Load translations from JSON file
        $jsonPath = database_path('seeders/data/country_translations.json');

        if (file_exists($jsonPath)) {
            $json = file_get_contents($jsonPath);
            return json_decode($json, true);
        }

        // Fallback to empty array if file doesn't exist
        return [];
    }
}
