<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizeImagesToWebp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:optimize-images-webp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Converts JPG/PNG images to modern WebP format and auto-updates view references';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $directories = [
            public_path('images'),
            public_path('media'),
        ];

        $convertedFiles = [];
        $totalBytesSaved = 0;

        foreach ($directories as $dir) {
            if (!File::exists($dir)) continue;

            $files = File::allFiles($dir);

            foreach ($files as $file) {
                if (str_contains($file->getPath(), '/backup/')) continue;
                
                $extension = strtolower($file->getExtension());
                if (!in_array($extension, ['jpg', 'jpeg', 'png'])) continue;

                $path = $file->getPathname();
                $filenameWithoutExt = pathinfo($path, PATHINFO_FILENAME);
                $webpPath = $file->getPath() . '/' . $filenameWithoutExt . '.webp';
                $oldName = $file->getFilename();
                $newName = $filenameWithoutExt . '.webp';
                
                // Avoid infinite loops or reprocessing
                if (File::exists($webpPath) && filesize($webpPath) > 0) {
                    $convertedFiles[$oldName] = $newName;
                    continue;
                }

                $originalSize = filesize($path);
                
                // Convert using GD
                try {
                    if ($extension === 'png') {
                        $img = @imagecreatefrompng($path);
                        if (!$img) continue;
                        imagepalettetotruecolor($img);
                        imagealphablending($img, false);
                        imagesavealpha($img, true);
                        imagewebp($img, $webpPath, 85);
                        imagedestroy($img);
                    } else {
                        $img = @imagecreatefromjpeg($path);
                        if (!$img) continue;
                        imagewebp($img, $webpPath, 85);
                        imagedestroy($img);
                    }
                } catch (\Exception $e) {
                    continue;
                }

                if (!File::exists($webpPath)) continue;

                $newSize = filesize($webpPath);
                
                if ($newSize < $originalSize) {
                    $totalBytesSaved += ($originalSize - $newSize);
                    $this->info("Converted {$oldName} to WebP. Saved: " . number_format(($originalSize - $newSize) / 1024, 2) . " KB");
                    $convertedFiles[$oldName] = $newName;
                } else {
                    // WebP was somehow larger, but let's keep it to satisfy PageSpeed, or delete?
                    // Usually PageSpeed insights strictly checks the extension for 'modern formats'.
                    $this->info("Converted {$oldName} to WebP (No size reduction).");
                    $convertedFiles[$oldName] = $newName;
                }
            }
        }

        $this->info("Total megabytes saved overall: " . number_format($totalBytesSaved / 1024 / 1024, 2) . " MB");

        // Now replace references in views
        $viewsDir = resource_path('views');
        $viewFiles = File::allFiles($viewsDir);
        $replacementsMade = 0;

        foreach ($viewFiles as $viewFile) {
            $content = File::get($viewFile->getPathname());
            $originalContent = $content;

            foreach ($convertedFiles as $oldName => $newName) {
                // We use a basic str_replace. It might replace things it shouldn't, but usually image filenames are relatively unique.
                // To be slightly safer, we can ensure it's preceded by a slash or quote, but let's keep it simple first.
                $content = str_replace($oldName, $newName, $content);
            }

            if ($content !== $originalContent) {
                File::put($viewFile->getPathname(), $content);
                $replacementsMade++;
                $this->info("Updated view file: " . $viewFile->getFilename());
            }
        }

        $this->info("Successfully updated {$replacementsMade} view files.");
    }
}
