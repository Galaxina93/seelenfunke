<?php

namespace App\Livewire\Shop\Support;

use App\Models\Ai\AiCall;
use App\Models\Ai\AiContact;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class SupportTelephony extends Component
{
    use WithPagination, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    public $currentTab = 'calls'; // calls, contacts, settings

    public function render()
    {
        $activeCalls = AiCall::with(['agent', 'contact'])
            ->whereIn('status', ['initiated', 'ringing', 'in_progress'])
            ->get();

        $historyCalls = AiCall::with(['agent', 'contact'])
            ->whereIn('status', ['completed', 'failed', 'busy', 'no_answer'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $contacts = AiContact::orderBy('name')->paginate(20);

        // KPIs
        $totalCalls = AiCall::whereDate('created_at', Carbon::today())->count();
        $successfulCalls = AiCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->count();
        $avgDurationSeconds = AiCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->avg('duration_seconds');

        $kpi = [
            'total_calls_today' => $totalCalls,
            'total_minutes_today' => round(AiCall::whereDate('created_at', Carbon::today())->sum('duration_seconds') / 60, 1),
            'success_rate' => $totalCalls > 0 ? round(($successfulCalls / $totalCalls) * 100) : 0,
            'avg_duration' => $avgDurationSeconds ? gmdate("i:s", (int) $avgDurationSeconds) : '00:00'
        ];

        return view('livewire.shop.support.support-telephony', [
            'activeCalls' => $activeCalls,
            'historyCalls' => $historyCalls,
            'contacts' => $contacts,
            'kpi' => $kpi
        ])->layout('components.layouts.backend_layout');
    }
}
