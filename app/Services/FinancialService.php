<?php
namespace App\Services;

use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialService
{
    public function getMonthlyStats($adminId, $month, $year, $isNet = false)
    {
        // 1. Fixkosten (Bleibt wie gehabt)
        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        $fixedIncome = 0;
        $fixedExpenses = 0;

        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month)) {
                    $amount = $item->amount;
                    if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
                        $amount = $amount / (1 + ($item->tax_rate / 100));
                    }
                    if ($amount >= 0) {
                        $fixedIncome += $amount;
                    } else {
                        $fixedExpenses += $amount;
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
        $shopRevenueQuery = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note']);
            
        if ($isNet) {
            $shopRevenueQuery->selectRaw('SUM(total - tax_amount) as sum_total');
        } else {
            $shopRevenueQuery->selectRaw('SUM(total) as sum_total');
        }
        
        $shopRevenue = $shopRevenueQuery->value('sum_total') ?? 0;

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

    private function isDueInMonth($item, $currentMonth)
    {
        $startMonth = $item->first_payment_date->month;
        $interval = $item->interval_months;

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
            foreach ($group->items as $item) {
                $catKey = $item->amount >= 0 ? 'income' : ($item->is_business ? 'fixed_business' : 'fixed_private');

                $itemRow = ['name' => $item->name . ' (' . $group->name . ')', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];

                for ($m = 1; $m <= 12; $m++) {
                    if ($this->isDueInMonth($item, $m)) {
                        $amount = $item->amount;
                        if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
                            $amount = $amount / (1 + ($item->tax_rate / 100));
                        }
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
        $shopInvoicesQuery = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->groupBy('month');
            
        if ($isNet) {
            $shopInvoicesQuery->selectRaw('MONTH(invoice_date) as month, SUM(total - tax_amount) as total');
        } else {
            $shopInvoicesQuery->selectRaw('MONTH(invoice_date) as month, SUM(total) as total');
        }
            
        $shopInvoices = $shopInvoicesQuery->get();

        $shopRow = ['name' => 'Online Shop', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
        foreach($shopInvoices as $invoiceAgg) {
            $m = $invoiceAgg->month;
            $amount = $invoiceAgg->total / 100;

            $structure['shop_income']['months'][$m] += $amount;
            $structure['shop_income']['year_sum'] += $amount;

            $shopRow['months'][$m] = $amount;
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

        return ['categories' => $structure, 'totals' => $totals];
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
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

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

                    if ($this->isDueInMonth($item, $dateObj->month)) {
                        $amount = $item->amount;
                        if ($isNet && isset($item->tax_rate) && $item->tax_rate > 0) {
                            $amount = $amount / (1 + ($item->tax_rate / 100));
                        }
                        
                        if (!$isMonthly) {
                            if ($dateObj->day == $dayOfMonth) $this->addToChartData($val, $amount);
                        } else {
                            $this->addToChartData($val, $amount);
                        }
                    }
                }
            }
        }

        // Variable (Specials)
        $specials = AccountingSpecialIssue::where('admin_id', $adminId)->whereBetween('execution_date', [$from, $to])->get();
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
                ->whereBetween('transaction_date', [$from, $to])
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
        $invoices = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [$from, $to])
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get();

        foreach($invoices as $invoice) {
            $key = $isMonthly ? $invoice->invoice_date->format('Y-m') : $invoice->invoice_date->format('Y-m-d');
            if(isset($days[$key])) {
                $amountEuro = ($isNet ? ($invoice->total - $invoice->tax_amount) : $invoice->total) / 100;
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

    private function addToChartData(&$dataArray, $amount) {
        if ($amount >= 0) {
            // Einnahme
            $dataArray['income'] += $amount;
        } else {
            // Ausgabe (wird als positiver Wert für den Chartbalken gespeichert)
            // Wenn es hier eine Rückerstattung gibt (amount > 0), ist es oben in Income gelandet.
            // Das ist für den Bar Chart (Cashflow) auch korrekt.
            $dataArray['expense'] += abs($amount);
        }
    }

    public function generateTaxExport($adminId, $month, $year)
    {
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
            foreach ($group->items as $item) {
                if ($this->isDueInMonth($item, $month)) {
                    $fixedCosts->push((object)[
                        'name' => $item->name,
                        'category' => $group->name,
                        'amount' => $item->amount,
                        'tax_rate' => $item->tax_rate ?? 0,
                        'is_business' => $item->is_business
                    ]);
                }
            }
        }

        $invoices = \App\Models\Accounting\AccountingInvoice::whereYear('invoice_date', $year)
            ->whereMonth('invoice_date', $month)
            ->whereIn('status', ['paid', 'cancelled'])
            ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
            ->get();

        $shopStats = [
            'gross' => $invoices->sum('total') / 100,
            'net'   => $invoices->sum(fn($i) => ($i->total - $i->tax_amount)) / 100,
            'tax'   => $invoices->sum('tax_amount') / 100,
            'count' => $invoices->where('total', '>', 0)->count(),
            'returns' => $invoices->where('total', '<', 0)->count(),
        ];
        $shopStats['aov'] = $shopStats['count'] > 0 ? ($shopStats['gross'] / $shopStats['count']) : 0;

        $liquidityPreview = [];
        $avgShopNet = \App\Models\Accounting\AccountingInvoice::whereBetween('invoice_date', [
            Carbon::create($year, $month, 1)->subMonths(3), 
            Carbon::create($year, $month, 1)->endOfMonth()
        ])->whereIn('status', ['paid'])->sum(DB::raw('total - tax_amount')) / 100 / 3;

        for ($i = 1; $i <= 3; $i++) {
            $previewMonth = Carbon::create($year, $month, 1)->addMonths($i);
            $m = $previewMonth->month;
            
            $previewFixed = 0;
            foreach ($fixedGroups as $group) {
                foreach ($group->items as $item) {
                    if ($this->isDueInMonth($item, $m) && $item->amount < 0) {
                        $previewFixed += ($item->amount / (1 + (($item->tax_rate ?? 0) / 100)));
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

        $zipFileName = "Finanzbericht_{$year}_{$month}.zip";
        $zipPath = storage_path("app/public/exports/{$zipFileName}");

        if(!is_dir(storage_path("app/public/exports"))) mkdir(storage_path("app/public/exports"), 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {

            $csvHandle = fopen('php://temp', 'r+');
            fprintf($csvHandle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($csvHandle, ['Datum', 'Typ', 'Bezeichnung', 'Kategorie', 'Brutto', 'Steuersatz', 'Netto', 'Steuer'], ';');

            foreach ($specials as $s) {
                $net = $s->amount / (1 + (($s->tax_rate ?? 0) / 100));
                $tax = $s->amount - $net;
                fputcsv($csvHandle, [
                    $s->execution_date->format('d.m.Y'), 'Variabel', $s->title, $s->category,
                    number_format($s->amount, 2, ',', '.'), ($s->tax_rate ?? 0).'%', 
                    number_format($net, 2, ',', '.'), number_format($tax, 2, ',', '.')
                ], ';');

                if($s->file_paths) {
                    foreach($s->file_paths as $index => $filePath) {
                        if(Storage::disk('public')->exists($filePath)) {
                            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                            $cleanTitle = \Illuminate\Support\Str::slug($s->title);
                            $newName = "Belege/{$s->execution_date->format('Y-m-d')}_{$cleanTitle}_{$index}.{$ext}";
                            $zip->addFile(storage_path("app/public/{$filePath}"), $newName);
                        }
                    }
                }
            }
            
            foreach ($bankTxs as $tx) {
                $catName = $tx->financeCategory ? $tx->financeCategory->name : 'Sonstiges';
                $title = '🏦 ' . ($tx->counterpart_name ?? $tx->purpose ?? 'Unbekannte Abbuchung');
                fputcsv($csvHandle, [
                    \Carbon\Carbon::parse($tx->transaction_date)->format('d.m.Y'), 'Variabel (Bank)', $title, $catName,
                    number_format($tx->amount, 2, ',', '.'), '0%', 
                    number_format($tx->amount, 2, ',', '.'), '0,00'
                ], ';');

                if($tx->file_paths) {
                    foreach($tx->file_paths as $index => $filePath) {
                        if(Storage::disk('public')->exists($filePath)) {
                            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                            $cleanTitle = \Illuminate\Support\Str::slug($title);
                            $dateStr = \Carbon\Carbon::parse($tx->transaction_date)->format('Y-m-d');
                            $newName = "Belege_Bank/{$dateStr}_{$cleanTitle}_{$index}.{$ext}";
                            $zip->addFile(storage_path("app/public/{$filePath}"), $newName);
                        }
                    }
                }
            }

            foreach ($fixedCosts as $f) {
                $net = $f->amount / (1 + (($f->tax_rate ?? 0) / 100));
                $tax = $f->amount - $net;
                fputcsv($csvHandle, [
                    Carbon::create($year, $month, 1)->format('d.m.Y'), 'Fixkosten', $f->name, $f->category,
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
            ]);
            $zip->addFromString("Liquiditaets_und_Finanzbericht_{$year}_{$month}.pdf", $pdf->output());

            $zip->close();
        }

        return $zipPath;
    }
}
