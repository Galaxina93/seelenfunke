<?php

namespace App\Livewire\Shop\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use App\Livewire\Traits\WithDepartmentTheming;
use Spatie\Backup\BackupDestination\BackupDestination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

#[Layout('components.layouts.backend_layout')]
class SystemBackups extends Component
{
    use WithDepartmentTheming;
    use \Livewire\WithPagination;

    public string $themingDepartment = 'System';
    #[Locked]
    public $appName;
    #[Locked]
    public $backupName;
    #[Locked]
    public $diskName;
    public $cronjobSchedule;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->appName = config('app.name');
        $this->backupName = config('backup.backup.name');
        $this->diskName = config('backup.backup.destination.disks.0', 'local');
        
        $cronjob = \App\Models\System\SystemCronjob::where('command', 'backup:clean')->first();
        $this->cronjobSchedule = $cronjob ? $cronjob->schedule : 'Nicht konfiguriert';
    }

    private function isValidBackupPath($path): bool
    {
        if (!is_string($path) || $path === '') {
            return false;
        }
        $normalizedPath = str_replace('\\', '/', $path);
        if (str_contains($normalizedPath, '..') || str_contains($normalizedPath, "\0") || str_starts_with($normalizedPath, '/') || !str_ends_with(strtolower($normalizedPath), '.zip')) {
            return false;
        }
        $expectedPrefix = trim((string) ($this->backupName ?: config('backup.backup.name')), '/');
        if ($expectedPrefix !== '' && !str_starts_with($normalizedPath, $expectedPrefix . '/')) {
            return false;
        }
        return true;
    }

    public function getBackupsProperty()
    {
        try {
            $backupDestination = BackupDestination::create($this->diskName, $this->backupName);
            $backups = $backupDestination->backups();
            
            $results = [];
            foreach ($backups as $backup) {
                $results[] = [
                    'path' => $backup->path(),
                    'date' => $backup->date(),
                    'size' => $backup->sizeInBytes(),
                    'sizeFormatted' => $this->formatBytes($backup->sizeInBytes()),
                ];
            }
            
            // Sort by date descending
            usort($results, function ($a, $b) {
                return $b['date'] <=> $a['date'];
            });
            
            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getBackupStatsProperty()
    {
        $backups = $this->getBackupsProperty();
        $count = count($backups);
        $totalSize = array_sum(array_column($backups, 'size'));
        
        return [
            'count' => $count,
            'total_size' => $this->formatBytes($totalSize),
            'newest_date' => $count > 0 ? $backups[0]['date'] : null,
            'newest_size' => $count > 0 ? $backups[0]['sizeFormatted'] : '0 B',
            'oldest_date' => $count > 0 ? end($backups)['date'] : null,
        ];
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public function runTestBackup()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
            session()->flash('success', 'Ein Datenbank-Backup wurde erfolgreich erstellt.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Erstellen des Backups: ' . $e->getMessage());
        }
    }

    public function deleteBackup($path)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        try {
            $normalizedPath = str_replace('\\', '/', (string) $path);
            if (!$this->isValidBackupPath($normalizedPath)) {
                session()->flash('error', 'Ungültiger Backup-Pfad.');
                return;
            }

            if (Storage::disk($this->diskName)->exists($normalizedPath)) {
                Storage::disk($this->diskName)->delete($normalizedPath);
                session()->flash('success', 'Das Backup wurde erfolgreich gelöscht.');
            } else {
                session()->flash('error', 'Das Backup wurde nicht gefunden.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Löschen: ' . $e->getMessage());
        }
    }

    public function downloadBackup($path)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $normalizedPath = str_replace('\\', '/', (string) $path);
        if (!$this->isValidBackupPath($normalizedPath)) {
            session()->flash('error', 'Ungültiger Backup-Pfad.');
            return;
        }

        if (Storage::disk($this->diskName)->exists($normalizedPath)) {
            return Storage::disk($this->diskName)->download($normalizedPath);
        }
        
        session()->flash('error', 'Das Backup wurde nicht gefunden.');
    }

    public function render()
    {
        $allBackups = $this->getBackupsProperty();
        $page = $this->getPage();
        $perPage = 10;
        
        $paginatedBackups = new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($allBackups, ($page - 1) * $perPage, $perPage),
            count($allBackups),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        return view('livewire.shop.system.system-backups', [
            'backups' => $paginatedBackups,
            'stats' => $this->getBackupStatsProperty(),
        ]);
    }
}
