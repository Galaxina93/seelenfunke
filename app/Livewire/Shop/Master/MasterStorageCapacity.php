<?php

namespace App\Livewire\Shop\Master;

use Livewire\Component;
use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class MasterStorageCapacity extends Component
{
    public int $threshold1 = 70;
    public int $threshold2 = 85;
    public int $threshold3 = 95;

    public float $percentage = 0.0;
    public int $level = 0;
    
    public float $totalSpaceGb = 0;
    public float $freeSpaceGb = 0;
    public float $usedSpaceGb = 0;

    public array $largestFiles = [];
    public array $folderSizes = [];
    public float $totalStorageAppGb = 0;

    public array $actionLog = [];

    public function mount()
    {
        $this->threshold1 = (int) Cache::get('storage_capacity_threshold_1', SystemSetting::where('key', 'storage_capacity_threshold_1')->value('value') ?? 70);
        $this->threshold2 = (int) Cache::get('storage_capacity_threshold_2', SystemSetting::where('key', 'storage_capacity_threshold_2')->value('value') ?? 85);
        $this->threshold3 = (int) Cache::get('storage_capacity_threshold_3', SystemSetting::where('key', 'storage_capacity_threshold_3')->value('value') ?? 95);

        $this->calculateCapacity();
    }

    public function calculateCapacity()
    {
        // Absolute Disk Space
        $storagePath = storage_path();
        $totalBytes = disk_total_space($storagePath);
        $freeBytes = disk_free_space($storagePath);
        $usedBytes = $totalBytes - $freeBytes;

        $this->totalSpaceGb = round($totalBytes / 1024 / 1024 / 1024, 2);
        $this->freeSpaceGb = round($freeBytes / 1024 / 1024 / 1024, 2);
        $this->usedSpaceGb = round($usedBytes / 1024 / 1024 / 1024, 2);

        $this->percentage = $this->totalSpaceGb > 0 ? round(($this->usedSpaceGb / $this->totalSpaceGb) * 100, 1) : 0;

        // Level (0-4)
        if ($this->percentage < $this->threshold1) {
            $this->level = 0;
        } elseif ($this->percentage < $this->threshold2) {
            $this->level = 1;
        } elseif ($this->percentage < $this->threshold3) {
            $this->level = 2;
        } elseif ($this->percentage < 100) {
            $this->level = 3;
        } else {
            $this->level = 4;
        }

        $this->generateActionLog();
    }

    public function loadStorageDetails()
    {
        // Cache this for 5 minutes because `File::allFiles` can take up to a second.
        $cachedData = Cache::remember('storage_capacity_file_analysis', 300, function () {
            $allFiles = File::allFiles(storage_path());
            
            $fileData = [];
            $folderData = [];
            $totalStorageAppBytes = 0;

            foreach ($allFiles as $file) {
                $size = $file->getSize();
                $path = $file->getRelativePathname();
                $topFolder = explode('/', $path)[0] ?? 'root';
                
                $totalStorageAppBytes += $size;

                if (!isset($folderData[$topFolder])) {
                    $folderData[$topFolder] = 0;
                }
                $folderData[$topFolder] += $size;

                $fileData[] = [
                    'name' => basename($path),
                    'path' => $path,
                    'size' => $size,
                    'last_modified' => date("Y-m-d H:i:s", $file->getMTime())
                ];
            }

            // Top 10 files by size
            usort($fileData, fn($a, $b) => $b['size'] <=> $a['size']);
            $topFiles = array_slice($fileData, 0, 10);

            // Format to MB
            foreach ($topFiles as &$f) {
                $f['size_mb'] = round($f['size'] / 1024 / 1024, 2);
            }

            // Format folders to MB
            $formattedFolders = [];
            foreach ($folderData as $folder => $size) {
                $formattedFolders[] = [
                    'name' => $folder,
                    'size_mb' => round($size / 1024 / 1024, 2)
                ];
            }
            usort($formattedFolders, fn($a, $b) => $b['size_mb'] <=> $a['size_mb']);

            return [
                'total_gb' => round($totalStorageAppBytes / 1024 / 1024 / 1024, 2),
                'top_files' => $topFiles,
                'folders' => $formattedFolders
            ];
        });

        $this->totalStorageAppGb = $cachedData['total_gb'];
        $this->largestFiles = $cachedData['top_files'];
        $this->folderSizes = $cachedData['folders'];
    }

    public function clearLaravelLogs()
    {
        try {
            $logPath = storage_path('logs');
            $files = File::files($logPath);
            $deleted = 0;
            $freedBytes = 0;

            foreach ($files as $file) {
                if ($file->getExtension() === 'log') {
                    $freedBytes += $file->getSize();
                    File::delete($file->getPathname());
                    $deleted++;
                }
            }

            $freedMb = round($freedBytes / 1024 / 1024, 2);
            $this->actionLog[] = ['type' => 'success', 'msg' => "System: $deleted Log-Datei(en) gelöscht. $freedMb MB freigegeben."];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Fehler beim Löschen der Logs: " . $e->getMessage()];
        }
    }

    public function clearFrameworkCache()
    {
        try {
            Artisan::call('view:clear');
            // Artisan::call('cache:clear'); removed because it can delete user sessions!
            
            $this->actionLog[] = ['type' => 'success', 'msg' => 'System: Laravel Blade Views gereinigt. Speicher freigegeben.'];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Fehler beim Cache-Clear: " . $e->getMessage()];
        }
    }

    public function clearTempDirectory()
    {
        try {
            $pathsToClear = [
                storage_path('app/system/livewire-tmp'),
                storage_path('app/system/tmp'),
                storage_path('app/public/system/tmp')
            ];

            $freedBytes = 0;
            $deletedFiles = 0;

            foreach ($pathsToClear as $path) {
                if (File::exists($path)) {
                    $files = File::allFiles($path);
                    foreach($files as $file) {
                        $freedBytes += $file->getSize();
                        File::delete($file->getPathname());
                        $deletedFiles++;
                    }
                }
            }

            $freedMb = round($freedBytes / 1024 / 1024, 2);
            $this->actionLog[] = ['type' => 'success', 'msg' => "System: $deletedFiles temporäre Datei(en) gelöscht. $freedMb MB freigegeben."];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Fehler beim Löschen des Temp-Ordners: " . $e->getMessage()];
        }
    }

    public function refreshAnalysis()
    {
        Cache::forget('storage_capacity_file_analysis');
        $this->calculateCapacity(); // Update total Server disk usage
        $this->loadStorageDetails(); // Load specific file sizes
    }

    public function updateThresholds($t1, $t2, $t3)
    {
        $this->threshold1 = max(1, min(100, (int)$t1));
        $this->threshold2 = max($this->threshold1 + 1, min(100, (int)$t2));
        $this->threshold3 = max($this->threshold2 + 1, min(100, (int)$t3));

        SystemSetting::updateOrCreate(['key' => 'storage_capacity_threshold_1'], ['value' => $this->threshold1]);
        SystemSetting::updateOrCreate(['key' => 'storage_capacity_threshold_2'], ['value' => $this->threshold2]);
        SystemSetting::updateOrCreate(['key' => 'storage_capacity_threshold_3'], ['value' => $this->threshold3]);

        Cache::put('storage_capacity_threshold_1', $this->threshold1);
        Cache::put('storage_capacity_threshold_2', $this->threshold2);
        Cache::put('storage_capacity_threshold_3', $this->threshold3);
        
        $this->calculateCapacity();
    }

    private function generateActionLog()
    {
        $this->actionLog = [];

        if ($this->level === 0) {
            $this->actionLog[] = ['type' => 'success', 'msg' => 'Serverfestplatte meldet intakte Platzreserven.'];
        }

        if ($this->level >= 1) {
            $this->actionLog[] = ['type' => 'warning', 'msg' => "Speicherbedarf hat Warnstufe 1 ({$this->threshold1}%) überschritten."];
        }

        if ($this->level >= 2) {
            $this->actionLog[] = ['type' => 'danger', 'msg' => "Speicherbedarf hat Warnstufe 2 ({$this->threshold2}%) erreicht. Bereinigung empfohlen."];
        }

        if ($this->level >= 3) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Kritischer Mangel an Harddrive Space ({$this->threshold3}%+). I/O Failures möglich!"];
        }

        if ($this->level === 4) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Festplattenkapazität bei 100%! Systemabsturz steht unmittelbar bevor.'];
        }
    }

    public function render()
    {
        return view('livewire.shop.master.master-storage-capacity');
    }
}
