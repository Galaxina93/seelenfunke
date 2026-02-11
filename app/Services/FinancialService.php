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
    /**
     * Berechnet die Übersicht für einen spezifischen Monat für den Admin.
     */
    public function getMonthlyStats($adminId, $month, $year)
    {
        // 1. Fixkosten (Manuell)
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

        // 2. Sonderausgaben (DB)
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

        // 3. Shop Umsätze (Automatisch aus Orders)
        // Nur bezahlte Bestellungen, Summe Total Price (in Cents -> /100)
        $shopRevenue = Order::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->where('payment_status', 'paid')
            ->sum('total_price');

        $shopIncome = $shopRevenue / 100; // Umrechnung Cent -> Euro

        // Gesamtrechnung
        // Shop Umsatz wird zu den Einnahmen gezählt
        $totalBudget = $fixedIncome + $specialIncome + $shopIncome;
        $totalSpent = $fixedExpenses + $specialExpenses;
        $available = $totalBudget + $totalSpent; // Spent ist negativ

        return [
            'fixed_income' => $fixedIncome,
            'fixed_expenses' => $fixedExpenses,
            'special_income' => $specialIncome,
            'special_expenses' => $specialExpenses,
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
        // Struktur initialisieren
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

        // 2. Sonderausgaben
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)->whereYear('execution_date', $year)->get();
        foreach ($specials as $special) {
            $m = $special->execution_date->month;
            $catKey = $special->amount >= 0 ? 'income' : ($special->is_business ? 'special_business' : 'special_private');

            $structure[$catKey]['months'][$m] += $special->amount;
            $structure[$catKey]['year_sum'] += $special->amount;

            $groupName = $special->category ?: 'Sonstiges';
            if (!isset($structure[$catKey]['items'][$groupName])) {
                $structure[$catKey]['items'][$groupName] = ['name' => $groupName . ' (Kumuliert)', 'months' => array_fill(1, 12, 0), 'year_sum' => 0];
            }
            $structure[$catKey]['items'][$groupName]['months'][$m] += $special->amount;
            $structure[$catKey]['items'][$groupName]['year_sum'] += $special->amount;
        }

        // 3. Shop Umsätze
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


        // Totals berechnen
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
            ->where('amount', '<', 0)
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

        // 1. Fixkosten
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

        // 2. Sonderausgaben
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)->whereBetween('execution_date', [$from, $to])->get();
        foreach($specials as $special) {
            $key = $isMonthly ? $special->execution_date->format('Y-m') : $special->execution_date->format('Y-m-d');
            if(isset($days[$key])) $this->addToChartData($days[$key], $special->amount);
        }

        // 3. Shop Umsätze (Orders)
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
        if ($amount >= 0) $dataArray['income'] += $amount;
        else $dataArray['expense'] += abs($amount);
    }

    /**
     * Erstellt den ZIP Export für den Steuerberater
     */
    public function generateTaxExport($adminId, $month, $year)
    {
        $specials = FinanceSpecialIssue::where('admin_id', $adminId)
            ->whereYear('execution_date', $year)
            ->whereMonth('execution_date', $month)
            ->where('is_business', true)
            ->get();

        // Stats für das PDF holen
        $stats = $this->getMonthlyStats($adminId, $month, $year);

        $zipFileName = "Steuerunterlagen_{$year}_{$month}.zip";
        $zipPath = storage_path("app/public/exports/{$zipFileName}");

        if(!is_dir(storage_path("app/public/exports"))) mkdir(storage_path("app/public/exports"), 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {

            // 1. CSV Erstellen
            $csvHandle = fopen('php://temp', 'r+');
            // BOM für Excel
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

                // 2. Dateien hinzufügen (Automatisch umbenennen)
                if($s->file_paths) {
                    foreach($s->file_paths as $index => $filePath) {
                        if(Storage::disk('public')->exists($filePath)) {
                            $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                            // Logische Benennung: 2026-02-15_Amazon_RechnungNr_1.pdf
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

            // 3. PDF Generieren und hinzufügen
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
