<?php

namespace App\Livewire\Shop\Master;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\System\SystemSetting;
use App\Models\System\SystemCronjob;
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
    public array $storageZones = [];
    public float $totalStorageAppGb = 0;
    public int $totalFilesCount = 0;

    public array $actionLog = [];

    /**
     * Storage-related cronjob commands displayed in the compact cleanup monitor.
     */
    protected array $storageCronCommands = [
        'shop:cleanup-orphaned-uploads',
        'backup:clean',
        'backup:run',
    ];

    public function boot(): void
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->threshold1 = (int) Cache::get('storage_capacity_threshold_1', SystemSetting::where('key', 'storage_capacity_threshold_1')->value('value') ?? 70);
        $this->threshold2 = (int) Cache::get('storage_capacity_threshold_2', SystemSetting::where('key', 'storage_capacity_threshold_2')->value('value') ?? 85);
        $this->threshold3 = (int) Cache::get('storage_capacity_threshold_3', SystemSetting::where('key', 'storage_capacity_threshold_3')->value('value') ?? 95);

        $this->calculateCapacity();
    }

    #[Computed]
    public function storageCronjobs()
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('system_cronjobs')) {
                return collect();
            }

            return SystemCronjob::whereIn('command', $this->storageCronCommands)
                ->orderByRaw("CASE command 
                    WHEN 'shop:cleanup-orphaned-uploads' THEN 1 
                    WHEN 'backup:clean' THEN 2 
                    WHEN 'backup:run' THEN 3 
                    ELSE 4 END")
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
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
        $cachedData = Cache::remember('storage_capacity_file_analysis', 300, function () {
            $finder = new \Symfony\Component\Finder\Finder();
            $finder->files()->in(storage_path())->ignoreUnreadableDirs();
            
            $fileData = [];
            $folderData = [];
            $totalStorageAppBytes = 0;
            $totalFilesCount = 0;

            // 3-Zonen-Architektur Tracking
            $zones = [
                'private' => [
                    'key' => 'private',
                    'title' => 'Zone 1: Privat-Tresor',
                    'subtitle' => 'storage/app/ (Nicht öffentlich erreichbar)',
                    'icon' => 'bi-shield-lock-fill',
                    'color' => 'emerald',
                    'description' => 'Hochsensible Geschäftsdaten ohne Symlink nach außen: Buchhaltung, ELSTER ERiC Laufzeit, DHL-Labels, KI-Workspace (Dateitrichter), Verträge & DB-Backups.',
                    'size_bytes' => 0,
                    'files_count' => 0,
                    'departments' => [],
                ],
                'public' => [
                    'key' => 'public',
                    'title' => 'Zone 2: Öffentlicher Shop-Speicher',
                    'subtitle' => 'storage/app/public/ (Via /storage Symlink)',
                    'icon' => 'bi-globe2',
                    'color' => 'cyan',
                    'description' => 'Öffentlich ausgelieferte Assets für Shop & Frontend: Produktmedien, optimierte 3D-Modelle (.glb), Konfigurator-Uploads, Marketing, Linktree & KI-Exporte.',
                    'size_bytes' => 0,
                    'files_count' => 0,
                    'departments' => [],
                ],
                'temp_system' => [
                    'key' => 'temp_system',
                    'title' => 'Zone 3: Temp, Logs & Framework',
                    'subtitle' => 'storage/app/temp/, logs/ & framework/',
                    'icon' => 'bi-recycle',
                    'color' => 'amber',
                    'description' => 'Kurzlebige Laufzeitdaten: Livewire-Uploads (24h TTL), Mail-Anhänge, kompilierte Blade-Views, Sessions und System-Logdateien.',
                    'size_bytes' => 0,
                    'files_count' => 0,
                    'departments' => [],
                ],
            ];

            $privateDeptMeta = [
                'buchhaltung' => ['label' => 'buchhaltung/', 'desc' => 'Rechnungen, Belege, Steuerexporte & ELSTER ERiC C++ Runtime'],
                'bestellungen' => ['label' => 'bestellungen/', 'desc' => 'Geschützte Bestelldokumente & DHL-Versandlabels'],
                'agenten' => ['label' => 'agenten/', 'desc' => 'Privater KI-Workspace (Dateitrichter), Artefakte & Chat-Uploads'],
                'system' => ['label' => 'system/', 'desc' => 'Automatisierte Datenbank-Backups (Spatie Backup)'],
                'leitung' => ['label' => 'leitung/', 'desc' => 'Interne Verträge, Führung & vertrauliche Dokumente'],
            ];

            $publicDeptMeta = [
                'produkte' => ['label' => 'produkte/', 'desc' => 'Produktbilder, Videos, Kategorien & optimierte 3D-Modelle (.glb)'],
                'bestellungen' => ['label' => 'bestellungen/', 'desc' => 'Kunden-Uploads im Produktkonfigurator (cart-uploads)'],
                'system' => ['label' => 'system/', 'desc' => 'Shop-Assets, 3D-Snapshots, Funkenflug-Audio & Profilbilder'],
                'marketing' => ['label' => 'marketing/', 'desc' => 'Blog, Landingpages, WhatsApp-Medien, Vorlagen & Linktree'],
                'agenten' => ['label' => 'agenten/', 'desc' => 'KI-Avatare, TTS-Stimmen & temporäre HTML/PDF-Exporte (7d TTL)'],
                'support' => ['label' => 'support/', 'desc' => 'Kunden-Support Ticket-Anhänge'],
            ];

            $tempDeptMeta = [
                'temp' => ['label' => 'app/temp/', 'desc' => 'Livewire-Uploads, Mail-Anhänge & Backup-Temp (Auto-Purge > 24h)'],
                'logs' => ['label' => 'logs/', 'desc' => 'Laravel System- & Fehlerprotokolle (laravel.log)'],
                'framework' => ['label' => 'framework/', 'desc' => 'Kompilierte Blade-Views, aktive Sessions & App-Cache'],
            ];

            foreach ($finder as $file) {
                $size = $file->getSize();
                $path = str_replace('\\', '/', $file->getRelativePathname());
                $parts = explode('/', $path);
                $topFolder = $parts[0] ?? 'root';
                
                $totalStorageAppBytes += $size;
                $totalFilesCount++;

                if (!isset($folderData[$topFolder])) {
                    $folderData[$topFolder] = 0;
                }
                $folderData[$topFolder] += $size;

                // Classify into the 3 Storage Zones
                if ($topFolder === 'app') {
                    $sub1 = $parts[1] ?? 'root';
                    if ($sub1 === 'public') {
                        $dept = $parts[2] ?? 'root';
                        $zones['public']['size_bytes'] += $size;
                        $zones['public']['files_count']++;
                        if (!isset($zones['public']['departments'][$dept])) {
                            $zones['public']['departments'][$dept] = [
                                'name' => $publicDeptMeta[$dept]['label'] ?? ($dept . '/'),
                                'desc' => $publicDeptMeta[$dept]['desc'] ?? 'Öffentlicher Shop-Ordner',
                                'size_bytes' => 0,
                                'files_count' => 0,
                            ];
                        }
                        $zones['public']['departments'][$dept]['size_bytes'] += $size;
                        $zones['public']['departments'][$dept]['files_count']++;
                    } elseif ($sub1 === 'temp') {
                        $zones['temp_system']['size_bytes'] += $size;
                        $zones['temp_system']['files_count']++;
                        if (!isset($zones['temp_system']['departments']['temp'])) {
                            $zones['temp_system']['departments']['temp'] = [
                                'name' => $tempDeptMeta['temp']['label'],
                                'desc' => $tempDeptMeta['temp']['desc'],
                                'size_bytes' => 0,
                                'files_count' => 0,
                            ];
                        }
                        $zones['temp_system']['departments']['temp']['size_bytes'] += $size;
                        $zones['temp_system']['departments']['temp']['files_count']++;
                    } else {
                        $dept = $sub1;
                        $zones['private']['size_bytes'] += $size;
                        $zones['private']['files_count']++;
                        if (!isset($zones['private']['departments'][$dept])) {
                            $zones['private']['departments'][$dept] = [
                                'name' => $privateDeptMeta[$dept]['label'] ?? ($dept . '/'),
                                'desc' => $privateDeptMeta[$dept]['desc'] ?? 'Privater Tresor-Ordner',
                                'size_bytes' => 0,
                                'files_count' => 0,
                            ];
                        }
                        $zones['private']['departments'][$dept]['size_bytes'] += $size;
                        $zones['private']['departments'][$dept]['files_count']++;
                    }
                } else {
                    $dept = $topFolder;
                    $zones['temp_system']['size_bytes'] += $size;
                    $zones['temp_system']['files_count']++;
                    if (!isset($zones['temp_system']['departments'][$dept])) {
                        $zones['temp_system']['departments'][$dept] = [
                            'name' => $tempDeptMeta[$dept]['label'] ?? ($dept . '/'),
                            'desc' => $tempDeptMeta[$dept]['desc'] ?? 'Laravel System-Ordner',
                            'size_bytes' => 0,
                            'files_count' => 0,
                        ];
                    }
                    $zones['temp_system']['departments'][$dept]['size_bytes'] += $size;
                    $zones['temp_system']['departments'][$dept]['files_count']++;
                }

                $fileData[] = [
                    'name' => basename($path),
                    'path' => $path,
                    'ext' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    'size' => $size,
                    'last_modified' => date("Y-m-d H:i:s", $file->getMTime())
                ];
            }

            // Format zones and sort departments by size descending
            foreach ($zones as &$zone) {
                $zone['size_mb'] = round($zone['size_bytes'] / 1024 / 1024, 2);
                $zone['share_percent'] = $totalStorageAppBytes > 0
                    ? round(($zone['size_bytes'] / $totalStorageAppBytes) * 100, 1)
                    : 0;
                $depts = array_values($zone['departments']);
                foreach ($depts as &$d) {
                    $d['size_mb'] = round($d['size_bytes'] / 1024 / 1024, 2);
                }
                usort($depts, fn($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);
                $zone['departments'] = $depts;
            }
            unset($zone);

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
                'total_files' => $totalFilesCount,
                'top_files' => $topFiles,
                'folders' => $formattedFolders,
                'zones' => array_values($zones),
            ];
        });

        $this->totalStorageAppGb = $cachedData['total_gb'];
        $this->totalFilesCount = $cachedData['total_files'] ?? 0;
        $this->largestFiles = $cachedData['top_files'];
        $this->folderSizes = $cachedData['folders'];
        $this->storageZones = $cachedData['zones'] ?? [];
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
                storage_path('app/temp/livewire-tmp'),
                storage_path('app/temp/mail-attachments'),
                storage_path('app/temp/backup-temp'),
                storage_path('app/system/livewire-tmp'),
                storage_path('app/system/tmp'),
                storage_path('app/public/system/tmp')
            ];

            $freedBytes = 0;
            $deletedFiles = 0;

            foreach ($pathsToClear as $path) {
                if (File::exists($path)) {
                    $finder = new \Symfony\Component\Finder\Finder();
                    $finder->files()->in($path)->ignoreUnreadableDirs();
                    foreach ($finder as $file) {
                        if (str_starts_with($file->getFilename(), '.')) {
                            continue;
                        }
                        $freedBytes += $file->getSize();
                        File::delete($file->getPathname());
                        $deletedFiles++;
                    }
                }
            }

            $freedMb = round($freedBytes / 1024 / 1024, 2);
            $this->actionLog[] = ['type' => 'success', 'msg' => "Temp-Zone: $deletedFiles temporäre Datei(en) gelöscht. $freedMb MB freigegeben."];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Fehler beim Löschen des Temp-Ordners: " . $e->getMessage()];
        }
    }

    public function runOrphanedCleanup()
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403);
        }

        try {
            Artisan::call('shop:cleanup-orphaned-uploads');

            // Update SystemCronjob status if present
            if (\Illuminate\Support\Facades\Schema::hasTable('system_cronjobs')) {
                SystemCronjob::where('command', 'shop:cleanup-orphaned-uploads')->update([
                    'status' => 'success',
                    'last_run_at' => now(),
                ]);
            }

            unset($this->storageCronjobs);
            $this->actionLog[] = ['type' => 'success', 'msg' => 'Müllabfuhr: Verwaiste Uploads (>48h), Temp (>24h), KI-Exporte (>7d) & alte Warenkörbe bereinigt.'];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Fehler bei der Müllabfuhr: ' . $e->getMessage()];
        }
    }

    public function toggleStorageCronjob(string $id)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403);
        }

        $job = SystemCronjob::find($id);
        if ($job && in_array($job->command, $this->storageCronCommands, true)) {
            $job->is_active = !$job->is_active;
            $job->save();
            unset($this->storageCronjobs);

            $stateText = $job->is_active ? 'aktiviert' : 'pausiert';
            $this->actionLog[] = ['type' => $job->is_active ? 'success' : 'warning', 'msg' => "Cronjob '{$job->name}' wurde {$stateText}."];
        }
    }

    public function runStorageCronjob(string $id)
    {
        if (!\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            abort(403);
        }

        $job = SystemCronjob::find($id);
        if (!$job || !in_array($job->command, $this->storageCronCommands, true)) {
            return;
        }

        $job->update(['status' => 'running']);

        try {
            $parameters = $job->parameters ? explode(' ', $job->parameters) : [];
            Artisan::call($job->command, $parameters);

            $job->update([
                'status' => 'success',
                'last_run_at' => now(),
            ]);

            unset($this->storageCronjobs);
            $this->actionLog[] = ['type' => 'success', 'msg' => "Cronjob '{$job->name}' ({$job->command}) erfolgreich manuell ausgeführt."];
            $this->refreshAnalysis();
        } catch (\Exception $e) {
            $job->update([
                'status' => 'error',
                'last_run_at' => now(),
            ]);

            unset($this->storageCronjobs);
            Log::error("Manuelle Speicher-Cronjob-Ausführung fehlgeschlagen: " . $e->getMessage());
            $this->actionLog[] = ['type' => 'critical', 'msg' => "Cronjob '{$job->name}' Fehler: " . $e->getMessage()];
        }
    }

    public function refreshAnalysis()
    {
        $existingLogs = $this->actionLog;
        Cache::forget('storage_capacity_file_analysis');
        $this->calculateCapacity(); // Update total Server disk usage
        if (!empty($existingLogs)) {
            $this->actionLog = $existingLogs;
        }
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
