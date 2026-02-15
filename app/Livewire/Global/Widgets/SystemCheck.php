<?php

namespace App\Livewire\Global\Widgets;

use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
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

    // Pagination Einstellungen
    protected $paginationTheme = 'tailwind';

    // Toggle States für Tabellen
    public $showFailedLogins = false;
    public $showFullLogins = false;

    // Filter
    public $dateStart;
    public $dateEnd;
    public $filterType = 'all'; // 'all', 'business', 'private'

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
        } else {
            // Standardwerte (Ganzes Jahr per Default wie gewünscht)
            $this->setWholeYear(false);
        }
    }

    public function saveSettings($rangeMode = 'custom')
    {
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
        // --- BESTEHENDE LOGIK (Logins & Visits) ---
        $lastLogins = $this->getAllLoginsCollection();

        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();

        $visitsByDay = PageVisit::whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->select(DB::raw('WEEKDAY(created_at) as weekday'), DB::raw('count(*) as total'))
            ->groupBy('weekday')
            ->pluck('total', 'weekday')
            ->toArray();

        $visitCounts = [];
        for ($i = 0; $i < 7; $i++) {
            $visitCounts[] = $visitsByDay[$i] ?? 0;
        }

        // --- NEUE LOGIK (Business Intelligence) ---

        $start = Carbon::parse($this->dateStart)->startOfDay();
        $end = Carbon::parse($this->dateEnd)->endOfDay();

        // 1. Chart-Daten Generierung
        $chartData = [
            'labels' => [],
            'revenue' => [],
            'expenses' => [],
            'profit' => []
        ];

        // Fixkosten Query Vorbereitung
        $costItemsQuery = FinanceCostItem::query();
        if($this->filterType === 'business') $costItemsQuery->where('is_business', true);
        if($this->filterType === 'private') $costItemsQuery->where('is_business', false);
        $allCostItems = $costItemsQuery->get();

        $currentDate = $start->copy()->startOfMonth();
        $finalDate = $end->copy()->endOfMonth();

        $totalRevenuePeriod = 0;
        $totalExpensesPeriod = 0;
        $totalFixedExpensesPeriod = 0;
        $totalVariableExpensesPeriod = 0;

        // Loop für Chart-Daten
        while ($currentDate <= $finalDate) {
            $mStart = $currentDate->copy()->startOfMonth();
            $mEnd = $currentDate->copy()->endOfMonth();

            if($mStart < $start) $mStart = $start;
            if($mEnd > $end) $mEnd = $end;

            if($mStart > $mEnd) {
                $currentDate->addMonth();
                continue;
            }

            // Fixkosten Berechnung
            $fixedIncomeMonth = 0;
            $fixedExpenseMonth = 0;

            $daysInMonth = $currentDate->daysInMonth;
            $daysInPeriod = $mStart->diffInDays($mEnd) + 1;
            $factor = $daysInPeriod / $daysInMonth;

            foreach($allCostItems as $item) {
                $monthlyAmount = $item->amount / ($item->interval_months ?: 1);
                $periodAmount = $monthlyAmount * $factor;

                if ($periodAmount >= 0) {
                    $fixedIncomeMonth += $periodAmount;
                } else {
                    $fixedExpenseMonth += abs($periodAmount);
                }
            }

            // Fixkosten nach Typ trennen für Detail-Anzeige
            // Wir berechnen hier aber den Gesamt-Flow für das Chart

            // Variable Einnahmen/Ausgaben (Special Issues)
            $specialsQuery = FinanceSpecialIssue::whereBetween('execution_date', [$mStart, $mEnd]);
            if($this->filterType === 'business') $specialsQuery->where('is_business', true);
            if($this->filterType === 'private') $specialsQuery->where('is_business', false);
            $specials = $specialsQuery->get();

            $specialIncome = $specials->where('amount', '>=', 0)->sum('amount');
            $specialExpenses = abs($specials->where('amount', '<', 0)->sum('amount'));

            // Shop Umsatz
            $shopRevenue = 0;
            if ($this->filterType !== 'private') {
                $shopRevenue = Order::whereBetween('created_at', [$mStart, $mEnd])
                        ->where('payment_status', 'paid')
                        ->sum('total_price') / 100;
            }

            $revenue = $shopRevenue + $specialIncome + $fixedIncomeMonth;
            $expenses = $specialExpenses + $fixedExpenseMonth;
            $profit = $revenue - $expenses;

            $totalRevenuePeriod += $revenue;
            $totalExpensesPeriod += $expenses;
            $totalFixedExpensesPeriod += $fixedExpenseMonth;
            $totalVariableExpensesPeriod += $specialExpenses;

            $chartData['labels'][] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
            $chartData['revenue'][] = round($revenue, 2);
            $chartData['expenses'][] = round($expenses, 2);
            $chartData['profit'][] = round($profit, 2);

            $currentDate->addMonth();
        }

        // 2. KPIs & Vergleiche
        $durationInDays = max(1, $start->diffInDays($end) + 1);
        $prevStart = $start->copy()->subDays($durationInDays);
        $prevEnd = $start->copy()->subDay();

        $prevRevenue = $this->calculateRevenueForPeriod($prevStart, $prevEnd);
        $revenueGrowth = $prevRevenue > 0 ? (($totalRevenuePeriod - $prevRevenue) / $prevRevenue) * 100 : 0;

        $monthsCount = max(1, count($chartData['labels'] ?? []));
        $avgRevenue = $totalRevenuePeriod / $monthsCount;
        $avgProfit = ($totalRevenuePeriod - $totalExpensesPeriod) / $monthsCount;

        $projectedProfit = $avgProfit * 12;
        $margin = $totalRevenuePeriod > 0 ? (($totalRevenuePeriod - $totalExpensesPeriod) / $totalRevenuePeriod) * 100 : 0;

        // Break Even
        $monthlyFixedBase = 0;
        foreach($allCostItems as $item) {
            $monthlyFixedBase += abs($item->amount) / ($item->interval_months ?: 1);
        }
        $breakEvenPoint = $monthlyFixedBase;

        // DETAIL KPIs für die neuen Kacheln

        // Einnahmen (Fix) Betrag (nur Fixkosten Income)
        $fixedIncomeTotal = 0;
        foreach($allCostItems as $item) {
            if($item->amount > 0) {
                $val = $item->amount / ($item->interval_months ?: 1);
                // Auf gewählten Zeitraum hochrechnen
                $fixedIncomeTotal += ($val * ($durationInDays/30));
            }
        }

        // Shop Umsätze (schon berechnet, aber nochmal explizit für Kachel)
        $shopRevenueTotalKpi = 0;
        if ($this->filterType !== 'private') {
            $shopRevenueTotalKpi = Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100;
        }

        // Fixkosten Privat vs Gewerbe
        $fixPrivat = FinanceCostItem::where('is_business', false)->get()->sum(fn($i) => abs($i->amount)/($i->interval_months?:1)) * ($durationInDays/30);
        $fixGewerbe = FinanceCostItem::where('is_business', true)->get()->sum(fn($i) => abs($i->amount)/($i->interval_months?:1)) * ($durationInDays/30);

        // Variable Kosten Privat vs Gewerbe
        $varPrivat = abs(FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])->where('is_business', false)->where('amount', '<', 0)->sum('amount'));
        $varGewerbe = abs(FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])->where('is_business', true)->where('amount', '<', 0)->sum('amount'));


        // 3. Top Listen
        $topExpensesQuery = FinanceSpecialIssue::select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0);

        if($this->filterType !== 'all') $topExpensesQuery->where('is_business', $this->filterType === 'business');
        $topExpenses = $topExpensesQuery->groupBy('category')->orderByDesc('total')->take(5)->get();

        // 4. Top Kunden
        $topCustomers = collect();
        if ($this->filterType !== 'private') {
            $topCustomers = Order::select('email', DB::raw('SUM(total_price) as total'))
                ->whereBetween('created_at', [$start, $end])
                ->where('payment_status', 'paid')
                ->groupBy('email')
                ->orderByDesc('total')
                ->take(5)
                ->get()
                ->map(function($order) {
                    $order->total = $order->total / 100;
                    $order->display_name = $order->email;
                    return $order;
                });
        }

        // 5. Produkte
        $highRevenueProd = null;
        $lowRevenueProd = null;
        $topRanking = collect();

        if ($this->filterType !== 'private') {
            $productRevenue = OrderItem::whereHas('order', function($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid');
            })
                ->select('product_name', DB::raw('SUM(total_price) as total'))
                ->groupBy('product_name')
                ->orderByDesc('total')
                ->get();

            $highRevenueProd = $productRevenue->first();
            $lowRevenueProd = $productRevenue->last();

            $topRanking = OrderItem::whereHas('order', function($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid');
            })
                ->select('product_name', DB::raw('SUM(quantity) as count'))
                ->groupBy('product_name')
                ->orderByDesc('count')
                ->take(3)
                ->get();
        }

        // 6. Sonstiges
        $pendingInvoicesCount = Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->count();
        $pendingInvoicesSum = Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->sum('total') / 100;

        $totalCustomers = Customer::count();
        $newCustomersPeriod = Customer::whereBetween('created_at', [$start, $end])->count();

        $this->stats = [
            // User / System
            'total_users'            => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today'     => $lastLogins->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count()
                + Admin::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count()
                + Employee::whereBetween('created_at', [$startOfWeek, $endOfWeek])->count(),
            'failed_logins'          => DB::table('login_attempts')->where('success', false)->count(),
            'active_sessions'        => DB::table('sessions')->count(),
            'never_logged_in'        => $lastLogins->whereNull('last_seen')->count(),
            'inactive_30_days'       => $lastLogins->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(Carbon::now()->subDays(30)))->count(),
            'frontend_visits_today'  => PageVisit::whereDate('created_at', now())->count(),
            'visit_days'             => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            'visit_counts'           => $visitCounts,

            // Business Stats
            'chart_data'             => $chartData,
            'total_revenue'          => $totalRevenuePeriod,
            'total_profit'           => $totalRevenuePeriod - $totalExpensesPeriod,
            'revenue_growth'         => round($revenueGrowth, 1),
            'avg_revenue'            => $avgRevenue,
            'avg_profit'             => $avgProfit,
            'projected_year'         => $projectedProfit,
            'margin'                 => round($margin, 1),
            'break_even_monthly'     => $breakEvenPoint,

            // Neue Detail KPIs
            'fixed_income_total'     => $fixedIncomeTotal,
            'shop_revenue'           => $shopRevenueTotalKpi,
            'fixed_expenses_priv'    => $fixPrivat,
            'fixed_expenses_gew'     => $fixGewerbe,
            'var_expenses_priv'      => $varPrivat,
            'var_expenses_gew'       => $varGewerbe,
            'variable_expenses'      => $totalVariableExpensesPeriod,

            'pending_invoices'       => ['count' => $pendingInvoicesCount, 'sum' => $pendingInvoicesSum],
            'top_expenses'           => $topExpenses,
            'top_customers'          => $topCustomers,
            'high_revenue_prod'      => $highRevenueProd,
            'low_revenue_prod'       => $lowRevenueProd,
            'product_ranking'        => $topRanking,
            'customer_stats'         => ['total' => $totalCustomers, 'new_period' => $newCustomersPeriod]
        ];

        $this->dispatch('update-charts', stats: $this->stats);
    }

    private function calculateRevenueForPeriod($start, $end) {
        $shop = 0;
        if($this->filterType !== 'private') {
            $shop = Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100;
        }

        $specialsQuery = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])->where('amount', '>=', 0);
        if($this->filterType === 'business') $specialsQuery->where('is_business', true);
        if($this->filterType === 'private') $specialsQuery->where('is_business', false);
        $special = $specialsQuery->sum('amount');

        $costItemsQuery = FinanceCostItem::query();
        if($this->filterType === 'business') $costItemsQuery->where('is_business', true);
        if($this->filterType === 'private') $costItemsQuery->where('is_business', false);

        $fixed = 0;
        $daysInPeriod = max(1, $start->diffInDays($end) + 1);
        $daysInMonth = 30;
        $factor = $daysInPeriod / $daysInMonth;

        foreach($costItemsQuery->get() as $item) {
            $val = $item->amount / ($item->interval_months ?: 1);
            if($val > 0) $fixed += ($val * $factor);
        }

        return $shop + $special + $fixed;
    }

    private function getAllLoginsCollection()
    {
        $adminLogins = Admin::with('profile')->get()->map(fn($u) => [
            'type' => 'Admin', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);
        $customerLogins = Customer::with('profile')->get()->map(fn($u) => [
            'type' => 'Customer', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);
        $employeeLogins = Employee::with('profile')->get()->map(fn($u) => [
            'type' => 'Employee', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen
        ]);
        return $adminLogins->merge($customerLogins)->merge($employeeLogins);
    }

    public function getPaginatedLoginsProperty()
    {
        $allLogins = $this->getAllLoginsCollection()->sortByDesc('last_seen')->values();
        $perPage = 8;
        $currentPage = LengthAwarePaginator::resolveCurrentPage('loginsPage');
        $items = $allLogins->forPage($currentPage, $perPage);
        return new LengthAwarePaginator($items, $allLogins->count(), $perPage, $currentPage, ['path' => request()->url(), 'pageName' => 'loginsPage']);
    }

    public function getPaginatedFailedLoginsProperty()
    {
        return LoginAttempt::where('success', false)->orderByDesc('attempted_at')->paginate(5, ['email', 'ip_address', 'attempted_at'], 'failedPage');
    }

    public function render()
    {
        return view('livewire.global.widgets.system-check.system-check', [
            'paginatedLogins' => $this->paginatedLogins,
            'paginatedFailedLogins' => $this->paginatedFailedLogins,
        ]);
    }
}
