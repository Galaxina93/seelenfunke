<?php

namespace App\Livewire\Shop\Support;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Support\SupportContactRequest;
use App\Models\Support\SupportCustomerChat;
use App\Models\Support\SupportExperienceReview;
use App\Models\Support\SupportTicket;
use App\Services\AI\AiAuthHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class SupportAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Support';

    private const ALLOWED_DATE_RANGES = ['7', '30', '90', '365', 'all'];

    private const TICKET_STATUS_LABELS = [
        'open' => 'Offen',
        'in_progress' => 'In Bearbeitung',
        'customer_reply' => 'Kundenantwort',
        'waiting' => 'Wartend',
        'resolved' => 'Gelöst',
        'closed' => 'Geschlossen',
    ];

    private const CHAT_STATUS_LABELS = [
        'open' => 'Offen',
        'active' => 'Aktiv',
        'needs_employee' => 'Mitarbeiter nötig',
        'resolved_auto' => 'KI gelöst',
        'resolved_admin' => 'Admin gelöst',
        'resolved' => 'Gelöst',
        'closed' => 'Geschlossen',
    ];

    #[Url]
    public string $dateRange = '30';

    protected ?Carbon $dateFrom = null;
    protected ?Carbon $dateTo = null;

    // Chart Data
    public array $volumeData = ['labels' => [], 'data' => []];
    public array $sourceData = ['labels' => [], 'data' => []];
    public array $ticketStatusData = ['labels' => [], 'data' => []];
    public array $chatStatusData = ['labels' => [], 'data' => []];
    public array $chatRatingData = ['labels' => [], 'data' => []];
    public array $ticketRatingData = ['labels' => [], 'data' => []];

    // KPI Data
    public int $kpiTicketsOpen = 0;
    public int $kpiTicketsClosed = 0;
    public float $kpiAvgResolutionHours = 0.0;
    public float $kpiAvgTicketRating = 0.0;
    public float $kpiAvgChatRating = 0.0;

    public function boot(): void
    {
        if (!AiAuthHelper::isAdmin() && !AiAuthHelper::isEmployee()) {
            abort(403, 'Zugriff verweigert: Nur für autorisiertes Personal.');
        }

        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }
    }

    public function mount(): void
    {
        $this->computeAnalytics();
    }

    public function updatedDateRange(): void
    {
        if (!in_array($this->dateRange, self::ALLOWED_DATE_RANGES, true)) {
            $this->dateRange = '30';
        }

        $this->computeAnalytics();
        $this->dispatch('analytics-updated');
    }

    protected function resolveDateWindow(): array
    {
        $to = Carbon::now()->endOfDay();

        if ($this->dateRange === 'all') {
            $earliestTicket = SupportTicket::min('created_at');
            $earliestChat = SupportCustomerChat::min('created_at');
            $earliestContact = SupportContactRequest::min('created_at');

            $candidates = array_filter([$earliestTicket, $earliestChat, $earliestContact]);
            $from = !empty($candidates)
                ? Carbon::parse(min($candidates))->startOfDay()
                : Carbon::now()->subDays(30)->startOfDay();
        } else {
            $days = max(1, (int) $this->dateRange);
            $from = Carbon::now()->subDays($days)->startOfDay();
        }

        $this->dateFrom = $from;
        $this->dateTo = $to;

        return [$from, $to];
    }

    public function computeAnalytics(): void
    {
        [$from, $to] = $this->resolveDateWindow();
        $groupByMonth = in_array($this->dateRange, ['365', 'all'], true) && $from->diffInDays($to) > 90;

        $tickets = SupportTicket::whereBetween('created_at', [$from, $to])->get();
        $chats = SupportCustomerChat::whereBetween('created_at', [$from, $to])->get();
        $contacts = SupportContactRequest::whereBetween('created_at', [$from, $to])->get();

        $experienceReviewsCount = (class_exists(SupportExperienceReview::class) && Schema::hasTable('support_experience_reviews'))
            ? SupportExperienceReview::whereBetween('created_at', [$from, $to])->count()
            : 0;

        // 0. KPIs
        $this->kpiTicketsOpen = $tickets->filter(
            fn ($t) => !in_array((string) $t->status, ['closed', 'resolved'], true)
        )->count();

        $resolvedTickets = $tickets->filter(
            fn ($t) => in_array((string) $t->status, ['closed', 'resolved'], true)
        );
        $this->kpiTicketsClosed = $resolvedTickets->count();

        if ($this->kpiTicketsClosed > 0) {
            $totalMinutes = 0.0;
            foreach ($resolvedTickets as $ticket) {
                if ($ticket->created_at && $ticket->updated_at) {
                    $totalMinutes += abs($ticket->updated_at->diffInMinutes($ticket->created_at));
                }
            }
            $this->kpiAvgResolutionHours = round(($totalMinutes / 60.0) / $this->kpiTicketsClosed, 1);
        } else {
            $this->kpiAvgResolutionHours = 0.0;
        }

        $ratedTickets = $tickets->whereNotNull('rating');
        $this->kpiAvgTicketRating = $ratedTickets->count() > 0
            ? round((float) $ratedTickets->avg('rating'), 1)
            : 0.0;

        $ratedChats = $chats->whereNotNull('rating');
        $this->kpiAvgChatRating = $ratedChats->count() > 0
            ? round((float) $ratedChats->avg('rating'), 1)
            : 0.0;

        // 1. Support Volume Over Time
        $timeline = [];
        $cursor = $groupByMonth ? $from->copy()->startOfMonth() : $from->copy();
        while ($cursor->lte($to)) {
            $key = $groupByMonth ? $cursor->format('m/Y') : $cursor->format('d.m.');
            $timeline[$key] = 0;
            $cursor = $groupByMonth ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ([$tickets, $chats, $contacts] as $collection) {
            foreach ($collection as $item) {
                if (!$item->created_at) {
                    continue;
                }
                $key = $groupByMonth ? $item->created_at->format('m/Y') : $item->created_at->format('d.m.');
                if (array_key_exists($key, $timeline)) {
                    $timeline[$key]++;
                }
            }
        }

        $this->volumeData = [
            'labels' => array_keys($timeline),
            'data' => array_values($timeline),
        ];

        // 2. Source Distribution
        $sourceLabels = ['Tickets', 'KI-Chats', 'Kontaktformular'];
        $sourceCounts = [$tickets->count(), $chats->count(), $contacts->count()];
        if ($experienceReviewsCount > 0) {
            $sourceLabels[] = 'Feedback-Sterne';
            $sourceCounts[] = $experienceReviewsCount;
        }
        $this->sourceData = [
            'labels' => $sourceLabels,
            'data' => $sourceCounts,
        ];

        // 3. Ticket Status Distribution
        $tStatuses = $tickets->groupBy('status')->map->count();
        $tLabels = [];
        $tValues = [];
        foreach ($tStatuses as $rawStatus => $cnt) {
            $key = strtolower((string) $rawStatus);
            $tLabels[] = self::TICKET_STATUS_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key ?: 'Unbekannt'));
            $tValues[] = (int) $cnt;
        }
        $this->ticketStatusData = [
            'labels' => $tLabels,
            'data' => $tValues,
        ];

        // 4. Chat Status Distribution
        $cStatuses = $chats->groupBy('status')->map->count();
        $cLabels = [];
        $cValues = [];
        foreach ($cStatuses as $rawStatus => $cnt) {
            $key = strtolower((string) $rawStatus);
            $cLabels[] = self::CHAT_STATUS_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key ?: 'Unbekannt'));
            $cValues[] = (int) $cnt;
        }
        $this->chatStatusData = [
            'labels' => $cLabels,
            'data' => $cValues,
        ];

        // 5. Chat Ratings (5 down to 1 Star)
        $cRatingLabels = [];
        $cRatingValues = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $cnt = $ratedChats->where('rating', $stars)->count();
            if ($cnt > 0) {
                $cRatingLabels[] = $stars . ' Sterne';
                $cRatingValues[] = $cnt;
            }
        }
        $this->chatRatingData = [
            'labels' => $cRatingLabels,
            'data' => $cRatingValues,
        ];

        // 6. Ticket Ratings (5 down to 1 Star)
        $tRatingLabels = [];
        $tRatingValues = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $cnt = $ratedTickets->where('rating', $stars)->count();
            if ($cnt > 0) {
                $tRatingLabels[] = $stars . ' Sterne';
                $tRatingValues[] = $cnt;
            }
        }
        $this->ticketRatingData = [
            'labels' => $tRatingLabels,
            'data' => $tRatingValues,
        ];
    }

    public function render()
    {
        return view('livewire.shop.support.support-analytics.support-analytics');
    }
}
