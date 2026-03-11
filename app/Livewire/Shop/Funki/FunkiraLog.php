<?php

namespace App\Livewire\Shop\Funki;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use App\Models\Funki\FunkiLog;
use App\Models\LoginAttempt;
use Livewire\WithPagination;

class FunkiraLog extends Component
{
    use WithPagination;

    public $search = '';
    public $typeFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => '']
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function getSystemLogsProperty()
    {
        $logs = collect();

        // 1. Echte Funki Logs holen
        if (class_exists(FunkiLog::class)) {
            $funkiQuery = FunkiLog::query();
            
            if (!empty($this->search)) {
                $funkiQuery->where(function($q) {
                    $q->where('title', 'like', '%' . $this->search . '%')
                      ->orWhere('message', 'like', '%' . $this->search . '%');
                });
            }
            
            if (!empty($this->typeFilter)) {
                $funkiQuery->where('type', $this->typeFilter);
            }

            $funki = $funkiQuery->orderByDesc('started_at')->limit(100)->get()->map(function($log) {
                return [
                    'id' => 'fl_'.$log->id,
                    'title' => $log->title,
                    'message' => $log->message,
                    'status' => $log->status, // running, success, error, info, warning
                    'type' => $log->type, // automation, ai, marketing, system
                    'timestamp' => $log->started_at,
                ];
            });
            $logs = $logs->concat($funki);
        }

        // 2. Sicherheitswarnungen (Fehlerhafte Logins)
        if (class_exists(LoginAttempt::class) && (empty($this->typeFilter) || $this->typeFilter === 'security')) {
            $failedLoginsQuery = LoginAttempt::where('success', false);
            
            if (!empty($this->search)) {
                $failedLoginsQuery->where(function($q) {
                    $q->where('email', 'like', '%' . $this->search . '%')
                      ->orWhere('ip_address', 'like', '%' . $this->search . '%');
                });
            }

            $failedLogins = $failedLoginsQuery->orderByDesc('attempted_at')->limit(50)->get()->map(function($fail) {
                return [
                    'id' => 'la_'.$fail->id,
                    'title' => 'Fehlgeschlagener Login',
                    'message' => 'Versuch mit: ' . $fail->email . ' (IP: ' . $fail->ip_address . ')',
                    'status' => 'error',
                    'type' => 'security',
                    'timestamp' => $fail->attempted_at,
                ];
            });
            $logs = $logs->concat($failedLogins);
        }

        // Zusammen nach Zeit sortieren
        return $logs->sortByDesc('timestamp')->values();
    }
    
    public function getStatsProperty()
    {
        $allLogs = $this->systemLogs;
        
        $errors = $allLogs->where('status', 'error')->count();
        $success = $allLogs->where('status', 'success')->count();
        $warnings = $allLogs->where('status', 'warning')->count();
        
        return [
            'total' => $allLogs->count(),
            'errors' => $errors,
            'success' => $success,
            'warnings' => $warnings,
            'health_score' => $allLogs->count() > 0 ? max(0, 100 - ($errors * 5) - ($warnings * 2)) : 100
        ];
    }

    public function fixSystem()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            \Illuminate\Support\Facades\Artisan::call('queue:restart');
            
            if (class_exists(FunkiLog::class)) {
                FunkiLog::create([
                    'title' => 'System Healing durch Admin initiert',
                    'message' => 'Caches, Configs und Views wurden geleert. Queue-Worker Restart angefragt.',
                    'status' => 'success',
                    'type' => 'system',
                    'started_at' => now(),
                    'finished_at' => now(),
                    'action_id' => 'system_heal_' . time()
                ]);
            }
            
            $this->dispatch('toast', message: 'System Healing erfolgreich ausgeführt!', type: 'success');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Fehler beim System Healing: ' . $e->getMessage(), type: 'error');
        }
    }

    public function clearLogs()
    {
        if (class_exists(FunkiLog::class)) {
            FunkiLog::truncate();
            $this->dispatch('toast', message: 'Das Logbuch wurde komplett gelöscht.', type: 'success');
            return redirect()->route('admin.funkira-log');
        }
    }

    public function render()
    {
        return view('livewire.shop.funki.funkira-log', [
            'logs' => $this->systemLogs,
            'stats' => $this->stats
        ]);
    }
}
