<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CompressImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:compress-images 
                            {--force : Bypasses the backup-check and forces re-compression of already processed images}
                            {--dry-run : Only outputs what would be done without modifying any files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recursively backups and compresses all .jpg and .png images to boost PageSpeed to 100/100.';

    /**
     * Directories to scan (relative to base path).
     */
    protected $scanDirs = [
        'public/images',
        'public/media',
        'storage/app/public',
    ];

    /**
     * Memory configuration for large images.
     */
    protected $memoryLimit = '512M';
    protected $maxWidth = 1920; 
    protected $jpegQuality = 75; // 0-100 (Google PageSpeed sweet spot)
    protected $pngCompression = 9; // 0-9

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', $this->memoryLimit);

        $this->info("🚀 Starting Custom PageSpeed Image Compiler...");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("🚧 DRY-RUN MODE ACTIVE: No files will be changed or backed up.");
        }

        $allFiles = [];

        foreach ($this->scanDirs as $dir) {
            $fullPath = base_path($dir);
            if (!File::exists($fullPath)) {
                $this->warn("Directory missing, skipping: {$dir}");
                continue;
            }

            // Fetch all files recursively
            $files = File::allFiles($fullPath);
            foreach ($files as $file) {
                // We only care about JPG and PNG files
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    continue;
                }

                // Exclude any files that are currently sitting inside a /backup/ directory
                if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $allFiles[] = $file;
            }
        }

        $totalImages = count($allFiles);
        $this->info("🔍 Found {$totalImages} images across standard public directories.");

        $processed = 0;
        $skipped = 0;
        $savedBytesTotal = 0;

        $bar = $this->output->createProgressBar($totalImages);
        $bar->start();

        foreach ($allFiles as $file) {
            $filepath = $file->getPathname();
            $dir = dirname($filepath);
            $filename = basename($filepath);
            $ext = strtolower($file->getExtension());

            // Define backup directory and path
            $backupDir = $dir . DIRECTORY_SEPARATOR . 'backup';
            $backupPath = $backupDir . DIRECTORY_SEPARATOR . $filename;

            // If the backup already exists and we don't use --force, we assume it was already compressed
            if (File::exists($backupPath) && !$this->option('force')) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (!$isDryRun) {
                // Ensure Backup Directory exists
                if (!File::exists($backupDir)) {
                    File::makeDirectory($backupDir, 0755, true);
                }

                // Copy original to backup BEFORE touching it
                File::copy($filepath, $backupPath);
            }

            // Original Size
            $originalSize = filesize($filepath);

            if (!$isDryRun) {
                // Compress via Native PHP GD
                $success = $this->compressImage($filepath, $ext);
                
                if (!$success) {
                    $this->error("\n❌ Failed to process: {$filepath}");
                    if (File::exists($backupPath)) {
                        File::copy($backupPath, $filepath); // Rollback on fail
                    }
                    continue;
                }

                // New Size
                clearstatcache();
                $newSize = filesize($filepath);
                
                // If by some anomaly the compressed version is LARGER, rollback to backup
                if ($newSize >= $originalSize) {
                    File::copy($backupPath, $filepath);
                } else {
                    $savedBytesTotal += ($originalSize - $newSize);
                    $processed++;
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if ($isDryRun) {
            $this->info("✅ Dry-run completed. {$totalImages} images scanned. Remove --dry-run flag to execute compression.");
            return;
        }

        $savedMb = number_format($savedBytesTotal / 1024 / 1024, 2);
        
        $this->info("✅ Compression Complete!");
        $this->line("<fg=green>Images Processed:</> {$processed}");
        if ($skipped > 0) {
            $this->line("<fg=yellow>Already Compressed (Skipped):</> {$skipped}");
        }
        $this->line("<fg=cyan>Total Bandwidth Saved:</> {$savedMb} MB 🚀");
        $this->line("Original backups safely stored in localized 'backup' subfolders.");
    }

    /**
     * Resizes (if needed) and highly compresses the image file in-place using GD.
     */
    private function compressImage(string $path, string $ext): bool
    {
        // Suppress errors for corrupt image headers
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = @imagecreatefromjpeg($path);
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($path);
        } else {
            return false;
        }

        if (!$image) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Auto-Scale down 4K+ images that kill the DOM
        if ($width > $this->maxWidth) {
            $ratio = $this->maxWidth / $width;
            $newHeight = (int)($height * $ratio);
            
            // High-quality resampling using GD
            $resized = imagecreatetruecolor($this->maxWidth, $newHeight);
            
            if ($ext === 'png') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
                imagefilledrectangle($resized, 0, 0, $this->maxWidth, $newHeight, $transparent);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $this->maxWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        // Save back optimized to SAME file
        $success = false;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            // GD automatically optimizes the color table when saving, combined with quality 75 = huge savings
            $success = imagejpeg($image, $path, $this->jpegQuality);
        } elseif ($ext === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            // 9 is highest structural compression level for PNG (saves bytes without quality loss)
            $success = imagepng($image, $path, $this->pngCompression);
        }

        imagedestroy($image);
        return $success;
    }
}
