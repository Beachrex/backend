<?php

/**
 * Script to download and merge country translations from multiple sources
 * into a single JSON file for our database seeder
 */

// Languages we support: en, sl, de, hr, fr, es, it, ua, ru, pl, cs, hu, bg, sk
$languages = [
    'en' => 'en',  // English
    'sl' => 'sl',  // Slovenian
    'de' => 'de',  // German
    'hr' => 'hr',  // Croatian
    'fr' => 'fr',  // French
    'es' => 'es',  // Spanish
    'it' => 'it',  // Italian
    'ua' => 'uk',  // Ukrainian (source uses 'uk')
    'ru' => 'ru',  // Russian
    'pl' => 'pl',  // Polish
    'cs' => 'cs',  // Czech
    'hu' => 'hu',  // Hungarian
    'bg' => 'bg',  // Bulgarian
    'sk' => 'sk',  // Slovak
];

$baseUrl = 'https://raw.githubusercontent.com/stefangabos/world_countries/master/data/countries/';

echo "Downloading country translations...\n";

$allTranslations = [];

foreach ($languages as $ourCode => $sourceCode) {
    $url = $baseUrl . $sourceCode . '/world.json';
    echo "Downloading {$ourCode} ({$sourceCode})... ";
    
    $json = @file_get_contents($url);
    
    if ($json === false) {
        echo "FAILED\n";
        continue;
    }
    
    $data = json_decode($json, true);
    
    if (!$data) {
        echo "INVALID JSON\n";
        continue;
    }
    
    echo "OK (" . count($data) . " countries)\n";
    
    // Process each country
    foreach ($data as $country) {
        $code = strtoupper($country['alpha2']);
        $name = $country['name'];
        
        // Initialize country if not exists
        if (!isset($allTranslations[$code])) {
            $allTranslations[$code] = [];
        }
        
        // Add translation for this language
        $allTranslations[$code][$ourCode] = [
            'name' => $name,
            'full_name' => $name, // We'll use the same for now
        ];
    }
}

echo "\nProcessed " . count($allTranslations) . " countries\n";

// Save to JSON file
$outputPath = __DIR__ . '/../data/country_translations.json';
file_put_contents($outputPath, json_encode($allTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Saved to: {$outputPath}\n";
echo "Done!\n";

