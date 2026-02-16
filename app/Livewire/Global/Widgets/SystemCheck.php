<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Pagination\LengthAwarePaginator;

// Models
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\LoginAttempt;
use App\Models\PageVisit;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Invoice;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceCostItem;
use App\Models\SystemCheckConfig;

class SystemCheck extends Component
{
    use WithPagination;

    public $stats = [];
    public $rangeMode = 'year';

    protected $paginationTheme = 'tailwind';

    public $showFailedLogins = false;
    public $showFullLogins = false;

    public $dateStart;
    public $dateEnd;
    public $filterType = 'all';

    public function mount()
    {
        $this->loadSettings();
        $this->loadStats();
    }

    public function loadSettings()
    {
        $config = SystemCheckConfig::where('user_id', auth()->id())->first();

        if ($config) {
            $this->filterType = $config->filter_type;
            $this->dateStart = $config->date_start;
            $this->dateEnd = $config->date_end;
            $this->rangeMode = $config->range_mode ?? 'custom';
        } else {
            $this->setWholeYear(false);
        }
    }

    public function saveSettings($rangeMode = 'custom')
    {
        $this->rangeMode = $rangeMode;
        SystemCheckConfig::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'filter_type' => $this->filterType,
                'date_start' => $this->dateStart,
                'date_end' => $this->dateEnd,
                'range_mode' => $rangeMode
            ]
        );
    }

    public function setCurrentMonth($save = true)
    {
        $this->dateStart = now()->startOfMonth()->format('Y-m-d');
        $this->dateEnd = now()->endOfMonth()->format('Y-m-d');
        if($save) {
            $this->saveSettings('current_month');
            $this->loadStats();
        }
    }

    public function setWholeYear($save = true)
    {
        $this->dateStart = now()->startOfYear()->format('Y-m-d');
        $this->dateEnd = now()->endOfYear()->format('Y-m-d');
        if($save) {
            $this->saveSettings('year');
            $this->loadStats();
        }
    }

    public function updated($property)
    {
        if (in_array($property, ['dateStart', 'dateEnd', 'filterType'])) {
            $this->saveSettings('custom');
            $this->loadStats();
        }
    }

    public function loadStats()
    {
        $lastLogins = $this->getAllLoginsCollection();

        // Traffic Analyse Daten
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        $visitsByDay = PageVisit::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'))
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $visitCounts = [];
        $visitLabels = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
        $periodTraffic = CarbonPeriod::create($startOfWeek, $endOfWeek);
        foreach ($periodTraffic as $date) {
            $visitCounts[] = $visitsByDay[$date->format('Y-m-d')] ?? 0;
        }

        $start = Carbon::parse($this->dateStart)->startOfDay();
        $end = Carbon::parse($this->dateEnd)->endOfDay();

        $chartData = [
            'labels' => [],
            'revenue' => [],
            'expenses' => [],
            'profit' => []
        ];

        $costItemsQuery = FinanceCostItem::query();
        if($this->filterType === 'business') $costItemsQuery->where('is_business', true);
        if($this->filterType === 'private') $costItemsQuery->where('is_business', false);
        $allCostItems = $costItemsQuery->get();

        $diffInDays = $start->diffInDays($end);

        if ($diffInDays <= 31) {
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $fixedIncomeDay = 0; $fixedExpenseDay = 0;
                foreach($allCostItems as $item) {
                    $dailyAmount = ($item->amount / ($item->interval_months ?: 1)) / 30.42;
                    if ($dailyAmount >= 0) $fixedIncomeDay += $dailyAmount;
                    else $fixedExpenseDay += abs($dailyAmount);
                }

                $specials = FinanceSpecialIssue::whereBetween('execution_date', [$dayStart, $dayEnd])
                    ->when($this->filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($this->filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialInc = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExp = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRev = 0;
                if ($this->filterType !== 'private') {
                    $shopRev = Order::whereBetween('created_at', [$dayStart, $dayEnd])
                            ->where('payment_status', 'paid')
                            ->sum('total_price') / 100;
                }

                $rev = $shopRev + $specialInc + $fixedIncomeDay;
                $exp = $specialExp + $fixedExpenseDay;

                $chartData['labels'][] = $date->format('d.m.');
                $chartData['revenue'][] = round($rev, 2);
                $chartData['expenses'][] = round($exp, 2);
                $chartData['profit'][] = round($rev - $exp, 2);
            }
        } else {
            $currentDate = $start->copy()->startOfMonth();
            $finalDate = $end->copy()->endOfMonth();
            while ($currentDate <= $finalDate) {
                $mStart = $currentDate->copy()->startOfMonth();
                $mEnd = $currentDate->copy()->endOfMonth();
                if($mStart < $start) $mStart = $start;
                if($mEnd > $end) $mEnd = $end;

                $fixedIncomeMonth = 0; $fixedExpenseMonth = 0;
                $factor = ($mStart->diffInDays($mEnd) + 1) / $currentDate->daysInMonth;

                foreach($allCostItems as $item) {
                    $monthlyAmount = $item->amount / ($item->interval_months ?: 1);
                    $periodAmount = $monthlyAmount * $factor;
                    if ($periodAmount >= 0) $fixedIncomeMonth += $periodAmount;
                    else $fixedExpenseMonth += abs($periodAmount);
                }

                $specials = FinanceSpecialIssue::whereBetween('execution_date', [$mStart, $mEnd])
                    ->when($this->filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($this->filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialIncome = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExpenses = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRevenue = ($this->filterType !== 'private') ? Order::whereBetween('created_at', [$mStart, $mEnd])->where('payment_status', 'paid')->sum('total_price') / 100 : 0;

                $revenue = $shopRevenue + $specialIncome + $fixedIncomeMonth;
                $expenses = $specialExpenses + $fixedExpenseMonth;

                $chartData['labels'][] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $chartData['revenue'][] = round($revenue, 2);
                $chartData['expenses'][] = round($expenses, 2);
                $chartData['profit'][] = round($revenue - $expenses, 2);
                $currentDate->addMonth();
            }
        }

        // Kostentreiber Aggregation
        $topExpenses = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($this->filterType !== 'all', fn($q) => $q->where('is_business', $this->filterType === 'business'))
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        // Berechnung der Periodenwerte
        $totalRevenuePeriod = array_sum($chartData['revenue']);
        $totalExpensesPeriod = array_sum($chartData['expenses']);
        $durationInDays = max(1, $start->diffInDays($end) + 1);

        $prevStart = $start->copy()->subDays($durationInDays);
        $prevEnd = $start->copy()->subDay();
        $prevRevenue = $this->calculateRevenueForPeriod($prevStart, $prevEnd);
        $revenueGrowth = $prevRevenue > 0 ? (($totalRevenuePeriod - $prevRevenue) / $prevRevenue) * 100 : 0;

        $unitsCount = max(1, count($chartData['labels']));
        $avgProfit = ($totalRevenuePeriod - $totalExpensesPeriod) / $unitsCount;
        $projectedProfit = ($diffInDays <= 31) ? ($avgProfit * 365 / 30.42) : ($avgProfit * 12);
        $margin = $totalRevenuePeriod > 0 ? (($totalRevenuePeriod - $totalExpensesPeriod) / $totalRevenuePeriod) * 100 : 0;

        // Fixkosten
        $fixGewerbe = $allCostItems->where('is_business', true)->where('amount', '<', 0)->sum(fn($i) => abs($i->amount)/($i->interval_months?:1)) * ($durationInDays/30.42);
        $fixPrivat = $allCostItems->where('is_business', false)->where('amount', '<', 0)->sum(fn($i) => abs($i->amount)/($i->interval_months?:1)) * ($durationInDays/30.42);

        $variableExpensesTotal = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($this->filterType !== 'all', fn($q) => $q->where('is_business', $this->filterType === 'business'))
            ->sum(DB::raw('ABS(amount)'));

        // Produkt-Highlights (Original-Logik wiederhergestellt)
        $productStatsQuery = OrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))
            ->select('product_name', DB::raw('SUM(total_price)/100 as total'))
            ->groupBy('product_name')
            ->orderByDesc('total');

        $highRevenueProd = $productStatsQuery->first();
        $lowRevenueProd = $productStatsQuery->clone()->orderBy('total', 'asc')->first();

        // Calculate Break Even
        $breakEvenValue = ($fixGewerbe + $fixPrivat) / max(1, ($durationInDays/30.42));

        // NEU: Qualitäts-Score berechnen
        $qualityScore = $this->calculateShopQualityScore(
            $margin,
            $revenueGrowth,
            $totalRevenuePeriod - $totalExpensesPeriod, // Profit
            $totalRevenuePeriod,
            $breakEvenValue * ($durationInDays/30.42) // Break Even für Periode
        );

        $this->stats = [
            'total_users'            => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today'     => $lastLogins->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count() + Admin::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count() + Employee::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
            'failed_logins'          => LoginAttempt::where('success', false)->count(),
            'active_sessions'        => DB::table('sessions')->count(),
            'never_logged_in'        => $lastLogins->whereNull('last_seen')->count(),
            'inactive_30_days'       => $lastLogins->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(now()->subDays(30)))->count(),
            'frontend_visits_today'  => PageVisit::whereDate('created_at', now())->count(),
            'visit_days'             => $visitLabels,
            'visit_counts'           => $visitCounts,

            'chart_data'             => $chartData,
            'total_revenue'          => $totalRevenuePeriod,
            'total_profit'           => $totalRevenuePeriod - $totalExpensesPeriod,
            'revenue_growth'         => round($revenueGrowth, 1),
            'avg_profit'             => $avgProfit,
            'projected_year'         => $projectedProfit,
            'margin'                 => round($margin, 1),
            'break_even_monthly'     => ($fixGewerbe + $fixPrivat) / ($durationInDays/30.42),
            'shop_quality_score'     => $qualityScore,

            'fixed_income_total'     => $allCostItems->where('amount', '>', 0)->sum(fn($i) => $i->amount/($i->interval_months?:1)) * ($durationInDays/30.42),
            'shop_revenue'           => ($this->filterType !== 'private') ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100 : 0,
            'fixed_expenses_priv'    => $fixPrivat,
            'fixed_expenses_gew'     => $fixGewerbe,
            'variable_expenses'      => $variableExpensesTotal,

            'pending_invoices'       => [
                'count' => Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->count(),
                'sum' => Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->sum('total') / 100
            ],
            'top_expenses'           => $topExpenses,
            'top_customers'          => $this->filterType !== 'private' ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->select('email', DB::raw('SUM(total_price)/100 as total'))->groupBy('email')->orderByDesc('total')->take(5)->get()->map(fn($o) => ['category' => $o->email, 'total' => $o->total]) : collect(),
            'high_revenue_prod'      => $highRevenueProd,
            'low_revenue_prod'       => $lowRevenueProd,
            'product_ranking'        => $this->filterType !== 'private' ? OrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))->select('product_name', DB::raw('SUM(quantity) as qty'))->groupBy('product_name')->orderByDesc('qty')->take(3)->get() : collect(),
        ];

        $this->dispatch('update-charts', stats: $this->stats);
    }

    // NEU: Methode zur Berechnung des Shop-Qualitäts-Scores (0-100)
    private function calculateShopQualityScore($margin, $growth, $profit, $revenue, $breakEvenTotal) {
        $score = 0;

        // 1. Profitabilität (Max 40 Punkte)
        // Ist der Shop profitabel?
        if ($profit > 0) {
            $score += 20;
        }
        // Ist der Umsatz höher als der Break-Even?
        if ($revenue > $breakEvenTotal && $breakEvenTotal > 0) {
            $score += 20;
        }

        // 2. Marge (Max 30 Punkte)
        // Annahme: Eine Marge von 30% ist sehr gut.
        // Formel: Marge * 1 (capped bei 30)
        $score += min(30, max(0, $margin));

        // 3. Wachstum (Max 30 Punkte)
        // Annahme: 20% Wachstum ist sehr gut.
        if ($growth > 0) {
            $score += min(30, $growth * 1.5);
        } else {
            // Abzug bei negativem Wachstum, aber Score nicht unter 0 fallen lassen im Gesamten (später handled durch max/min)
            $score -= min(20, abs($growth));
        }

        // Ergebnis begrenzen auf 0 bis 100
        return max(0, min(100, round($score)));
    }

    private function calculateRevenueForPeriod($start, $end) {
        $shop = ($this->filterType !== 'private') ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100 : 0;
        $special = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])->where('amount', '>=', 0)->when($this->filterType !== 'all', fn($q) => $q->where('is_business', $this->filterType === 'business'))->sum('amount');
        return $shop + $special;
    }

    private function getAllLoginsCollection()
    {
        return Admin::with('profile')->get()->map(fn($u) => ['type' => 'Admin', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen])
            ->merge(Customer::with('profile')->get()->map(fn($u) => ['type' => 'Customer', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]))
            ->merge(Employee::with('profile')->get()->map(fn($u) => ['type' => 'Employee', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]));
    }

    public function getPaginatedLoginsProperty()
    {
        $allLogins = $this->getAllLoginsCollection()->sortByDesc('last_seen')->values();
        return new LengthAwarePaginator($allLogins->forPage(LengthAwarePaginator::resolveCurrentPage('loginsPage'), 8), $allLogins->count(), 8, null, ['path' => request()->url(), 'pageName' => 'loginsPage']);
    }

    public function getPaginatedFailedLoginsProperty()
    {
        return LoginAttempt::where('success', false)->orderByDesc('attempted_at')->paginate(5, ['*'], 'failedPage');
    }

    public function render()
    {
        return view('livewire.global.widgets.system-check.system-check', [
            'paginatedLogins' => $this->paginatedLogins,
            'paginatedFailedLogins' => $this->paginatedFailedLogins,
        ]);
    }
}
