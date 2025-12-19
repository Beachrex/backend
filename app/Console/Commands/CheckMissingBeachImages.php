<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckMissingBeachImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beaches:check-missing-images
                            {--output= : Output file path for the report (default: missing_images_report.txt)}
                            {--beach-id= : Check specific beach ID only}
                            {--folder-name= : Check specific folder name only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for missing beach images in DigitalOcean Spaces and generate a report. Handles special characters (č, š, ž) with Unicode normalization.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $outputFile = $this->option('output') ?: 'missing_images_report.txt';
        $beachId = $this->option('beach-id');
        $folderName = $this->option('folder-name');

        $this->info('🔍 Checking for missing beach images in DigitalOcean Spaces...');
        $this->newLine();

        // Get all beaches with media
        $query = Beach::whereNotNull('folder_name')
            ->whereHas('media', function ($q) {
                $q->where('status_id', 1)
                  ->whereHas('mediaType', function ($mt) {
                      $mt->where('code', 'image');
                  });
            });

        if ($beachId) {
            $query->where('id', $beachId);
        }

        if ($folderName) {
            $query->where('folder_name', $folderName);
        }

        $beaches = $query->with(['media' => function ($q) {
            $q->where('status_id', 1)
              ->whereHas('mediaType', function ($mt) {
                  $mt->where('code', 'image');
              })
              ->with('mediaType');
        }])->get();

        if ($beaches->isEmpty()) {
            $this->warn('⚠️  No beaches found with active images');
            return 0;
        }

        $this->info("Found {$beaches->count()} beach(es) to check");
        $this->newLine();

        $disk = Storage::disk('spaces');
        $beachesWithMissingImages = [];
        $totalBeaches = 0;
        $totalImages = 0;
        $totalMissing = 0;
        $totalFound = 0;

        $progressBar = $this->output->createProgressBar($beaches->count());
        $progressBar->start();

        foreach ($beaches as $beach) {
            $totalBeaches++;
            $missingImages = [];
            $foundImages = 0;
            $beachImageCount = 0;

            foreach ($beach->media as $media) {
                // Only check images
                if ($media->mediaType->code !== 'image') {
                    continue;
                }

                $totalImages++;
                $beachImageCount++;

                // Check if file exists in DigitalOcean Spaces
                // The file_path is stored without 'public/' prefix in DB
                // But in Spaces it's stored with 'public/' prefix
                $pathToCheck = 'public/' . ltrim($media->file_path, '/');

                try {
                    $fileExists = false;

                    // Try the path as-is first
                    if ($disk->exists($pathToCheck)) {
                        $fileExists = true;
                    } else {
                        // Special characters (č, š, ž, etc.) can be stored in different Unicode normalization forms
                        // Try both NFC (composed) and NFD (decomposed) forms

                        // Try NFC normalization (composed form: č as single character)
                        $pathNFC = \Normalizer::normalize($pathToCheck, \Normalizer::NFC);
                        if ($pathNFC !== $pathToCheck && $disk->exists($pathNFC)) {
                            $fileExists = true;
                        }

                        // Try NFD normalization (decomposed form: c + combining caron)
                        if (!$fileExists) {
                            $pathNFD = \Normalizer::normalize($pathToCheck, \Normalizer::NFD);
                            if ($pathNFD !== $pathToCheck && $disk->exists($pathNFD)) {
                                $fileExists = true;
                            }
                        }
                    }

                    if ($fileExists) {
                        $foundImages++;
                        $totalFound++;
                    } else {
                        $missingImages[] = [
                            'media_id' => $media->id,
                            'file_name' => $media->file_name,
                            'file_path' => $media->file_path,
                            'expected_path' => $pathToCheck,
                            'is_primary' => $media->is_primary,
                        ];
                        $totalMissing++;
                    }
                } catch (\Exception $e) {
                    $missingImages[] = [
                        'media_id' => $media->id,
                        'file_name' => $media->file_name,
                        'file_path' => $media->file_path,
                        'expected_path' => $pathToCheck,
                        'is_primary' => $media->is_primary,
                        'error' => $e->getMessage(),
                    ];
                    $totalMissing++;
                }
            }

            if (!empty($missingImages)) {
                $beachesWithMissingImages[] = [
                    'beach_id' => $beach->id,
                    'folder_name' => $beach->folder_name,
                    'mobile_upload' => $beach->mobile_upload,
                    'total_images' => $beachImageCount,
                    'found_images' => $foundImages,
                    'missing_images' => $missingImages,
                ];
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Generate report
        $this->info('📊 Generating report...');
        $this->newLine();

        $reportContent = $this->generateReport($beachesWithMissingImages, $totalBeaches, $totalImages, $totalFound, $totalMissing);

        // Save to file
        file_put_contents($outputFile, $reportContent);

        // Display summary
        $this->displaySummary($beachesWithMissingImages, $totalBeaches, $totalImages, $totalFound, $totalMissing);

        $this->newLine();
        $this->info("📄 Full report saved to: {$outputFile}");

        return 0;
    }

    /**
     * Generate the report content
     */
    private function generateReport(array $beachesWithMissing, int $totalBeaches, int $totalImages, int $totalFound, int $totalMissing): string
    {
        $report = "MISSING BEACH IMAGES REPORT\n";
        $report .= "Generated: " . now()->toDateTimeString() . "\n";
        $report .= str_repeat("=", 80) . "\n\n";

        $report .= "SUMMARY:\n";
        $report .= "  Total beaches checked: {$totalBeaches}\n";
        $report .= "  Total images checked: {$totalImages}\n";
        $report .= "  Images found: {$totalFound}\n";
        $report .= "  Images missing: {$totalMissing}\n";
        $report .= "  Beaches with missing images: " . count($beachesWithMissing) . "\n";
        $report .= str_repeat("=", 80) . "\n\n";

        if (empty($beachesWithMissing)) {
            $report .= "✅ All images found! No missing images detected.\n";
            return $report;
        }

        $report .= "BEACHES WITH MISSING IMAGES:\n";
        $report .= str_repeat("-", 80) . "\n\n";

        foreach ($beachesWithMissing as $beach) {
            $report .= "Beach ID: {$beach['beach_id']}\n";
            $report .= "Folder Name: {$beach['folder_name']}\n";
            $report .= "Mobile Upload: " . ($beach['mobile_upload'] ? 'Yes' : 'No') . "\n";
            $report .= "Total Images: {$beach['total_images']}\n";
            $report .= "Found: {$beach['found_images']}\n";
            $report .= "Missing: " . count($beach['missing_images']) . "\n";
            $report .= "\nMissing Files:\n";

            foreach ($beach['missing_images'] as $missing) {
                $report .= "  - Media ID: {$missing['media_id']}\n";
                $report .= "    File: {$missing['file_name']}\n";
                $report .= "    Path: {$missing['file_path']}\n";
                $report .= "    Expected: {$missing['expected_path']}\n";
                $report .= "    Primary: " . ($missing['is_primary'] ? 'Yes' : 'No') . "\n";
                if (isset($missing['error'])) {
                    $report .= "    Error: {$missing['error']}\n";
                }
                $report .= "\n";
            }

            $report .= str_repeat("-", 80) . "\n\n";
        }

        // Add folder names list at the end
        $report .= "\nFOLDER NAMES WITH MISSING IMAGES (for easy reference):\n";
        $report .= str_repeat("-", 80) . "\n";
        foreach ($beachesWithMissing as $beach) {
            $report .= "{$beach['folder_name']}\n";
        }

        // Add missing image file names list
        $report .= "\n\nMISSING IMAGE FILE NAMES (for easy reference):\n";
        $report .= str_repeat("-", 80) . "\n";
        $allMissingFileNames = [];
        foreach ($beachesWithMissing as $beach) {
            foreach ($beach['missing_images'] as $missing) {
                $allMissingFileNames[] = $missing['file_name'];
            }
        }
        // Remove duplicates and sort
        $allMissingFileNames = array_unique($allMissingFileNames);
        sort($allMissingFileNames);
        foreach ($allMissingFileNames as $fileName) {
            $report .= "{$fileName}\n";
        }

        // Add missing image full paths list
        $report .= "\n\nMISSING IMAGE FULL PATHS (for easy reference):\n";
        $report .= str_repeat("-", 80) . "\n";
        $allMissingPaths = [];
        foreach ($beachesWithMissing as $beach) {
            foreach ($beach['missing_images'] as $missing) {
                $allMissingPaths[] = $missing['file_path'];
            }
        }
        // Remove duplicates and sort
        $allMissingPaths = array_unique($allMissingPaths);
        sort($allMissingPaths);
        foreach ($allMissingPaths as $path) {
            $report .= "{$path}\n";
        }

        return $report;
    }

    /**
     * Display summary in console
     */
    private function displaySummary(array $beachesWithMissing, int $totalBeaches, int $totalImages, int $totalFound, int $totalMissing): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 SUMMARY:');
        $this->info("   Total beaches checked: {$totalBeaches}");
        $this->info("   Total images checked: {$totalImages}");
        $this->info("   Images found: {$totalFound}");

        if ($totalMissing > 0) {
            $this->error("   Images missing: {$totalMissing}");
            $this->error("   Beaches with missing images: " . count($beachesWithMissing));
        } else {
            $this->info("   Images missing: {$totalMissing}");
            $this->info('   ✅ All images found!');
        }

        if (!empty($beachesWithMissing)) {
            $this->newLine();
            $this->warn('⚠️  Beaches with missing images:');
            foreach ($beachesWithMissing as $beach) {
                $missingCount = count($beach['missing_images']);
                $this->line("   • {$beach['folder_name']} (Beach ID: {$beach['beach_id']}) - {$missingCount} missing");
            }
        }
    }
}

