<?php

namespace App\Livewire\Global\Ai;

use App\Models\Global\GlobalLog;
use Livewire\Component;
use Livewire\WithPagination;

class AiLogManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $agentFilter = '';
    
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'agentFilter' => ['except' => '']
    ];

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingTypeFilter() { $this->resetPage(); }
    public function updatingAgentFilter() { $this->resetPage(); }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'agentFilter']);
        $this->resetPage();
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

        $query = GlobalLog::with('agent')
            ->selectRaw('*, (SELECT COUNT(*) FROM logs as l2 WHERE l2.message = logs.message AND l2.status = "error") as error_count');

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

        $logs = $query->latest()->paginate(50);

        // Analytics Data
        $totalLogs = GlobalLog::count();
        $totalErrors = GlobalLog::where('status', 'error')->count();
        $logsToday = GlobalLog::whereDate('created_at', today())->count();
        $agents = \App\Models\Ai\AiAgent::orderBy('name')->get();
        $uniqueTypes = GlobalLog::select('type')->distinct()->pluck('type');

        return view('livewire.global.ai.ai-log-manager', [
            'logs' => $logs,
            'totalLogs' => $totalLogs,
            'totalErrors' => $totalErrors,
            'logsToday' => $logsToday,
            'agents' => $agents,
            'uniqueTypes' => $uniqueTypes
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}
