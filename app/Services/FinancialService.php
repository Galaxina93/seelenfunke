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

    public function getMonthlyStats($adminId, $month, $year, $isNet = false)
    {
        // 1. Fixkosten (Bleibt wie gehabt)
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        $fixedIncome = 0;
        $fixedExpenses = 0;

        foreach ($groups as $group) {
            $isIncome = ($group->type === 'income');
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month, $year)) {
                    $amount = $item->amount;
                    if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
                        $amount = $amount / (1 + ($item->tax_rate / 100));
                    }
                    if ($isIncome) {
                        $fixedIncome += abs($amount);
                    } else {
                        $fixedExpenses += -abs($amount);
                    }
                }
            }
        }

        // 2. Sonderausgaben (Variable Kosten) - KORRIGIERTE LOGIK: Netto-Betrachtung
        $specialIssues = AccountingSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->get();

        // Wir summieren ALLES zusammen. Positive Werte (Rückzahlungen) reduzieren automatisch die negativen Werte.
        $netSpecialSum = 0;
        foreach ($specialIssues as $special) {
            $amount = $special->amount;
            if ($isNet && isset($special->tax_rate) && $special->tax_rate > 0) {
                $amount = $amount / (1 + ($special->tax_rate / 100));
            }
            $netSpecialSum += $amount;
        }
        
        $bankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->get()
            : collect();
            
        foreach ($bankTxs as $tx) {
            $netSpecialSum += $tx->amount;
        }

        $specialExpenses = 0;
        $specialIncome = 0;

        // Wir weisen das Netto-Ergebnis der korrekten Seite zu
        if ($netSpecialSum < 0) {
            $specialExpenses = $netSpecialSum; // z.B. -4.00
        } else {
            $specialIncome = $netSpecialSum;   // z.B. +5.00 (falls man Plus gemacht hat)
        }

        // 3. Shop Umsätze (Basis: Rechnungen statt Bestellungen)
        $shopInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get(['type', 'total', 'tax_amount']);

        $shopRevenue = $shopInvoices->sum(fn($inv) => self::signedInvoiceCents($inv, (bool) $isNet));

        $shopIncome = $shopRevenue / 100; // Umrechnung Cent -> Euro

        // 4. Totals berechnen
        // $specialExpenses ist bereits negativ, daher addieren wir es einfach (+ minus = minus).
        $totalBudget = $fixedIncome + $specialIncome + $shopIncome;
        $totalSpent = $fixedExpenses + $specialExpenses;

        // Verfügbar: Einnahmen (pos) + Ausgaben (neg)
        $available = $totalBudget + $totalSpent;

        return [
            'fixed_income' => $fixedIncome,
            'fixed_expenses' => $fixedExpenses,
            'special_income' => $specialIncome,     // Wird meistens 0 sein, es sei denn Netto-Plus
            'special_expenses' => $specialExpenses, // Dies ist nun der korrekt verrechnete Wert (z.B. -4,13)
            'shop_income' => $shopIncome,
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'available' => $available
        ];
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

    public function getYearlyMatrix($adminId, $year, $isNet = false)
    {
        $structure = [
            'income' => ['label' => 'Einnahmen (Fix)', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'shop_income' => ['label' => 'Shop Umsätze', 'color' => 'text-teal-600', 'bg' => 'bg-teal-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'fixed_private' => ['label' => 'Fixkosten (Privat)', 'color' => 'text-rose-500', 'bg' => 'bg-rose-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'fixed_business' => ['label' => 'Fixkosten (Gewerbe)', 'color' => 'text-blue-500', 'bg' => 'bg-blue-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'special_private' => ['label' => 'Variabel (Privat)', 'color' => 'text-orange-500', 'bg' => 'bg-orange-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
            'special_business' => ['label' => 'Variabel (Gewerbe)', 'color' => 'text-indigo-500', 'bg' => 'bg-indigo-400', 'months' => array_fill(1, 12, 0), 'year_sum' => 0, 'items' => []],
        ];

        // 1. Fixkosten
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            $isIncome = ($group->type === 'income');
            foreach ($group->items as $item) {
                $catKey = $isIncome ? 'income' : ($item->is_business ? 'fixed_business' : 'fixed_private');

                $itemRow = ['name' => $item->name . ' (' . $group->name . ')', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];

                for ($m = 1; $m <= 12; $m++) {
                    if ($this->isDueInMonth($item, $m, $year)) {
                        $amount = $item->amount;
                        if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
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
        $bankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereYear('transaction_date', $year)
                ->get()
            : collect();

        foreach ($specials as $special) {
            $m = $special->execution_date->month;

            // KORREKTUR: Positive Beträge (Rückerstattungen) bleiben in ihrer Kategorie,
            // damit sie die Kosten dort reduzieren und nicht als allgemeine Einnahme gelten.
            if ($special->is_business) {
                $catKey = 'special_business';
            } else {
                // Wenn es privat ist, landet es bei special_private (auch wenn positiv, z.B. Rückzahlung)
                // Ausnahme: Wenn man es explizit als Einnahme will, müsste man die Logik hier ändern,
                // aber für "Sonderausgaben soll sich reduzieren" ist dies korrekt.
                $catKey = 'special_private';
            }

            $amount = $special->amount;
            if ($isNet && isset($special->tax_rate) && $special->tax_rate > 0) {
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
            $m = \Carbon\Carbon::parse($tx->transaction_date)->month;
            $isBusiness = $tx->is_business ?? ($tx->account ? $tx->account->is_business : false);
            $catKey = $isBusiness ? 'special_business' : 'special_private';

            $amount = $tx->amount;

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
        $shopInvoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get(['invoice_date', 'type', 'total', 'tax_amount']);

        $shopRow = ['name' => 'Online Shop', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
        foreach($shopInvoices as $inv) {
            $m = (int) $inv->invoice_date->month;
            $amount = self::signedInvoiceCents($inv, (bool) $isNet) / 100;

            $structure['shop_income']['months'][$m] += $amount;
            $structure['shop_income']['year_sum'] += $amount;

            $shopRow['months'][$m] += $amount;
            $shopRow['year_sum'] += $amount;
        }
        $structure['shop_income']['items'][] = $shopRow;

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

    public function getPieChartData($adminId, $from, $to)
    {
        $specialsData = AccountingSpecialIssue::where('admin_id', $adminId)
            ->whereBetween('execution_date', [$from, $to])
            ->where('amount', '<', 0) // Pie Chart zeigt nur echte Ausgabenverteilung
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->get();

        $bankTxData = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with('financeCategory')
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereBetween('transaction_date', [$from, $to])
                ->where('amount', '<', 0)
                ->get()
                ->groupBy(fn($tx) => $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges')
                ->map(fn($group, $key) => (object)['category' => $key, 'total' => $group->sum(fn($tx) => abs($tx->amount))])
                ->values()
            : collect();

        // Merge two collections and group
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

    public function getBarChartData($adminId, $from, $to, $isNet = false)
    {
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
        while($current <= $end) {
            $key = $isMonthly ? $current->format('Y-m') : $current->format('Y-m-d');
            $label = $isMonthly ? $current->format('M Y') : $current->format('d.m.');

            if(!isset($days[$key])) $days[$key] = ['label' => $label, 'income' => 0, 'expense' => 0];

            $isMonthly ? $current->addMonth() : $current->addDay();
        }

        // Fixkosten
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach($groups as $group) {
            foreach($group->items as $item) {
                $dayOfMonth = $item->first_payment_date->day;

                foreach($days as $dateKey => &$val) {
                    $dateObj = $isMonthly
                        ? Carbon::createFromFormat('Y-m', $dateKey)->startOfMonth()
                        : Carbon::createFromFormat('Y-m-d', $dateKey);

                    if ($this->isDueInMonth($item, $dateObj->month, $dateObj->year)) {
                        $amount = $item->amount;
                        if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
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
        $specials = AccountingSpecialIssue::where('admin_id', $adminId)->whereBetween('execution_date', [$start, $end])->get();
        foreach($specials as $special) {
            $key = $isMonthly ? $special->execution_date->format('Y-m') : $special->execution_date->format('Y-m-d');
            if(isset($days[$key])) {
                $amount = $special->amount;
                if ($isNet && isset($special->tax_rate) && $special->tax_rate > 0) {
                    $amount = $amount / (1 + ($special->tax_rate / 100));
                }
                $this->addToChartData($days[$key], $amount);
            }
        }
        
        $bankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereBetween('transaction_date', [$start, $end])
                ->get()
            : collect();

        foreach($bankTxs as $tx) {
            $date = \Carbon\Carbon::parse($tx->transaction_date);
            $key = $isMonthly ? $date->format('Y-m') : $date->format('Y-m-d');
            if(isset($days[$key])) {
                $this->addToChartData($days[$key], $tx->amount);
            }
        }

        // Shop Invoices (anstatt Orders)
        $invoices = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [$start, $end])
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get();

        foreach($invoices as $invoice) {
            $key = $isMonthly ? $invoice->invoice_date->format('Y-m') : $invoice->invoice_date->format('Y-m-d');
            if(isset($days[$key])) {
                $amountEuro = self::signedInvoiceCents($invoice, (bool) $isNet) / 100;
                // Positive Beträge (Einnahmen) zu income, negative (Stornos) zu expense
                if ($amountEuro >= 0) {
                    $days[$key]['income'] += $amountEuro;
                } else {
                    $days[$key]['expense'] += abs($amountEuro);
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

    public function generateTaxExport($adminId, $month, $year)
    {
        $month = max(1, min(12, (int) $month));
        $year = max(2000, min(2100, (int) $year));

        $specials = AccountingSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->orderBy('execution_date')
            ->get();
            
        $bankTxs = class_exists(\App\Models\Accounting\AccountingBankTransaction::class)
            ? \App\Models\Accounting\AccountingBankTransaction::with(['account', 'financeCategory'])
                ->whereHas('account', fn($q) => $q->where('admin_id', $adminId))
                ->whereNotNull('accounting_category_id')
                ->whereYear('transaction_date', $year)
                ->whereMonth('transaction_date', $month)
                ->orderBy('transaction_date')
                ->get()
            : collect();

        $fixedGroups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        $fixedCosts = collect();
        foreach ($fixedGroups as $group) {
            $isIncomeGroup = ($group->type === 'income');
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month, $year)) {
                    $signedAmount = $isIncomeGroup ? abs($item->amount) : -abs($item->amount);
                    $fixedCosts->push((object)[
                        'name' => $item->name,
                        'category' => $group->name,
                        'amount' => $signedAmount,
                        'tax_rate' => $item->tax_rate ?? 0,
                        'is_business' => $item->is_business
                    ]);
                }
            }
        }

        $invoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereNotNull('paid_at')
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get();

        $shopStats = [
            'gross' => $invoices->sum(fn($i) => self::signedInvoiceCents($i, false)) / 100,
            'net'   => $invoices->sum(fn($i) => self::signedInvoiceCents($i, true)) / 100,
            'tax'   => $invoices->sum(fn($i) => self::signedInvoiceCents($i, false) - self::signedInvoiceCents($i, true)) / 100,
            'count' => $invoices->filter(fn($i) => self::signedInvoiceCents($i, false) > 0)->count(),
            'returns' => $invoices->filter(fn($i) => self::signedInvoiceCents($i, false) < 0)->count(),
        ];
        $shopStats['aov'] = $shopStats['count'] > 0 ? ($shopStats['gross'] / $shopStats['count']) : 0;

        $liquidityPreview = [];
        $avgInvoices = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [
            Carbon::create($year, $month, 1)->subMonths(2)->startOfMonth(), 
            Carbon::create($year, $month, 1)->endOfMonth()
        ])->whereIn('status', ['paid', 'cancelled'])
          ->whereNotNull('paid_at')
          ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
          ->get(['type', 'total', 'tax_amount']);
        $avgShopNet = $avgInvoices->sum(fn($i) => self::signedInvoiceCents($i, true)) / 100 / 3;

        for ($i = 1; $i <= 3; $i++) {
            $previewMonth = Carbon::create($year, $month, 1)->addMonths($i);
            $m = $previewMonth->month;
            
            $previewFixed = 0;
            foreach ($fixedGroups as $group) {
                if ($group->type === 'income') {
                    continue;
                }
                foreach ($group->items as $item) {
                    if ($this->isDueInMonth($item, $m, $previewMonth->year)) {
                        $previewFixed += (abs($item->amount) / (1 + (($item->tax_rate ?? 0) / 100)));
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

        $dir = storage_path('app/buchhaltung/exports');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $zipFileName = "Finanzbericht_{$year}_{$month}.zip";
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
            fprintf($csvHandle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($csvHandle, ['Datum', 'Typ', 'Bezeichnung', 'Kategorie', 'Brutto', 'Steuersatz', 'Netto', 'Steuer'], ';');

            foreach ($specials as $s) {
                $sRate = self::isEligibleGermanInputVatRate($s->tax_rate ?? 0) ? (int) round((float) $s->tax_rate) : 0;
                $net = $sRate > 0 ? ($s->amount / (1 + ($sRate / 100))) : $s->amount;
                $tax = $s->amount - $net;
                fputcsv($csvHandle, [
                    $s->execution_date->format('d.m.Y'), 'Variabel', $sanitizeCsvCell($s->title), $sanitizeCsvCell($s->category),
                    number_format($s->amount, 2, ',', '.'), $sRate.'%', 
                    number_format($net, 2, ',', '.'), number_format($tax, 2, ',', '.')
                ], ';');

                if($s->file_paths) {
                    $storageRoot = realpath(storage_path('app'));
                    foreach($s->file_paths as $index => $filePath) {
                        if (!is_string($filePath) || str_contains($filePath, '..') || str_contains($filePath, "\0")) {
                            continue;
                        }
                        $sourcePath = Storage::disk('local')->exists($filePath)
                            ? Storage::disk('local')->path($filePath)
                            : (Storage::disk('public')->exists($filePath) ? Storage::disk('public')->path($filePath) : null);

                        if($sourcePath && is_file($sourcePath) && !is_link($sourcePath)) {
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
                $title = '🏦 ' . $sanitizeCsvCell($rawTxLabel);
                fputcsv($csvHandle, [
                    \Carbon\Carbon::parse($tx->transaction_date)->format('d.m.Y'), 'Variabel (Bank)', $sanitizeCsvCell($title), $sanitizeCsvCell($catName),
                    number_format($tx->amount, 2, ',', '.'), '0%', 
                    number_format($tx->amount, 2, ',', '.'), '0,00'
                ], ';');

                if($tx->file_paths) {
                    $storageRoot = realpath(storage_path('app'));
                    foreach($tx->file_paths as $index => $filePath) {
                        if (!is_string($filePath) || str_contains($filePath, '..') || str_contains($filePath, "\0")) {
                            continue;
                        }
                        $sourcePath = Storage::disk('local')->exists($filePath)
                            ? Storage::disk('local')->path($filePath)
                            : (Storage::disk('public')->exists($filePath) ? Storage::disk('public')->path($filePath) : null);

                        if($sourcePath && is_file($sourcePath) && !is_link($sourcePath)) {
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
                fputcsv($csvHandle, [
                    Carbon::create($year, $month, 1)->format('d.m.Y'), 'Fixkosten', $sanitizeCsvCell($f->name), $sanitizeCsvCell($f->category),
                    number_format($f->amount, 2, ',', '.'), $fRate.'%', 
                    number_format($net, 2, ',', '.'), number_format($tax, 2, ',', '.')
                ], ';');
            }

            rewind($csvHandle);
            $csvContent = stream_get_contents($csvHandle);
            fclose($csvHandle);
            $zip->addFromString("Transaktionen_{$year}_{$month}.csv", $csvContent);

            $pdf = Pdf::loadView('global.pdf.financial_report', [
                'month' => $month, 'year' => $year,
                'shopStats' => $shopStats,
                'fixedCosts' => $fixedCosts,
                'specials' => $specials,
                'liquidityPreview' => $liquidityPreview,
                'statsBrutto' => $this->getMonthlyStats($adminId, $month, $year, false),
                'statsNetto' => $this->getMonthlyStats($adminId, $month, $year, true)
            ])->setOption(['isRemoteEnabled' => false]);
            $zip->addFromString("Liquiditaets_und_Finanzbericht_{$year}_{$month}.pdf", $pdf->output());

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

        // 3. VORSTEUER DYNAMISCH BERECHNEN (Kz 66: ausschließlich echte deutsche Vorsteuersätze 7 % & 19 %)
        $vorsteuerSpecial = 0.0;
        $vorsteuerFix = 0.0;
        $expensesNet = 0.0;

        foreach ($businessSpecials as $s) {
            $rawRate = $s->tax_rate ?? 0;
            $amt = abs((float) $s->amount);

            if (self::isEligibleGermanInputVatRate($rawRate)) {
                $rate = (int) round((float) $rawRate);
                $net = $amt / (1 + ($rate / 100));
                $vorsteuerSpecial += ($amt - $net);
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        foreach ($businessFixed as $f) {
            $rawRate = $f->tax_rate ?? 0;
            $amt = abs((float) $f->amount);

            if (self::isEligibleGermanInputVatRate($rawRate)) {
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
        }

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
     * Berechnet UStVA-Daten flexibel für einen Einzelmonat ('month', 1..12) oder ein Quartal ('quarter', Q1..Q4 => ELSTER 41..44).
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

        if ($mode !== 'quarter') {
            return $this->getUstvaMonthData($resolvedAdminId, max(1, min(12, $monthOrQuarter)), $year, $submissionType);
        }

        $quarter = max(1, min(4, $monthOrQuarter));
        $months = range(($quarter - 1) * 3 + 1, $quarter * 3);
        $elsterZeitraum = (string) (40 + $quarter);

        $monthPayloads = array_map(
            fn (int $m) => $this->getUstvaMonthData($resolvedAdminId, $m, $year, $submissionType),
            $months
        );

        $qStart = Carbon::createFromDate($year, $months[0], 1)->startOfMonth();
        $qEnd = Carbon::createFromDate($year, $months[2], 1)->endOfMonth();
        $isFuture = $qStart->isFuture();
        $isPast = $qEnd->isPast();
        $deadline = Carbon::createFromDate($year, $months[2], 10)->addMonth();

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

        $firstShort = mb_substr($monthPayloads[0]['month_name'], 0, 3);
        $lastShort = mb_substr($monthPayloads[2]['month_name'], 0, 3);

        return [
            'period_mode' => 'quarter',
            'elster_zeitraum' => $elsterZeitraum,
            'export_period_slug' => "Q{$quarter}",
            'months_in_period' => $months,
            'quarter' => $quarter,
            'month_name' => "Q{$quarter} ({$firstShort}-{$lastShort})",
            'month_number' => $elsterZeitraum,
            'year' => $year,
            'revenue_gross' => $sum('revenue_gross'),
            'revenue_net' => $sum('revenue_net'),
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
     * Erstellt den DATEV EXTF Buchungsstapel CSV-String aus den UStVA-Monatsdaten.
     */
    public function buildDatevCsv(array $data): string
    {
        $csv = '"EXTF";700;21;"Buchungsstapel";9;"";"";"";"";"";"1";"";"";"";"";"";"";"";"";"";"";"";"";"";""' . "\n";
        $csv .= '"Umsatz (ohne Soll/Haben-Kz)";"Soll/Haben-Kennzeichen";"Konto";"Gegenkonto (ohne BU-Schlüssel)";"Belegdatum";"Belegfeld 1";"Buchungstext"' . "\n";

        foreach ($data['raw_orders'] ?? [] as $order) {
            $amount = number_format($order->total_price / 100, 2, ',', '');
            $date = $order->created_at->format('dm');
            $safeOrderNo = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) $order->order_number));
            $csv .= '"' . $amount . '";"S";"1200";"8400";"' . $date . '";"' . $safeOrderNo . '";"Bestellung ' . $safeOrderNo . '"' . "\n";
        }

        foreach ($data['raw_manual_invoices'] ?? [] as $manualInv) {
            $amount = number_format(abs($manualInv->total / 100), 2, ',', '');
            $date = ($manualInv->invoice_date ?? $manualInv->created_at ?? now())->format('dm');
            $safeInvNo = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) $manualInv->invoice_number));
            $csv .= '"' . $amount . '";"S";"1200";"8400";"' . $date . '";"' . $safeInvNo . '";"Rechnung ' . $safeInvNo . '"' . "\n";
        }

        foreach ($data['raw_credits'] ?? [] as $credit) {
            $amount = number_format(abs($credit->total / 100), 2, ',', '');
            $date = ($credit->invoice_date ?? $credit->created_at ?? now())->format('dm');
            $safeCreditNo = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) $credit->invoice_number));
            $csv .= '"' . $amount . '";"H";"1200";"8400";"' . $date . '";"' . $safeCreditNo . '";"Gutschrift / Storno ' . $safeCreditNo . '"' . "\n";
        }

        foreach ($data['raw_specials'] ?? [] as $special) {
            $amount = number_format(abs($special->amount), 2, ',', '');
            $date = Carbon::parse($special->execution_date)->format('dm');
            $safeTitle = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) $special->title));
            $safeBeleg = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) ($special->invoice_number ?? 'Beleg')));
            $csv .= '"' . $amount . '";"H";"4900";"1200";"' . $date . '";"' . $safeBeleg . '";"' . $safeTitle . '"' . "\n";
        }

        foreach ($data['raw_fixed'] ?? [] as $fixed) {
            $amount = number_format(abs($fixed->amount), 2, ',', '');
            $dueMonth = (int) ($fixed->_due_month ?? ($data['months_in_period'][0] ?? $data['month_number'] ?? 1));
            $dueMonth = max(1, min(12, $dueMonth));
            $date = '28' . str_pad((string) $dueMonth, 2, '0', STR_PAD_LEFT);
            $safeName = preg_replace('/^[=+\-@]/', "'$0", str_replace(['"', ';', "\r", "\n"], '', (string) $fixed->name));
            $csv .= '"' . $amount . '";"H";"4900";"1200";"' . $date . '";"Fixkosten";"' . $safeName . '"' . "\n";
        }

        return $csv;
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
     * inklusive EXTF Buchungsstapel CSV, UStVA PDF und allen gewerblichen Belegen.
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
}
