<?php

namespace App\Livewire\Shop\System;

use App\Console\Commands\System\CleanupSystemLogsCommand;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class SystemLogs extends Component
{
    use WithPagination, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public string $viewTab = 'open';
    public string $platformFilter = '';
    public $search = '';
    public $statusFilter = '';
    public $typeFilter = '';
    public $agentFilter = '';
    public $domainFilter = '';

    protected $queryString = [
        'viewTab' => ['except' => 'open'],
        'platformFilter' => ['except' => ''],
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'agentFilter' => ['except' => ''],
        'domainFilter' => ['except' => ''],
    ];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        if (!in_array($this->viewTab, ['open', 'archived'], true)) {
            $this->viewTab = 'open';
        }

        if ($this->platformFilter !== '' && SystemLog::normalizePlatformAlias($this->platformFilter) === null) {
            $this->platformFilter = '';
        }

        CleanupSystemLogsCommand::pruneOpportunistically();
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingPlatformFilter(): void { $this->resetPage(); }
    public function updatingStatusFilter(): void { $this->resetPage(); }
    public function updatingTypeFilter(): void { $this->resetPage(); }
    public function updatingAgentFilter(): void { $this->resetPage(); }
    public function updatingDomainFilter(): void { $this->resetPage(); }

    public function switchViewTab(string $tab): void
    {
        $this->viewTab = $tab === 'archived' ? 'archived' : 'open';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function selectPlatform(string $platform): void
    {
        $normalized = SystemLog::normalizePlatformAlias($platform) ?? '';
        $this->platformFilter = ($this->platformFilter === $normalized) ? '' : $normalized;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'platformFilter', 'statusFilter', 'typeFilter', 'agentFilter', 'domainFilter']);
        $this->resetPage();
    }

    public function markAllAsDone(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $query = SystemLog::openIssues();
        $this->applySharedFilters($query);

        if ($this->statusFilter && in_array($this->statusFilter, ['error', 'warning'], true)) {
            $query->where('status', $this->statusFilter);
        }

        $ids = $query->pluck('id');

        if ($ids->isNotEmpty()) {
            foreach ($ids->chunk(500) as $chunk) {
                SystemLog::whereIn('id', $chunk)->update([
                    'status' => 'success',
                    'finished_at' => now(),
                ]);

                SystemLog::whereIn('id', $chunk)
                    ->where(function ($q) {
                        $q->whereNull('title')->orWhere('title', '');
                    })
                    ->update(['title' => '[GELÖST]']);

                SystemLog::whereIn('id', $chunk)
                    ->whereNotNull('title')
                    ->where('title', '!=', '')
                    ->where('title', 'not like', '[GELÖST]%')
                    ->update(['title' => DB::raw("CONCAT('[GELÖST] ', title)")]);
            }
        }

        $this->resetPage();
    }

    public function pruneArchiveNow(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        CleanupSystemLogsCommand::executePrune(7, 30, true);
        $this->resetPage();
    }

    public function toggleStatus($logId): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $log = SystemLog::find($logId);
        if ($log) {
            if (in_array($log->status, ['error', 'warning'], true)) {
                $log->status = 'success';
                $log->finished_at = now();
                if (!str_starts_with((string) $log->title, '[GELÖST]')) {
                    $log->title = '[GELÖST] ' . $log->title;
                }
            } else {
                $log->status = 'error';
                if (str_starts_with((string) $log->title, '[GELÖST] ')) {
                    $log->title = substr((string) $log->title, strlen('[GELÖST] '));
                }
            }
            $log->save();
        }
    }

    public function deleteLog($logId): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $log = SystemLog::find($logId);
        if ($log) {
            $log->delete();
        }
    }

    protected function applySharedFilters($query): void
    {
        if ($this->platformFilter !== '') {
            $query->forPlatform($this->platformFilter);
        }

        if ($this->search) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('message', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('title', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('action_id', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('type', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('payload', 'like', '%' . $escapedSearch . '%');
            });
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
            $query->where('action_id', 'like', addcslashes((string) $this->domainFilter, '%_\\') . '%');
        }
    }

    public function render()
    {
        try {
            if (!\App\Models\Ai\AiAgent::where('name', 'Funkira')->exists()) {
                \App\Models\Ai\AiAgent::create([
                    'name' => 'Funkira',
                    'role_description' => 'CEO Agent und Hauptkoordinatorin des Seelenfunke Systems.',
                    'system_prompt' => 'Du bist Funkira, die künstliche Intelligenz und CEO des Seelenfunke Systems.',
                    'is_active' => true,
                    'model' => 'gemini-2.5-flash',
                    'color' => 'cyan-500',
                    'icon' => 'sparkles',
                ]);
            }
        } catch (\Throwable $ignore) {
        }

        $query = SystemLog::with('agent');

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        } elseif ($this->viewTab === 'archived') {
            $query->archivedEntries();
        } else {
            $query->openIssues();
        }

        $this->applySharedFilters($query);

        $logs = $query->orderByDesc('finished_at')->orderByDesc('id')->paginate(50);

        $openErrors = SystemLog::where('status', 'error')->count();
        $openWarnings = SystemLog::where('status', 'warning')->count();
        $openTotal = $openErrors + $openWarnings;
        $archivedCount = SystemLog::archivedEntries()->count();

        $openByPlatform = [];
        foreach (SystemLog::PLATFORMS as $pKey => $pMeta) {
            $openByPlatform[$pKey] = SystemLog::openIssues()->forPlatform($pKey)->count();
        }

        $agents = \App\Models\Ai\AiAgent::orderBy('name')->get();
        $uniqueTypes = SystemLog::select('type')->whereNotNull('type')->distinct()->orderBy('type')->pluck('type');

        $actionIds = SystemLog::select('action_id')->whereNotNull('action_id')->distinct()->pluck('action_id');
        $uniqueDomains = collect();
        foreach ($actionIds as $id) {
            if (str_contains((string) $id, ':')) {
                $domain = explode(':', (string) $id)[0];
                if ($domain !== '' && !$uniqueDomains->contains($domain)) {
                    $uniqueDomains->push($domain);
                }
            }
        }
        $uniqueDomains = $uniqueDomains->sort()->values();

        return view('livewire.shop.system.system-logs', [
            'logs' => $logs,
            'platforms' => SystemLog::PLATFORMS,
            'openErrors' => $openErrors,
            'openWarnings' => $openWarnings,
            'openTotal' => $openTotal,
            'archivedCount' => $archivedCount,
            'openByPlatform' => $openByPlatform,
            'agents' => $agents,
            'uniqueTypes' => $uniqueTypes,
            'uniqueDomains' => $uniqueDomains,
        ])->layout('components.layouts.backend_layout', ['guard' => 'admin']);
    }
}
