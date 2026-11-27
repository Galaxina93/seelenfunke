<?php

namespace App\Livewire\Shop\Support;

use App\Models\Ai\AiCall;
use App\Services\System\FunkiraPhoneConciergeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;
use Livewire\Attributes\On;

class SupportTelephony extends Component
{
    use WithPagination, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    public $currentTab = 'calls'; // calls, contacts, settings
    public bool $conciergeEnabled = true;
    public bool $customerServiceEnabled = true;
    public int $ringDelaySeconds = 12;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isEmployee() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->conciergeEnabled = FunkiraPhoneConciergeService::isEnabled();
        $this->customerServiceEnabled = FunkiraPhoneConciergeService::isCustomerServiceEnabled();
        $this->ringDelaySeconds = FunkiraPhoneConciergeService::getRingDelaySeconds();
    }

    public function toggleConciergeEnabled(): void
    {
        $this->conciergeEnabled = !$this->conciergeEnabled;
        FunkiraPhoneConciergeService::updateConfig(enabled: $this->conciergeEnabled);
    }

    public function toggleCustomerServiceEnabled(): void
    {
        $this->customerServiceEnabled = !$this->customerServiceEnabled;
        FunkiraPhoneConciergeService::updateConfig(customerServiceEnabled: $this->customerServiceEnabled);
    }

    public function saveRingDelay(): void
    {
        $this->validate([
            'ringDelaySeconds' => 'required|integer|min:0|max:30',
        ]);
        $this->ringDelaySeconds = max(0, min(30, (int) $this->ringDelaySeconds));
        FunkiraPhoneConciergeService::updateConfig(ringDelaySeconds: $this->ringDelaySeconds);
    }

    #[On('refreshTelephony')]
    #[On('echo-private:telephony,SupportTelephonyUpdated')]
    public function refreshList()
    {
        // Löst einfach ein Livewire-Rerender aus, da render() danach automatisch aufgerufen wird
    }

    public function render()
    {
        $activeCalls = \App\Models\SupportTelephonyCall::where('status', 'ongoing')->get();

        $historyCalls = \App\Models\SupportTelephonyCall::orderBy('created_at', 'desc')
            ->paginate(10);

        // KPIs
        $totalCalls = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->count();
        $successfulCalls = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->count();
        $avgDurationSeconds = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->avg('duration_seconds');

        $kpi = [
            'total_calls_today' => $totalCalls,
            'total_minutes_today' => round(\App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->sum('duration_seconds') / 60, 1),
            'success_rate' => $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100) : 0,
            'avg_duration' => $avgDurationSeconds ? gmdate("i:s", (int) $avgDurationSeconds) : '00:00'
        ];

        return view('livewire.shop.support.support-telephony', [
            'activeCalls' => $activeCalls,
            'historyCalls' => $historyCalls,
            'kpi' => $kpi
        ])->layout('components.layouts.backend_layout');
    }
}
