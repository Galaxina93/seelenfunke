<?php
namespace App\Services;

use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\OrderOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialService
{
    public static function signedInvoiceCents(\App\Models\Accounting\AccountingInvoice $invoice, bool $isNet = false): int
    {
        $rawTotal = (int) $invoice->total;
        $rawTax = (int) $invoice->tax_amount;
        $isCredit = in_array($invoice->type, ['cancellation', 'credit_note'], true) || $rawTotal < 0;

        $total = $isCredit ? -abs($rawTotal) : abs($rawTotal);
        $tax = $isCredit ? -abs($rawTax) : abs($rawTax);

        return $isNet ? ($total - $tax) : $total;
    }

    public function filterNonDuplicateBankTransactions($bankTxs, $specialIssues)
    {
        if ($bankTxs->isEmpty()) {
            return collect();
        }

        return $bankTxs->filter(function ($tx) use ($specialIssues) {
            if (!empty($tx->accounting_cost_item_id) || !empty($tx->accounting_special_issue_id)) {
                return false;
            }
            $txAmount = round((float) $tx->amount, 2);
            $txDate = $tx->transaction_date ? Carbon::parse($tx->transaction_date) : null;

            foreach ($specialIssues as $special) {
                if (round((float) $special->amount, 2) === $txAmount) {
                    if (!$txDate || !$special->execution_date) {
                        return false;
                    }
                    if (abs($txDate->diffInDays(Carbon::parse($special->execution_date))) <= 7) {
                        return false;
                    }
                }
            }

            return true;
        })->values();
    }

    public function getMonthlyStats($adminId, $month, $year, $isNet = false, string $scope = 'all')
    {
        $scope = in_array($scope, ['all', 'business', 'private'], true) ? $scope : 'all';

        // 1. Fixkosten sauber getrennt nach Gewerbe & Privat
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        $fixedIncomeBiz = 0.0;
        $fixedIncomePriv = 0.0;
        $fixedExpensesBiz = 0.0;
        $fixedExpensesPriv = 0.0;

        foreach ($groups as $group) {
            $isIncome = ($group->type === 'income');
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month, $year)) {
                    $amount = (float) $item->amount;
                    if ($isNet && $item->is_business && isset($item->tax_rate) && $item->tax_rate > 0) {
                        $amount = $amount / (1 + ($item->tax_rate / 100));
                    }
                    if ($isIncome) {
                        if ($item->is_business) {
                            $fixedIncomeBiz += abs($amount);
                        } else {
                            $fixedIncomePriv += abs($amount);
                        }
                    } else {
                        if ($item->is_business) {
                            $fixedExpensesBiz += -abs($amount);
                        } else {
                            $fixedExpensesPriv += -abs($amount);
                        }
                    }
                }
            }
        }

        // 2. Sonderausgaben getrennt nach Gewerbe & Privat
        $specialIssues = AccountingSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->get();

        $netSpecialBiz = 0.0;
        $netSpecialPriv = 0.0;
        foreach ($specialIssues as $special) {
            $amount = (float) $special->amount;
            if ($isNet && $special->is_business && isset($special->tax_rate) && $special->tax_rate > 0) {
                $amount = $amount / (1 + ($special->tax_rate / 100));
            }
            if ($special->is_business) {
                $netSpecialBiz += $amount;
            } else {
                $netSpecialPriv += $amount;
            }
        }

        $rawBankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with('account')
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereNull('accounting_cost_item_id')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->get()
            : collect();

        $bankTxs = $this->filterNonDuplicateBankTransactions($rawBankTxs, $specialIssues);
        foreach ($bankTxs as $tx) {
            $isTxBiz = (bool) ($tx->is_business ?? ($tx->account ? $tx->account->is_business : false));
            if ($isTxBiz) {
                $netSpecialBiz += (float) $tx->amount;
            } else {
                $netSpecialPriv += (float) $tx->amount;
            }
        }

        // 3. Shop Umsätze (Basis: Rechnungen)
        $shopInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get(['type', 'total', 'tax_amount']);

        $shopRevenue = $shopInvoices->sum(fn($inv) => self::signedInvoiceCents($inv, (bool) $isNet));
        $shopIncome = $shopRevenue / 100;

        $buildBucket = function (float $fixIn, float $fixEx, float $netSpec, float $shopIn): array {
            $specEx = $netSpec < 0 ? $netSpec : 0.0;
            $specIn = $netSpec >= 0 ? $netSpec : 0.0;
            $budget = round($fixIn + $specIn + $shopIn, 2);
            $spent = round($fixEx + $specEx, 2);

            return [
                'fixed_income' => round($fixIn, 2),
                'fixed_expenses' => round($fixEx, 2),
                'special_income' => round($specIn, 2),
                'special_expenses' => round($specEx, 2),
                'shop_income' => round($shopIn, 2),
                'total_budget' => $budget,
                'total_spent' => $spent,
                'available' => round($budget + $spent, 2),
            ];
        };

        $bizBucket = $buildBucket($fixedIncomeBiz, $fixedExpensesBiz, $netSpecialBiz, $shopIncome);
        $privBucket = $buildBucket($fixedIncomePriv, $fixedExpensesPriv, $netSpecialPriv, 0.0);
        $allBucket = $buildBucket(
            $fixedIncomeBiz + $fixedIncomePriv,
            $fixedExpensesBiz + $fixedExpensesPriv,
            $netSpecialBiz + $netSpecialPriv,
            $shopIncome
        );

        $selectedBucket = match ($scope) {
            'business' => $bizBucket,
            'private' => $privBucket,
            default => $allBucket,
        };

        $selectedBucket['scope'] = $scope;
        $selectedBucket['breakdown'] = [
            'business' => $bizBucket,
            'private' => $privBucket,
            'all' => $allBucket,
        ];

        return $selectedBucket;
    }

    public function resolveMandantAdminId($adminId = null): ?string
    {
        if (!empty($adminId)) {
            return (string) $adminId;
        }

        if (auth()->guard('admin')->check()) {
            return (string) auth()->guard('admin')->id();
        }

        $adminWithAccounting = AccountingGroup::whereNotNull('admin_id')->value('admin_id')
            ?: AccountingSpecialIssue::whereNotNull('admin_id')->value('admin_id');

        if ($adminWithAccounting) {
            return (string) $adminWithAccounting;
        }

        $primaryAdminId = \App\Models\Admin\Admin::orderBy('created_at')->value('id');
        return $primaryAdminId ? (string) $primaryAdminId : null;
    }

    public function isDueInMonth($item, $currentMonth, $currentYear = null)
    {
        $start = Carbon::parse($item->first_payment_date);
        if ($currentYear !== null) {
            $check = Carbon::createFromDate((int)$currentYear, (int)$currentMonth, 1)->startOfMonth();
            $startMonth = $start->copy()->startOfMonth();
            if ($check->lt($startMonth)) return false;
            if ($item->last_payment_date && $check->gt(Carbon::parse($item->last_payment_date)->endOfMonth())) {
                return false;
            }
            $diffMonths = $check->diffInMonths($startMonth);
            return ($diffMonths % max(1, (int)$item->interval_months)) === 0;
        }

        $startMonth = $item->first_payment_date->month;
        $interval = max(1, (int)$item->interval_months);

        $diff = ($currentMonth - $startMonth);
        if ($diff < 0) $diff += 12;

        return ($diff % $interval) === 0;
    }

    public function getYearlyMatrix($adminId, $year, $isNet = false, string $scope = 'all')
    {
        $scope = in_array($scope, ['all', 'business', 'private'], true) ? $scope : 'all';

        $allCategories = [
            'income' => ['label' => 'Einnahmen (Fix)', 'color' => 'text-emerald-600 dark:text-emerald-400', 'bg' => 'bg-emerald-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'shop_income' => ['label' => 'Shop Umsätze', 'color' => 'text-teal-600 dark:text-teal-400', 'bg' => 'bg-teal-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'fixed_private' => ['label' => 'Fixkosten (Privat)', 'color' => 'text-rose-600 dark:text-rose-400', 'bg' => 'bg-rose-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'fixed_business' => ['label' => 'Fixkosten (Gewerbe)', 'color' => 'text-blue-600 dark:text-blue-400', 'bg' => 'bg-blue-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'special_private' => ['label' => 'Variabel (Privat)', 'color' => 'text-orange-600 dark:text-orange-400', 'bg' => 'bg-orange-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'special_business' => ['label' => 'Variabel (Gewerbe)', 'color' => 'text-indigo-600 dark:text-indigo-400', 'bg' => 'bg-indigo-500', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
        ];

        $allowedKeys = match ($scope) {
            'business' => ['shop_income', 'fixed_business', 'special_business'],
            'private' => ['income', 'fixed_private', 'special_private'],
            default => array_keys($allCategories),
        };

        $structure = array_intersect_key($allCategories, array_flip($allowedKeys));

        // 1. Fixkosten
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            $isIncome = ($group->type === 'income');
            foreach ($group->items as $item) {
                if ($scope === 'business' && !$item->is_business) {
                    continue;
                }
                if ($scope === 'private' && $item->is_business) {
                    continue;
                }

                $catKey = $isIncome
                    ? ($scope === 'business' ? 'shop_income' : 'income')
                    : ($item->is_business ? 'fixed_business' : 'fixed_private');

                if (!isset($structure[$catKey])) {
                    continue;
                }

                $itemRow = ['name' => $item->name . ' (' . $group->name . ')', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];

                for ($m = 1; $m <= 12; $m++) {
                    if ($this->isDueInMonth($item, $m, $year)) {
                        $amount = (float) $item->amount;
                        if ($isNet && $item->is_business && isset($item->tax_rate) && $item->tax_rate > 0) {
                            $amount = $amount / (1 + ($item->tax_rate / 100));
                        }
                        $amount = $isIncome ? abs($amount) : -abs($amount);

                        $structure[$catKey]['months'][$m] += $amount;
                        $structure[$catKey]['year_sum'] += $amount;
                        $itemRow['months'][$m] = $amount;
                        $itemRow['year_sum'] += $amount;
                    }
                }
                $structure[$catKey]['items'][] = $itemRow;
            }
        }

        // 2. Sonderausgaben (Variable Kosten)
        $specials = AccountingSpecialIssue::where('admin_id', $adminId)->whereYear('execution_date', $year)->get();
        $rawBankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereNull('accounting_cost_item_id')
                ->whereYear('transaction_date', $year)
                ->get()
            : collect();
        $bankTxs = $this->filterNonDuplicateBankTransactions($rawBankTxs, $specials);

        foreach ($specials as $special) {
            $catKey = $special->is_business ? 'special_business' : 'special_private';
            if (!isset($structure[$catKey])) {
                continue;
            }

            $m = $special->execution_date->month;
            $amount = (float) $special->amount;
            if ($isNet && $special->is_business && isset($special->tax_rate) && $special->tax_rate > 0) {
                $amount = $amount / (1 + ($special->tax_rate / 100));
            }

            $structure[$catKey]['months'][$m] += $amount;
            $structure[$catKey]['year_sum'] += $amount;

            $groupName = $special->category ?: 'Sonstiges';
            if (!isset($structure[$catKey]['items'][$groupName])) {
                $structure[$catKey]['items'][$groupName] = ['name' => $groupName . ' (Kumuliert)', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
            }
            $structure[$catKey]['items'][$groupName]['months'][$m] += $amount;
            $structure[$catKey]['items'][$groupName]['year_sum'] += $amount;
        }

        foreach ($bankTxs as $tx) {
            $isBusiness = (bool) ($tx->is_business ?? ($tx->account ? $tx->account->is_business : false));
            $catKey = $isBusiness ? 'special_business' : 'special_private';
            if (!isset($structure[$catKey])) {
                continue;
            }

            $m = \Carbon\Carbon::parse($tx->transaction_date)->month;
            $amount = (float) $tx->amount;

            $structure[$catKey]['months'][$m] += $amount;
            $structure[$catKey]['year_sum'] += $amount;

            $groupName = $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges';
            if (!isset($structure[$catKey]['items'][$groupName])) {
                $structure[$catKey]['items'][$groupName] = ['name' => $groupName . ' (Kumuliert)', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
            }
            $structure[$catKey]['items'][$groupName]['months'][$m] += $amount;
            $structure[$catKey]['items'][$groupName]['year_sum'] += $amount;
        }

        // 3. Shop (Basis: Rechnungen)
        if (isset($structure['shop_income'])) {
            $shopInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get(['invoice_date', 'type', 'total', 'tax_amount']);

            $shopRow = ['name' => 'Online Shop', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
            foreach ($shopInvoices as $inv) {
                $m = (int) $inv->invoice_date->month;
                $amount = self::signedInvoiceCents($inv, (bool) $isNet) / 100;

                $structure['shop_income']['months'][$m] += $amount;
                $structure['shop_income']['year_sum'] += $amount;

                $shopRow['months'][$m] += $amount;
                $shopRow['year_sum'] += $amount;
            }
            $structure['shop_income']['items'][] = $shopRow;
        }

        // Totals
        $totals = ['months' => array_fill(1, 12, 0), 'year_sum' => 0];
        foreach ($structure as $cat) {
            for ($m = 1; $m <= 12; $m++) {
                $totals['months'][$m] += $cat['months'][$m];
            }
            $totals['year_sum'] += $cat['year_sum'];
        }

        return ['product_categories' => $structure, 'totals' => $totals];
    }

    public function getPieChartData($adminId, $from, $to, string $scope = 'all')
    {
        $scope = in_array($scope, ['all', 'business', 'private'], true) ? $scope : 'all';

        $specialsQuery = AccountingSpecialIssue::where('admin_id', $adminId)
            ->whereBetween('execution_date', [$from, $to])
            ->where('amount', '<', 0);

        if ($scope === 'business') {
            $specialsQuery->where('is_business', true);
        } elseif ($scope === 'private') {
            $specialsQuery->where('is_business', false);
        }

        $specialsRaw = (clone $specialsQuery)->get();
        $specialsData = $specialsQuery
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->get();

        $rawBankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereNull('accounting_cost_item_id')
                ->whereBetween('transaction_date', [$from, $to])
                ->where('amount', '<', 0)
                ->get()
            : collect();

        $bankTxData = $this->filterNonDuplicateBankTransactions($rawBankTxs, $specialsRaw)
            ->filter(function ($tx) use ($scope) {
                if ($scope === 'all') {
                    return true;
                }
                $isBiz = (bool) ($tx->is_business ?? ($tx->account ? $tx->account->is_business : false));

                return $scope === 'business' ? $isBiz : !$isBiz;
            })
            ->groupBy(fn($tx) => $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges')
            ->map(fn($group, $key) => (object)['category' => $key, 'total' => $group->sum(fn($tx) => abs($tx->amount))])
            ->values();

        $merged = $specialsData->concat($bankTxData)
            ->groupBy('category')
            ->map(fn($group, $key) => (object)['category' => $key, 'total' => $group->sum('total')])
            ->sortByDesc('total')
            ->values();

        return [
            'labels' => $merged->pluck('category'),
            'data' => $merged->pluck('total'),
        ];
    }

    public function getBarChartData($adminId, $from, $to, $isNet = false, string $scope = 'all')
    {
        $scope = in_array($scope, ['all', 'business', 'private'], true) ? $scope : 'all';
        $days = [];
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($start->diffInDays($end) > 3660) {
            $start = $end->copy()->subDays(3660)->startOfDay();
        }

        $diff = $start->diffInDays($end);
        $isMonthly = $diff > 32;

        $current = $start->copy();
        while ($current <= $end) {
            $key = $isMonthly ? $current->format('Y-m') : $current->format('Y-m-d');
            $label = $isMonthly ? $current->format('M Y') : $current->format('d.m.');

            if (!isset($days[$key])) {
                $days[$key] = ['label' => $label, 'income' => 0, 'expense' => 0];
            }

            $isMonthly ? $current->addMonth() : $current->addDay();
        }

        // Fixkosten
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                if ($scope === 'business' && !$item->is_business) {
                    continue;
                }
                if ($scope === 'private' && $item->is_business) {
                    continue;
                }

                $dayOfMonth = $item->first_payment_date->day;

                foreach ($days as $dateKey => &$val) {
                    $dateObj = $isMonthly
                        ? Carbon::createFromFormat('Y-m', $dateKey)->startOfMonth()
                        : Carbon::createFromFormat('Y-m-d', $dateKey);

                    if ($this->isDueInMonth($item, $dateObj->month, $dateObj->year)) {
                        $amount = (float) $item->amount;
                        if ($isNet && $item->is_business && isset($item->tax_rate) && $item->tax_rate > 0) {
                            $amount = $amount / (1 + ($item->tax_rate / 100));
                        }

                        $isIncome = ($group->type === 'income');
                        if (!$isMonthly) {
                            $effectiveDay = min((int) $dayOfMonth, (int) $dateObj->daysInMonth);
                            if ((int) $dateObj->day === $effectiveDay) {
                                $this->addToChartData($val, $amount, $isIncome);
                            }
                        } else {
                            $this->addToChartData($val, $amount, $isIncome);
                        }
                    }
                }
            }
        }

        // Variable (Specials)
        $specialsQuery = AccountingSpecialIssue::where('admin_id', $adminId)->whereBetween('execution_date', [$start, $end]);
        if ($scope === 'business') {
            $specialsQuery->where('is_business', true);
        } elseif ($scope === 'private') {
            $specialsQuery->where('is_business', false);
        }
        $specials = $specialsQuery->get();

        foreach ($specials as $special) {
            $key = $isMonthly ? $special->execution_date->format('Y-m') : $special->execution_date->format('Y-m-d');
            if (isset($days[$key])) {
                $amount = (float) $special->amount;
                if ($isNet && $special->is_business && isset($special->tax_rate) && $special->tax_rate > 0) {
                    $amount = $amount / (1 + ($special->tax_rate / 100));
                }
                $this->addToChartData($days[$key], $amount);
            }
        }

        $rawBankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with('account')
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereNull('accounting_cost_item_id')
                ->whereBetween('transaction_date', [$start, $end])
                ->get()
            : collect();

        $bankTxs = $this->filterNonDuplicateBankTransactions($rawBankTxs, $specials);
        foreach ($bankTxs as $tx) {
            $isTxBiz = (bool) ($tx->is_business ?? ($tx->account ? $tx->account->is_business : false));
            if ($scope === 'business' && !$isTxBiz) {
                continue;
            }
            if ($scope === 'private' && $isTxBiz) {
                continue;
            }

            $date = \Carbon\Carbon::parse($tx->transaction_date);
            $key = $isMonthly ? $date->format('Y-m') : $date->format('Y-m-d');
            if (isset($days[$key])) {
                $this->addToChartData($days[$key], (float) $tx->amount);
            }
        }

        // Shop Invoices (nur bei Gewerbe oder Gesamt)
        if ($scope !== 'private') {
            $invoices = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [$start, $end])
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get();

            foreach ($invoices as $invoice) {
                $key = $isMonthly ? $invoice->invoice_date->format('Y-m') : $invoice->invoice_date->format('Y-m-d');
                if (isset($days[$key])) {
                    $amountEuro = self::signedInvoiceCents($invoice, (bool) $isNet) / 100;
                    if ($amountEuro >= 0) {
                        $days[$key]['income'] += $amountEuro;
                    } else {
                        $days[$key]['expense'] += abs($amountEuro);
                    }
                }
            }
        }

        return [
            'labels' => array_column($days, 'label'),
            'income' => array_column($days, 'income'),
            'expense' => array_column($days, 'expense'),
        ];
    }

    private function addToChartData(&$dataArray, $amount, $isIncome = null) {
        if ($isIncome !== null ? $isIncome : ($amount >= 0)) {
            // Einnahme
            $dataArray['income'] += abs($amount);
        } else {
            // Ausgabe (wird als positiver Wert für den Chartbalken gespeichert)
            // Wenn es hier eine Rückerstattung gibt (amount > 0), ist es oben in Income gelandet.
            // Das ist für den Bar Chart (Cashflow) auch korrekt.
            $dataArray['expense'] += abs($amount);
        }
    }

    public function generateTaxExport(
        $adminId,
        $month,
        $year,
        string $scope = 'business',
        ?array $monthsOverride = null,
        ?string $periodSlug = null
    ) {
        $month = max(1, min(12, (int) $month));
        $year = max(2000, min(2100, (int) $year));
        $scope = in_array($scope, ['all', 'business', 'private'], true) ? $scope : 'business';
        $resolvedAdminId = $this->resolveMandantAdminId($adminId) ?: $adminId;

        $monthsInPeriod = !empty($monthsOverride)
            ? array_values(array_unique(array_map(fn ($m) => max(1, min(12, (int) $m)), $monthsOverride)))
            : [$month];
        sort($monthsInPeriod);

        $allSpecialsInPeriod = AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
            ->whereYear('execution_date', $year)
            ->whereIn(DB::raw('MONTH(execution_date)'), $monthsInPeriod)
            ->orderBy('execution_date')
            ->get();

        $specials = $allSpecialsInPeriod->filter(function ($s) use ($scope) {
            if ($scope === 'business') {
                return (bool) $s->is_business;
            }
            if ($scope === 'private') {
                return !(bool) $s->is_business;
            }
            return true;
        })->values();

        $rawBankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn ($q) => $q->where('admin_id', $resolvedAdminId))
                ->whereNotNull('accounting_category_id')
                ->whereYear('transaction_date', $year)
                ->whereIn(DB::raw('MONTH(transaction_date)'), $monthsInPeriod)
                ->orderBy('transaction_date')
                ->get()
            : collect();

        $bankTxs = $this->filterNonDuplicateBankTransactions($rawBankTxs, $allSpecialsInPeriod)
            ->filter(function ($tx) use ($scope) {
                $txIsBusiness = (bool) ($tx->is_business ?? $tx->account?->is_business_account ?? false);
                if ($scope === 'business') {
                    return $txIsBusiness;
                }
                if ($scope === 'private') {
                    return !$txIsBusiness;
                }
                return true;
            })
            ->values();

        $fixedGroups = AccountingGroup::with('items')->where('admin_id', $resolvedAdminId)->get();
        $fixedCosts = collect();
        foreach ($fixedGroups as $group) {
            $isIncomeGroup = ($group->type === 'income');
            foreach ($group->items as $item) {
                $itemIsBusiness = (bool) $item->is_business;
                if ($scope === 'business' && ($isIncomeGroup || !$itemIsBusiness)) {
                    continue;
                }
                if ($scope === 'private' && $itemIsBusiness) {
                    continue;
                }
                foreach ($monthsInPeriod as $mInP) {
                    if ($this->isDueInMonth($item, $mInP, $year)) {
                        $signedAmount = $isIncomeGroup ? abs((float) $item->amount) : -abs((float) $item->amount);
                        $fixedCosts->push((object) [
                            'name' => $item->name,
                            'category' => $group->name,
                            'amount' => $signedAmount,
                            'tax_rate' => $item->tax_rate ?? 0,
                            'is_business' => $itemIsBusiness,
                            'due_month' => $mInP,
                        ]);
                    }
                }
            }
        }

        $invoices = $scope === 'private'
            ? collect()
            : \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
                ->whereIn(DB::raw('MONTH(invoice_date)'), $monthsInPeriod)
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get();

        $shopStats = [
            'gross' => $invoices->sum(fn ($i) => self::signedInvoiceCents($i, false)) / 100,
            'net' => $invoices->sum(fn ($i) => self::signedInvoiceCents($i, true)) / 100,
            'tax' => $invoices->sum(fn ($i) => self::signedInvoiceCents($i, false) - self::signedInvoiceCents($i, true)) / 100,
            'count' => $invoices->filter(fn ($i) => self::signedInvoiceCents($i, false) > 0)->count(),
            'returns' => $invoices->filter(fn ($i) => self::signedInvoiceCents($i, false) < 0)->count(),
        ];
        $shopStats['aov'] = $shopStats['count'] > 0 ? ($shopStats['gross'] / $shopStats['count']) : 0;

        $anchorMonth = end($monthsInPeriod) ?: $month;
        $liquidityPreview = [];
        $avgInvoices = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [
            Carbon::create($year, $anchorMonth, 1)->subMonths(2)->startOfMonth(),
            Carbon::create($year, $anchorMonth, 1)->endOfMonth(),
        ])->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get(['type', 'total', 'tax_amount']);
        $avgShopNet = $scope === 'private' ? 0.0 : ($avgInvoices->sum(fn ($i) => self::signedInvoiceCents($i, true)) / 100 / 3);

        for ($i = 1; $i <= 3; $i++) {
            $previewMonth = Carbon::create($year, $anchorMonth, 1)->addMonths($i);
            $m = $previewMonth->month;

            $previewFixed = 0.0;
            foreach ($fixedGroups as $group) {
                if ($group->type === 'income') {
                    continue;
                }
                foreach ($group->items as $item) {
                    $itemIsBiz = (bool) $item->is_business;
                    if ($scope === 'business' && !$itemIsBiz) {
                        continue;
                    }
                    if ($scope === 'private' && $itemIsBiz) {
                        continue;
                    }
                    if ($this->isDueInMonth($item, $m, $previewMonth->year)) {
                        $validRate = self::isEligibleGermanInputVatRate($item->tax_rate ?? 0)
                            ? (float) round((float) $item->tax_rate)
                            : 0.0;
                        $previewFixed += (abs((float) $item->amount) / (1 + ($validRate / 100)));
                    }
                }
            }

            $liquidityPreview[] = [
                'month' => $previewMonth->locale('de')->monthName,
                'year' => $previewMonth->year,
                'expected_income' => $avgShopNet > 0 ? $avgShopNet : 0,
                'expected_fixed_costs' => abs($previewFixed),
            ];
        }

        $aggregateStats = function (bool $isNetFlag) use ($resolvedAdminId, $monthsInPeriod, $year, $scope): array {
            $acc = [
                'shop_revenue' => 0.0,
                'fixed_income' => 0.0,
                'fixed_expenses' => 0.0,
                'special_expenses' => 0.0,
                'total_budget' => 0.0,
                'total_spent' => 0.0,
                'available' => 0.0,
            ];
            foreach ($monthsInPeriod as $mInP) {
                $st = $this->getMonthlyStats($resolvedAdminId, $mInP, $year, $isNetFlag, $scope);
                foreach ($acc as $k => $_) {
                    $acc[$k] += (float) ($st[$k] ?? 0);
                }
            }
            return $acc;
        };

        $statsBrutto = $aggregateStats(false);
        $statsNetto = $aggregateStats(true);
        $assetSchedule = $this->getFixedAssetsAndDepreciation($resolvedAdminId, $year, $anchorMonth);

        $periodTitle = count($monthsInPeriod) === 12
            ? "Gesamtjahr {$year}"
            : (count($monthsInPeriod) === 3
                ? 'Q' . (int) ceil($monthsInPeriod[0] / 3) . " {$year}"
                : Carbon::createFromDate($year, $month, 1)->locale('de')->monthName . " {$year}");

        $filePeriodSuffix = $periodSlug !== null && $periodSlug !== '' ? $periodSlug : (string) $month;

        $dir = storage_path('app/buchhaltung/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $zipFileName = "Finanzbericht_{$year}_{$filePeriodSuffix}.zip";
        $zipPath = $dir . '/' . $zipFileName;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {

            $sanitizeCsvCell = static function ($value): string {
                $str = (string) $value;
                $trimmed = ltrim($str, " \t\n\r\0\x0B");
                if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                    return "'" . $str;
                }
                return $str;
            };

            $csvHandle = fopen('php://temp', 'r+');
            fprintf($csvHandle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($csvHandle, ['Datum', 'Typ', 'Bezeichnung', 'Kategorie', 'SKR03', 'Brutto', 'Steuersatz', 'Netto', 'Steuer'], ';');

            foreach ($specials as $s) {
                $sRate = self::isEligibleGermanInputVatRate($s->tax_rate ?? 0) ? (int) round((float) $s->tax_rate) : 0;
                $net = $sRate > 0 ? ($s->amount / (1 + ($sRate / 100))) : $s->amount;
                $tax = $s->amount - $net;
                $skr = $s->is_business ? ($this->resolveSkr03ExpenseAccountAndBuKey($s)['account'] ?? '4900') : 'Privat';
                fputcsv($csvHandle, [
                    $s->execution_date->format('d.m.Y'),
                    'Variabel',
                    $sanitizeCsvCell($s->title),
                    $sanitizeCsvCell($s->category),
                    $skr,
                    number_format($s->amount, 2, ',', '.'),
                    $sRate . '%',
                    number_format($net, 2, ',', '.'),
                    number_format($tax, 2, ',', '.'),
                ], ';');

                if ($s->file_paths) {
                    $storageRoot = realpath(storage_path('app'));
                    foreach ($s->file_paths as $index => $filePath) {
                        if (!is_string($filePath) || str_contains($filePath, '..') || str_contains($filePath, "\0")) {
                            continue;
                        }
                        $sourcePath = Storage::disk('local')->exists($filePath)
                            ? Storage::disk('local')->path($filePath)
                            : (Storage::disk('public')->exists($filePath) ? Storage::disk('public')->path($filePath) : null);

                        if ($sourcePath && is_file($sourcePath) && !is_link($sourcePath)) {
                            $realSource = realpath($sourcePath);
                            if ($realSource === false || ($storageRoot !== false && !str_starts_with($realSource, $storageRoot . DIRECTORY_SEPARATOR))) {
                                continue;
                            }
                            $ext = pathinfo($realSource, PATHINFO_EXTENSION);
                            $cleanTitle = \Illuminate\Support\Str::slug($s->title) ?: 'Beleg';
                            $newName = "Belege/{$s->execution_date->format('Y-m-d')}_{$cleanTitle}_{$index}.{$ext}";
                            $zip->addFile($realSource, $newName);
                        }
                    }
                }
            }

            foreach ($bankTxs as $tx) {
                $catName = $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges';
                $rawTxLabel = $tx->counterpart_name ?? $tx->purpose ?? 'Unbekannte Abbuchung';
                $title = 'Bank: ' . $sanitizeCsvCell($rawTxLabel);
                fputcsv($csvHandle, [
                    \Carbon\Carbon::parse($tx->transaction_date)->format('d.m.Y'),
                    'Variabel (Bank)',
                    $sanitizeCsvCell($title),
                    $sanitizeCsvCell($catName),
                    '4900',
                    number_format($tx->amount, 2, ',', '.'),
                    '0%',
                    number_format($tx->amount, 2, ',', '.'),
                    '0,00',
                ], ';');

                if ($tx->file_paths) {
                    $storageRoot = realpath(storage_path('app'));
                    foreach ($tx->file_paths as $index => $filePath) {
                        if (!is_string($filePath) || str_contains($filePath, '..') || str_contains($filePath, "\0")) {
                            continue;
                        }
                        $sourcePath = Storage::disk('local')->exists($filePath)
                            ? Storage::disk('local')->path($filePath)
                            : (Storage::disk('public')->exists($filePath) ? Storage::disk('public')->path($filePath) : null);

                        if ($sourcePath && is_file($sourcePath) && !is_link($sourcePath)) {
                            $realSource = realpath($sourcePath);
                            if ($realSource === false || ($storageRoot !== false && !str_starts_with($realSource, $storageRoot . DIRECTORY_SEPARATOR))) {
                                continue;
                            }
                            $ext = pathinfo($realSource, PATHINFO_EXTENSION);
                            $cleanTitle = \Illuminate\Support\Str::slug($title) ?: 'Beleg_Bank';
                            $dateStr = \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d');
                            $newName = "Belege_Bank/{$dateStr}_{$cleanTitle}_{$index}.{$ext}";
                            $zip->addFile($realSource, $newName);
                        }
                    }
                }
            }

            foreach ($fixedCosts as $f) {
                $fRate = self::isEligibleGermanInputVatRate($f->tax_rate ?? 0) ? (int) round((float) $f->tax_rate) : 0;
                $net = $fRate > 0 ? ($f->amount / (1 + ($fRate / 100))) : $f->amount;
                $tax = $f->amount - $net;
                $dueM = (int) ($f->due_month ?? $month);
                $skr = $f->is_business ? ($this->resolveSkr03ExpenseAccountAndBuKey($f)['account'] ?? '4900') : 'Privat';
                fputcsv($csvHandle, [
                    Carbon::create($year, $dueM, 1)->format('d.m.Y'),
                    'Fixkosten',
                    $sanitizeCsvCell($f->name),
                    $sanitizeCsvCell($f->category),
                    $skr,
                    number_format($f->amount, 2, ',', '.'),
                    $fRate . '%',
                    number_format($net, 2, ',', '.'),
                    number_format($tax, 2, ',', '.'),
                ], ';');
            }

            rewind($csvHandle);
            $csvContent = stream_get_contents($csvHandle);
            fclose($csvHandle);
            $zip->addFromString("Transaktionen_{$year}_{$filePeriodSuffix}.csv", $csvContent);

            if ($scope !== 'private') {
                $zip->addFromString(
                    "Anlagenspiegel_und_AfA_{$year}.csv",
                    $this->buildAssetScheduleCsv($resolvedAdminId, $year, $anchorMonth)
                );
            }

            $pdf = Pdf::loadView('global.pdf.financial_report', [
                'month' => $month,
                'year' => $year,
                'periodTitle' => $periodTitle,
                'scope' => $scope,
                'shopStats' => $shopStats,
                'fixedCosts' => $fixedCosts,
                'specials' => $specials,
                'assetSchedule' => $assetSchedule,
                'liquidityPreview' => $liquidityPreview,
                'statsBrutto' => $statsBrutto,
                'statsNetto' => $statsNetto,
            ])->setOption(['isRemoteEnabled' => false]);
            $zip->addFromString("Liquiditaets_und_Finanzbericht_{$year}_{$filePeriodSuffix}.pdf", $pdf->output());

            $zip->close();
        }

        return $zipPath;
    }

    public function getCompanyTaxMeta(): array
    {
        return [
            'name' => shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke')),
            'owner' => shop_setting('owner_proprietor', 'Alina Steinhauer'),
            'street' => shop_setting('company_street')
                ? trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', ''))
                : shop_setting('owner_street', 'Musterstraße 1'),
            'city' => shop_setting('company_city')
                ? trim(shop_setting('company_zip', '') . ' ' . shop_setting('company_city'))
                : shop_setting('owner_city', '12345 Musterstadt'),
            'tax_id' => shop_setting('owner_tax_id', 'XX/XXX/XXXXX'),
            'ust_id' => shop_setting('company_ust_id', shop_setting('owner_ust_id', 'DE XXXXXXXXX')),
        ];
    }

    /**
     * Prüft, ob ein Steuersatz als echter deutscher Vorsteuersatz (7 % oder 19 %) in Kz 66 abziehbar ist.
     * Ausländische Steuersätze (z. B. 25 % DK MOMS) oder 0 % (Versicherungssteuer / steuerfrei) werden ausgeschlossen.
     */
    public static function isEligibleGermanInputVatRate(int|float|null $taxRate): bool
    {
        if ($taxRate === null) {
            return false;
        }
        return in_array((int) round((float) $taxRate), [7, 19], true);
    }

    /**
     * Prüft, ob zu einer Ausgabe der Beleg fehlt oder nur ein vorläufiger Nachweis (Bestellübersicht / PayPal-Auszug / Nicht-USt-Beleg) vorliegt.
     */
    public static function hasProvisionalOrMissingTaxReceipt(array $filePaths, ?string $note = null): bool
    {
        $cleanFiles = array_values(array_filter($filePaths, fn ($p) => is_string($p) && trim($p) !== ''));
        if (empty($cleanFiles)) {
            return true;
        }

        $noteLower = mb_strtolower((string) $note);
        if (
            str_contains($noteLower, 'keine umsatzsteuer-rechnung')
            || str_contains($noteLower, 'aktuell amazon-bestellübersicht')
            || str_contains($noteLower, 'bestellübersicht;')
            || str_contains($noteLower, 'vorläufiger beleg')
            || str_contains($noteLower, 'rechnung nachladen')
        ) {
            return true;
        }

        $hasInvoicePdf = collect($cleanFiles)->contains(function (string $p) {
            $base = mb_strtolower(basename($p));
            return str_contains($base, 'rechnung') || str_contains($base, 'invoice') || str_contains($base, 'kassenbon');
        });

        if (!$hasInvoicePdf && str_contains($noteLower, 'paypal-transaktionscode')) {
            return true;
        }

        return false;
    }

    /**
     * Teilt einen Brutto- und Steuerbetrag präzise auf die deutschen Steuersätze 19 % (Kz 81) und 7 % (Kz 86) auf.
     */
    private function splitGrossAndTaxIntoRates(
        float $signedGrossEur,
        float $signedTaxEur,
        iterable $items = [],
        bool $isYearSmallBusiness = false,
        bool $forceRetroactiveCalc = false
    ): array {
        if ($isYearSmallBusiness || abs($signedGrossEur) < 0.0001) {
            return [
                'gross' => $signedGrossEur,
                'net_19' => 0.0,
                'vat_19' => 0.0,
                'net_7' => 0.0,
                'vat_7' => 0.0,
                'net_0' => $signedGrossEur,
                'total_vat' => 0.0,
                'retroactive_vat' => 0.0,
            ];
        }

        $sign = $signedGrossEur < 0 ? -1.0 : 1.0;
        $absGross = abs($signedGrossEur);
        $absTax = abs($signedTaxEur);
        $defaultRate = (float) shop_setting('default_tax_rate', 19.0);

        $bucket19Gross = 0.0;
        $bucket7Gross = 0.0;
        $bucket0Gross = 0.0;

        foreach ($items as $item) {
            $obj = is_array($item) ? (object) $item : $item;
            $qty = max(1.0, (float) ($obj->quantity ?? 1));
            if (isset($obj->total_price)) {
                $lineGrossEur = abs((float) $obj->total_price) / 100.0;
            } elseif (isset($obj->unit_price)) {
                $lineGrossEur = (abs((float) $obj->unit_price) * $qty) / 100.0;
            } elseif (isset($obj->gross)) {
                $lineGrossEur = abs((float) $obj->gross);
            } else {
                $lineGrossEur = 0.0;
            }

            $itemRate = (int) round((float) ($obj->tax_rate ?? $defaultRate));
            if ($itemRate === 7) {
                $bucket7Gross += $lineGrossEur;
            } elseif ($itemRate === 0) {
                $bucket0Gross += $lineGrossEur;
            } else {
                $bucket19Gross += $lineGrossEur;
            }
        }

        $totalItemGross = $bucket19Gross + $bucket7Gross + $bucket0Gross;
        $retroactiveVat = 0.0;

        if ($absTax < 0.0001 && $forceRetroactiveCalc) {
            if ($totalItemGross > 0 && $bucket7Gross > 0) {
                $scale = $absGross / $totalItemGross;
                $alloc7 = $bucket7Gross * $scale;
                $alloc0 = $bucket0Gross * $scale;
                $alloc19 = max(0.0, $absGross - $alloc7 - $alloc0);

                $net7 = $alloc7 / 1.07;
                $vat7 = $alloc7 - $net7;
                $net19 = $alloc19 / 1.19;
                $vat19 = $alloc19 - $net19;
                $retroactiveVat = $vat7 + $vat19;

                return [
                    'gross' => $sign * $absGross,
                    'net_19' => $sign * $net19,
                    'vat_19' => $sign * $vat19,
                    'net_7' => $sign * $net7,
                    'vat_7' => $sign * $vat7,
                    'net_0' => $sign * $alloc0,
                    'total_vat' => $sign * ($vat19 + $vat7),
                    'retroactive_vat' => $sign * $retroactiveVat,
                ];
            }

            $effRate = ((int) round($defaultRate) === 7) ? 7.0 : 19.0;
            $net = $absGross / (1.0 + ($effRate / 100.0));
            $vat = $absGross - $net;
            $retroactiveVat = $vat;

            return [
                'gross' => $sign * $absGross,
                'net_19' => $effRate === 19.0 ? ($sign * $net) : 0.0,
                'vat_19' => $effRate === 19.0 ? ($sign * $vat) : 0.0,
                'net_7' => $effRate === 7.0 ? ($sign * $net) : 0.0,
                'vat_7' => $effRate === 7.0 ? ($sign * $vat) : 0.0,
                'net_0' => 0.0,
                'total_vat' => $sign * $vat,
                'retroactive_vat' => $sign * $retroactiveVat,
            ];
        }

        if ($absTax < 0.0001) {
            return [
                'gross' => $sign * $absGross,
                'net_19' => 0.0,
                'vat_19' => 0.0,
                'net_7' => 0.0,
                'vat_7' => 0.0,
                'net_0' => $sign * $absGross,
                'total_vat' => 0.0,
                'retroactive_vat' => 0.0,
            ];
        }

        // Fall 1: Reine 7 %-Positionen vorhanden
        if ($bucket7Gross > 0 && $bucket19Gross == 0.0) {
            $vat7 = $absTax;
            $net7 = max(0.0, $absGross - $absTax - $bucket0Gross);
            $net0 = max(0.0, $absGross - $absTax - $net7);

            return [
                'gross' => $sign * $absGross,
                'net_19' => 0.0,
                'vat_19' => 0.0,
                'net_7' => $sign * $net7,
                'vat_7' => $sign * $vat7,
                'net_0' => $sign * $net0,
                'total_vat' => $sign * $vat7,
                'retroactive_vat' => 0.0,
            ];
        }

        // Fall 2: Gemischter Warenkorb (7 % und 19 % gemeinsam)
        if ($bucket7Gross > 0 && $bucket19Gross > 0 && $totalItemGross > 0) {
            $scale = $absGross / $totalItemGross;
            $allocGross7 = $bucket7Gross * $scale;
            $allocGross0 = $bucket0Gross * $scale;
            $net7 = round($allocGross7 / 1.07, 2);
            $vat7 = min($absTax, round($allocGross7 - $net7, 2));
            $vat19 = max(0.0, $absTax - $vat7);
            $net19 = max(0.0, ($absGross - $allocGross7 - $allocGross0) - $vat19);

            return [
                'gross' => $sign * $absGross,
                'net_19' => $sign * $net19,
                'vat_19' => $sign * $vat19,
                'net_7' => $sign * $net7,
                'vat_7' => $sign * $vat7,
                'net_0' => $sign * $allocGross0,
                'total_vat' => $sign * ($vat19 + $vat7),
                'retroactive_vat' => 0.0,
            ];
        }

        // Fall 3: Keine Einzelpositionen hinterlegt -> Steuersatz aus Verhältnis Steuer/Netto ableiten
        if ($totalItemGross == 0.0) {
            $impliedNet = max(0.0, $absGross - $absTax);
            $impliedRate = $impliedNet > 0 ? (($absTax / $impliedNet) * 100.0) : 19.0;
            if (abs($impliedRate - 7.0) <= 0.85) {
                return [
                    'gross' => $sign * $absGross,
                    'net_19' => 0.0,
                    'vat_19' => 0.0,
                    'net_7' => $sign * $impliedNet,
                    'vat_7' => $sign * $absTax,
                    'net_0' => 0.0,
                    'total_vat' => $sign * $absTax,
                    'retroactive_vat' => 0.0,
                ];
            }
        }

        // Standardfall: 19 % (Kz 81)
        $net19 = max(0.0, $absGross - $absTax - $bucket0Gross);
        $net0 = max(0.0, $absGross - $absTax - $net19);

        return [
            'gross' => $sign * $absGross,
            'net_19' => $sign * $net19,
            'vat_19' => $sign * $absTax,
            'net_7' => 0.0,
            'vat_7' => 0.0,
            'net_0' => $sign * $net0,
            'total_vat' => $sign * $absTax,
            'retroactive_vat' => 0.0,
        ];
    }

    /**
     * Zentralisierte UStVA- & Monats-Steuerberechnung für Buchhaltung & Steuerberater-Cockpit.
     */
    public function getUstvaMonthData($adminId, int $month, int $year, string $submissionType = 'Erstübermittlung'): array
    {
        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));
        $resolvedAdminId = $this->resolveMandantAdminId($adminId);

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $isFutureMonth = $startDate->isFuture();
        $isPastMonth = $endDate->isPast();
        $deadline = Carbon::createFromDate($year, $month, 10)->addMonth();

        // 1. UMSÄTZE (Einnahmen dynamisch nach 19 % Kz 81 und 7 % Kz 86 splitten)
        $orders = OrderOrder::with('items')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->get();

        $manualInvoices = \App\Models\Accounting\AccountingInvoice::whereNull('order_id')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->where('type', 'invoice')
            ->where(function ($q) {
                $q->where('status', 'paid')
                    ->orWhere(function ($sub) {
                        $sub->where('status', 'cancelled')->whereNotNull('paid_at');
                    });
            })
            ->get();

        $creditNotes = \App\Models\Accounting\AccountingInvoice::with('order.items')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('type', ['credit_note', 'cancellation'])
            ->where(function ($q) {
                $q->where('status', 'paid')
                    ->orWhereNotNull('paid_at');
            })
            ->get();

        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $isYearSmallBusiness = $taxService->isSmallBusinessForYear($year);
        $isMidyearSwitch = $taxService->isMidyearSwitch($year);
        $switchDate = $taxService->getMidyearSwitchDate();

        $revenueGross = 0.0;
        $revenueNet19 = 0.0;
        $vatCollected19 = 0.0;
        $revenueNet7 = 0.0;
        $vatCollected7 = 0.0;
        $vatCollected = 0.0;
        $retroactiveVatCollected = 0.0;
        $igErwerbTax = 0.0;
        $paragraph13bTax = 0.0;

        foreach ($orders as $order) {
            $total = $order->total_price / 100;
            $tax = $order->tax_amount / 100;
            $forceRetro = !$isYearSmallBusiness && $tax == 0 && ($isMidyearSwitch || $order->is_small_business);

            $split = $this->splitGrossAndTaxIntoRates($total, $tax, $order->items ?? [], $isYearSmallBusiness, $forceRetro);
            $revenueGross += $split['gross'];
            $retroactiveVatCollected += $split['retroactive_vat'];

            if (isset($order->is_reverse_charge) && $order->is_reverse_charge) {
                $paragraph13bTax += $split['total_vat'];
            } elseif (isset($order->is_ig_erwerb) && $order->is_ig_erwerb) {
                $igErwerbTax += $split['total_vat'];
            } else {
                $revenueNet19 += $split['net_19'];
                $vatCollected19 += $split['vat_19'];
                $revenueNet7 += $split['net_7'];
                $vatCollected7 += $split['vat_7'];
                $vatCollected += $split['total_vat'];
            }
        }

        // MANUELLE RECHNUNGEN (ohne verknüpfte Bestellung) EINBEZIEHEN
        foreach ($manualInvoices as $manualInv) {
            $total = abs($manualInv->total / 100);
            $tax = abs($manualInv->tax_amount / 100);
            $forceRetro = !$isYearSmallBusiness && $tax == 0 && ($isMidyearSwitch || $manualInv->is_small_business);

            $split = $this->splitGrossAndTaxIntoRates($total, $tax, $manualInv->custom_items ?? [], $isYearSmallBusiness, $forceRetro);
            $revenueGross += $split['gross'];
            $retroactiveVatCollected += $split['retroactive_vat'];
            $revenueNet19 += $split['net_19'];
            $vatCollected19 += $split['vat_19'];
            $revenueNet7 += $split['net_7'];
            $vatCollected7 += $split['vat_7'];
            $vatCollected += $split['total_vat'];
        }

        // GUTSCHRIFTEN VERRECHNEN
        foreach ($creditNotes as $cn) {
            $total = -abs($cn->total / 100);
            $tax = -abs($cn->tax_amount / 100);
            $forceRetro = !$isYearSmallBusiness && $tax == 0 && ($isMidyearSwitch || $cn->is_small_business);
            $cnItems = !empty($cn->custom_items) ? $cn->custom_items : ($cn->order?->items ?? []);

            $split = $this->splitGrossAndTaxIntoRates($total, $tax, $cnItems, $isYearSmallBusiness, $forceRetro);
            $revenueGross += $split['gross'];
            $retroactiveVatCollected += $split['retroactive_vat'];

            if ($cn->order && isset($cn->order->is_reverse_charge) && $cn->order->is_reverse_charge) {
                $paragraph13bTax += $split['total_vat'];
            } elseif ($cn->order && isset($cn->order->is_ig_erwerb) && $cn->order->is_ig_erwerb) {
                $igErwerbTax += $split['total_vat'];
            } else {
                $revenueNet19 += $split['net_19'];
                $vatCollected19 += $split['vat_19'];
                $revenueNet7 += $split['net_7'];
                $vatCollected7 += $split['vat_7'];
                $vatCollected += $split['total_vat'];
            }
        }

        $revenueNet = $revenueGross - ($vatCollected + $igErwerbTax + $paragraph13bTax);
        $totalTax = $vatCollected + $igErwerbTax + $paragraph13bTax;

        // 2. AUSGABEN (Gewerblich Variabel & Fix)
        $businessSpecials = AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
            ->where('is_business', true)
            ->whereBetween('execution_date', [$startDate, $endDate])
            ->get();

        $businessFixed = collect();
        $groups = AccountingGroup::with('items')->where('admin_id', $resolvedAdminId)->get();
        foreach ($groups as $group) {
            if ($group->type === 'income') {
                continue;
            }
            foreach ($group->items as $item) {
                if ($item->is_business == 1 || $item->is_business === true) {
                    if ($this->isDueInMonth($item, $month, $year)) {
                        $clonedItem = clone $item;
                        $clonedItem->setAttribute('_due_month', $month);
                        $businessFixed->push($clonedItem);
                    }
                }
            }
        }

        $totalExpensesGross = abs($businessSpecials->sum('amount')) + abs($businessFixed->sum('amount'));

        // 3. VORSTEUER & EÜR-ABGRENZUNG DYNAMISCH BERECHNEN (Kz 66: ausschließlich echte deutsche Vorsteuersätze 7 % & 19 %)
        $vorsteuerSpecial = 0.0;
        $vorsteuerFix = 0.0;
        $expensesNet = 0.0;
        $capitalAdditionsGross = 0.0;
        $capitalAdditionsNet = 0.0;

        foreach ($businessSpecials as $s) {
            $rawRate = $s->tax_rate ?? 0;
            $rawSigned = (float) $s->amount;
            $amt = abs($rawSigned);
            // Positive Beträge in Sonderausgaben sind Lieferanten-Gutschriften/Retouren-Erstattungen -> mindern Betriebsausgabe & Vorsteuer
            $expenseSign = $rawSigned > 0.0 ? -1.0 : 1.0;

            if (!$isYearSmallBusiness && self::isEligibleGermanInputVatRate($rawRate)) {
                $rate = (int) round((float) $rawRate);
                $net = $amt / (1 + ($rate / 100));
                $vorsteuerSpecial += $expenseSign * ($amt - $net);
                $expensesNet += $expenseSign * $net;
            } else {
                $net = $amt;
                $expensesNet += $expenseSign * $amt;
            }

            if ($expenseSign > 0 && $this->isCapitalAssetIssue($s, round($net, 2))) {
                $capitalAdditionsGross += $amt;
                $capitalAdditionsNet += $net;
            }
        }

        foreach ($businessFixed as $f) {
            $rawRate = $f->tax_rate ?? 0;
            $amt = abs((float) $f->amount);

            if (!$isYearSmallBusiness && self::isEligibleGermanInputVatRate($rawRate)) {
                $rate = (int) round((float) $rawRate);
                $net = $amt / (1 + ($rate / 100));
                $vorsteuerFix += ($amt - $net);
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        $vatPaid = $vorsteuerSpecial + $vorsteuerFix;

        if ($isYearSmallBusiness) {
            $vorsteuerSpecial = 0.0;
            $vorsteuerFix = 0.0;
            $vatPaid = 0.0;
            $expensesNet = $totalExpensesGross;
            $capitalAdditionsNet = $capitalAdditionsGross;
        }

        $operatingExpensesNet = max(0.0, $expensesNet - $capitalAdditionsNet);
        $assetMonthSummary = $this->getFixedAssetsAndDepreciation($resolvedAdminId, $year, $month);
        $depreciationNet = (float) ($assetMonthSummary['monthly_afa'] ?? 0.0);
        $euerExpensesNet = round($operatingExpensesNet + $depreciationNet, 2);
        $euerProfit = round($revenueNet - $euerExpensesNet, 2);

        $zahllast = $totalTax - $vatPaid;
        $profit = $revenueNet - $expensesNet;

        // 4. READINESS & MISSING DATA
        $progress = 0;
        $missingReceiptItems = collect();

        foreach ($businessSpecials as $s) {
            if (empty($s->file_paths)) {
                $missingReceiptItems->push([
                    'id' => $s->id,
                    'type' => 'variable',
                    'title' => $s->title,
                    'date' => Carbon::parse($s->execution_date)->format('d.m.Y'),
                    'amount' => abs($s->amount),
                ]);
            }
        }
        foreach ($businessFixed as $f) {
            if (empty($f->contract_file_path)) {
                $missingReceiptItems->push([
                    'id' => $f->id,
                    'type' => 'fixed',
                    'title' => $f->name,
                    'date' => 'Wiederkehrend',
                    'amount' => abs($f->amount),
                ]);
            }
        }

        $missingReceiptsCount = $missingReceiptItems->count();
        $totalBusinessItems = $businessSpecials->count() + $businessFixed->count();

        $taxId = shop_setting('owner_tax_id', '');
        $proprietor = shop_setting('owner_proprietor', '');

        $checklist = [
            'buchhaltung/receipts' => [
                'name' => '100% Belege verbucht (' . $totalBusinessItems . ' Transaktionen)',
                'passed' => $missingReceiptsCount === 0,
                'description' => 'Nur eine vollständige Belegablage garantiert eine rechtssichere Vorsteuerermittlung (Kz 66). Fehlende Belege blockieren den Export.',
            ],
            'tax_id' => [
                'name' => 'Gültige Steuernummer hinterlegt',
                'passed' => !empty($taxId) && strlen($taxId) > 8,
                'description' => "Ohne gültige Steuernummer kann das Finanzamt die XML nicht verarbeiten. (Aktuell: " . ($taxId ?: 'Fehlt') . ")",
            ],
            'proprietor' => [
                'name' => 'Vor- und Nachname des Inhabers',
                'passed' => !empty($proprietor),
                'description' => 'Ein Pflichtfeld für das ERiC-Schema. Der Vor- und Nachname muss in den System-Einstellungen hinterlegt sein.',
            ],
        ];

        $allChecklistPassed = collect($checklist)->every(fn ($item) => $item['passed']);

        if ($isFutureMonth) {
            $progress = 0;
            $status = 'future';
        } else {
            if ($totalBusinessItems > 0) {
                $progress += (($totalBusinessItems - $missingReceiptsCount) / $totalBusinessItems) * 70;
            } else {
                $progress += 70;
            }

            if ($taxId && $proprietor) {
                $progress += 30;
            }

            if ($isPastMonth) {
                $status = $allChecklistPassed ? 'ready' : 'missing_data';
            } else {
                $status = 'in_progress';
            }
        }

        $monthStr = str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        return [
            'period_mode' => 'month',
            'elster_zeitraum' => $monthStr,
            'export_period_slug' => $monthStr,
            'months_in_period' => [$month],
            'quarter' => (int) ceil($month / 3),
            'month_name' => $startDate->locale('de')->monthName,
            'month_number' => $monthStr,
            'year' => $year,
            'revenue_gross' => $revenueGross,
            'revenue_net' => $revenueNet,
            'revenue_net_19' => $revenueNet19,
            'vat_collected_19' => $vatCollected19,
            'revenue_net_7' => $revenueNet7,
            'vat_collected_7' => $vatCollected7,
            'kz81_base' => (int) floor(max(0.0, round($revenueNet19, 2))),
            'kz86_base' => (int) floor(max(0.0, round($revenueNet7, 2))),
            'vat_collected' => $vatCollected,
            'retroactive_vat_collected' => $retroactiveVatCollected,
            'is_small_business_year' => $isYearSmallBusiness,
            'is_midyear_switch' => $isMidyearSwitch,
            'switch_date' => $switchDate,
            'ig_erwerb_tax' => $igErwerbTax,
            'paragraph_13b_tax' => $paragraph13bTax,
            'total_tax' => $totalTax,
            'expenses_gross' => $totalExpensesGross,
            'expenses_net' => $expensesNet,
            'operating_expenses_net' => round($operatingExpensesNet, 2),
            'capital_additions_gross' => round($capitalAdditionsGross, 2),
            'capital_additions_net' => round($capitalAdditionsNet, 2),
            'depreciation_net' => round($depreciationNet, 2),
            'euer_expenses_net' => $euerExpensesNet,
            'euer_profit' => $euerProfit,
            'vorsteuer_special' => $vorsteuerSpecial,
            'vorsteuer_fix' => $vorsteuerFix,
            'vat_paid' => $vatPaid,
            'zahllast' => $zahllast,
            'profit' => $profit,
            'progress' => min(100, round($progress)),
            'missing_receipts_count' => $missingReceiptsCount,
            'missing_items' => $missingReceiptItems,
            'checklist' => $checklist,
            'all_checklist_passed' => $allChecklistPassed,
            'status' => $status,
            'order_count' => $orders->count() + $manualInvoices->count(),
            'expense_count' => $totalBusinessItems,
            'raw_orders' => $orders,
            'raw_manual_invoices' => $manualInvoices,
            'raw_credits' => $creditNotes,
            'raw_specials' => $businessSpecials,
            'raw_fixed' => $businessFixed,
            'is_future' => $isFutureMonth,
            'is_past' => $isPastMonth,
            'deadline' => $deadline,
            'submission_type' => $submissionType,
            'company' => $this->getCompanyTaxMeta(),
        ];
    }

    /**
     * Berechnet UStVA- & EÜR-Daten flexibel für Einzelmonat ('month', 1..12), Quartal ('quarter', Q1..Q4 => ELSTER 41..44)
     * oder Gesamtjahr ('year' => GESAMT).
     */
    public function getUstvaPeriodData(
        int $year,
        int $monthOrQuarter,
        string $mode = 'month',
        $adminId = null,
        string $submissionType = 'Erstübermittlung'
    ): array {
        $year = max(2000, min(2100, $year));
        $resolvedAdminId = $this->resolveMandantAdminId($adminId);

        if (!in_array($mode, ['quarter', 'year'], true)) {
            return $this->getUstvaMonthData($resolvedAdminId, max(1, min(12, $monthOrQuarter)), $year, $submissionType);
        }

        if ($mode === 'year') {
            $quarter = 0;
            $months = range(1, 12);
            $elsterZeitraum = 'JAHR';
            $exportSlug = 'GESAMT';
            $monthNumberLabel = 'GESAMT';
        } else {
            $quarter = max(1, min(4, $monthOrQuarter));
            $months = range(($quarter - 1) * 3 + 1, $quarter * 3);
            $elsterZeitraum = (string) (40 + $quarter);
            $exportSlug = "Q{$quarter}";
            $monthNumberLabel = $elsterZeitraum;
        }

        $monthPayloads = array_map(
            fn (int $m) => $this->getUstvaMonthData($resolvedAdminId, $m, $year, $submissionType),
            $months
        );

        $firstMonthInt = $months[0];
        $lastMonthInt = end($months);
        $pStart = Carbon::createFromDate($year, $firstMonthInt, 1)->startOfMonth();
        $pEnd = Carbon::createFromDate($year, $lastMonthInt, 1)->endOfMonth();
        $isFuture = $pStart->isFuture();
        $isPast = $pEnd->isPast();
        $deadline = $mode === 'year'
            ? Carbon::createFromDate($year + 1, 7, 31)
            : Carbon::createFromDate($year, $lastMonthInt, 10)->addMonth();

        $sum = fn (string $key): float => (float) collect($monthPayloads)->sum($key);

        $revenueNet19 = $sum('revenue_net_19');
        $revenueNet7 = $sum('revenue_net_7');

        $rawOrders = collect($monthPayloads)->flatMap(fn ($d) => $d['raw_orders'])->values();
        $rawManualInvoices = collect($monthPayloads)->flatMap(fn ($d) => $d['raw_manual_invoices'])->values();
        $rawCredits = collect($monthPayloads)->flatMap(fn ($d) => $d['raw_credits'])->values();
        $rawSpecials = collect($monthPayloads)->flatMap(fn ($d) => $d['raw_specials'])->values();
        $rawFixed = collect($monthPayloads)->flatMap(fn ($d) => $d['raw_fixed'])->values();

        $missingItems = collect($monthPayloads)
            ->flatMap(fn ($d) => $d['missing_items'])
            ->unique(fn ($item) => ($item['type'] ?? '') . ':' . ($item['id'] ?? ''))
            ->values();

        $missingReceiptsCount = $missingItems->count();
        $totalBusinessItems = $rawSpecials->count() + $rawFixed->count();

        $taxId = shop_setting('owner_tax_id', '');
        $proprietor = shop_setting('owner_proprietor', '');

        $checklist = [
            'buchhaltung/receipts' => [
                'name' => '100% Belege verbucht (' . $totalBusinessItems . ' Transaktionen)',
                'passed' => $missingReceiptsCount === 0,
                'description' => 'Nur eine vollständige Belegablage garantiert eine rechtssichere Vorsteuerermittlung (Kz 66). Fehlende Belege blockieren den Export.',
            ],
            'tax_id' => [
                'name' => 'Gültige Steuernummer hinterlegt',
                'passed' => !empty($taxId) && strlen($taxId) > 8,
                'description' => "Ohne gültige Steuernummer kann das Finanzamt die XML nicht verarbeiten. (Aktuell: " . ($taxId ?: 'Fehlt') . ")",
            ],
            'proprietor' => [
                'name' => 'Vor- und Nachname des Inhabers',
                'passed' => !empty($proprietor),
                'description' => 'Ein Pflichtfeld für das ERiC-Schema. Der Vor- und Nachname muss in den System-Einstellungen hinterlegt sein.',
            ],
        ];

        $allChecklistPassed = collect($checklist)->every(fn ($item) => $item['passed']);
        $progress = 0;

        if ($isFuture) {
            $status = 'future';
        } else {
            $progress += $totalBusinessItems > 0
                ? ((($totalBusinessItems - min($totalBusinessItems, $missingReceiptsCount)) / $totalBusinessItems) * 70)
                : 70;
            if ($taxId && $proprietor) {
                $progress += 30;
            }
            $status = $isPast ? ($allChecklistPassed ? 'ready' : 'missing_data') : 'in_progress';
        }

        if ($mode === 'year') {
            $periodDisplayName = "Gesamtjahr {$year}";
        } else {
            $firstShort = mb_substr($monthPayloads[0]['month_name'], 0, 3);
            $lastShort = mb_substr($monthPayloads[2]['month_name'], 0, 3);
            $periodDisplayName = "Q{$quarter} ({$firstShort}-{$lastShort})";
        }

        $operatingNetSum = round($sum('operating_expenses_net'), 2);
        $depreciationNetSum = round($sum('depreciation_net'), 2);
        $euerExpensesSum = round($operatingNetSum + $depreciationNetSum, 2);
        $revenueNetSum = $sum('revenue_net');

        return [
            'period_mode' => $mode,
            'elster_zeitraum' => $elsterZeitraum,
            'export_period_slug' => $exportSlug,
            'months_in_period' => $months,
            'quarter' => $quarter,
            'month_name' => $periodDisplayName,
            'month_number' => $monthNumberLabel,
            'year' => $year,
            'revenue_gross' => $sum('revenue_gross'),
            'revenue_net' => $revenueNetSum,
            'revenue_net_19' => $revenueNet19,
            'vat_collected_19' => $sum('vat_collected_19'),
            'revenue_net_7' => $revenueNet7,
            'vat_collected_7' => $sum('vat_collected_7'),
            'kz81_base' => (int) floor(max(0.0, round($revenueNet19, 2))),
            'kz86_base' => (int) floor(max(0.0, round($revenueNet7, 2))),
            'vat_collected' => $sum('vat_collected'),
            'retroactive_vat_collected' => $sum('retroactive_vat_collected'),
            'is_small_business_year' => $monthPayloads[0]['is_small_business_year'],
            'is_midyear_switch' => $monthPayloads[0]['is_midyear_switch'],
            'switch_date' => $monthPayloads[0]['switch_date'],
            'ig_erwerb_tax' => $sum('ig_erwerb_tax'),
            'paragraph_13b_tax' => $sum('paragraph_13b_tax'),
            'total_tax' => $sum('total_tax'),
            'expenses_gross' => $sum('expenses_gross'),
            'expenses_net' => $sum('expenses_net'),
            'operating_expenses_net' => $operatingNetSum,
            'capital_additions_gross' => round($sum('capital_additions_gross'), 2),
            'capital_additions_net' => round($sum('capital_additions_net'), 2),
            'depreciation_net' => $depreciationNetSum,
            'euer_expenses_net' => $euerExpensesSum,
            'euer_profit' => round($revenueNetSum - $euerExpensesSum, 2),
            'vorsteuer_special' => $sum('vorsteuer_special'),
            'vorsteuer_fix' => $sum('vorsteuer_fix'),
            'vat_paid' => $sum('vat_paid'),
            'zahllast' => $sum('zahllast'),
            'profit' => $sum('profit'),
            'progress' => min(100, round($progress)),
            'missing_receipts_count' => $missingReceiptsCount,
            'missing_items' => $missingItems,
            'checklist' => $checklist,
            'all_checklist_passed' => $allChecklistPassed,
            'status' => $status,
            'order_count' => (int) $sum('order_count'),
            'expense_count' => $totalBusinessItems,
            'raw_orders' => $rawOrders,
            'raw_manual_invoices' => $rawManualInvoices,
            'raw_credits' => $rawCredits,
            'raw_specials' => $rawSpecials,
            'raw_fixed' => $rawFixed,
            'is_future' => $isFuture,
            'is_past' => $isPast,
            'deadline' => $deadline,
            'submission_type' => $submissionType,
            'company' => $this->getCompanyTaxMeta(),
        ];
    }

    /**
     * Berechnet den potenziellen Steuerrückgabe-Wert (Vorsteuer-Überhang) für 3 Sichten:
     * - total_all: Gesamt inkl. Vorgründung (alle bisherigen gewerblichen Ausgaben bis heute, ohne zukünftige Monate)
     * - since_launch: Ab Gewerbestart 01.11.2026 bis heute
     * - selected_period: Gewählter Monat oder gewähltes Quartal
     */
    public function getPotentialTaxRefundSummary(
        ?int $selectedYear = null,
        ?int $selectedPeriod = null,
        string $periodMode = 'month',
        $adminId = null,
        ?Carbon $asOfDate = null
    ): array {
        $today = ($asOfDate ? $asOfDate->copy() : Carbon::today())->endOfDay();
        $launchDate = Carbon::create(2026, 11, 1)->startOfDay();
        $resolvedAdminId = $this->resolveMandantAdminId($adminId);

        $selYear = $selectedYear ?: (int) $today->year;
        $selPeriod = $selectedPeriod ?: ($periodMode === 'quarter' ? (int) ceil($today->month / 3) : (int) $today->month);

        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $smallBizCache = [];
        $midyearCache = [];
        $isYearSmall = function (int $y) use ($taxService, &$smallBizCache): bool {
            return $smallBizCache[$y] ??= $taxService->isSmallBusinessForYear($y);
        };
        $isMidyear = function (int $y) use ($taxService, &$midyearCache): bool {
            return $midyearCache[$y] ??= $taxService->isMidyearSwitch($y);
        };

        $computeRange = function (?Carbon $fromDate, Carbon $toDate, bool $capFutureMonths = true) use (
            $resolvedAdminId,
            $isYearSmall,
            $isMidyear
        ): array {
            if ($fromDate && $fromDate->gt($toDate)) {
                return [
                    'vorsteuer_special' => 0.0,
                    'vorsteuer_fix' => 0.0,
                    'vorsteuer_total' => 0.0,
                    'ust_sales' => 0.0,
                    'net_refund' => 0.0,
                    'verified_vorsteuer' => 0.0,
                    'pending_doc_vorsteuer' => 0.0,
                    'pending_doc_count' => 0,
                ];
            }

            // 1. Gewerbliche Sonderausgaben
            $specialsQuery = AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
                ->where('is_business', true)
                ->whereDate('execution_date', '<=', $toDate->toDateString());
            if ($fromDate) {
                $specialsQuery->whereDate('execution_date', '>=', $fromDate->toDateString());
            }
            $specials = $specialsQuery->get();

            $vorsteuerSpecial = 0.0;
            $verifiedVorsteuer = 0.0;
            $pendingDocVorsteuer = 0.0;
            $pendingDocCount = 0;

            foreach ($specials as $s) {
                $yr = (int) Carbon::parse($s->execution_date)->year;
                if ($isYearSmall($yr)) {
                    continue;
                }
                $rawRate = $s->tax_rate ?? 0;
                if (!self::isEligibleGermanInputVatRate($rawRate)) {
                    continue;
                }
                $rate = (int) round((float) $rawRate);
                $amt = abs((float) $s->amount);
                $vat = $amt - ($amt / (1 + ($rate / 100)));
                $vorsteuerSpecial += $vat;

                $isProvisional = self::hasProvisionalOrMissingTaxReceipt(
                    is_array($s->file_paths) ? $s->file_paths : [],
                    $s->note
                );
                if ($isProvisional) {
                    $pendingDocVorsteuer += $vat;
                    $pendingDocCount++;
                } else {
                    $verifiedVorsteuer += $vat;
                }
            }

            // 2. Gewerbliche Fixkosten (monatsgenau bis maximal $toDate-Monat)
            $groups = AccountingGroup::with('items')->where('admin_id', $resolvedAdminId)->get();
            $bizFixedItems = collect();
            foreach ($groups as $group) {
                if ($group->type === 'income') {
                    continue;
                }
                foreach ($group->items as $item) {
                    if ($item->is_business == 1 || $item->is_business === true) {
                        $bizFixedItems->push($item);
                    }
                }
            }

            $vorsteuerFix = 0.0;
            if ($bizFixedItems->isNotEmpty()) {
                $earliestStart = $bizFixedItems
                    ->map(fn ($it) => $it->first_payment_date ? Carbon::parse($it->first_payment_date)->startOfMonth() : null)
                    ->filter()
                    ->sort()
                    ->first();

                $cursorStart = $fromDate
                    ? $fromDate->copy()->startOfMonth()
                    : ($earliestStart ? $earliestStart->copy() : $toDate->copy()->startOfYear());
                $cursorEnd = $toDate->copy()->startOfMonth();

                $pendingFixedIdsCounted = [];
                $cursor = $cursorStart->copy();
                while ($cursor->lte($cursorEnd)) {
                    $y = (int) $cursor->year;
                    $m = (int) $cursor->month;
                    if (!$isYearSmall($y)) {
                        foreach ($bizFixedItems as $f) {
                            if (!$this->isDueInMonth($f, $m, $y)) {
                                continue;
                            }
                            if ($capFutureMonths && $y === (int) $toDate->year && $m === (int) $toDate->month && $f->first_payment_date) {
                                // Falls der allererste Vertragsbeginn erst nach dem Stichtag liegt, noch nicht mitzählen
                                if (Carbon::parse($f->first_payment_date)->startOfDay()->gt($toDate)) {
                                    continue;
                                }
                            }
                            $rawRate = $f->tax_rate ?? 0;
                            if (!self::isEligibleGermanInputVatRate($rawRate)) {
                                continue;
                            }
                            $rate = (int) round((float) $rawRate);
                            $amt = abs((float) $f->amount);
                            $vat = $amt - ($amt / (1 + ($rate / 100)));
                            $vorsteuerFix += $vat;

                            $fixedFiles = !empty($f->contract_file_path) ? [$f->contract_file_path] : [];
                            $isProvisional = self::hasProvisionalOrMissingTaxReceipt($fixedFiles, $f->description);
                            if ($isProvisional) {
                                $pendingDocVorsteuer += $vat;
                                if (!isset($pendingFixedIdsCounted[$f->id])) {
                                    $pendingFixedIdsCounted[$f->id] = true;
                                    $pendingDocCount++;
                                }
                            } else {
                                $verifiedVorsteuer += $vat;
                            }
                        }
                    }
                    $cursor->addMonth();
                }
            }

            // 3. Ausgangs-Umsatzsteuer aus bezahlten Bestellungen & Rechnungen abzüglich Gutschriften
            $ordersQ = OrderOrder::with('items')
                ->where('payment_status', 'paid')
                ->where('created_at', '<=', $toDate);
            if ($fromDate) {
                $ordersQ->where('created_at', '>=', $fromDate);
            }
            $orders = $ordersQ->get();

            $manualQ = \App\Models\Accounting\AccountingInvoice::whereNull('order_id')
                ->where('type', 'invoice')
                ->whereDate('invoice_date', '<=', $toDate->toDateString())
                ->where(function ($q) {
                    $q->where('status', 'paid')
                        ->orWhere(function ($sub) {
                            $sub->where('status', 'cancelled')->whereNotNull('paid_at');
                        });
                });
            if ($fromDate) {
                $manualQ->whereDate('invoice_date', '>=', $fromDate->toDateString());
            }
            $manuals = $manualQ->get();

            $creditsQ = \App\Models\Accounting\AccountingInvoice::with('order.items')
                ->whereIn('type', ['credit_note', 'cancellation'])
                ->whereDate('invoice_date', '<=', $toDate->toDateString())
                ->where(function ($q) {
                    $q->where('status', 'paid')->orWhereNotNull('paid_at');
                });
            if ($fromDate) {
                $creditsQ->whereDate('invoice_date', '>=', $fromDate->toDateString());
            }
            $credits = $creditsQ->get();

            $ustSales = 0.0;
            foreach ($orders as $o) {
                $yr = (int) $o->created_at->year;
                $total = $o->total_price / 100;
                $tax = $o->tax_amount / 100;
                $split = $this->splitGrossAndTaxIntoRates($total, $tax, $o->items ?? [], $isYearSmall($yr), !$isYearSmall($yr) && $tax == 0 && ($isMidyear($yr) || $o->is_small_business));
                $ustSales += $split['total_vat'];
            }
            foreach ($manuals as $inv) {
                $yr = (int) Carbon::parse($inv->invoice_date)->year;
                $total = abs($inv->total / 100);
                $tax = abs($inv->tax_amount / 100);
                $split = $this->splitGrossAndTaxIntoRates($total, $tax, $inv->custom_items ?? [], $isYearSmall($yr), !$isYearSmall($yr) && $tax == 0 && ($isMidyear($yr) || $inv->is_small_business));
                $ustSales += $split['total_vat'];
            }
            foreach ($credits as $cn) {
                $yr = (int) Carbon::parse($cn->invoice_date)->year;
                $total = -abs($cn->total / 100);
                $tax = -abs($cn->tax_amount / 100);
                $cnItems = !empty($cn->custom_items) ? $cn->custom_items : ($cn->order?->items ?? []);
                $split = $this->splitGrossAndTaxIntoRates($total, $tax, $cnItems, $isYearSmall($yr), !$isYearSmall($yr) && $tax == 0 && ($isMidyear($yr) || $cn->is_small_business));
                $ustSales += $split['total_vat'];
            }

            $vorsteuerTotal = $vorsteuerSpecial + $vorsteuerFix;

            return [
                'vorsteuer_special' => round($vorsteuerSpecial, 2),
                'vorsteuer_fix' => round($vorsteuerFix, 2),
                'vorsteuer_total' => round($vorsteuerTotal, 2),
                'ust_sales' => round($ustSales, 2),
                'net_refund' => round($vorsteuerTotal - $ustSales, 2),
                'verified_vorsteuer' => round($verifiedVorsteuer, 2),
                'pending_doc_vorsteuer' => round($pendingDocVorsteuer, 2),
                'pending_doc_count' => $pendingDocCount,
            ];
        };

        // Sicht 1: Gesamt inkl. Vorgründung bis heute
        $totalAll = $computeRange(null, $today, true);
        $totalAll['label'] = 'Gesamt Bisher';

        // Sicht 2: Ab Gewerbestart 01.11.2026 bis heute
        $sinceLaunch = $computeRange($launchDate, $today, true);
        $sinceLaunch['label'] = 'Ab Gründung 11/26';

        // Sicht 3: Gewählter Monat oder Quartal (ohne zukünftige unbezahlte Monate!)
        if ($periodMode === 'quarter') {
            $q = max(1, min(4, $selPeriod));
            $pStart = Carbon::createFromDate($selYear, ($q - 1) * 3 + 1, 1)->startOfMonth();
            $pEnd = Carbon::createFromDate($selYear, $q * 3, 1)->endOfMonth();
            $periodLabel = "Q{$q}/{$selYear}";
        } else {
            $m = max(1, min(12, $selPeriod));
            $pStart = Carbon::createFromDate($selYear, $m, 1)->startOfMonth();
            $pEnd = $pStart->copy()->endOfMonth();
            $periodLabel = str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "/{$selYear}";
        }

        $effectivePeriodEnd = $pEnd->gt($today) ? $today : $pEnd;
        $selectedPeriodSummary = $computeRange($pStart, $effectivePeriodEnd, true);
        $selectedPeriodSummary['label'] = $periodLabel;

        return [
            'as_of' => $today->format('d.m.Y'),
            'total_all' => $totalAll,
            'since_launch' => $sinceLaunch,
            'selected_period' => $selectedPeriodSummary,
        ];
    }

    /**
     * Ermittelt das passende SKR03-Erlöskonto für Ausgangsumsätze & Gutschriften.
     */
    public function resolveSkr03RevenueAccount(
        int $rate,
        bool $isSmallBusiness = false,
        bool $isReverseCharge = false,
        bool $isIgErwerb = false
    ): string {
        if ($isSmallBusiness) {
            return '8195';
        }
        if ($isReverseCharge) {
            return '8337';
        }
        if ($isIgErwerb) {
            return '8125';
        }
        if ($rate === 7) {
            return '8300';
        }
        if ($rate === 0) {
            return '8120';
        }
        return '8400';
    }

    /**
     * Ordnet einer gewerblichen Ausgabe deterministisch das passende SKR03-Sachkonto
     * sowie den korrekten DATEV BU-Steuerschlüssel zu (Automatik-Konten bleiben ohne BU-Schlüssel).
     */
    public function resolveSkr03ExpenseAccountAndBuKey(object $item, bool $isSmallBusiness = false): array
    {
        $gross = abs((float) ($item->amount ?? 0));
        $rawRate = $item->tax_rate ?? 0;
        $rate = (!$isSmallBusiness && self::isEligibleGermanInputVatRate($rawRate))
            ? (int) round((float) $rawRate)
            : 0;
        $net = $rate > 0 ? round($gross / (1 + ($rate / 100.0)), 2) : round($gross, 2);

        $title = mb_strtolower((string) ($item->title ?? $item->name ?? ''));
        $cat = mb_strtolower((string) ($item->category ?? $item->group_name ?? ($item->group->name ?? '')));
        $haystack = $cat . ' ' . $title;

        $buKey = $rate === 19 ? '9' : ($rate === 7 ? '8' : '');

        // 1. Anlagevermögen > 800 € Netto (lineare AfA -> SKR03 0440 Maschinen / Betriebsausstattung)
        if ($item instanceof AccountingSpecialIssue && $this->isCapitalAssetIssue($item, $net)) {
            return ['account' => '0440', 'bu_key' => $buKey, 'label' => 'Maschinen & Anlagen'];
        }
        if (!($item instanceof AccountingSpecialIssue) && isset($item->execution_date) && $net > 800.00 && !str_contains($haystack, 'rohmaterial') && !str_contains($haystack, 'wareneinkauf')) {
            return ['account' => '0440', 'bu_key' => $buKey, 'label' => 'Maschinen & Anlagen'];
        }

        // 2. Geringwertige Wirtschaftsgüter (GWG 150–800 € Netto -> SKR03 0480)
        if (
            isset($item->execution_date)
            && $net >= 150.00
            && $net <= 800.00
            && in_array($cat, ['arbeitsmaterial', 'technik', 'maschinen'], true)
            && !\Illuminate\Support\Str::contains($title, ['visitenkarten', 'aufkleber', 'umschläge', 'karton'])
        ) {
            return ['account' => '0480', 'bu_key' => $buKey, 'label' => 'GWG (≤ 800 €)'];
        }

        // 3. Rohmaterial & Wareneingang (SKR03 3400 / 3300 = DATEV Automatik-Konten -> kein BU-Schlüssel!)
        if (\Illuminate\Support\Str::contains($haystack, ['rohmaterial', 'wareneinkauf', 'rohling'])) {
            if ($rate === 19) {
                return ['account' => '3400', 'bu_key' => '', 'label' => 'Wareneingang 19 %'];
            }
            if ($rate === 7) {
                return ['account' => '3300', 'bu_key' => '', 'label' => 'Wareneingang 7 %'];
            }
            return ['account' => '3200', 'bu_key' => '', 'label' => 'Wareneingang 0 %'];
        }

        // 4. Verpackungsmaterial (SKR03 4710)
        if (\Illuminate\Support\Str::contains($haystack, ['verpackung', 'versandkarton', 'schmuckbox', 'westpack', 'karton'])) {
            return ['account' => '4710', 'bu_key' => $buKey, 'label' => 'Verpackungsmaterial'];
        }

        // 5. Betriebliche Versicherungen (SKR03 4360, i.d.R. 0 % Vorsteuer wg. Versicherungssteuer)
        if (\Illuminate\Support\Str::contains($haystack, ['versicherung', 'haftpflicht', 'hiscox'])) {
            return ['account' => '4360', 'bu_key' => '', 'label' => 'Versicherungen'];
        }

        // 6. Behördengebühren, Markenamt, Gewerbeanmeldung, LUCID, IHK (SKR03 4390)
        if (\Illuminate\Support\Str::contains($haystack, ['dpma', 'marke', 'gewerbeanmeldung', 'gebühr', 'lucid', 'ihk'])) {
            return ['account' => '4390', 'bu_key' => $buKey, 'label' => 'Abgaben & Gebühren'];
        }

        // 7. Marketing, Werbung & Creator-/Influencer-Provisionen (SKR03 4600)
        if (\Illuminate\Support\Str::contains($haystack, ['werbung', 'marketing', 'affiliate', 'influencer', 'visitenkarten'])) {
            return ['account' => '4600', 'bu_key' => $buKey, 'label' => 'Werbekosten'];
        }

        // 8. Software, Cloud, Server, Domains & KI-Lizenzen (SKR03 4964)
        if (\Illuminate\Support\Str::contains($haystack, ['software', 'hosting', 'server', 'domain', 'mittwald', 'google', 'suno', 'cloud', 'it &', 'lizenz'])) {
            return ['account' => '4964', 'bu_key' => $buKey, 'label' => 'EDV & Hosting'];
        }

        // 9. Porto & Versanddienstleister (SKR03 4910)
        if (\Illuminate\Support\Str::contains($haystack, ['porto', 'briefmarke', 'dhl', 'ausgangsfracht'])) {
            return ['account' => '4910', 'bu_key' => $buKey, 'label' => 'Porto & Versand'];
        }

        // 10. Werkstatt- & Betriebsbedarf (SKR03 4980)
        if (\Illuminate\Support\Str::contains($haystack, ['arbeitsmaterial', 'werkstatt', 'büro', 'reinigung', 'isopropanol', 'etikett'])) {
            return ['account' => '4980', 'bu_key' => $buKey, 'label' => 'Betriebsbedarf'];
        }

        return ['account' => '4900', 'bu_key' => $buKey, 'label' => 'Sonst. betr. Aufwand'];
    }

    /**
     * Erstellt den DATEV EXTF Buchungsstapel CSV-String inklusive SKR03-Vorkontierung und BU-Schlüssel.
     */
    public function buildDatevCsv(array $data): string
    {
        $isSmallBiz = !empty($data['is_small_business_year']);

        $sanitizeField = static function (?string $val): string {
            $clean = str_replace(["\r", "\n", ';'], ' ', (string) $val);
            $clean = str_replace('"', '""', trim($clean));
            if ($clean !== '' && in_array($clean[0], ['=', '+', '-', '@', "\t"], true)) {
                $clean = "'" . $clean;
            }
            return $clean;
        };

        $csv = '"EXTF";700;21;"Buchungsstapel";9;"";"";"";"";"";"1";"";"";"";"";"";"";"";"";"";"";"";"";"";""' . "\n";
        $csv .= '"Umsatz (ohne Soll/Haben-Kz)";"Soll/Haben-Kennzeichen";"Konto";"Gegenkonto (ohne BU-Schlüssel)";"BU-Schlüssel";"Belegdatum";"Belegfeld 1";"Buchungstext"' . "\n";

        foreach ($data['raw_orders'] ?? [] as $order) {
            $date = $order->created_at->format('dm');
            $safeOrderNo = $sanitizeField((string) $order->order_number);
            $isRevCharge = !empty($order->is_reverse_charge);
            $isIg = !empty($order->is_ig_erwerb);

            $split = $this->splitGrossAndTaxIntoRates(
                $order->total_price / 100,
                $order->tax_amount / 100,
                $order->items ?? [],
                $isSmallBiz
            );

            $gross7 = round($split['net_7'] + $split['vat_7'], 2);
            $gross19 = round($split['net_19'] + $split['vat_19'], 2);

            if (!$isSmallBiz && !$isRevCharge && !$isIg && $gross7 > 0.004 && $gross19 > 0.004) {
                $csv .= '"' . number_format($gross19, 2, ',', '') . '";"S";"1200";"8400";"";"' . $date . '";"' . $safeOrderNo . '";"Bestellung ' . $safeOrderNo . ' (19%)"' . "\n";
                $csv .= '"' . number_format($gross7, 2, ',', '') . '";"S";"1200";"8300";"";"' . $date . '";"' . $safeOrderNo . '";"Bestellung ' . $safeOrderNo . ' (7%)"' . "\n";
            } else {
                $dominantRate = $gross7 > 0.004 && $gross19 <= 0.004 ? 7 : (($order->tax_amount ?? 0) == 0 && !$isSmallBiz ? 0 : 19);
                $revAccount = $this->resolveSkr03RevenueAccount($dominantRate, $isSmallBiz, $isRevCharge, $isIg);
                $amount = number_format($order->total_price / 100, 2, ',', '');
                $csv .= '"' . $amount . '";"S";"1200";"' . $revAccount . '";"";"' . $date . '";"' . $safeOrderNo . '";"Bestellung ' . $safeOrderNo . '"' . "\n";
            }
        }

        foreach ($data['raw_manual_invoices'] ?? [] as $manualInv) {
            $amount = number_format(abs($manualInv->total / 100), 2, ',', '');
            $date = ($manualInv->invoice_date ?? $manualInv->created_at ?? now())->format('dm');
            $safeInvNo = $sanitizeField((string) $manualInv->invoice_number);
            $split = $this->splitGrossAndTaxIntoRates(abs($manualInv->total / 100), abs($manualInv->tax_amount / 100), $manualInv->custom_items ?? [], $isSmallBiz);
            $dominantRate = ($split['net_7'] + $split['vat_7']) > 0.004 && ($split['net_19'] + $split['vat_19']) <= 0.004 ? 7 : (($manualInv->tax_amount ?? 0) == 0 ? 0 : 19);
            $revAccount = $this->resolveSkr03RevenueAccount($dominantRate, $isSmallBiz);
            $csv .= '"' . $amount . '";"S";"1200";"' . $revAccount . '";"";"' . $date . '";"' . $safeInvNo . '";"Rechnung ' . $safeInvNo . '"' . "\n";
        }

        foreach ($data['raw_credits'] ?? [] as $credit) {
            $amount = number_format(abs($credit->total / 100), 2, ',', '');
            $date = ($credit->invoice_date ?? $credit->created_at ?? now())->format('dm');
            $safeCreditNo = $sanitizeField((string) $credit->invoice_number);
            $cnItems = !empty($credit->custom_items) ? $credit->custom_items : ($credit->order?->items ?? []);
            $split = $this->splitGrossAndTaxIntoRates(abs($credit->total / 100), abs($credit->tax_amount / 100), $cnItems, $isSmallBiz);
            $dominantRate = ($split['net_7'] + $split['vat_7']) > 0.004 && ($split['net_19'] + $split['vat_19']) <= 0.004 ? 7 : (($credit->tax_amount ?? 0) == 0 ? 0 : 19);
            $revAccount = $this->resolveSkr03RevenueAccount(
                $dominantRate,
                $isSmallBiz,
                (bool) ($credit->order?->is_reverse_charge ?? false),
                (bool) ($credit->order?->is_ig_erwerb ?? false)
            );
            $csv .= '"' . $amount . '";"H";"1200";"' . $revAccount . '";"";"' . $date . '";"' . $safeCreditNo . '";"Gutschrift / Storno ' . $safeCreditNo . '"' . "\n";
        }

        foreach ($data['raw_specials'] ?? [] as $special) {
            $amount = number_format(abs((float) $special->amount), 2, ',', '');
            $date = Carbon::parse($special->execution_date)->format('dm');
            $safeTitle = $sanitizeField((string) $special->title);
            $safeBeleg = $sanitizeField((string) ($special->invoice_number ?? 'Beleg'));
            $skr = $this->resolveSkr03ExpenseAccountAndBuKey($special, $isSmallBiz);
            $shFlag = ((float) ($special->amount ?? -1)) > 0 ? 'S' : 'H';
            $csv .= '"' . $amount . '";"' . $shFlag . '";"' . $skr['account'] . '";"1200";"' . $skr['bu_key'] . '";"' . $date . '";"' . $safeBeleg . '";"' . $safeTitle . '"' . "\n";
        }

        foreach ($data['raw_fixed'] ?? [] as $fixed) {
            $amount = number_format(abs((float) $fixed->amount), 2, ',', '');
            $dueMonth = (int) ($fixed->_due_month ?? ($data['months_in_period'][0] ?? (is_numeric($data['month_number'] ?? null) ? $data['month_number'] : 1)));
            $dueMonth = max(1, min(12, $dueMonth));
            $date = '28' . str_pad((string) $dueMonth, 2, '0', STR_PAD_LEFT);
            $safeName = $sanitizeField((string) $fixed->name);
            $skr = $this->resolveSkr03ExpenseAccountAndBuKey($fixed, $isSmallBiz);
            $csv .= '"' . $amount . '";"H";"' . $skr['account'] . '";"1200";"' . $skr['bu_key'] . '";"' . $date . '";"Fixkosten";"' . $safeName . '"' . "\n";
        }

        return $csv;
    }

    /**
     * Erstellt den Anlagenspiegel & GWG-/IAB-Übersicht als CSV für den Steuerberater (Anlage AVEÜR).
     */
    public function buildAssetScheduleCsv($adminId, int $year, ?int $upToMonth = null): string
    {
        $resolvedAdminId = $this->resolveMandantAdminId($adminId) ?: $adminId;
        $schedule = $this->getFixedAssetsAndDepreciation($resolvedAdminId, $year, $upToMonth);

        $sanitizeCsvCell = static function ($value): string {
            $str = (string) $value;
            $trimmed = ltrim($str, " \t\n\r\0\x0B");
            if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'" . $str;
            }
            return $str;
        };

        $handle = fopen('php://temp', 'r+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, [
            'Abschnitt',
            'SKR03-Konto',
            'Bezeichnung',
            'Anschaffungsdatum',
            'AfA-Ende',
            'Brutto (EUR)',
            'Netto-AK (EUR)',
            'Vorsteuer (EUR)',
            'Nutzungsdauer (Jahre)',
            'Monats-AfA (EUR)',
            "Jahres-AfA {$year} (EUR)",
            'Kumulierte AfA (EUR)',
            'Restbuchwert (EUR)',
        ], ';', '"', '');

        foreach ($schedule['assets'] ?? [] as $asset) {
            fputcsv($handle, [
                'Anlagevermögen (> 800 € Netto, linear)',
                '0440',
                $sanitizeCsvCell($asset['title']),
                $asset['purchase_date'],
                $asset['end_date'],
                number_format($asset['gross_cost'], 2, ',', '.'),
                number_format($asset['net_cost'], 2, ',', '.'),
                number_format($asset['vat_amount'], 2, ',', '.'),
                $asset['afa_years'],
                number_format($asset['monthly_afa'], 2, ',', '.'),
                number_format($asset['year_afa'], 2, ',', '.'),
                number_format($asset['accumulated_afa'], 2, ',', '.'),
                number_format($asset['book_value'], 2, ',', '.'),
            ], ';', '"', '');
        }

        foreach ($schedule['gwg_items'] ?? [] as $gwg) {
            fputcsv($handle, [
                'GWG Sofortabschreibung (§ 6 Abs. 2 EStG)',
                '0480',
                $sanitizeCsvCell($gwg['title']),
                $gwg['purchase_date'],
                'Sofort',
                number_format($gwg['gross_cost'], 2, ',', '.'),
                number_format($gwg['net_cost'], 2, ',', '.'),
                number_format($gwg['vat_amount'], 2, ',', '.'),
                '1',
                number_format($gwg['net_cost'], 2, ',', '.'),
                number_format($gwg['net_cost'], 2, ',', '.'),
                number_format($gwg['net_cost'], 2, ',', '.'),
                '0,00',
            ], ';', '"', '');
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('accounting_investment_plans') && $resolvedAdminId) {
            $plans = \App\Models\Accounting\AccountingInvestmentPlan::where('admin_id', $resolvedAdminId)
                ->where('is_active', true)
                ->whereNull('completed_special_issue_id')
                ->orderBy('priority')
                ->get();

            foreach ($plans as $plan) {
                $grossEur = round((float) $plan->target_amount_gross, 2);
                $netEur = (float) $plan->target_amount_net;
                $iabPreview = round($netEur * 0.50, 2);
                fputcsv($handle, [
                    'Geplante Reinvestition / § 7g EStG IAB-Vorschau (max. 50 % = ' . number_format($iabPreview, 2, ',', '.') . ' €)',
                    'IAB-Plan',
                    $sanitizeCsvCell($plan->title),
                    $plan->target_date ? $plan->target_date->format('d.m.Y') : 'Geplant',
                    '—',
                    number_format($grossEur, 2, ',', '.'),
                    number_format($netEur, 2, ',', '.'),
                    number_format((float) $plan->target_vat_refund, 2, ',', '.'),
                    $plan->afa_years,
                    number_format((float) $plan->monthly_rate, 2, ',', '.'),
                    '0,00',
                    number_format((float) $plan->saved_initial, 2, ',', '.'),
                    '0,00',
                ], ';', '"', '');
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return (string) $content;
    }

    /**
     * Erstellt das PDF-Prüfprotokoll für die Umsatzsteuer-Voranmeldung (global.pdf.ustva_report).
     */
    public function generateUstvaPdf(array $data)
    {
        if (empty($data['company'])) {
            $data['company'] = $this->getCompanyTaxMeta();
        }

        return Pdf::loadView('global.pdf.ustva_report', ['data' => $data])
            ->setOptions(['isRemoteEnabled' => false]);
    }

    /**
     * Erstellt das vollständige DATEV- & Steuer-Export-ZIP (TaxExport_YYYY_MM.zip)
     * inklusive EXTF Buchungsstapel CSV, Anlagenspiegel CSV, UStVA PDF und allen gewerblichen Belegen.
     */
    public function generateDatevExportZip(array $data): array
    {
        $monthStr = $data['export_period_slug'] ?? $data['month_number'];
        $yearStr = $data['year'];
        $monthsInPeriod = $data['months_in_period'] ?? [max(1, min(12, (int) $data['month_number']))];

        $tresorPath = env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor');
        $isAbs = str_starts_with($tresorPath, '/');
        $fullTresor = $isAbs ? $tresorPath : base_path($tresorPath);
        $tempDir = $fullTresor . "/exports/temp_export_{$monthStr}_{$yearStr}";

        if (\Illuminate\Support\Facades\File::exists($tempDir)) {
            \Illuminate\Support\Facades\File::deleteDirectory($tempDir);
        }
        \Illuminate\Support\Facades\File::makeDirectory($tempDir, 0755, true);

        // 1. DATEV CSV
        $csvPath = $tempDir . "/EXTF_Buchungsstapel_{$monthStr}_{$yearStr}.csv";
        $csvContent = $this->buildDatevCsv($data);
        \Illuminate\Support\Facades\File::put($csvPath, mb_convert_encoding($csvContent, 'Windows-1252', 'UTF-8'));

        // 1b. ANLAGENSPIEGEL & AfA CSV (Anlage AVEÜR)
        $lastMonthInPeriod = !empty($monthsInPeriod) ? max($monthsInPeriod) : 12;
        $assetCsvPath = $tempDir . "/Anlagenspiegel_und_AfA_{$yearStr}.csv";
        $assetCsvContent = $this->buildAssetScheduleCsv($this->resolveMandantAdminId(), (int) $yearStr, (int) $lastMonthInPeriod);
        \Illuminate\Support\Facades\File::put($assetCsvPath, $assetCsvContent);

        // 2. UStVA PDF REPORT
        $pdfPath = $tempDir . "/UStVA_Auswertung_{$monthStr}_{$yearStr}.pdf";
        $pdf = $this->generateUstvaPdf($data);
        $pdf->save($pdfPath);

        // 3. BELEGE KOPIEREN
        $receiptsDir = $tempDir . "/Belege";
        if (!\Illuminate\Support\Facades\File::exists($receiptsDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($receiptsDir, 0755, true);
        }

        $copyCount = 0;
        foreach ($data['raw_specials'] ?? [] as $special) {
            if (!empty($special->file_paths)) {
                foreach ($special->file_paths as $idx => $path) {
                    if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
                        continue;
                    }
                    $source = str_starts_with($path, 'buchhaltung/') ? storage_path('app/' . $path) : storage_path('app/buchhaltung/' . $path);
                    if (\Illuminate\Support\Facades\File::exists($source) && !is_link($source)) {
                        $ext = pathinfo($source, PATHINFO_EXTENSION);
                        \Illuminate\Support\Facades\File::copy(
                            $source,
                            $receiptsDir . "/Ausgabe_" . date('Y-m-d', strtotime($special->execution_date)) . "_" . \Illuminate\Support\Str::slug($special->title) . "_{$idx}.{$ext}"
                        );
                        $copyCount++;
                    }
                }
            }
        }
        foreach ($data['raw_fixed'] ?? [] as $fixed) {
            if (!empty($fixed->contract_file_path)) {
                $path = $fixed->contract_file_path;
                if (!is_string($path) || $path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
                    continue;
                }
                $source = str_starts_with($path, 'buchhaltung/') ? storage_path('app/' . $path) : storage_path('app/buchhaltung/' . $path);
                if (\Illuminate\Support\Facades\File::exists($source) && !is_link($source)) {
                    $ext = pathinfo($source, PATHINFO_EXTENSION);
                    $destFile = $receiptsDir . "/Fixkosten_" . \Illuminate\Support\Str::slug($fixed->name) . ".{$ext}";
                    if (!\Illuminate\Support\Facades\File::exists($destFile)) {
                        \Illuminate\Support\Facades\File::copy($source, $destFile);
                        $copyCount++;
                    }
                }
            }
        }

        // 4. ZIPPEN
        $zipName = "TaxExport_{$yearStr}_{$monthStr}.zip";
        $zipDir = storage_path('app/buchhaltung/tax_exports');
        if (!\Illuminate\Support\Facades\File::exists($zipDir)) {
            \Illuminate\Support\Facades\File::makeDirectory($zipDir, 0755, true);
        }
        $zipPath = "{$zipDir}/{$zipName}";

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if (\Illuminate\Support\Facades\File::exists($csvPath)) {
                $zip->addFile($csvPath, basename($csvPath));
            }
            if (\Illuminate\Support\Facades\File::exists($assetCsvPath)) {
                $zip->addFile($assetCsvPath, basename($assetCsvPath));
            }
            if (\Illuminate\Support\Facades\File::exists($pdfPath)) {
                $zip->addFile($pdfPath, basename($pdfPath));
            }
            if (\Illuminate\Support\Facades\File::exists($receiptsDir)) {
                $files = \Illuminate\Support\Facades\File::files($receiptsDir);
                foreach ($files as $file) {
                    $zip->addFile($file->getPathname(), 'Belege/' . $file->getFilename());
                }
            }

            // 5. AUSGANGSRECHNUNGEN & STORNOS DES ZEITRAUMS
            $yearInt = (int) $yearStr;
            $periodInvoices = \App\Models\Accounting\AccountingInvoice::where('status', '!=', 'draft')
                ->whereYear('invoice_date', $yearInt)
                ->whereIn(DB::raw('MONTH(invoice_date)'), $monthsInPeriod)
                ->get();

            foreach ($periodInvoices as $inv) {
                $safeInvNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $inv->invoice_number);
                $invPrefix = match ($inv->type) {
                    'cancellation' => 'Storno_',
                    'credit_note' => 'Gutschrift_',
                    default => 'Rechnung_',
                };
                $archivedPdfPath = ltrim(str_replace('\\', '/', (string) $inv->pdf_storage_path), '/');
                if (
                    $inv->has_archived_pdf
                    && $archivedPdfPath !== ''
                    && !str_contains($archivedPdfPath, '..')
                    && !is_link(Storage::disk('local')->path($archivedPdfPath))
                ) {
                    $zip->addFile(Storage::disk('local')->path($archivedPdfPath), "Ausgangsrechnungen/{$invPrefix}{$safeInvNo}.pdf");
                    $copyCount++;
                }
            }

            // 6. CREATOR-GUTSCHRIFTEN (§ 14 UStG) DES ZEITRAUMS
            if (\Illuminate\Support\Facades\Schema::hasTable('marketing_influencer_payouts')) {
                $periodPayouts = \App\Models\Marketing\MarketingInfluencerPayout::with('influencer.employee.profile')
                    ->where('status', '!=', 'cancelled')
                    ->whereYear('created_at', $yearInt)
                    ->whereIn(DB::raw('MONTH(created_at)'), $monthsInPeriod)
                    ->get();

                $infService = app(\App\Services\Marketing\InfluencerService::class);
                foreach ($periodPayouts as $payout) {
                    if (empty($payout->pdf_path) || !Storage::disk('local')->exists((string) $payout->pdf_path)) {
                        try {
                            $infService->generateCreditNotePdf($payout);
                            $payout->refresh();
                        } catch (\Throwable $e) {
                            continue;
                        }
                    }
                    $pPath = (string) $payout->pdf_path;
                    if ($pPath !== '' && !str_contains($pPath, '..') && str_starts_with($pPath, 'buchhaltung/receipts/') && Storage::disk('local')->exists($pPath) && !is_link(Storage::disk('local')->path($pPath))) {
                        $safeCreditNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $payout->credit_note_number);
                        $zip->addFile(Storage::disk('local')->path($pPath), "Creator_Gutschriften/Gutschrift_{$safeCreditNo}.pdf");
                        $copyCount++;
                    }
                }
            }

            // 7. PERSONALAKTEN, STUNDENZETTEL & LOHNABRECHNUNGEN DES ZEITRAUMS
            if (\Illuminate\Support\Facades\Schema::hasTable('employee_contracts')) {
                $hrContracts = \App\Models\Employee\EmployeeContract::with(['employee.profile', 'payslips' => fn ($q) => $q->where('period_year', $yearInt)->whereIn('period_month', $monthsInPeriod)])
                    ->whereNotNull('invitation_accepted_at')
                    ->get();

                $hrService = app(\App\Services\Employee\EmployeeHrService::class);
                foreach ($hrContracts as $contract) {
                    $safeContractNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $contract->contract_number);

                    if (empty($contract->contract_pdf_path) || !Storage::disk('local')->exists((string) $contract->contract_pdf_path)) {
                        $hrService->generateAndStoreContractPdf($contract);
                        $contract->refresh();
                    }
                    $cPath = (string) $contract->contract_pdf_path;
                    if ($cPath !== '' && !str_contains($cPath, '..') && str_starts_with($cPath, 'buchhaltung/contracts/') && Storage::disk('local')->exists($cPath) && !is_link(Storage::disk('local')->path($cPath))) {
                        $zip->addFile(Storage::disk('local')->path($cPath), "Personal_und_Lohn/Arbeitsvertrag_{$safeContractNo}.pdf");
                        $copyCount++;
                    }

                    if (empty($contract->questionnaire_pdf_path) || !Storage::disk('local')->exists((string) $contract->questionnaire_pdf_path)) {
                        $hrService->generateAndStoreQuestionnairePdf($contract);
                        $contract->refresh();
                    }
                    $qPath = (string) $contract->questionnaire_pdf_path;
                    if ($qPath !== '' && !str_contains($qPath, '..') && str_starts_with($qPath, 'buchhaltung/contracts/') && Storage::disk('local')->exists($qPath) && !is_link(Storage::disk('local')->path($qPath))) {
                        $zip->addFile(Storage::disk('local')->path($qPath), "Personal_und_Lohn/Personalbogen_{$safeContractNo}.pdf");
                        $copyCount++;
                    }

                    foreach ($monthsInPeriod as $mInt) {
                        $mStrPadded = str_pad((string) $mInt, 2, '0', STR_PAD_LEFT);
                        $tPath = $hrService->generateAndStoreTimesheetPdf($contract, $yearInt, $mInt);
                        if ($tPath && !str_contains($tPath, '..') && str_starts_with($tPath, 'buchhaltung/receipts/') && Storage::disk('local')->exists($tPath) && !is_link(Storage::disk('local')->path($tPath))) {
                            $zip->addFile(Storage::disk('local')->path($tPath), "Personal_und_Lohn/Stundenzettel_{$safeContractNo}_{$yearStr}_{$mStrPadded}.pdf");
                            $copyCount++;
                        }
                    }

                    foreach ($contract->payslips as $payslip) {
                        $payPath = (string) $payslip->effective_pdf_path;
                        if ($payPath === '' || !Storage::disk('local')->exists($payPath)) {
                            $hrService->generateAndStorePayslipPdf($payslip);
                            $payslip->refresh();
                            $payPath = (string) $payslip->effective_pdf_path;
                        }
                        if ($payPath !== '' && !str_contains($payPath, '..') && str_starts_with($payPath, 'buchhaltung/receipts/') && Storage::disk('local')->exists($payPath) && !is_link(Storage::disk('local')->path($payPath))) {
                            $safePayslipNo = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $payslip->payslip_number);
                            $zip->addFile(Storage::disk('local')->path($payPath), "Personal_und_Lohn/Lohnabrechnung_{$safePayslipNo}.pdf");
                            $copyCount++;
                        }
                    }
                }
            }

            $zip->close();
        }

        \Illuminate\Support\Facades\File::deleteDirectory($tempDir);

        return [
            'zip_name' => $zipName,
            'zip_path' => $zipPath,
            'temp_dir' => $tempDir,
            'copy_count' => $copyCount,
        ];
    }

    /**
     * Exakte Einkommensteuer- & Gewerbesteuer-Berechnung nach § 32a EStG & § 35 EStG (Einzelunternehmen).
     * Gründungszuschuss (§ 3 Nr. 2a EStG) ist steuerfrei und unterliegt NICHT dem Progressionsvorbehalt.
     */
    public function calculateGermanIncomeTax(
        float $zvE,
        int $year = 2026,
        float $gewerbeGewinn = 0.0,
        float $hebesatz = 380.0
    ): array {
        $zvERounded = max(0.0, floor($zvE));

        // § 32a EStG Grundtarif (2026 vs. 2025/Vorjahre)
        $gfb = $year >= 2026 ? 12348.0 : 12096.0;
        $z2End = $year >= 2026 ? 17799.0 : 17443.0;
        $z3End = $year >= 2026 ? 69878.0 : 68480.0;
        $z4End = 277825.0;

        if ($zvERounded <= $gfb) {
            $estTarif = 0.0;
        } elseif ($zvERounded <= $z2End) {
            $y = ($zvERounded - $gfb) / 10000.0;
            $estTarif = floor((914.51 * $y + 1400.0) * $y);
        } elseif ($zvERounded <= $z3End) {
            $z = ($zvERounded - $z2End) / 10000.0;
            $estTarif = floor((173.10 * $z + 2397.0) * $z + 1034.87);
        } elseif ($zvERounded <= $z4End) {
            $estTarif = floor(0.42 * $zvERounded - 11135.63);
        } else {
            $estTarif = floor(0.45 * $zvERounded - 19470.38);
        }

        // Gewerbesteuer (§ 11 GewStG: 24.500 € Freibetrag für Einzelunternehmen, 3,5 % Messzahl)
        $abgerundeterGewinn = max(0.0, floor($gewerbeGewinn / 100.0) * 100.0);
        $gewerbeErtrag = max(0.0, $abgerundeterGewinn - 24500.0);
        $messbetrag = round($gewerbeErtrag * 0.035, 2);
        $gewerbesteuer = round($messbetrag * ($hebesatz / 100.0), 2);

        // § 35 EStG Steuerermäßigung: bis zum 4,0-fachen Messbetrag, max. tatsächlich gezahlte GewSt & tarifliche ESt
        $estAnrechnung = min($estTarif, $gewerbesteuer, round($messbetrag * 4.0, 2));
        $estNetto = max(0.0, round($estTarif - $estAnrechnung, 2));

        // Solidaritätszuschlag (Freigrenze 2026: 19.950 € ESt)
        $soliFreigrenze = $year >= 2026 ? 19950.0 : 18130.0;
        $soli = 0.0;
        if ($estNetto > $soliFreigrenze) {
            $soli = round(min($estNetto * 0.055, ($estNetto - $soliFreigrenze) * 0.119), 2);
        }

        $totalTax = round($estNetto + $gewerbesteuer + $soli, 2);
        $effectiveRate = $zvERounded > 0 ? round(($totalTax / $zvERounded) * 100.0, 2) : 0.0;

        return [
            'zvE' => $zvERounded,
            'grundfreibetrag' => $gfb,
            'est_tarif' => $estTarif,
            'gewerbe_freibetrag' => 24500.0,
            'gewerbe_ertrag' => $gewerbeErtrag,
            'gewerbe_messbetrag' => $messbetrag,
            'gewerbesteuer' => $gewerbesteuer,
            'est_anrechnung_35' => $estAnrechnung,
            'est_netto' => $estNetto,
            'soli' => $soli,
            'total_tax' => $totalTax,
            'effective_rate' => $effectiveRate,
        ];
    }

    /**
     * Prüft, ob eine gebuchte Sonderausgabe ein abnutzbares Anlagegut (> 800 € netto, § 7 EStG) ist.
     */
    public function isCapitalAssetIssue(AccountingSpecialIssue $issue, float $netCost): bool
    {
        if (!$issue->is_business || (float) $issue->amount >= 0 || $netCost <= 800.00) {
            return false;
        }

        $cat = mb_strtolower((string) $issue->category);
        $title = mb_strtolower((string) $issue->title);

        if (in_array($cat, ['rohmaterial', 'wareneinkauf', 'verpackungen', 'werbung & marketing', 'beratung & gründung'], true)) {
            return false;
        }

        if (\Illuminate\Support\Str::contains($title, ['schulung', 'seminar', 'kurs', 'beratung', 'credits', 'google one'])) {
            return false;
        }

        return true;
    }

    /**
     * Ermittelt automatisch Anlagevermögen (> 800 € netto -> lineare AfA) und GWG (250,01 € - 800,00 € netto)
     * aus den real gebuchten gewerblichen Sonderausgaben.
     */
    public function getFixedAssetsAndDepreciation($adminId, int $year, ?int $upToMonth = null): array
    {
        $resolvedAdminId = $this->resolveMandantAdminId($adminId);
        if (!$resolvedAdminId) {
            return [
                'assets' => [],
                'gwg_items' => [],
                'annual_afa' => 0.0,
                'monthly_afa' => 0.0,
                'total_book_value' => 0.0,
                'gwg_total_net' => 0.0,
            ];
        }

        $targetMonth = $upToMonth ? max(1, min(12, $upToMonth)) : 12;
        $cutoffDate = Carbon::createFromDate($year, $targetMonth, 1)->endOfMonth();
        $isSmallBusinessYear = app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($year);

        $issues = AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
            ->where('is_business', true)
            ->where('amount', '<', 0)
            ->whereDate('execution_date', '<=', $cutoffDate->toDateString())
            ->orderBy('execution_date', 'desc')
            ->get();

        $assets = [];
        $gwgItems = [];
        $annualAfaSum = 0.0;
        $monthlyAfaCurrent = 0.0;
        $totalBookValue = 0.0;
        $gwgTotalNet = 0.0;

        foreach ($issues as $issue) {
            $gross = abs((float) $issue->amount);
            $rate = (!$isSmallBusinessYear && self::isEligibleGermanInputVatRate($issue->tax_rate))
                ? (int) round((float) $issue->tax_rate)
                : 0;
            $net = $rate > 0 ? round($gross / (1 + ($rate / 100.0)), 2) : round($gross, 2);
            $vat = round($gross - $net, 2);

            if ($this->isCapitalAssetIssue($issue, $net)) {
                $afaYears = 5; // Standard-Nutzungsdauer Laser/Werkstattmaschinen lt. AfA-Tabelle (60 Monate)
                $totalMonths = $afaYears * 12;
                $monthlyAfa = $net / $totalMonths;

                $purchaseMonthStart = Carbon::parse($issue->execution_date)->startOfMonth();
                $endMonth = $purchaseMonthStart->copy()->addMonths($totalMonths - 1)->endOfMonth();

                // AfA für das gewählte Kalenderjahr $year (pro rata temporis)
                $yearAfaMonths = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $mStart = Carbon::createFromDate($year, $m, 1)->startOfMonth();
                    if ($mStart->gte($purchaseMonthStart) && $mStart->lte($endMonth)) {
                        $yearAfaMonths++;
                    }
                }
                $yearAfa = round($yearAfaMonths * $monthlyAfa, 2);

                // Ist das Anlagegut im gewählten Monat $targetMonth/$year aktiv in Abschreibung?
                $selectedMonthStart = Carbon::createFromDate($year, $targetMonth, 1)->startOfMonth();
                $isActiveInMonth = $selectedMonthStart->gte($purchaseMonthStart) && $selectedMonthStart->lte($endMonth);
                $currentMonthAfa = $isActiveInMonth ? round($monthlyAfa, 2) : 0.0;

                // Kumulierte AfA bis einschl. $selectedMonthStart
                $elapsedMonths = 0;
                if ($selectedMonthStart->gte($purchaseMonthStart)) {
                    $elapsedMonths = min($totalMonths, (int) $purchaseMonthStart->diffInMonths($selectedMonthStart) + 1);
                }
                $accumulatedAfa = min($net, round($elapsedMonths * $monthlyAfa, 2));
                $bookValue = max(0.0, round($net - $accumulatedAfa, 2));

                $annualAfaSum += $yearAfa;
                $monthlyAfaCurrent += $currentMonthAfa;
                $totalBookValue += $bookValue;

                $assets[] = [
                    'id' => $issue->id,
                    'title' => $issue->title,
                    'purchase_date' => $issue->execution_date->format('d.m.Y'),
                    'end_date' => $endMonth->format('m/Y'),
                    'gross_cost' => $gross,
                    'net_cost' => $net,
                    'vat_amount' => $vat,
                    'afa_years' => $afaYears,
                    'monthly_afa' => round($monthlyAfa, 2),
                    'year_afa' => $yearAfa,
                    'accumulated_afa' => $accumulatedAfa,
                    'book_value' => $bookValue,
                    'progress_percent' => $net > 0 ? min(100, (int) round(($accumulatedAfa / $net) * 100)) : 100,
                ];
            } elseif ((int) $issue->execution_date->year === $year && $net >= 150.00 && $net <= 800.00) {
                $cat = mb_strtolower((string) $issue->category);
                $titleLower = mb_strtolower((string) $issue->title);
                if (
                    in_array($cat, ['arbeitsmaterial', 'technik', 'maschinen'], true) &&
                    !\Illuminate\Support\Str::contains($titleLower, ['visitenkarten', 'aufkleber', 'umschläge', 'karton'])
                ) {
                    $gwgTotalNet += $net;
                    $gwgItems[] = [
                        'id' => $issue->id,
                        'title' => $issue->title,
                        'purchase_date' => $issue->execution_date->format('d.m.Y'),
                        'gross_cost' => $gross,
                        'net_cost' => $net,
                        'vat_amount' => $vat,
                    ];
                }
            }
        }

        return [
            'assets' => $assets,
            'gwg_items' => $gwgItems,
            'annual_afa' => round($annualAfaSum, 2),
            'monthly_afa' => round($monthlyAfaCurrent, 2),
            'total_book_value' => round($totalBookValue, 2),
            'gwg_total_net' => round($gwgTotalNet, 2),
        ];
    }

    public function getPayoutPlannerConfig(): array
    {
        $defaults = [
            'safety_buffer' => 1500.00,
            'private_living_allowance' => 600.00,
            'reserve_afa_replacement' => true,
            'smooth_annual_fix_costs' => true,
            'sim_enabled' => false,
            'sim_monthly_gross_revenue' => 3800.00,
            'sim_cogs_percent' => 25.0,
        ];

        $stored = shop_setting('accounting_payout_config');
        if (is_string($stored)) {
            $decoded = json_decode($stored, true);
            $stored = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($stored)) {
            return $defaults;
        }

        return [
            'safety_buffer' => max(0.0, round((float) ($stored['safety_buffer'] ?? $defaults['safety_buffer']), 2)),
            'private_living_allowance' => max(0.0, round((float) ($stored['private_living_allowance'] ?? $defaults['private_living_allowance']), 2)),
            'reserve_afa_replacement' => (bool) ($stored['reserve_afa_replacement'] ?? $defaults['reserve_afa_replacement']),
            'smooth_annual_fix_costs' => (bool) ($stored['smooth_annual_fix_costs'] ?? $defaults['smooth_annual_fix_costs']),
            'sim_enabled' => (bool) ($stored['sim_enabled'] ?? $defaults['sim_enabled']),
            'sim_monthly_gross_revenue' => max(0.0, round((float) ($stored['sim_monthly_gross_revenue'] ?? $defaults['sim_monthly_gross_revenue']), 2)),
            'sim_cogs_percent' => max(0.0, min(95.0, round((float) ($stored['sim_cogs_percent'] ?? $defaults['sim_cogs_percent']), 1))),
        ];
    }

    public function savePayoutPlannerConfig(array $input): array
    {
        $merged = array_merge($this->getPayoutPlannerConfig(), $input);
        $clean = [
            'safety_buffer' => max(0.0, min(100000.0, round((float) $merged['safety_buffer'], 2))),
            'private_living_allowance' => max(0.0, min(20000.0, round((float) $merged['private_living_allowance'], 2))),
            'reserve_afa_replacement' => (bool) $merged['reserve_afa_replacement'],
            'smooth_annual_fix_costs' => (bool) $merged['smooth_annual_fix_costs'],
            'sim_enabled' => (bool) $merged['sim_enabled'],
            'sim_monthly_gross_revenue' => max(0.0, min(500000.0, round((float) $merged['sim_monthly_gross_revenue'], 2))),
            'sim_cogs_percent' => max(0.0, min(95.0, round((float) $merged['sim_cogs_percent'], 1))),
        ];

        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'accounting_payout_config'],
            ['value' => json_encode($clean)]
        );
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        return $clean;
    }

    /**
     * CFO-Engine: Berechnet das dynamisch auszahlbare Monats-Gehalt (sichere Privatentnahme),
     * alle 5 Rücklagen-Töpfe, den privaten Haushalts-Spiegel (inkl. Wegfall Gründungszuschuss ab 05/2027)
     * sowie Fortschritt, Zieldatum & § 7g IAB-Hebel aller Reinvestitions-Ziele.
     */
    public function getDynamicSalaryAndReinvestmentData(
        $adminId,
        int $month,
        int $year,
        ?array $overrideConfig = null
    ): array {
        $resolvedAdminId = $this->resolveMandantAdminId($adminId);
        $month = max(1, min(12, $month));
        $year = max(2020, min(2100, $year));
        $config = $overrideConfig ? array_merge($this->getPayoutPlannerConfig(), $overrideConfig) : $this->getPayoutPlannerConfig();

        $isSmallBusinessYear = app(\App\Services\Tax\TaxSettingService::class)->isSmallBusinessForYear($year);
        $defaultVatRate = $isSmallBusinessYear ? 0.0 : (float) shop_setting('default_tax_rate', 19.0);
        $vatFactor = $defaultVatRate > 0 ? (1.0 + ($defaultVatRate / 100.0)) : 1.0;

        // 1. Anlagevermögen & reale AfA für das gewählte Jahr/Monat
        $depreciation = $this->getFixedAssetsAndDepreciation($resolvedAdminId, $year, $month);

        // 2. Fixkosten analysieren (Gewerbe vs. Privat, geglättet vs. fällig)
        $groups = $resolvedAdminId
            ? AccountingGroup::with('items')->where('admin_id', $resolvedAdminId)->get()
            : collect();

        $bizFixDueNet = 0.0;
        $bizFixDueVat = 0.0;
        $bizFixSmoothedNet = 0.0;
        $bizFixSmoothedVat = 0.0;
        $bizFixAnnualNet = 0.0;

        $privIncomeDue = 0.0;
        $privIncomePostGz = 0.0;
        $privFixDue = 0.0;
        $privFixSmoothed = 0.0;
        $rentalIncomeAnnual = 0.0;

        foreach ($groups as $group) {
            $isIncome = ($group->type === 'income');
            foreach ($group->items as $item) {
                $interval = max(1, (int) $item->interval_months);
                $absAmount = abs((float) $item->amount);
                $isDue = $this->isDueInMonth($item, $month, $year);

                if ($item->is_business) {
                    if (!$isIncome) {
                        $rate = (!$isSmallBusinessYear && self::isEligibleGermanInputVatRate($item->tax_rate))
                            ? (int) round((float) $item->tax_rate)
                            : 0;
                        $itemNet = $rate > 0 ? ($absAmount / (1.0 + ($rate / 100.0))) : $absAmount;
                        $itemVat = $absAmount - $itemNet;

                        if ($isDue) {
                            $bizFixDueNet += $itemNet;
                            $bizFixDueVat += $itemVat;
                        }

                        // Ist der Vertrag im gewählten Monat grundsätzlich aktiv?
                        $selectedStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                        $itemStart = Carbon::parse($item->first_payment_date)->startOfMonth();
                        $itemEnd = $item->last_payment_date ? Carbon::parse($item->last_payment_date)->endOfMonth() : null;
                        if ($selectedStart->gte($itemStart) && (!$itemEnd || $selectedStart->lte($itemEnd))) {
                            $bizFixSmoothedNet += ($itemNet / $interval);
                            $bizFixSmoothedVat += ($itemVat / $interval);
                        }

                        for ($m = 1; $m <= 12; $m++) {
                            if ($this->isDueInMonth($item, $m, $year)) {
                                $bizFixAnnualNet += $itemNet;
                            }
                        }
                    }
                } else {
                    if ($isIncome) {
                        if ($isDue) {
                            $privIncomeDue += $absAmount;
                        }
                        // Post-Gründungszuschuss Einkommen (z. B. Mai 2027)
                        if ($this->isDueInMonth($item, 6, max(2027, $year))) {
                            $privIncomePostGz += $absAmount / $interval;
                        }
                        $nameLower = mb_strtolower($item->name);
                        if (\Illuminate\Support\Str::contains($nameLower, ['miete'])) {
                            for ($m = 1; $m <= 12; $m++) {
                                if ($this->isDueInMonth($item, $m, $year)) {
                                    $rentalIncomeAnnual += $absAmount;
                                }
                            }
                        }
                    } else {
                        if ($isDue) {
                            $privFixDue += $absAmount;
                        }
                        $selectedStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                        $itemStart = Carbon::parse($item->first_payment_date)->startOfMonth();
                        $itemEnd = $item->last_payment_date ? Carbon::parse($item->last_payment_date)->endOfMonth() : null;
                        if ($selectedStart->gte($itemStart) && (!$itemEnd || $selectedStart->lte($itemEnd))) {
                            $privFixSmoothed += ($absAmount / $interval);
                        }
                    }
                }
            }
        }

        // 3. Echte Umsätze & laufende Sonderausgaben im Monat (oder Simulationsmodus)
        $simEnabled = (bool) $config['sim_enabled'];
        if ($simEnabled) {
            $grossRevenue = (float) $config['sim_monthly_gross_revenue'];
            $netRevenue = $vatFactor > 1.0 ? round($grossRevenue / $vatFactor, 2) : round($grossRevenue, 2);
            $salesVat = round($grossRevenue - $netRevenue, 2);

            $variableOperatingNet = round($netRevenue * ((float) $config['sim_cogs_percent'] / 100.0), 2);
            $variableInputVat = $vatFactor > 1.0 ? round($variableOperatingNet * ($defaultVatRate / 100.0), 2) : 0.0;
            $capitalAssetVatThisMonth = 0.0;

            $projectedAnnualNetRevenue = $netRevenue * 12.0;
            $projectedAnnualVariableNet = $variableOperatingNet * 12.0;
        } else {
            $monthInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
                ->whereMonth('invoice_date', $month)
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get(['type', 'total', 'tax_amount']);

            $grossRevenue = round($monthInvoices->sum(fn($i) => self::signedInvoiceCents($i, false)) / 100.0, 2);
            $netRevenue = round($monthInvoices->sum(fn($i) => self::signedInvoiceCents($i, true)) / 100.0, 2);
            $salesVat = max(0.0, round($grossRevenue - $netRevenue, 2));

            $monthSpecials = $resolvedAdminId
                ? AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
                    ->where('is_business', true)
                    ->whereYear('execution_date', $year)
                    ->whereMonth('execution_date', $month)
                    ->get()
                : collect();

            $variableOperatingNet = 0.0;
            $variableInputVat = 0.0;
            $capitalAssetVatThisMonth = 0.0;

            foreach ($monthSpecials as $sp) {
                $amt = (float) $sp->amount;
                $absGross = abs($amt);
                $rate = (!$isSmallBusinessYear && self::isEligibleGermanInputVatRate($sp->tax_rate))
                    ? (int) round((float) $sp->tax_rate)
                    : 0;
                $absNet = $rate > 0 ? ($absGross / (1.0 + ($rate / 100.0))) : $absGross;
                $absVat = $absGross - $absNet;

                if ($amt < 0 && $this->isCapitalAssetIssue($sp, $absNet)) {
                    // Großanlage (> 800 € netto): Kein operativer Sofortaufwand (läuft über AfA), aber Vorsteuer ist abziehbar!
                    $capitalAssetVatThisMonth += $absVat;
                } else {
                    $sign = $amt < 0 ? 1.0 : -1.0; // Ausgaben erhöhen Kosten, Rückerstattungen mindern Kosten
                    $variableOperatingNet += ($sign * $absNet);
                    $variableInputVat += ($sign * $absVat);
                }
            }
            $variableOperatingNet = max(0.0, round($variableOperatingNet, 2));
            $variableInputVat = max(0.0, round($variableInputVat, 2));

            // Jahres-Ist/Hochrechnung für reale Einkommensteuer-Rücklage
            $yearInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get(['type', 'total', 'tax_amount']);
            $projectedAnnualNetRevenue = round($yearInvoices->sum(fn($i) => self::signedInvoiceCents($i, true)) / 100.0, 2);

            $yearSpecials = $resolvedAdminId
                ? AccountingSpecialIssue::where('admin_id', $resolvedAdminId)
                    ->where('is_business', true)
                    ->whereYear('execution_date', $year)
                    ->get()
                : collect();
            $projectedAnnualVariableNet = 0.0;
            foreach ($yearSpecials as $ysp) {
                $amt = (float) $ysp->amount;
                $absGross = abs($amt);
                $rate = (!$isSmallBusinessYear && self::isEligibleGermanInputVatRate($ysp->tax_rate))
                    ? (int) round((float) $ysp->tax_rate)
                    : 0;
                $absNet = $rate > 0 ? ($absGross / (1.0 + ($rate / 100.0))) : $absGross;
                if ($amt < 0 && $this->isCapitalAssetIssue($ysp, $absNet)) {
                    continue;
                }
                $projectedAnnualVariableNet += ($amt < 0 ? $absNet : -$absNet);
            }
            $projectedAnnualVariableNet = max(0.0, round($projectedAnnualVariableNet, 2));
        }

        $effectiveFixNet = $config['smooth_annual_fix_costs'] ? round($bizFixSmoothedNet, 2) : round($bizFixDueNet, 2);
        $effectiveFixVat = $config['smooth_annual_fix_costs'] ? round($bizFixSmoothedVat, 2) : round($bizFixDueVat, 2);

        // Topf 1: Umsatzsteuer-Tresor
        $totalInputVatMonth = round($effectiveFixVat + $variableInputVat + $capitalAssetVatThisMonth, 2);
        $vatPot = max(0.0, round($salesVat - $totalInputVatMonth, 2));
        $vatRefundCredit = max(0.0, round($totalInputVatMonth - $salesVat, 2));

        // Topf 2: Laufende Betriebskosten (Netto)
        $operatingPot = round($effectiveFixNet + $variableOperatingNet, 2);

        // Operativer Überschuss vor Ertragsteuern, Puffer & Reinvestitionen
        $operatingSurplus = round($netRevenue - $operatingPot, 2);

        // 4. Reinvestitions-Pläne laden (für § 7g IAB & Topf 5)
        $plansCollection = ($resolvedAdminId && \Illuminate\Support\Facades\Schema::hasTable('accounting_investment_plans'))
            ? \App\Models\Accounting\AccountingInvestmentPlan::where('admin_id', $resolvedAdminId)
                ->orderBy('is_active', 'desc')
                ->orderBy('priority')
                ->orderBy('created_at')
                ->get()
            : collect();

        $totalIabDeduction = 0.0;
        foreach ($plansCollection as $p) {
            if ($p->is_active && !$p->completed_special_issue_id && $p->use_iab && (int) $p->afa_years > 0) {
                $totalIabDeduction += round($p->target_amount_net * 0.50, 2);
            }
        }

        // Topf 3: Einkommensteuer-Rücklage (§ 32a EStG + § 35 EStG, ohne Gründungszuschuss!)
        $annualEuerProfitWithoutIab = round($projectedAnnualNetRevenue - $bizFixAnnualNet - $projectedAnnualVariableNet - $depreciation['annual_afa'], 2);
        $annualEuerProfit = round($annualEuerProfitWithoutIab - $totalIabDeduction, 2);

        // Konservative Vermietungs-Einkünfte (Miete abzgl. geschätzter Darlehenszinsen/Gebäude-AfA ~ 50 %)
        $netRentalTaxable = round($rentalIncomeAnnual * 0.45, 2);
        $zvEWithoutIab = max(0.0, $annualEuerProfitWithoutIab + $netRentalTaxable);
        $zvEWithIab = max(0.0, $annualEuerProfit + $netRentalTaxable);

        $taxWithoutIab = $this->calculateGermanIncomeTax($zvEWithoutIab, $year, max(0.0, $annualEuerProfitWithoutIab));
        $taxResult = $this->calculateGermanIncomeTax($zvEWithIab, $year, max(0.0, $annualEuerProfit));
        $iabTotalTaxSaving = max(0.0, round($taxWithoutIab['total_tax'] - $taxResult['total_tax'], 2));

        // Nur den auf den Gewerbebetrieb entfallenden Steueranteil als Firmen-Rücklage blocken:
        $taxOnlyRental = $this->calculateGermanIncomeTax(max(0.0, $netRentalTaxable), $year, 0.0);
        $businessAnnualIncomeTax = max(0.0, round($taxResult['total_tax'] - $taxOnlyRental['total_tax'], 2));
        $incomeTaxPot = $operatingSurplus > 0 ? round($businessAnnualIncomeTax / 12.0, 2) : 0.0;

        // Topf 4: Eiserner Kontopuffer (Prüfung gegen FYRST-Geschäftskonto falls angelegt)
        $businessAccounts = ($resolvedAdminId && class_exists(\App\Models\Accounting\AccountingBankAccount::class))
            ? \App\Models\Accounting\AccountingBankAccount::where('admin_id', $resolvedAdminId)
                ->where('is_business', true)
                ->where('is_active_for_analysis', true)
                ->get()
            : collect();
        $hasBusinessBank = $businessAccounts->isNotEmpty();
        $businessBankBalance = $hasBusinessBank ? round((float) $businessAccounts->sum('balance'), 2) : null;
        $safetyBufferTarget = (float) $config['safety_buffer'];

        $surplusAfterTax = round($operatingSurplus - $incomeTaxPot, 2);
        $bufferPot = 0.0;
        if ($hasBusinessBank && $businessBankBalance < $safetyBufferTarget && $surplusAfterTax > 0) {
            $bufferPot = round(min($surplusAfterTax, $safetyBufferTarget - $businessBankBalance), 2);
        }

        $surplusBeforeReinvest = round($surplusAfterTax - $bufferPot, 2);

        // Topf 5: Kalkulatorische Reinvestitions- & AfA-Erhaltungs-Rücklage
        $afaReplacementRate = $config['reserve_afa_replacement'] ? $depreciation['monthly_afa'] : 0.0;
        $selectedMonthStart = Carbon::createFromDate($year, $month, 1)->startOfMonth();

        $plansOutput = [];
        $activePlansMonthlySum = 0.0;

        foreach ($plansCollection as $plan) {
            $targetGross = abs((float) $plan->target_amount_gross);
            $planRate = (!$isSmallBusinessYear && self::isEligibleGermanInputVatRate($plan->tax_rate))
                ? (int) $plan->tax_rate
                : 0;
            $targetNet = $planRate > 0 ? round($targetGross / (1.0 + ($planRate / 100.0)), 2) : round($targetGross, 2);
            $vatOnPurchase = round($targetGross - $targetNet, 2);
            $savedInitial = max(0.0, (float) $plan->saved_initial);

            $planStart = Carbon::parse($plan->start_date)->startOfMonth();
            $isStarted = $selectedMonthStart->gte($planStart);
            $isCompleted = !empty($plan->completed_special_issue_id);

            // Effektive Monatsrate des Plans (für Prognose & aktuellen Monat)
            if ($plan->allocation_mode === 'profit_percent') {
                $pct = max(0.0, (float) $plan->profit_percent);
                $monthlyContrib = max(0.0, round(max(0.0, $surplusBeforeReinvest - $afaReplacementRate) * ($pct / 100.0), 2));
            } else {
                $monthlyContrib = max(0.0, round((float) $plan->monthly_rate, 2));
            }

            // Bisher angespart bis zum gewählten Monat
            if ($isCompleted) {
                $accumulated = $targetNet;
            } elseif ($selectedMonthStart->lt($planStart)) {
                $accumulated = min($targetNet, $savedInitial);
            } else {
                $elapsedMonths = (int) $planStart->diffInMonths($selectedMonthStart) + 1;
                $accumulated = min($targetNet, round($savedInitial + ($elapsedMonths * $monthlyContrib), 2));
            }

            $remainingNet = max(0.0, round($targetNet - $accumulated, 2));
            $progressPct = $targetNet > 0 ? min(100, (int) round(($accumulated / $targetNet) * 100)) : 100;

            // Beitrag im aktuell gewählten Monat (nur wenn aktiv, gestartet und noch nicht 100 % voll)
            $thisMonthContribution = 0.0;
            if ($plan->is_active && !$isCompleted && $isStarted && $remainingNet > 0) {
                $prevAccumulated = max($savedInitial, $accumulated - $monthlyContrib);
                $thisMonthContribution = min($monthlyContrib, max(0.0, round($targetNet - $prevAccumulated, 2)));
                $activePlansMonthlySum += $thisMonthContribution;
            } elseif ($plan->is_active && !$isCompleted && !$isStarted) {
                // Für Zukunfts-Pläne (z. B. Start 01/2027 bei Betrachtung im Simulator) optional im Simulator vormerken:
                if ($simEnabled) {
                    $thisMonthContribution = min($monthlyContrib, $targetNet);
                    $activePlansMonthlySum += $thisMonthContribution;
                }
            }

            // Wann ist das Ziel erreicht?
            $monthsRemaining = null;
            $estimatedReachLabel = 'Pausiert';
            $estimatedReachDate = null;
            if ($isCompleted) {
                $monthsRemaining = 0;
                $estimatedReachLabel = 'Angeschafft';
            } elseif ($remainingNet <= 0.0) {
                $monthsRemaining = 0;
                $estimatedReachLabel = 'Ziel erreicht';
            } elseif ($monthlyContrib > 0.0) {
                $baseStartForFuture = $selectedMonthStart->lt($planStart) ? $planStart->copy() : $selectedMonthStart->copy()->addMonth();
                $monthsNeededFromBase = (int) ceil($remainingNet / $monthlyContrib);
                $reachCarbon = $baseStartForFuture->copy()->addMonths(max(0, $monthsNeededFromBase - 1));
                $monthsRemaining = (int) max(1, $selectedMonthStart->diffInMonths($reachCarbon));
                $estimatedReachLabel = $reachCarbon->locale('de')->isoFormat('MMM YYYY');
                $estimatedReachDate = $reachCarbon->format('Y-m-d');
            } else {
                $estimatedReachLabel = $plan->allocation_mode === 'profit_percent' ? 'Wartet auf Gewinn' : '0 € Rate';
            }

            // Wunsch-Zieldatum Check
            $requiredRateForTarget = null;
            $onTrack = true;
            if ($plan->target_date && !$isCompleted && $remainingNet > 0) {
                $targetCarbon = Carbon::parse($plan->target_date)->endOfMonth();
                $monthsUntilTarget = max(1, (int) $selectedMonthStart->diffInMonths($targetCarbon->copy()->startOfMonth(), false));
                if ($monthsUntilTarget > 0) {
                    $requiredRateForTarget = round($remainingNet / $monthsUntilTarget, 2);
                    if ($estimatedReachDate && Carbon::parse($estimatedReachDate)->gt($targetCarbon)) {
                        $onTrack = false;
                    }
                } else {
                    $requiredRateForTarget = $remainingNet;
                    $onTrack = false;
                }
            }

            $iab50 = ((int) $plan->afa_years > 0) ? round($targetNet * 0.50, 2) : 0.0;
            $futureMonthlyAfa = ((int) $plan->afa_years > 0) ? round($targetNet / ($plan->afa_years * 12), 2) : $targetNet;

            $plansOutput[] = [
                'id' => $plan->id,
                'title' => $plan->title,
                'target_gross' => $targetGross,
                'target_net' => $targetNet,
                'tax_rate' => $planRate,
                'vat_refund_on_purchase' => $vatOnPurchase,
                'saved_initial' => $savedInitial,
                'allocation_mode' => $plan->allocation_mode,
                'monthly_rate' => (float) $plan->monthly_rate,
                'profit_percent' => (float) $plan->profit_percent,
                'effective_monthly_rate' => $monthlyContrib,
                'this_month_contribution' => round($thisMonthContribution, 2),
                'accumulated_saved' => $accumulated,
                'remaining_net' => $remainingNet,
                'progress_percent' => $progressPct,
                'start_date' => $plan->start_date->format('Y-m-d'),
                'start_label' => $plan->start_date->format('m/Y'),
                'target_date' => $plan->target_date?->format('Y-m-d'),
                'target_label' => $plan->target_date?->format('m/Y'),
                'months_remaining' => $monthsRemaining,
                'estimated_reach_label' => $estimatedReachLabel,
                'required_rate_for_target' => $requiredRateForTarget,
                'on_track' => $onTrack,
                'afa_years' => (int) $plan->afa_years,
                'future_monthly_afa' => $futureMonthlyAfa,
                'use_iab' => (bool) $plan->use_iab,
                'iab_50_amount' => $iab50,
                'is_active' => (bool) $plan->is_active,
                'is_completed' => $isCompleted,
                'priority' => (int) $plan->priority,
                'note' => $plan->note,
            ];
        }

        $plannedReinvestmentTotal = round($afaReplacementRate + $activePlansMonthlySum, 2);
        // Effektive Reservierung aus dem Monatsüberschuss (gedeckelt auf vorhandenen Überschuss im Ist, plus Ausweis der Ziel-Sparrate)
        $reinvestmentPot = $surplusBeforeReinvest > 0
            ? round(min($surplusBeforeReinvest, $plannedReinvestmentTotal), 2)
            : 0.0;

        // 5. DEIN SICHER AUSZAHLBARES MONATS-GEHALT (Freie Privatentnahme)
        $safeMonthlySalary = max(0.0, round($surplusBeforeReinvest - $plannedReinvestmentTotal, 2));
        $unallocatedDeficit = $surplusBeforeReinvest < $plannedReinvestmentTotal
            ? round($plannedReinvestmentTotal - max(0.0, $surplusBeforeReinvest), 2)
            : 0.0;

        // 6. Privater Haushalts-Spiegel & Post-Gründungszuschuss-Radar (ab Mai 2027)
        $livingAllowance = (float) $config['private_living_allowance'];
        $privOutTotalNow = round($privFixSmoothed + $livingAllowance, 2);
        $privBalanceNow = round($privIncomeDue - $privOutTotalNow, 2);
        $neededFromShopNow = max(0.0, round(-$privBalanceNow, 2));

        // Ab Mai 2027 (nach Auslaufen der 2.106,30 € Gründungszuschuss Phase 1 am 30.04.2027)
        $postGzGap = max(0.0, round($privOutTotalNow - $privIncomePostGz, 2));

        // Rückwärts-Auflösung: Welcher Brutto-Monatsumsatz im Shop deckt (Post-GZ-Gehalt + Fixkosten + Reinvestitionen + ESt + USt)?
        $cogsRatio = ((float) $config['sim_cogs_percent']) / 100.0;
        $marginRatio = max(0.15, 1.0 - $cogsRatio);
        // Ziel-Netto-Überschuss vor Steuern (inkl. ca. 18 % Grenz-ESt-Puffer oberhalb Grundfreibetrag)
        $targetNetBeforeTax = ($postGzGap + $plannedReinvestmentTotal) * 1.18 + $bizFixSmoothedNet;
        $requiredNetSalesPostGz = round($targetNetBeforeTax / $marginRatio, 2);
        $requiredGrossSalesPostGz = round($requiredNetSalesPostGz * $vatFactor, 2);

        return [
            'config' => $config,
            'is_simulation' => $simEnabled,
            'period_label' => Carbon::createFromDate($year, $month, 1)->locale('de')->isoFormat('MMMM YYYY'),
            'gross_revenue' => $grossRevenue,
            'net_revenue' => $netRevenue,
            'pots' => [
                'vat_pot' => $vatPot,
                'vat_refund_credit' => $vatRefundCredit,
                'operating_pot' => $operatingPot,
                'fix_costs_net' => $effectiveFixNet,
                'variable_costs_net' => $variableOperatingNet,
                'income_tax_pot' => $incomeTaxPot,
                'buffer_pot' => $bufferPot,
                'reinvestment_pot' => $reinvestmentPot,
                'planned_reinvestment_total' => $plannedReinvestmentTotal,
                'afa_replacement_rate' => round($afaReplacementRate, 2),
                'active_plans_rate' => round($activePlansMonthlySum, 2),
            ],
            'safe_monthly_salary' => $safeMonthlySalary,
            'operating_surplus' => $operatingSurplus,
            'unallocated_deficit' => $unallocatedDeficit,
            'bank' => [
                'has_business_bank' => $hasBusinessBank,
                'balance' => $businessBankBalance,
                'safety_buffer' => $safetyBufferTarget,
            ],
            'tax_projection' => [
                'annual_euer_profit' => $annualEuerProfit,
                'iab_deduction_total' => $totalIabDeduction,
                'iab_tax_saving' => $iabTotalTaxSaving,
                'effective_tax_rate' => $taxResult['effective_rate'],
                'annual_business_tax' => $businessAnnualIncomeTax,
            ],
            'private_mirror' => [
                'private_income_now' => round($privIncomeDue, 2),
                'private_fix_smoothed' => round($privFixSmoothed, 2),
                'private_fix_due' => round($privFixDue, 2),
                'living_allowance' => round($livingAllowance, 2),
                'private_balance_now' => $privBalanceNow,
                'needed_from_shop_now' => $neededFromShopNow,
                'post_gz_income' => round($privIncomePostGz, 2),
                'post_gz_needed_salary' => $postGzGap,
                'post_gz_required_gross_revenue' => $requiredGrossSalesPostGz,
                'is_gz_active_now' => ($year === 2026 && $month >= 11) || ($year === 2027 && $month <= 4),
            ],
            'investment_plans' => $plansOutput,
            'depreciation' => $depreciation,
        ];
    }
}
