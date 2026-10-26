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
                $net = $s->amount / (1 + (($s->tax_rate ?? 0) / 100));
                $tax = $s->amount - $net;
                fputcsv($csvHandle, [
                    $s->execution_date->format('d.m.Y'), 'Variabel', $sanitizeCsvCell($s->title), $sanitizeCsvCell($s->category),
                    number_format($s->amount, 2, ',', '.'), ($s->tax_rate ?? 0).'%', 
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
                $net = $f->amount / (1 + (($f->tax_rate ?? 0) / 100));
                $tax = $f->amount - $net;
                fputcsv($csvHandle, [
                    Carbon::create($year, $month, 1)->format('d.m.Y'), 'Fixkosten', $sanitizeCsvCell($f->name), $sanitizeCsvCell($f->category),
                    number_format($f->amount, 2, ',', '.'), ($f->tax_rate ?? 0).'%', 
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
            'street' => trim(shop_setting('company_street', shop_setting('owner_street', 'Musterstraße 1')) . ' ' . shop_setting('company_street_number', '')),
            'city' => trim(shop_setting('company_zip', '') . ' ' . shop_setting('company_city', shop_setting('owner_city', '12345 Musterstadt'))),
            'tax_id' => shop_setting('owner_tax_id', 'XX/XXX/XXXXX'),
            'ust_id' => shop_setting('owner_ust_id', 'DE XXXXXXXXX'),
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

        // 1. UMSÄTZE (Einnahmen dynamisch splitten)
        $orders = OrderOrder::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->get();

        $creditNotes = \App\Models\Accounting\AccountingInvoice::with('order')
            ->whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('type', ['credit_note', 'cancellation'])
            ->whereIn('status', ['paid', 'cancelled'])
            ->get();

        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $isYearSmallBusiness = $taxService->isSmallBusinessForYear($year);
        $isMidyearSwitch = $taxService->isMidyearSwitch($year);
        $switchDate = $taxService->getMidyearSwitchDate();

        $revenueGross = 0;
        $vatCollected = 0;
        $retroactiveVatCollected = 0;
        $igErwerbTax = 0;
        $paragraph13bTax = 0;

        foreach ($orders as $order) {
            $total = $order->total_price / 100;
            $tax = $order->tax_amount / 100;

            if ($isYearSmallBusiness) {
                $tax = 0;
            } elseif ($tax == 0 && ($isMidyearSwitch || $order->is_small_business)) {
                $rate = (float) shop_setting('default_tax_rate', 19.0);
                $net = $total / (1 + ($rate / 100));
                $tax = $total - $net;
                $retroactiveVatCollected += $tax;
            }

            $revenueGross += $total;

            if (isset($order->is_reverse_charge) && $order->is_reverse_charge) {
                $paragraph13bTax += $tax;
            } elseif (isset($order->is_ig_erwerb) && $order->is_ig_erwerb) {
                $igErwerbTax += $tax;
            } else {
                $vatCollected += $tax;
            }
        }

        // GUTSCHRIFTEN VERRECHNEN
        foreach ($creditNotes as $cn) {
            $total = -abs($cn->total / 100);
            $tax = -abs($cn->tax_amount / 100);

            if ($isYearSmallBusiness) {
                $tax = 0;
            } elseif ($tax == 0 && ($isMidyearSwitch || $cn->is_small_business)) {
                $rate = (float) shop_setting('default_tax_rate', 19.0);
                $net = $total / (1 + ($rate / 100));
                $tax = $total - $net;
                $retroactiveVatCollected += $tax;
            }

            $revenueGross += $total;

            if ($cn->order && isset($cn->order->is_reverse_charge) && $cn->order->is_reverse_charge) {
                $paragraph13bTax += $tax;
            } elseif ($cn->order && isset($cn->order->is_ig_erwerb) && $cn->order->is_ig_erwerb) {
                $igErwerbTax += $tax;
            } else {
                $vatCollected += $tax;
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
                        $businessFixed->push($item);
                    }
                }
            }
        }

        $totalExpensesGross = abs($businessSpecials->sum('amount')) + abs($businessFixed->sum('amount'));

        // 3. VORSTEUER DYNAMISCH BERECHNEN (Kz 66)
        $vatPaid = 0;
        $expensesNet = 0;

        foreach ($businessSpecials as $s) {
            $rate = $s->tax_rate ?? (float) shop_setting('default_tax_rate', 19.0);
            $amt = abs($s->amount);

            if ($rate > 0) {
                $net = $amt / (1 + ($rate / 100));
                $vatPaid += $amt - $net;
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        foreach ($businessFixed as $f) {
            $rate = $f->tax_rate ?? (float) shop_setting('default_tax_rate', 19.0);
            $amt = abs($f->amount);

            if ($rate > 0) {
                $net = $amt / (1 + ($rate / 100));
                $vatPaid += $amt - $net;
                $expensesNet += $net;
            } else {
                $expensesNet += $amt;
            }
        }

        if ($isYearSmallBusiness) {
            $vatPaid = 0;
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

        $allChecklistPassed = collect($checklist)->every(fn($item) => $item['passed']);

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

        return [
            'month_name' => $startDate->locale('de')->monthName,
            'month_number' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
            'year' => $year,
            'revenue_gross' => $revenueGross,
            'revenue_net' => $revenueNet,
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
            'vat_paid' => $vatPaid,
            'zahllast' => $zahllast,
            'profit' => $profit,
            'progress' => min(100, round($progress)),
            'missing_receipts_count' => $missingReceiptsCount,
            'missing_items' => $missingReceiptItems,
            'checklist' => $checklist,
            'all_checklist_passed' => $allChecklistPassed,
            'status' => $status,
            'order_count' => $orders->count(),
            'expense_count' => $totalBusinessItems,
            'raw_orders' => $orders,
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
            $date = '28' . str_pad((string) ($data['month_number'] ?? '01'), 2, '0', STR_PAD_LEFT);
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
        $monthStr = $data['month_number'];
        $yearStr = $data['year'];

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
                    \Illuminate\Support\Facades\File::copy(
                        $source,
                        $receiptsDir . "/Fixkosten_" . \Illuminate\Support\Str::slug($fixed->name) . ".{$ext}"
                    );
                    $copyCount++;
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
