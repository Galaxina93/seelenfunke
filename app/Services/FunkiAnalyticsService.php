<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Tracking\PageVisit; // Geändert auf deinen neuen Namespace!
use App\Models\LoginAttempt;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Invoice;
use App\Models\Product\Product;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Financial\FinanceCostItem;

class FunkiAnalyticsService
{
    public function getHealthChecks(): array
    {
        if (!auth()->guard('admin')->check()) return [];

        return array_filter([
            'inventory' => $this->checkInventory(),
            'special_issues' => $this->checkSpecialIssues(),
            'contracts' => $this->checkContracts(),
        ]);
    }

    private function checkInventory(): array
    {
        $threshold = shop_setting('inventory_low_stock_threshold', 5);
        $lowStockProducts = Product::where('type', 'physical')
            ->where('track_quantity', true)
            ->where('quantity', '<', $threshold)
            ->where('status', 'active')
            ->get();

        return [
            'title' => 'Lagerbestand',
            'status' => $lowStockProducts->count() > 0 ? 'danger' : 'success',
            'message' => $lowStockProducts->count() > 0 ? $lowStockProducts->count() . " Artikel unter Limit!" : "Lagerbestände optimal.",
            'icon' => 'bi-box-seam',
            'count' => $lowStockProducts->count(),
            'data' => $lowStockProducts
        ];
    }

    private function checkSpecialIssues(): array
    {
        $missing = FinanceSpecialIssue::where(function ($query) {
            $query->whereNull('file_paths')
                ->orWhere('file_paths', '[]')
                ->orWhere('file_paths', '');
        })->orderBy('execution_date', 'desc')->get();

        return [
            'title' => 'Sonderausgaben',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Positionen ohne Beleg." : "Alle Ausgaben belegt.",
            'icon' => 'bi-receipt',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    private function checkContracts(): array
    {
        $missing = FinanceCostItem::whereNull('contract_file_path')->with('group')->get();

        return [
            'title' => 'Verträge',
            'status' => $missing->count() > 0 ? 'danger' : 'success',
            'message' => $missing->count() > 0 ? $missing->count() . " Unterlagen fehlen." : "Dokumente vollständig.",
            'icon' => 'bi-file-earmark-text',
            'count' => $missing->count(),
            'data' => $missing
        ];
    }

    public function getStats($dateStart, $dateEnd, $filterType, $lastLogins)
    {
        $start = Carbon::parse($dateStart)->startOfDay();
        $end = Carbon::parse($dateEnd)->endOfDay();
        $diffInDays = $start->diffInDays($end);

        // ==========================================
        // TRAFFIC & ANALYTICS (Eigene Datenbank)
        // ==========================================
        $visitsQuery = PageVisit::whereBetween('created_at', [$start, $end]);

        $totalPageViews = (clone $visitsQuery)->count();
        $uniqueVisitors = (clone $visitsQuery)->distinct('session_id')->count('session_id');

        // Geräte Analyse (Desktop vs Mobile)
        $mobileVisits = (clone $visitsQuery)->where(function($q) {
            $q->where('user_agent', 'LIKE', '%Mobile%')
                ->orWhere('user_agent', 'LIKE', '%Android%')
                ->orWhere('user_agent', 'LIKE', '%iPhone%')
                ->orWhere('user_agent', 'LIKE', '%iPad%');
        })->count();
        $desktopVisits = $totalPageViews - $mobileVisits;

        // Top Seiten
        $topPages = (clone $visitsQuery)
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(6)
            ->get();

        // Top Referrers (Herkunft) - Gruppiert nach Domain in PHP
        $rawReferrers = (clone $visitsQuery)
            ->whereNotNull('referer')
            ->select('referer', DB::raw('count(*) as count'))
            ->groupBy('referer')
            ->orderByDesc('count')
            ->limit(50) // Hole die Top 50 URLs, um sie dann nach Domain zu filtern
            ->get();

        $topReferrers = collect();
        foreach ($rawReferrers as $ref) {
            $host = parse_url($ref->referer, PHP_URL_HOST);
            $host = str_replace('www.', '', $host);
            if (!$host || str_contains($host, request()->getHost())) continue; // Eigene Domain ausschließen

            if ($topReferrers->has($host)) {
                $topReferrers[$host] += $ref->count;
            } else {
                $topReferrers->put($host, $ref->count);
            }
        }
        $topReferrers = $topReferrers->sortDesc()->take(5);

        // Traffic Chart Datenstruktur
        $visitLabels = [];
        $visitCounts = [];
        $uniqueCounts = [];

        if ($diffInDays <= 31) {
            // Gruppierung nach TAGEN
            $visitsByDay = (clone $visitsQuery)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as total'), DB::raw('count(distinct session_id) as unique_total'))
                ->groupBy('date')
                ->get()
                ->keyBy('date');

            $periodTraffic = CarbonPeriod::create($start, $end);
            foreach ($periodTraffic as $date) {
                $dateString = $date->format('Y-m-d');
                $visitLabels[] = $date->format('d.m.');
                $visitCounts[] = $visitsByDay->has($dateString) ? $visitsByDay[$dateString]->total : 0;
                $uniqueCounts[] = $visitsByDay->has($dateString) ? $visitsByDay[$dateString]->unique_total : 0;
            }
        } else {
            // Gruppierung nach MONATEN
            $visitsByMonth = (clone $visitsQuery)
                ->select(DB::raw('YEAR(created_at) as year'), DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as total'), DB::raw('count(distinct session_id) as unique_total'))
                ->groupBy('year', 'month')
                ->get();

            $currentDate = $start->copy()->startOfMonth();
            $finalDate = $end->copy()->endOfMonth();

            while ($currentDate <= $finalDate) {
                $y = $currentDate->year;
                $m = $currentDate->month;
                $match = $visitsByMonth->first(fn($v) => $v->year == $y && $v->month == $m);

                $visitLabels[] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $visitCounts[] = $match ? $match->total : 0;
                $uniqueCounts[] = $match ? $match->unique_total : 0;

                $currentDate->addMonth();
            }
        }

        // ==========================================
        // FINANZEN & SHOP (Dein bestehender Code)
        // ==========================================
        $chartData = ['labels' => [], 'revenue' => [], 'expenses' => [], 'profit' => []];

        $costItemsQuery = FinanceCostItem::query();
        if ($filterType === 'business') $costItemsQuery->where('is_business', true);
        if ($filterType === 'private') $costItemsQuery->where('is_business', false);
        $allCostItems = $costItemsQuery->get();

        if ($diffInDays <= 31) {
            $period = CarbonPeriod::create($start, $end);
            foreach ($period as $date) {
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();

                $fixedIncomeDay = 0;
                $fixedExpenseDay = 0;

                foreach ($allCostItems as $item) {
                    $dailyAmount = ($item->amount / ($item->interval_months ?: 1)) / 30.42;
                    if ($dailyAmount >= 0) $fixedIncomeDay += $dailyAmount;
                    else $fixedExpenseDay += abs($dailyAmount);
                }

                $specials = FinanceSpecialIssue::whereBetween('execution_date', [$dayStart, $dayEnd])
                    ->when($filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialInc = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExp = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRev = 0;
                if ($filterType !== 'private') {
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

                if ($mStart < $start) $mStart = $start;
                if ($mEnd > $end) $mEnd = $end;

                $fixedIncomeMonth = 0;
                $fixedExpenseMonth = 0;
                $factor = ($mStart->diffInDays($mEnd) + 1) / $currentDate->daysInMonth;

                foreach ($allCostItems as $item) {
                    $monthlyAmount = $item->amount / ($item->interval_months ?: 1);
                    $periodAmount = $monthlyAmount * $factor;
                    if ($periodAmount >= 0) $fixedIncomeMonth += $periodAmount;
                    else $fixedExpenseMonth += abs($periodAmount);
                }

                $specials = FinanceSpecialIssue::whereBetween('execution_date', [$mStart, $mEnd])
                    ->when($filterType === 'business', fn($q) => $q->where('is_business', true))
                    ->when($filterType === 'private', fn($q) => $q->where('is_business', false))
                    ->get();

                $specialIncome = $specials->where('amount', '>=', 0)->sum('amount');
                $specialExpenses = abs($specials->where('amount', '<', 0)->sum('amount'));

                $shopRevenue = ($filterType !== 'private') ? Order::whereBetween('created_at', [$mStart, $mEnd])->where('payment_status', 'paid')->sum('total_price') / 100 : 0;

                $revenue = $shopRevenue + $specialIncome + $fixedIncomeMonth;
                $expenses = $specialExpenses + $fixedExpenseMonth;

                $chartData['labels'][] = $currentDate->locale('de')->shortMonthName . ' ' . $currentDate->format('y');
                $chartData['revenue'][] = round($revenue, 2);
                $chartData['expenses'][] = round($expenses, 2);
                $chartData['profit'][] = round($revenue - $expenses, 2);

                $currentDate->addMonth();
            }
        }

        $topExpenses = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->take(5)
            ->get();

        $totalRevenuePeriod = array_sum($chartData['revenue']);
        $totalExpensesPeriod = array_sum($chartData['expenses']);

        $durationInDays = max(1, $start->diffInDays($end) + 1);
        $prevStart = $start->copy()->subDays($durationInDays);
        $prevEnd = $start->copy()->subDay();

        $prevRevenue = $this->calculateRevenueForPeriod($prevStart, $prevEnd, $filterType);
        $revenueGrowth = $prevRevenue > 0 ? (($totalRevenuePeriod - $prevRevenue) / $prevRevenue) * 100 : 0;

        $unitsCount = max(1, count($chartData['labels']));
        $avgProfit = ($totalRevenuePeriod - $totalExpensesPeriod) / $unitsCount;
        $projectedProfit = ($diffInDays <= 31) ? ($avgProfit * 365 / 30.42) : ($avgProfit * 12);

        $margin = $totalRevenuePeriod > 0 ? (($totalRevenuePeriod - $totalExpensesPeriod) / $totalRevenuePeriod) * 100 : 0;

        $fixGewerbe = $allCostItems->where('is_business', true)->where('amount', '<', 0)->sum(fn($i) => abs($i->amount) / ($i->interval_months ?: 1)) * ($durationInDays / 30.42);
        $fixPrivat = $allCostItems->where('is_business', false)->where('amount', '<', 0)->sum(fn($i) => abs($i->amount) / ($i->interval_months ?: 1)) * ($durationInDays / 30.42);

        $variableExpensesTotal = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])
            ->where('amount', '<', 0)
            ->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))
            ->sum(DB::raw('ABS(amount)'));

        $productStatsQuery = OrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))
            ->select('product_name', DB::raw('SUM(total_price)/100 as total'))
            ->groupBy('product_name')
            ->orderByDesc('total');

        $highRevenueProd = $productStatsQuery->first();
        $lowRevenueProd = $productStatsQuery->clone()->orderBy('total', 'asc')->first();

        $breakEvenValue = ($fixGewerbe + $fixPrivat) / max(1, ($durationInDays / 30.42));
        $qualityScore = $this->calculateShopQualityScore($margin, $revenueGrowth, $totalRevenuePeriod - $totalExpensesPeriod, $totalRevenuePeriod, $breakEvenValue * ($durationInDays / 30.42));

        return [
            'total_users' => Admin::count() + Customer::count() + Employee::count(),
            'active_users_today' => collect($lastLogins)->whereBetween('last_seen', [Carbon::today(), Carbon::now()])->count(),
            'new_registrations_week' => Customer::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count() + Admin::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count() + Employee::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'failed_logins' => LoginAttempt::where('success', false)->count(),
            'active_sessions' => DB::table('sessions')->count(),
            'never_logged_in' => collect($lastLogins)->whereNull('last_seen')->count(),
            'inactive_30_days' => collect($lastLogins)->filter(fn($u) => $u['last_seen'] && Carbon::parse($u['last_seen'])->lt(now()->subDays(30)))->count(),

            // NEUE TRAFFIC DATEN
            'frontend_visits_total' => $totalPageViews,
            'frontend_unique_total' => $uniqueVisitors,
            'desktop_visits' => $desktopVisits,
            'mobile_visits' => $mobileVisits,
            'top_pages' => $topPages,
            'top_referrers' => $topReferrers,
            'visit_days' => $visitLabels,
            'visit_counts' => $visitCounts,
            'unique_counts' => $uniqueCounts,

            // SHOP DATEN
            'chart_data' => $chartData,
            'total_revenue' => $totalRevenuePeriod,
            'total_profit' => $totalRevenuePeriod - $totalExpensesPeriod,
            'revenue_growth' => round($revenueGrowth, 1),
            'avg_profit' => $avgProfit,
            'projected_year' => $projectedProfit,
            'margin' => round($margin, 1),
            'break_even_monthly' => ($fixGewerbe + $fixPrivat) / ($durationInDays / 30.42),
            'shop_quality_score' => $qualityScore,
            'fixed_income_total' => $allCostItems->where('amount', '>', 0)->sum(fn($i) => $i->amount / ($i->interval_months ?: 1)) * ($durationInDays / 30.42),
            'shop_revenue' => ($filterType !== 'private') ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100 : 0,
            'fixed_expenses_priv' => $fixPrivat,
            'fixed_expenses_gew' => $fixGewerbe,
            'variable_expenses' => $variableExpensesTotal,
            'pending_invoices' => [
                'count' => Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->count(),
                'sum' => Invoice::where('status', 'open')->whereBetween('created_at', [$start, $end])->sum('total') / 100
            ],
            'top_expenses' => $topExpenses,
            'top_customers' => $filterType !== 'private' ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->select('email', DB::raw('SUM(total_price)/100 as total'))->groupBy('email')->orderByDesc('total')->take(5)->get()->map(fn($o) => ['category' => $o->email, 'total' => $o->total]) : collect(),
            'high_revenue_prod' => $highRevenueProd,
            'low_revenue_prod' => $lowRevenueProd,
            'product_ranking' => $filterType !== 'private' ? OrderItem::whereHas('order', fn($q) => $q->whereBetween('created_at', [$start, $end])->where('payment_status', 'paid'))->select('product_name', DB::raw('SUM(quantity) as qty'))->groupBy('product_name')->orderByDesc('qty')->take(3)->get() : collect(),
        ];
    }

    private function calculateShopQualityScore($margin, $growth, $profit, $revenue, $breakEvenTotal)
    {
        $score = 0;
        if ($profit > 0) $score += 20;
        if ($revenue > $breakEvenTotal && $breakEvenTotal > 0) $score += 20;
        $score += min(30, max(0, $margin));
        if ($growth > 0) $score += min(30, $growth * 1.5);
        else $score -= min(20, abs($growth));
        return max(0, min(100, round($score)));
    }

    private function calculateRevenueForPeriod($start, $end, $filterType)
    {
        $shop = ($filterType !== 'private') ? Order::whereBetween('created_at', [$start, $end])->where('payment_status', 'paid')->sum('total_price') / 100 : 0;
        $special = FinanceSpecialIssue::whereBetween('execution_date', [$start, $end])->where('amount', '>=', 0)->when($filterType !== 'all', fn($q) => $q->where('is_business', $filterType === 'business'))->sum('amount');
        return $shop + $special;
    }

    public function getAllLoginsCollection()
    {
        return Admin::with('profile')->get()->map(fn($u) => ['type' => 'Admin', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen])
            ->merge(Customer::with('profile')->get()->map(fn($u) => ['type' => 'Customer', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]))
            ->merge(Employee::with('profile')->get()->map(fn($u) => ['type' => 'Employee', 'first_name' => $u->first_name, 'last_name' => $u->last_name, 'last_seen' => optional($u->profile)->last_seen]));
    }
}
