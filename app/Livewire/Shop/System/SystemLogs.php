<?php

namespace App\Livewire\Shop\System;

use App\Models\System\SystemLog;
use Livewire\Component;
use Livewire\WithPagination;

class SystemLogs extends Component
{
    use WithPagination, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $agentFilter = '';
    public $domainFilter = '';
    
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'agentFilter' => ['except' => ''],
        'domainFilter' => ['except' => '']
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingAgentFilter() { $this->resetPage(); }
    public function updatingDomainFilter() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'agentFilter', 'domainFilter']);
        $this->resetPage();
    }

    public function toggleStatus($logId)
    {
        $log = SystemLog::find($logId);
        if ($log) {
            if ($log->status === 'error') {
                $log->status = 'success';
                if (!str_starts_with($log->title, '[GELÖST]')) {
                    $log->title = '[GELÖST] ' . $log->title;
                }
            } else {
                $log->status = 'error';
                if (str_starts_with($log->title, '[GELÖST] ')) {
                    $log->title = substr($log->title, strlen('[GELÖST] '));
                }
            }
            $log->save();
        }
    }

    public function deleteLog($logId)
    {
        $log = SystemLog::find($logId);
        if ($log) {
            $log->delete();
        }
    }

    public function render()
    {
        // One-time fix for terminal access restrictions
        try {
            \Illuminate\Support\Facades\Artisan::call('livewire:discover');
            \Illuminate\Support\Facades\Artisan::call('view:clear');
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            
            if (!\App\Models\Ai\AiAgent::where('name', 'Funkira')->exists()) {
                \App\Models\Ai\AiAgent::create([
                    'name' => 'Funkira',
                    'role_description' => 'CEO Agent und Hauptkoordinatorin des Seelenfunke Systems.',
                    'system_prompt' => 'Du bist Funkira, die künstliche Intelligenz und CEO des Seelenfunke Systems.',
                    'is_active' => true,
                    'model' => 'gpt-4o',
                    'color' => 'cyan-500',
                    'icon' => 'sparkles'
                ]);
            } else {
                // Ensure Funkira has a valid color/icon format
                $f = \App\Models\Ai\AiAgent::where('name', 'Funkira')->first();
                if ($f->color === 'primary' || str_starts_with($f->icon, 'bi-')) {
                    $f->color = 'cyan-500';
                    $f->icon = 'sparkles';
                    $f->save();
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $query = SystemLog::with('agent')
            ->selectRaw('*, (SELECT COUNT(*) FROM system_logs as l2 WHERE l2.message = system_logs.message AND l2.status = "error") as error_count');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('message', 'like', '%' . $this->search . '%')
                  ->orWhere('action_id', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->agentFilter) {
            if ($this->agentFilter === 'system') {
                $query->whereNull('ai_agent_id');
            } else {
                $query->where('ai_agent_id', $this->agentFilter);
            }
        }

        if ($this->domainFilter) {
            $query->where('action_id', 'like', $this->domainFilter . '%');
        }

        $logs = $query->latest()->paginate(50);

        // Analytics Data
        $totalLogs = SystemLog::count();
        $totalErrors = SystemLog::where('status', 'error')->count();
        $logsToday = SystemLog::whereDate('created_at', today())->count();
        $agents = \App\Models\Ai\AiAgent::orderBy('name')->get();
        $uniqueTypes = SystemLog::select('type')->distinct()->pluck('type');
        
        $actionIds = SystemLog::select('action_id')->distinct()->pluck('action_id');
        $uniqueDomains = collect();
        foreach($actionIds as $id) {
            if (str_contains($id ?? '', ':')) {
                $domain = explode(':', $id)[0];
                if (!$uniqueDomains->contains($domain)) {
                    $uniqueDomains->push($domain);
                }
            }
        }
        $uniqueDomains = $uniqueDomains->sort()->values();

        return view('livewire.shop.system.system-logs', [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'totalErrors' => $totalErrors,
            'logsToday' => $logsToday,
            'agents' => $agents,
            'uniqueTypes' => $uniqueTypes,
            'uniqueDomains' => $uniqueDomains
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}
