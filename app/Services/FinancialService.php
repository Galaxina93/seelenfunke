<?php

namespace App\Services;

use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialService
{
    /**
     * Berechnet die Übersicht für einen spezifischen Monat für den Admin.
     */
    public function getMonthlyStats($adminId, $month, $year)
    {
        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();

        $fixedIncome = 0;
        $fixedExpenses = 0;

        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month)) {
                    if ($item->amount >= 0) {
                        $fixedIncome += $item->amount;
                    } else {
                        $fixedExpenses += $item->amount;
                    }
                }
            }
        }

        $specialIssues = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->get();

        $specialExpenses = 0;
        $specialIncome = 0;

        foreach($specialIssues as $issue) {
            if($issue->amount >= 0) {
                $specialIncome += $issue->amount;
            } else {
                $specialExpenses += $issue->amount;
            }
        }

        $totalBudget = $fixedIncome + $specialIncome;
        $totalSpent = $fixedExpenses + $specialExpenses;
        $available = $totalBudget + $totalSpent;

        return [
            'fixed_income' => $fixedIncome,
            'fixed_expenses' => $fixedExpenses,
            'special_income' => $specialIncome,
            'special_expenses' => $specialExpenses,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'available' => $available
        ];
    }

    private function isDueInMonth($item, $currentMonth)
    {
        $startMonth = $item->first_payment_date->month;
        $interval = $item->interval_months;

        $diff = ($currentMonth - $startMonth);
        if ($diff < 0) $diff += 12;

        return ($diff % $interval) === 0;
    }

    /**
     * Generiert die detaillierte Jahresmatrix.
     */
    public function getYearlyMatrix($adminId, $year)
    {
        $structure = [
            'income' => [
                'label' => 'Einnahmen',
                'color' => 'text-emerald-600',
                'bg' => 'bg-emerald-400',
                'months' => array_fill(1, 12, 0),
                'year_sum' => 0,
                'items' => []
            ],
            'fixed_private' => [
                'label' => 'Fixkosten (Privat)',
                'color' => 'text-rose-500',
                'bg' => 'bg-rose-400',
                'months' => array_fill(1, 12, 0),
                'year_sum' => 0,
                'items' => []
            ],
            'fixed_business' => [
                'label' => 'Fixkosten (Gewerbe)',
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-400',
                'months' => array_fill(1, 12, 0),
                'year_sum' => 0,
                'items' => []
            ],
            'special_private' => [
                'label' => 'Variabel (Privat)',
                'color' => 'text-orange-500',
                'bg' => 'bg-orange-400',
                'months' => array_fill(1, 12, 0),
                'year_sum' => 0,
                'items' => []
            ],
            'special_business' => [
                'label' => 'Variabel (Gewerbe)',
                'color' => 'text-indigo-500',
                'bg' => 'bg-indigo-400',
                'months' => array_fill(1, 12, 0),
                'year_sum' => 0,
                'items' => []
            ],
        ];

        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();

        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                if ($item->amount >= 0) {
                    $catKey = 'income';
                } else {
                    $catKey = $item->is_business ? 'fixed_business' : 'fixed_private';
                }

                $itemRow = [
                    'name' => $item->name . ' (' . $group->name . ')',
                    'months' => array_fill(1, 12, 0),
                    'year_sum' => 0
                ];

                for ($m = 1; $m <= 12; $m++) {
                    if ($this->isDueInMonth($item, $m)) {
                        $structure[$catKey]['months'][$m] += $item->amount;
                        $structure[$catKey]['year_sum'] += $item->amount;

                        $itemRow['months'][$m] = $item->amount;
                        $itemRow['year_sum'] += $item->amount;
                    }
                }

                $structure[$catKey]['items'][] = $itemRow;
            }
        }

        $specials = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->get();

        foreach ($specials as $special) {
            $m = $special->execution_date->month;

            if ($special->amount >= 0) {
                $catKey = 'income';
            } else {
                $catKey = $special->is_business ? 'special_business' : 'special_private';
            }

            $structure[$catKey]['months'][$m] += $special->amount;
            $structure[$catKey]['year_sum'] += $special->amount;

            $groupName = $special->category ?: 'Sonstiges';

            if (!isset($structure[$catKey]['items'][$groupName])) {
                $structure[$catKey]['items'][$groupName] = [
                    'name' => $groupName . ' (Kumuliert)',
                    'months' => array_fill(1, 12, 0),
                    'year_sum' => 0
                ];
            }

            $structure[$catKey]['items'][$groupName]['months'][$m] += $special->amount;
            $structure[$catKey]['items'][$groupName]['year_sum'] += $special->amount;
        }

        $totals = [
            'months' => array_fill(1, 12, 0),
            'year_sum' => 0
        ];

        foreach ($structure as $cat) {
            for ($m = 1; $m <= 12; $m++) {
                $totals['months'][$m] += $cat['months'][$m];
            }
            $totals['year_sum'] += $cat['year_sum'];
        }

        return [
            'categories' => $structure,
            'totals' => $totals
        ];
    }

    /**
     * Daten für das Kreis-Diagramm (Kategorien)
     */
    public function getPieChartData($adminId, $from, $to)
    {
        $data = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereBetween('execution_date', [$from, $to])
            ->select('category', DB::raw('SUM(ABS(amount)) as total')) // Absolute Werte für Pie Chart
            ->groupBy('category')
            ->get();

        return [
            'labels' => $data->pluck('category'),
            'data' => $data->pluck('total'),
        ];
    }

    /**
     * Daten für das Balken-Diagramm (Einnahmen vs Ausgaben)
     */
    public function getBarChartData($adminId, $from, $to)
    {
        // 1. Init Data (Monatlich aggregiert für bessere Übersicht)
        $months = [];
        $incomeData = [];
        $expenseData = [];

        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        // Iteriere monatlich
        $current = $start->copy();
        while($current <= $end) {
            $key = $current->format('Y-m');
            $months[$key] = [
                'label' => $current->format('M Y'),
                'income' => 0,
                'expense' => 0
            ];
            $current->addMonth();
        }

        // 2. Fixkosten holen & verteilen
        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
        foreach($groups as $group) {
            foreach($group->items as $item) {
                // Prüfe für jeden Monat im Zeitraum
                foreach($months as $ym => &$data) {
                    $dateObj = Carbon::createFromFormat('Y-m', $ym);
                    if ($this->isDueInMonth($item, $dateObj->month)) {
                        if ($item->amount >= 0) {
                            $data['income'] += $item->amount;
                        } else {
                            $data['expense'] += abs($item->amount); // Als positiven Wert für Chart Balken
                        }
                    }
                }
            }
        }

        // 3. Sonderausgaben holen
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereBetween('execution_date', [$from, $to])
            ->get();

        foreach($specials as $special) {
            $key = $special->execution_date->format('Y-m');
            if(isset($months[$key])) {
                if ($special->amount >= 0) {
                    $months[$key]['income'] += $special->amount;
                } else {
                    $months[$key]['expense'] += abs($special->amount);
                }
            }
        }

        return [
            'labels' => array_column($months, 'label'),
            'income' => array_column($months, 'income'),
            'expense' => array_column($months, 'expense'),
        ];
    }
}
