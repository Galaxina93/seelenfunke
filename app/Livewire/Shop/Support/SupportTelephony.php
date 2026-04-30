<?php

namespace App\Livewire\Shop\Support;

use App\Models\Ai\AiCall;
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
        // Da wir nun SupportTelephonyCall für echte Twilio-Calls haben:
        $activeCalls = collect(); // Aktive Twilio Calls könnten wir über einen Status "ongoing" in SupportTelephonyCall ermitteln, wenn wir sie beim Start eintragen würden. Für jetzt leer.

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
