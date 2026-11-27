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

    public $currentTab = 'calls'; // calls, settings
    public string $filterMode = 'all'; // all, customer_service, secretary, spam
    public string $search = '';
    public bool $conciergeEnabled = true;
    public bool $customerServiceEnabled = true;
    public int $ringDelaySeconds = 12;
    public array $replyDrafts = [];
    public array $replySendEmail = [];
    public ?string $actionFeedback = null;

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

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setFilterMode(string $mode): void
    {
        if (in_array($mode, ['all', 'customer_service', 'secretary', 'spam'], true)) {
            $this->filterMode = $mode;
            $this->resetPage();
        }
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

    public function setQuickRingDelay(int $seconds): void
    {
        $this->ringDelaySeconds = max(0, min(30, $seconds));
        FunkiraPhoneConciergeService::updateConfig(ringDelaySeconds: $this->ringDelaySeconds);
    }

    public function saveRingDelay(): void
    {
        $this->validate([
            'ringDelaySeconds' => 'required|integer|min:0|max:30',
        ]);
        $this->ringDelaySeconds = max(0, min(30, (int) $this->ringDelaySeconds));
        FunkiraPhoneConciergeService::updateConfig(ringDelaySeconds: $this->ringDelaySeconds);
    }

    public function sendCallReply(int $callId): void
    {
        $message = trim((string) ($this->replyDrafts[$callId] ?? ''));
        if ($message === '') {
            $this->actionFeedback = 'Bitte zuerst einen Antworttext eingeben.';
            return;
        }

        $sendEmail = (bool) ($this->replySendEmail[$callId] ?? false);
        $res = FunkiraPhoneConciergeService::replyToCall((string) $callId, $message, $sendEmail);
        if (!empty($res['success'])) {
            $this->replyDrafts[$callId] = '';
            $this->actionFeedback = !empty($res['email_dispatched'])
                ? 'Antwort im Ticket gespeichert und per E-Mail versendet.'
                : 'Antwort im Vorgang gespeichert.';
        } else {
            $this->actionFeedback = (string) ($res['message'] ?? 'Antwort konnte nicht gesendet werden.');
        }
    }

    public function resolveCall(int $callId): void
    {
        $res = FunkiraPhoneConciergeService::resolveCall((string) $callId, true);
        if (!empty($res['success'])) {
            $this->actionFeedback = 'Anliegen samt Aufgabe und Ticket als erledigt markiert.';
        } else {
            $this->actionFeedback = (string) ($res['message'] ?? 'Anruf konnte nicht abgeschlossen werden.');
        }
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

        $query = \App\Models\SupportTelephonyCall::query()->orderBy('created_at', 'desc');
        if ($this->filterMode === 'customer_service') {
            $query->where('status', '!=', 'spam_filtered')
                ->where(function ($q) {
                    $q->where('objective', 'like', 'Funkira Kundenservice:%')
                        ->orWhere('next_steps', 'like', '%"inbound_customer_service"%');
                });
        } elseif ($this->filterMode === 'secretary') {
            $query->where('status', '!=', 'spam_filtered')
                ->where(function ($q) {
                    $q->where('objective', 'like', 'Funkira Telefon-Sekretärin:%')
                        ->orWhere('next_steps', 'like', '%"inbound_secretary"%');
                });
        } elseif ($this->filterMode === 'spam') {
            $query->where('status', 'spam_filtered');
        } else {
            $query->where('status', '!=', 'spam_filtered');
        }

        $qText = trim($this->search);
        if ($qText !== '') {
            $like = '%' . $qText . '%';
            $query->where(function ($q) use ($like) {
                $q->where('contact_name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('next_steps', 'like', $like);
            });
        }

        $historyCalls = $query->paginate(10);

        foreach ($historyCalls as $call) {
            if (!array_key_exists($call->id, $this->replyDrafts)) {
                $meta = is_string($call->next_steps) ? (json_decode($call->next_steps, true) ?: []) : [];
                $this->replyDrafts[$call->id] = (string) ($meta['suggested_reply'] ?? '');
                $this->replySendEmail[$call->id] = !empty($meta['caller_email']);
            }
        }

        // KPIs
        $totalCalls = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->where('status', '!=', 'spam_filtered')->count();
        $customerCallsToday = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())
            ->where('status', '!=', 'spam_filtered')
            ->where(function ($q) {
                $q->where('objective', 'like', 'Funkira Kundenservice:%')
                    ->orWhere('next_steps', 'like', '%"inbound_customer_service"%');
            })
            ->count();
        $successfulCalls = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->count();
        $avgDurationSeconds = \App\Models\SupportTelephonyCall::whereDate('created_at', Carbon::today())->where('status', 'completed')->avg('duration_seconds');

        $kpi = [
            'total_calls_today' => $totalCalls,
            'customer_calls_today' => $customerCallsToday,
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
