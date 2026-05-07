<?php

namespace App\Livewire\Shop\System;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Traits\WithDepartmentTheming;
use Spatie\Backup\BackupDestination\BackupDestination;
use Illuminate\Support\Facades\Storage;

#[Layout('components.layouts.backend_layout')]
class SystemBackups extends Component
{
    use WithDepartmentTheming;
    use \Livewire\WithPagination;

    public string $themingDepartment = 'System';
    public $appName;
    public $backupName;
    public $diskName;
    public $cronjobSchedule;

    public function mount()
    {
        $this->appName = config('app.name');
        $this->backupName = config('backup.backup.name');
        $this->diskName = config('backup.backup.destination.disks.0', 'local');
        
        $cronjob = \App\Models\System\SystemCronjob::where('command', 'backup:clean')->first();
        $this->cronjobSchedule = $cronjob ? $cronjob->schedule : 'Nicht konfiguriert';
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
        try {
            \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => true]);
            session()->flash('success', 'Ein Datenbank-Backup wurde erfolgreich erstellt.');
        } catch (\Exception $e) {
            session()->flash('error', 'Fehler beim Erstellen des Backups: ' . $e->getMessage());
        }
    }

    public function deleteBackup($path)
    {
        try {
            if (Storage::disk($this->diskName)->exists($path)) {
                Storage::disk($this->diskName)->delete($path);
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
        if (Storage::disk($this->diskName)->exists($path)) {
            return Storage::disk($this->diskName)->download($path);
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
