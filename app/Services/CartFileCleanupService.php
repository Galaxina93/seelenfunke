<?php

namespace App\Services;

use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Management\Mail\MailMessage;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CartFileCleanupService
{
    /**
     * Managed storage folders that hold customer uploads & configurator snapshots.
     */
    protected array $managedDirectories = [
        'bestellungen/cart-uploads',
        'system/snapshots',
        'snapshots',
    ];

    /**
     * Clean up all files associated with a specific cart item if they are not used anywhere else.
     *
     * @param CartItem $item
     * @return array List of deleted file paths
     */
    public function cleanupCartItemFiles(CartItem $item): array
    {
        $deleted = [];
        $filePaths = $this->extractManagedFilePathsFromConfig($item->configuration);

        foreach ($filePaths as $path) {
            $basename = basename($path);

            // Check if file is still used by another active cart item
            $usedInOtherCartItem = CartItem::where('id', '!=', $item->id)
                ->where('configuration', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')
                ->exists();

            if ($usedInOtherCartItem) {
                continue;
            }

            // Check if file is referenced in any placed order
            if ($this->isFileUsedInOrders($basename)) {
                continue;
            }

            // Check if file is referenced in any quote request
            if ($this->isFileUsedInQuotes($basename)) {
                continue;
            }

            // Safe to delete
            if ($this->deleteFileSafely($path)) {
                $deleted[] = $path;
            }
        }

        return $deleted;
    }

    /**
     * Clean up all orphaned files in managed directories that are older than $olderThanHours
     * and not referenced in any active cart, order, or quote.
     *
     * @param int $olderThanHours
     * @param bool $dryRun
     * @return array
     */
    public function cleanupOrphanedFiles(int $olderThanHours = 48, bool $dryRun = false): array
    {
        $cutoffTimestamp = now()->subHours($olderThanHours)->timestamp;
        $disk = Storage::disk('public');

        $scannedCount = 0;
        $deletedCount = 0;
        $bytesFreed = 0;
        $deletedFiles = [];
        $skippedActive = 0;

        foreach ($this->managedDirectories as $dir) {
            if (!$disk->exists($dir)) {
                continue;
            }

            $allFiles = $disk->files($dir);

            foreach ($allFiles as $file) {
                $basename = basename($file);

                // Never touch system/config files, .htaccess or backup folder
                if (str_starts_with($basename, '.') || $basename === 'backup') {
                    continue;
                }

                $scannedCount++;

                // 1. Age check: must be older than threshold
                try {
                    $lastModified = $disk->lastModified($file);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($lastModified > $cutoffTimestamp) {
                    // File is too young; user might still be in active session
                    $skippedActive++;
                    continue;
                }

                // 2. Reference checks: Order, Quote, Cart, ProductTemplate
                if ($this->isFileUsedInOrders($basename) || $this->isFileUsedInQuotes($basename) || $this->isFileUsedInCarts($basename) || $this->isFileUsedInTemplates($basename)) {
                    $skippedActive++;
                    continue;
                }

                // File is genuinely orphaned!
                $fileSize = 0;
                try {
                    $fileSize = $disk->size($file);
                } catch (\Throwable $e) {
                    $fileSize = 0;
                }

                if (!$dryRun) {
                    if ($this->deleteFileSafely($file)) {
                        $deletedCount++;
                        $bytesFreed += $fileSize;
                        $deletedFiles[] = $file;
                    }
                } else {
                    $deletedCount++;
                    $bytesFreed += $fileSize;
                    $deletedFiles[] = $file;
                }
            }
        }

        return [
            'scanned' => $scannedCount,
            'deleted' => $deletedCount,
            'bytes_freed' => $bytesFreed,
            'skipped_active' => $skippedActive,
            'deleted_files' => $deletedFiles,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Clean up expired temporary files, AI exports, and ephemeral chat uploads
     * across both private (local) and public storage disks.
     */
    public function cleanupExpiredTempAndExports(
        bool $dryRun = false,
        int $tempHours = 24,
        int $exportDays = 7,
        int $chatUploadDays = 30
    ): array {
        $rules = [
            // Temp Zone (local disk -> storage/app/temp/*)
            ['disk' => 'local', 'dir' => 'temp/livewire-tmp', 'cutoff' => now()->subHours($tempHours)->timestamp],
            ['disk' => 'local', 'dir' => 'temp/mail-attachments', 'cutoff' => now()->subHours($tempHours)->timestamp],
            ['disk' => 'local', 'dir' => 'temp/backup-temp', 'cutoff' => now()->subHours($tempHours)->timestamp],
            // AI Exports Zone (public disk -> storage/app/public/agenten/exports/*)
            ['disk' => 'public', 'dir' => 'agenten/exports/dossiers', 'cutoff' => now()->subDays($exportDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/exports/holidays', 'cutoff' => now()->subDays($exportDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/exports/maps', 'cutoff' => now()->subDays($exportDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/exports/places', 'cutoff' => now()->subDays($exportDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/exports/reports', 'cutoff' => now()->subDays($exportDays)->timestamp],
            // Ephemeral AI Chat Uploads (both local & public)
            ['disk' => 'local', 'dir' => 'agenten/ai-chat-uploads', 'cutoff' => now()->subDays($chatUploadDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/ai-chat-uploads', 'cutoff' => now()->subDays($chatUploadDays)->timestamp],
            ['disk' => 'local', 'dir' => 'agenten/workspace/chat-medien', 'cutoff' => now()->subDays($chatUploadDays)->timestamp],
            ['disk' => 'public', 'dir' => 'agenten/workspace/chat-medien', 'cutoff' => now()->subDays($chatUploadDays)->timestamp],
        ];

        $scanned = 0;
        $deleted = 0;
        $bytesFreed = 0;
        $deletedFiles = [];

        foreach ($rules as $rule) {
            $disk = Storage::disk($rule['disk']);
            $dir = $rule['dir'];

            if (!$disk->exists($dir)) {
                continue;
            }

            foreach ($disk->allFiles($dir) as $file) {
                $basename = basename($file);
                if (str_starts_with($basename, '.')) {
                    continue;
                }

                $scanned++;

                try {
                    $lastModified = $disk->lastModified($file);
                } catch (\Throwable $e) {
                    continue;
                }

                if ($lastModified > $rule['cutoff']) {
                    continue;
                }

                $size = 0;
                try {
                    $size = $disk->size($file);
                } catch (\Throwable $e) {
                    $size = 0;
                }

                if (!$dryRun) {
                    if ($disk->delete($file)) {
                        $deleted++;
                        $bytesFreed += $size;
                        $deletedFiles[] = $rule['disk'] . ':' . $file;
                    }
                } else {
                    $deleted++;
                    $bytesFreed += $size;
                    $deletedFiles[] = $rule['disk'] . ':' . $file;
                }
            }
        }

        // Clean up orphaned CRM mail-attachment folders whose MailMessage no longer exists
        $localDisk = Storage::disk('local');
        if ($localDisk->exists('leitung/crm/mail-attachments')) {
            foreach ($localDisk->directories('leitung/crm/mail-attachments') as $mailDir) {
                $msgId = basename($mailDir);
                if (!ctype_digit((string) $msgId)) {
                    continue;
                }
                if (!MailMessage::where('id', (int) $msgId)->exists()) {
                    foreach ($localDisk->allFiles($mailDir) as $orphanFile) {
                        $scanned++;
                        $size = 0;
                        try {
                            $size = $localDisk->size($orphanFile);
                        } catch (\Throwable $e) {
                            $size = 0;
                        }
                        $deleted++;
                        $bytesFreed += $size;
                        $deletedFiles[] = 'local:' . $orphanFile;
                    }
                    if (!$dryRun) {
                        $localDisk->deleteDirectory($mailDir);
                    }
                }
            }
        }

        return [
            'scanned' => $scanned,
            'deleted' => $deleted,
            'bytes_freed' => $bytesFreed,
            'deleted_files' => $deletedFiles,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Clean up abandoned carts older than $olderThanDays (e.g. 14 days)
     * and delete their associated files.
     *
     * @param int $olderThanDays
     * @return int Number of deleted carts
     */
    public function cleanupAbandonedCarts(int $olderThanDays = 14): int
    {
        $cutoff = now()->subDays($olderThanDays);
        $deletedCartsCount = 0;

        Cart::where('updated_at', '<', $cutoff)
            ->with('items')
            ->chunkById(100, function ($abandonedCarts) use (&$deletedCartsCount) {
                foreach ($abandonedCarts as $cart) {
                    foreach ($cart->items as $item) {
                        $this->cleanupCartItemFiles($item);
                        $item->delete();
                    }
                    $cart->delete();
                    $deletedCartsCount++;
                }
            });

        return $deletedCartsCount;
    }

    /**
     * Check if a file basename is used in any OrderOrderItem.
     */
    public function isFileUsedInOrders(string $basename): bool
    {
        if (empty($basename)) return false;
        return OrderOrderItem::where('configuration', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')->exists();
    }

    /**
     * Check if a file basename is used in any OrderQuoteRequestItem.
     */
    public function isFileUsedInQuotes(string $basename): bool
    {
        if (empty($basename)) return false;
        return OrderQuoteRequestItem::where('configuration', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')->exists();
    }

    /**
     * Check if a file basename is used in any CartItem.
     */
    public function isFileUsedInCarts(string $basename): bool
    {
        if (empty($basename)) return false;
        return CartItem::where('configuration', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')->exists();
    }

    /**
     * Check if a file basename is used in any ProductTemplate.
     */
    public function isFileUsedInTemplates(string $basename): bool
    {
        if (empty($basename)) return false;
        return ProductTemplate::where('configuration', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')
            ->orWhere('preview_image', 'LIKE', '%' . addcslashes($basename, '%_\\') . '%')
            ->exists();
    }

    /**
     * Extract all managed file paths from a configuration array.
     */
    public function extractManagedFilePathsFromConfig(mixed $config): array
    {
        if (!is_array($config)) {
            return [];
        }

        $paths = [];

        // 1. 'files' key: list of uploaded file paths
        if (!empty($config['files']) && is_array($config['files'])) {
            foreach ($config['files'] as $f) {
                if (is_string($f) && $this->isManagedPath($f)) {
                    $paths[] = $f;
                }
            }
        }

        // 2. 'snapshot_path' key: single string or array ('front' => ..., 'back' => ...)
        if (!empty($config['snapshot_path'])) {
            if (is_string($config['snapshot_path']) && $this->isManagedPath($config['snapshot_path'])) {
                $paths[] = $config['snapshot_path'];
            } elseif (is_array($config['snapshot_path'])) {
                foreach ($config['snapshot_path'] as $snap) {
                    if (is_string($snap) && $this->isManagedPath($snap)) {
                        $paths[] = $snap;
                    }
                }
            }
        }

        // 3. 'logo_path' key
        if (!empty($config['logo_path']) && is_string($config['logo_path']) && $this->isManagedPath($config['logo_path'])) {
            $paths[] = $config['logo_path'];
        }

        // 4. 'logos' array
        if (!empty($config['logos']) && is_array($config['logos'])) {
            foreach ($config['logos'] as $logo) {
                $val = $logo['value'] ?? null;
                if (is_string($val) && $this->isManagedPath($val)) {
                    $paths[] = $val;
                }
            }
        }

        // 5. 'logos_back' array
        if (!empty($config['logos_back']) && is_array($config['logos_back'])) {
            foreach ($config['logos_back'] as $logo) {
                $val = $logo['value'] ?? null;
                if (is_string($val) && $this->isManagedPath($val)) {
                    $paths[] = $val;
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * Check if a relative path belongs to the managed directories and is free of traversal.
     */
    public function isManagedPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', trim($path, " \t\n\r\0\x0B/"));

        if (str_contains($normalized, '..')) {
            return false;
        }

        foreach ($this->managedDirectories as $dir) {
            $normalizedDir = str_replace('\\', '/', trim($dir, " \t\n\r\0\x0B/"));
            if (str_starts_with($normalized, $normalizedDir . '/') || $normalized === $normalizedDir) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete file from public disk safely.
     */
    public function deleteFileSafely(string $path): bool
    {
        if (!$this->isManagedPath($path)) {
            return false;
        }

        $normalized = str_replace('\\', '/', trim($path, " \t\n\r\0\x0B/"));
        $disk = Storage::disk('public');

        if ($disk->exists($normalized)) {
            return $disk->delete($normalized);
        }

        // If path is missing 'system/' prefix or vice versa, check both
        if (str_starts_with($normalized, 'snapshots/')) {
            $altPath = 'system/' . $normalized;
            if ($disk->exists($altPath)) {
                return $disk->delete($altPath);
            }
        }

        return false;
    }
}
