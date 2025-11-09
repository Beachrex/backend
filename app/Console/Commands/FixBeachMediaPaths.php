<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBeachMediaPaths extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beaches:fix-media-paths
                            {--dry-run : Show what would be updated without making changes}
                            {--beach-id= : Fix media for a specific beach ID only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix media file paths for beaches with mobile_upload flag';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $beachId = $this->option('beach-id');

        $this->info('🔍 Scanning for beaches with incorrect media paths...');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Build query for beaches with mobile_upload = true
        $query = Beach::where('mobile_upload', true)
            ->whereNotNull('folder_name');

        if ($beachId) {
            $query->where('id', $beachId);
        }

        $beaches = $query->get();

        if ($beaches->isEmpty()) {
            $this->info('✅ No beaches found with mobile_upload flag');
            return 0;
        }

        $this->info("Found {$beaches->count()} beach(es) with mobile_upload = true");
        $this->newLine();

        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($beaches as $beach) {
            $this->info("Processing Beach ID: {$beach->id} - Folder: {$beach->folder_name}");

            // Get all media for this beach
            $mediaRecords = Media::where('mediable_type', 'App\\Models\\Beach')
                ->where('mediable_id', $beach->id)
                ->get();

            if ($mediaRecords->isEmpty()) {
                $this->line("  ⚠️  No media records found");
                $this->newLine();
                continue;
            }

            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($mediaRecords as $media) {
                // Check if path needs updating
                // Current incorrect path: img/beaches/{folder_name}/{filename}
                // Correct path should be: img/beaches_mobile/{folder_name}/{filename}
                
                if (str_starts_with($media->file_path, 'img/beaches/') && 
                    !str_starts_with($media->file_path, 'img/beaches_mobile/')) {
                    
                    $oldPath = $media->file_path;
                    $newPath = str_replace('img/beaches/', 'img/beaches_mobile/', $oldPath);

                    if ($dryRun) {
                        $this->line("  📝 Would update: {$oldPath} → {$newPath}");
                        $updatedCount++;
                    } else {
                        // Update the path
                        $media->file_path = $newPath;
                        $media->save();
                        
                        $this->line("  ✅ Updated: {$oldPath} → {$newPath}");
                        $updatedCount++;
                    }
                } else {
                    $skippedCount++;
                }
            }

            $totalUpdated += $updatedCount;
            $totalSkipped += $skippedCount;

            if ($updatedCount > 0) {
                $this->info("  Updated {$updatedCount} media record(s)");
            }
            if ($skippedCount > 0) {
                $this->line("  Skipped {$skippedCount} media record(s) (already correct)");
            }
            
            $this->newLine();
        }

        // Summary
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Summary:');
        $this->info("   Beaches processed: {$beaches->count()}");
        $this->info("   Media records updated: {$totalUpdated}");
        $this->info("   Media records skipped: {$totalSkipped}");
        
        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('✅ Media paths have been updated successfully!');
        }

        return 0;
    }
}

