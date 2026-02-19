<?php
namespace App\Services;

use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Order\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Barryvdh\DomPDF\Facade\Pdf;

class FinancialService
{
    public function getMonthlyStats($adminId, $month, $year)
    {
        // 1. Fixkosten (Bleibt wie gehabt)
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

        // 2. Sonderausgaben (Variable Kosten) - KORRIGIERTE LOGIK: Netto-Betrachtung
        $specialIssues = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->get();

        // Wir summieren ALLES zusammen. Positive Werte (Rückzahlungen) reduzieren automatisch die negativen Werte.
        // Beispiel: -14.00 (Ausgabe) + 10.00 (Rückzahlung) = -4.00
        $netSpecialSum = $specialIssues->sum('amount');

        $specialExpenses = 0;
        $specialIncome = 0;

        // Wir weisen das Netto-Ergebnis der korrekten Seite zu
        if ($netSpecialSum < 0) {
            $specialExpenses = $netSpecialSum; // z.B. -4.00
        } else {
            $specialIncome = $netSpecialSum;   // z.B. +5.00 (falls man Plus gemacht hat)
        }

        // 3. Shop Umsätze
        $shopRevenue = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->sum('total_price');

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

    public function getYearlyMatrix($adminId, $year)
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
        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                $catKey = $item->amount >= 0 ? 'income' : ($item->is_business ? 'fixed_business' : 'fixed_private');

                $itemRow = ['name' => $item->name . ' (' . $group->name . ')', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];

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

        // 2. Sonderausgaben (Variable Kosten)
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)->whereYear('execution_date', $year)->get();

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

            $structure[$catKey]['months'][$m] += $special->amount;
            $structure[$catKey]['year_sum'] += $special->amount;

            $groupName = $special->category ?: 'Sonstiges';
            if (!isset($structure[$catKey]['items'][$groupName])) {
                $structure[$catKey]['items'][$groupName] = ['name' => $groupName . ' (Kumuliert)', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
            }
            $structure[$catKey]['items'][$groupName]['months'][$m] += $special->amount;
            $structure[$catKey]['items'][$groupName]['year_sum'] += $special->amount;
        }

        // 3. Shop
        $shopOrders = Order::whereYear('created_at', $year)
            ->where('payment_status', 'paid')
            ->selectRaw('MONTH(created_at) as month, SUM(total_price) as total')
            ->groupBy('month')
            ->get();

        $shopRow = ['name' => 'Online Shop', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
        foreach($shopOrders as $orderAgg) {
            $m = $orderAgg->month;
            $amount = $orderAgg->total / 100;

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
        $data = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereBetween('execution_date', [$from, $to])
            ->where('amount', '<', 0) // Pie Chart zeigt nur echte Ausgabenverteilung
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $data->pluck('category'),
            'data' => $data->pluck('total'),
        ];
    }

    public function getBarChartData($adminId, $from, $to)
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
        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
        foreach($groups as $group) {
            foreach($group->items as $item) {
                $dayOfMonth = $item->first_payment_date->day;

                foreach($days as $dateKey => &$val) {
                    $dateObj = $isMonthly
                        ? Carbon::createFromFormat('Y-m', $dateKey)->startOfMonth()
                        : Carbon::createFromFormat('Y-m-d', $dateKey);

                    if ($this->isDueInMonth($item, $dateObj->month)) {
                        if (!$isMonthly) {
                            if ($dateObj->day == $dayOfMonth) $this->addToChartData($val, $item->amount);
                        } else {
                            $this->addToChartData($val, $item->amount);
                        }
                    }
                }
            }
        }

        // Variable (Specials)
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)->whereBetween('execution_date', [$from, $to])->get();
        foreach($specials as $special) {
            $key = $isMonthly ? $special->execution_date->format('Y-m') : $special->execution_date->format('Y-m-d');
            if(isset($days[$key])) {
                $this->addToChartData($days[$key], $special->amount);
            }
        }

        // Shop Orders
        $orders = Order::whereBetween('created_at', [$from, $to])
            ->where('payment_status', 'paid')
            ->get();

        foreach($orders as $order) {
            $key = $isMonthly ? $order->created_at->format('Y-m') : $order->created_at->format('Y-m-d');
            if(isset($days[$key])) {
                $days[$key]['income'] += ($order->total_price / 100);
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
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->where('is_business', true)
            ->get();

        $stats = $this->getMonthlyStats($adminId, $month, $year);

        $zipFileName = "Steuerunterlagen_{$year}_{$month}.zip";
        $zipPath = storage_path("app/public/exports/{$zipFileName}");

        if(!is_dir(storage_path("app/public/exports"))) mkdir(storage_path("app/public/exports"), 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {

            $csvHandle = fopen('php://temp', 'r+');
            fprintf($csvHandle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($csvHandle, ['Datum', 'Titel', 'Kategorie', 'Rechnungsnr.', 'Steuersatz', 'Betrag (Brutto)', 'Notiz'], ';');

            foreach ($specials as $s) {
                fputcsv($csvHandle, [
                    $s->execution_date->format('d.m.Y'),
                    $s->title,
                    $s->category,
                    $s->invoice_number ?? '-',
                    $s->tax_rate ? $s->tax_rate.'%' : '0%',
                    number_format($s->amount, 2, ',', '.'),
                    $s->note
                ], ';');

                if($s->file_paths) {
                    foreach($s->file_paths as $index => $filePath) {
                        if(Storage::disk('public')->exists($filePath)) {
                            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                            $cleanTitle = \Illuminate\Support\Str::slug($s->title);
                            $cleanInv = $s->invoice_number ? '_'.$s->invoice_number : '';
                            $newName = "Belege/{$s->execution_date->format('Y-m-d')}_{$cleanTitle}{$cleanInv}_{$index}.{$ext}";
                            $zip->addFile(storage_path("app/public/{$filePath}"), $newName);
                        }
                    }
                }
            }
            rewind($csvHandle);
            $csvContent = stream_get_contents($csvHandle);
            fclose($csvHandle);
            $zip->addFromString("Bericht_{$year}_{$month}.csv", $csvContent);

            $pdf = Pdf::loadView('global.pdf.financial_report', [
                'stats' => $stats,
                'specials' => $specials,
                'month' => $month,
                'year' => $year
            ]);
            $zip->addFromString("Gesamtbericht_{$year}_{$month}.pdf", $pdf->output());

            $zip->close();
        }

        return $zipPath;
    }
}
