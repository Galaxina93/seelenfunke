<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Order\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class FinancialLiquidityPlanner extends Component
{
    public array $years = [];
    public int $activeYear;
    public array $data = [];
    public array $totals = [];
    public array $rentabilitaet = [];
    public array $kapitalbedarf = [];
    public float $startBalance = 0.0;

    public array $receiptRows = [
        'sales' => ['label' => 'aus Forderungseingängen', 'tooltip' => 'Bezahlte Rechnungen (Shop-Bestellungen und Marktplätze)'],
        'cash' => ['label' => 'bar (-> Sofortzahlung)', 'tooltip' => 'Direkte Verkäufe (z.B. auf Märkten)'],
        'tax_refund' => ['label' => 'Vorsteuererstattung', 'tooltip' => 'Vom Finanzamt erstattete Vorsteuer'],
        'subsidy' => ['label' => 'Zuschüsse (ALG1 / Gründungsz.)', 'tooltip' => 'Staatliche Förderungen in der Anlaufphase (Monate 4-9)'],
        'other_in' => ['label' => 'sonstige Einzahlungen', 'tooltip' => 'Zinsen etc.'],
    ];

    public array $expenseRows = [
        'investments' => ['label' => 'Investitionen / Markteinführung', 'tooltip' => 'Einmalige Anschaffungen (Laser, Shop-Setup)'],
        'goods' => ['label' => 'Wareneinkauf', 'tooltip' => 'Einkauf von Rohlingen und Verpackungsmaterial'],
        'personnel' => ['label' => 'Personalkosten', 'tooltip' => 'Löhne, Sozialabgaben (inkl. Aushilfen)'],
        'room' => ['label' => 'Raumkosten', 'tooltip' => 'Miete für Produktionsräume'],
        'room_extra' => ['label' => 'Raumnebenkosten', 'tooltip' => 'Energie, Heizung, Instandhaltung'],
        'vehicle' => ['label' => 'Fahrzeugkosten', 'tooltip' => 'Benzin, Leasing, Instandhaltung'],
        'office' => ['label' => 'Bürokosten', 'tooltip' => 'PC, Telefon, Software (Sevdesk), Porto'],
        'marketing' => ['label' => 'Werbung / Vertrieb', 'tooltip' => 'Marketingkosten (TikTok Ads, Etsy Ads)'],
        'insurance' => ['label' => 'Versicherungen / Beiträge', 'tooltip' => 'Betriebshaftpflicht, IHK, Verpackungslizenz'],
        'taxes' => ['label' => 'Ertragssteuern', 'tooltip' => 'Gewerbesteuer, Einkommensteuer Vorauszahlungen'],
        'interest' => ['label' => 'Zinsen', 'tooltip' => 'Zinsen für Darlehen'],
        'repayment' => ['label' => 'Tilgungen', 'tooltip' => 'Tilgungsraten von Krediten'],
        'tax_payment' => ['label' => 'Umsatzsteuer', 'tooltip' => 'Umsatzsteuerzahllast an das Finanzamt'],
        'private' => ['label' => 'Private Ausgaben (Lebenshaltung)', 'tooltip' => 'Kosten für das Privatleben, die aus der Firma entnommen werden'],
        'other_out' => ['label' => 'sonstige Kosten', 'tooltip' => 'Reisekosten, Weiterbildungen'],
    ];

    public array $adjustmentRows = [
        'private_in' => ['label' => 'Privateinlage', 'tooltip' => 'Eigenkapital und private Einnahmen'],
        'loan' => ['label' => 'Darlehen', 'tooltip' => 'Auszahlung eines Bankkredits'],
        'overdraft' => ['label' => 'Kontokorrentkredit', 'tooltip' => 'Nutzung des Dispos'],
    ];

    public array $rentRows = [
        'umsatz' => 'Umsatzerlöse',
        'material' => 'Material-/ Warenaufwand',
        'rohertrag' => 'Rohertrag',
        'personal' => 'Personalkosten (Löhne, Gehälter, Sozialabgaben)',
        'raum' => 'Raumkosten (Miete)',
        'raumneben' => 'Raumnebenkosten (Energie, Instandhaltung, Rep.)',
        'fahrzeug' => 'Fahrzeugkosten (Benzin, Leasing, Instandhaltung)',
        'buero' => 'Bürokosten (PC, Telefon, Material, Porto,...)',
        'werbung' => 'Werbung/Vertrieb',
        'versicherung' => 'Versicherungen / Beiträge / sonst. Steuern',
        'abschreibung' => 'Abschreibungen',
        'sonstige' => 'sonstige betriebliche Aufwendungen (Reisekosten,...)',
        'betriebsergebnis' => 'Betriebsergebnis',
        'zinsen' => 'Zinsaufwendungen und Zinserträge',
        'ergebnis_vor_steuern' => 'Ergebnis vor Steuern',
        'steuern' => 'Steuern (Gewerbe, Körperschaft bzw. Ertrag)',
        'gewinn' => 'Gewinn / Jahresüberschuss',
        'cashflow' => 'Cash-Flow (Gewinn + Abschreibungen)',
    ];

    public function mount()
    {
        $currentYear = 2026;
        $this->years = [$currentYear, $currentYear + 1, $currentYear + 2];
        $this->activeYear = $currentYear;

        $this->initEmptyData();
        $this->injectSeelenfunkeBusinessPlan();
        $this->injectLiveData();
        $this->calculate();
    }

    public function updated($propertyName)
    {
        $this->calculate();
    }

    // NEU: Funktion umgeht das JS-Dot-Notation Problem bei Livewire
    public function updateValue($year, $month, $type, $key, $value)
    {
        // Falls leer oder null, setzen wir es auf null, ansonsten als Float
        $val = ($value === '' || $value === null) ? null : (float) str_replace(',', '.', $value);
        $this->data[$year][$month][$type][$key] = $val;

        // Berechne alle Summen sofort neu
        $this->calculate();
    }

    public function setActiveYear($year)
    {
        $this->activeYear = $year;
    }

    public function addYear()
    {
        $nextYear = max($this->years) + 1;
        $this->years[] = $nextYear;
        $this->initYear($nextYear);
        $this->injectSeelenfunkeBusinessPlan();
        $this->injectLiveData();
        $this->calculate();

        $this->activeYear = $nextYear;
    }

    public function removeYear()
    {
        if (count($this->years) > 1) {
            $yearToRemove = array_pop($this->years);
            unset($this->data[$yearToRemove]);
            unset($this->rentabilitaet[$yearToRemove]);

            if ($this->activeYear === $yearToRemove) {
                $this->activeYear = end($this->years);
            }

            $this->calculate();
        }
    }

    public function syncLiveData()
    {
        $this->initEmptyData();
        $this->injectSeelenfunkeBusinessPlan();
        $this->injectLiveData();
        $this->calculate();
        session()->flash('success', 'Livedaten erfolgreich synchronisiert.');
    }

    private function initEmptyData()
    {
        foreach ($this->years as $year) {
            $this->initYear($year);
        }
    }

    private function initYear($year)
    {
        for ($month = 1; $month <= 12; $month++) {
            foreach ($this->receiptRows as $key => $row) { $this->data[$year][$month]['in'][$key] = null; }
            foreach ($this->expenseRows as $key => $row) { $this->data[$year][$month]['out'][$key] = null; }
            foreach ($this->adjustmentRows as $key => $row) { $this->data[$year][$month]['adj'][$key] = null; }
        }
    }

    private function injectSeelenfunkeBusinessPlan()
    {
        // Jahr 1 (2026)
        if (in_array(2026, $this->years)) {
            $this->data[2026][4]['out']['investments'] = 7815;
            $this->data[2026][4]['out']['other_out'] = 600;

            for ($m = 4; $m <= 9; $m++) {
                $this->data[2026][$m]['in']['subsidy'] = 2100;
                $this->data[2026][$m]['out']['private'] = 2100;
            }

            $this->data[2026][4]['in']['sales'] = 800;  $this->data[2026][4]['out']['goods'] = 120;
            $this->data[2026][5]['in']['sales'] = 1200; $this->data[2026][5]['out']['goods'] = 180;
            $this->data[2026][6]['in']['sales'] = 1800; $this->data[2026][6]['out']['goods'] = 270;
            $this->data[2026][7]['in']['sales'] = 2000; $this->data[2026][7]['out']['goods'] = 300;
            $this->data[2026][8]['in']['sales'] = 2500; $this->data[2026][8]['out']['goods'] = 375;
            $this->data[2026][9]['in']['sales'] = 3200; $this->data[2026][9]['out']['goods'] = 480;

            $salesSelfSustaining = [
                10 => ['sales' => 4200, 'goods' => 630, 'marketing' => 150],
                11 => ['sales' => 5800, 'goods' => 870, 'marketing' => 200],
                12 => ['sales' => 6500, 'goods' => 975, 'marketing' => 200]
            ];

            foreach ($salesSelfSustaining as $m => $val) {
                $this->data[2026][$m]['in']['sales'] = $val['sales'];
                $this->data[2026][$m]['out']['goods'] = $val['goods'];
                $this->data[2026][$m]['out']['marketing'] = $val['marketing'];
                $this->data[2026][$m]['out']['private'] = 1600;
            }
        }

        // Jahr 2 (2027)
        if (in_array(2027, $this->years)) {
            $salesY2 = [1=>2200, 2=>3800, 3=>2800, 4=>3200, 5=>4200, 6=>2500, 7=>2400, 8=>2500, 9=>3000, 10=>3500, 11=>5200, 12=>5800];
            foreach($salesY2 as $m => $val) {
                $this->data[2027][$m]['in']['sales'] = $val;
                $this->data[2027][$m]['out']['goods'] = $val * 0.15;
                $this->data[2027][$m]['out']['marketing'] = 150;
                $this->data[2027][$m]['out']['private'] = 1800;
            }
        }

        // Jahr 3 (2028)
        if (in_array(2028, $this->years)) {
            $salesY3 = [1=>2800, 2=>4500, 3=>3200, 4=>3800, 5=>5000, 6=>3000, 7=>2800, 8=>3000, 9=>3500, 10=>4200, 11=>6000, 12=>6500];
            foreach($salesY3 as $m => $val) {
                $this->data[2028][$m]['in']['sales'] = $val;
                $this->data[2028][$m]['out']['goods'] = $val * 0.15;
                $this->data[2028][$m]['out']['marketing'] = 200;
                $this->data[2028][$m]['out']['private'] = 2000;
            }
        }
    }

    private function injectLiveData()
    {
        $adminId = Auth::guard('admin')->id();
        $dbData = [];

        $orders = Order::where('payment_status', 'paid')->get();
        foreach ($orders as $order) {
            $y = $order->created_at->year;
            $m = $order->created_at->month;
            if (in_array($y, $this->years)) {
                $dbData[$y][$m]['in']['sales'] = ($dbData[$y][$m]['in']['sales'] ?? 0) + ($order->total_price / 100);
            }
        }

        $groups = FinanceGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                $mapping = $this->mapItemToRow($item->name, $group->name, $item->is_business, $item->amount >= 0 ? 'receipt' : 'expense', $item->tags);
                $type = $mapping['type'];
                $rowKey = $mapping['key'];
                $amt = abs($item->amount);

                foreach ($this->years as $y) {
                    for ($m = 1; $m <= 12; $m++) {
                        if ($this->isDueInMonthYear($item, $m, $y)) {
                            $dbData[$y][$m][$type][$rowKey] = ($dbData[$y][$m][$type][$rowKey] ?? 0) + $amt;
                        }
                    }
                }
            }
        }

        $specials = FinanceSpecialIssue::where('admin_id', $adminId)->get();
        foreach ($specials as $special) {
            $date = Carbon::parse($special->execution_date);
            $y = $date->year;
            $m = $date->month;
            if (in_array($y, $this->years)) {
                $rowType = $special->amount >= 0 ? 'receipt' : 'expense';
                $mapping = $this->mapItemToRow($special->title, $special->category, $special->is_business, $rowType, []);
                $type = $mapping['type'];
                $rowKey = $mapping['key'];
                $amt = abs($special->amount);
                $dbData[$y][$m][$type][$rowKey] = ($dbData[$y][$m][$type][$rowKey] ?? 0) + $amt;
            }
        }

        foreach ($this->years as $y) {
            for ($m = 1; $m <= 12; $m++) {
                $isFuture = Carbon::createFromDate($y, $m, 1)->startOfMonth()->isFuture();

                foreach ($this->receiptRows as $key => $row) {
                    if (isset($dbData[$y][$m]['in'][$key])) {
                        if ($key === 'sales' && $isFuture && $dbData[$y][$m]['in'][$key] == 0) {
                            // Behalte Businessplan
                        } else {
                            $this->data[$y][$m]['in'][$key] = round($dbData[$y][$m]['in'][$key], 2);
                        }
                    }
                }
                foreach ($this->expenseRows as $key => $row) {
                    if (isset($dbData[$y][$m]['out'][$key])) {
                        if (in_array($key, ['goods', 'marketing', 'investments', 'other_out']) && $isFuture && $dbData[$y][$m]['out'][$key] == 0) {
                            // Behalte Businessplan
                        } else {
                            $this->data[$y][$m]['out'][$key] = round($dbData[$y][$m]['out'][$key], 2);
                        }
                    }
                }
                foreach ($this->adjustmentRows as $key => $row) {
                    if (isset($dbData[$y][$m]['adj'][$key])) {
                        $this->data[$y][$m]['adj'][$key] = round($dbData[$y][$m]['adj'][$key], 2);
                    }
                }
            }
        }
    }

    private function isDueInMonthYear($item, $month, $year) {
        $start = Carbon::parse($item->first_payment_date);
        $check = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $startMonth = $start->copy()->startOfMonth();

        if ($check->lt($startMonth)) return false;

        $diffMonths = $check->diffInMonths($startMonth);
        return ($diffMonths % max(1, $item->interval_months)) === 0;
    }

    private function mapItemToRow($title, $category, $isBusiness, $type, $tags = []) {
        $text = mb_strtolower($title . ' ' . $category);
        $tagsArray = is_string($tags) ? json_decode($tags, true) : (array)$tags;
        $tagsString = mb_strtolower(implode(' ', $tagsArray ?? []));

        if (!$isBusiness) {
            if ($type === 'expense') return ['type' => 'out', 'key' => 'private'];
            if ($type === 'receipt') {
                if (Str::contains($text, ['zuschuss', 'alg', 'amt'])) return ['type' => 'in', 'key' => 'subsidy'];
                return ['type' => 'adj', 'key' => 'private_in'];
            }
        }

        if ($type === 'receipt') {
            if (Str::contains($text, ['zuschuss', 'alg', 'amt', 'gründer'])) return ['type' => 'in', 'key' => 'subsidy'];
            if (Str::contains($text, ['vorsteuer', 'erstattung'])) return ['type' => 'in', 'key' => 'tax_refund'];
            return ['type' => 'in', 'key' => 'other_in'];
        }

        if (Str::contains($tagsString, ['versicherung', 'versicherungen']) || Str::contains($text, ['versicherung', 'beitrag', 'haftpflicht', 'arbeitslos'])) {
            return ['type' => 'out', 'key' => 'insurance'];
        }

        if (Str::contains($text, ['investition', 'anschaffung', 'laser', 'technik'])) return ['type' => 'out', 'key' => 'investments'];
        if (Str::contains($text, ['waren', 'rohling', 'material', 'verpackung', 'glas', 'schiefer', 'porto', 'dhl', 'logistik'])) return ['type' => 'out', 'key' => 'goods'];
        if (Str::contains($text, ['lohn', 'gehalt', 'personal'])) return ['type' => 'out', 'key' => 'personnel'];
        if (Str::contains($text, ['miete', 'raum'])) return ['type' => 'out', 'key' => 'room'];
        if (Str::contains($text, ['büro', 'telefon', 'internet', 'software', 'lizenz', 'server', 'hosting'])) return ['type' => 'out', 'key' => 'office'];
        if (Str::contains($text, ['marketing', 'werbung', 'ads', 'tiktok', 'instagram'])) return ['type' => 'out', 'key' => 'marketing'];
        if (Str::contains($text, ['umsatzsteuer', 'ustva', 'zahllast', 'steuer'])) return ['type' => 'out', 'key' => 'tax_payment'];
        if (Str::contains($text, ['kfz', 'auto', 'benzin', 'tanken', 'leasing', 'fahrzeug'])) return ['type' => 'out', 'key' => 'vehicle'];

        return ['type' => 'out', 'key' => 'other_out'];
    }

    private function calculate()
    {
        $currentBalance = (float) $this->startBalance;
        $this->totals = [];

        foreach ($this->years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $sumIn = 0; $sumOut = 0; $sumAdj = 0;

                foreach ($this->receiptRows as $key => $row) { $sumIn += (float) ($this->data[$year][$month]['in'][$key] ?? 0); }
                foreach ($this->expenseRows as $key => $row) { $sumOut += (float) ($this->data[$year][$month]['out'][$key] ?? 0); }
                foreach ($this->adjustmentRows as $key => $row) { $sumAdj += (float) ($this->data[$year][$month]['adj'][$key] ?? 0); }

                $net = $sumIn - $sumOut;
                $this->totals[$year][$month] = [
                    'start' => $currentBalance,
                    'in' => $sumIn,
                    'out' => $sumOut,
                    'net' => $net,
                    'adj' => $sumAdj,
                    'end' => $currentBalance + $net + $sumAdj
                ];
                $currentBalance = $this->totals[$year][$month]['end'];
            }
        }

        $this->calculateRentabilitaet();
        $this->calculateKapitalbedarf();
        $this->dispatch('update-liquidity-chart', chartData: $this->generateChartData());
    }

    private function calculateRentabilitaet()
    {
        $this->rentabilitaet = [];

        foreach ($this->years as $year) {
            $umsatz = 0; $material = 0; $personal = 0; $raum = 0; $raumneben = 0;
            $fahrzeug = 0; $buero = 0; $werbung = 0; $versicherung = 0;
            $sonstige = 0; $zinsen = 0; $steuern = 0; $abschreibung = 0;

            for ($m = 1; $m <= 12; $m++) {
                $umsatz += (float) ($this->data[$year][$m]['in']['sales'] ?? 0);
                $material += (float) ($this->data[$year][$m]['out']['goods'] ?? 0);
                $personal += (float) ($this->data[$year][$m]['out']['personnel'] ?? 0);
                $raum += (float) ($this->data[$year][$m]['out']['room'] ?? 0);
                $raumneben += (float) ($this->data[$year][$m]['out']['room_extra'] ?? 0);
                $fahrzeug += (float) ($this->data[$year][$m]['out']['vehicle'] ?? 0);
                $buero += (float) ($this->data[$year][$m]['out']['office'] ?? 0);
                $werbung += (float) ($this->data[$year][$m]['out']['marketing'] ?? 0);
                $versicherung += (float) ($this->data[$year][$m]['out']['insurance'] ?? 0);
                $sonstige += (float) ($this->data[$year][$m]['out']['other_out'] ?? 0);
                $zinsen += (float) ($this->data[$year][$m]['out']['interest'] ?? 0);
                $steuern += (float) ($this->data[$year][$m]['out']['taxes'] ?? 0);
            }

            $totalInvestments = 0;
            foreach ($this->years as $y) {
                if ($y <= $year) {
                    for ($m = 1; $m <= 12; $m++) {
                        $totalInvestments += (float) ($this->data[$y][$m]['out']['investments'] ?? 0);
                    }
                }
            }
            $abschreibung = $totalInvestments / 3;

            $rohertrag = $umsatz - $material;
            $summe_kosten = $personal + $raum + $raumneben + $fahrzeug + $buero + $werbung + $versicherung + $abschreibung + $sonstige;
            $betriebsergebnis = $rohertrag - $summe_kosten;
            $ergebnis_vor_steuern = $betriebsergebnis - $zinsen;
            $gewinn = $ergebnis_vor_steuern - $steuern;
            $cashflow = $gewinn + $abschreibung;

            $this->rentabilitaet[$year] = [
                'umsatz' => $umsatz, 'material' => $material, 'rohertrag' => $rohertrag,
                'personal' => $personal, 'raum' => $raum, 'raumneben' => $raumneben,
                'fahrzeug' => $fahrzeug, 'buero' => $buero, 'werbung' => $werbung,
                'versicherung' => $versicherung, 'abschreibung' => $abschreibung,
                'sonstige' => $sonstige, 'betriebsergebnis' => $betriebsergebnis,
                'zinsen' => $zinsen, 'ergebnis_vor_steuern' => $ergebnis_vor_steuern,
                'steuern' => $steuern, 'gewinn' => $gewinn, 'cashflow' => $cashflow,
            ];
        }
    }

    private function calculateKapitalbedarf()
    {
        $this->kapitalbedarf = [
            'investitionen' => [
                'grundstueck' => 0, 'gebaeude' => 0, 'umbau' => 0, 'einrichtung' => 0,
                'maschinen' => 0, 'waren' => 0, 'fahrzeuge' => 0, 'unternehmenswert' => 0, 'sonstiges' => 0,
            ],
            'gruendung' => [
                'werbung' => 0, 'beratung' => 0, 'anmeldungen' => 0, 'handelsregister' => 0, 'notar' => 0, 'sonstiges' => 0,
            ],
            'finanzierung' => [
                'eigenmittel' => $this->startBalance,
                'kontokorrent' => 0, 'darlehen' => 0, 'puffer' => 0,
            ]
        ];

        if (count($this->years) > 0) {
            $firstY = $this->years[0];
            $this->kapitalbedarf['investitionen']['maschinen'] = (float) ($this->data[$firstY][4]['out']['investments'] ?? 0);
            $this->kapitalbedarf['investitionen']['waren'] = (float) ($this->data[$firstY][4]['out']['goods'] ?? 0) + (float) ($this->data[$firstY][5]['out']['goods'] ?? 0);
            $this->kapitalbedarf['gruendung']['werbung'] = (float) ($this->data[$firstY][4]['out']['marketing'] ?? 0);
            $this->kapitalbedarf['gruendung']['beratung'] = (float) ($this->data[$firstY][4]['out']['other_out'] ?? 0);
            $this->kapitalbedarf['finanzierung']['eigenmittel'] += (float) ($this->data[$firstY][4]['adj']['private_in'] ?? 0);
            $this->kapitalbedarf['finanzierung']['darlehen'] = (float) ($this->data[$firstY][4]['adj']['loan'] ?? 0);
        }
    }

    private function getDetailedScore()
    {
        $avgSalesAfterSubsidy = 0;
        if (isset($this->data[2026])) {
            $q4Sales = ($this->data[2026][10]['in']['sales'] ?? 0) + ($this->data[2026][11]['in']['sales'] ?? 0) + ($this->data[2026][12]['in']['sales'] ?? 0);
            $avgSalesAfterSubsidy = $q4Sales / 3;
            $q4Costs = ($this->data[2026][10]['out']['goods'] ?? 0) + ($this->data[2026][10]['out']['marketing'] ?? 0) + ($this->data[2026][10]['out']['private'] ?? 0) +
                ($this->data[2026][11]['out']['goods'] ?? 0) + ($this->data[2026][11]['out']['marketing'] ?? 0) + ($this->data[2026][11]['out']['private'] ?? 0) +
                ($this->data[2026][12]['out']['goods'] ?? 0) + ($this->data[2026][12]['out']['marketing'] ?? 0) + ($this->data[2026][12]['out']['private'] ?? 0);
            $avgCostsAfterSubsidy = $q4Costs / 3;
        } else {
            $avgCostsAfterSubsidy = 0;
        }

        $scoreRent = 0;
        if ($avgCostsAfterSubsidy > 0) {
            $ratio = $avgSalesAfterSubsidy / $avgCostsAfterSubsidy;
            if ($ratio >= 1.5) $scoreRent = 40;
            elseif ($ratio >= 1.2) $scoreRent = 30;
            elseif ($ratio >= 1.0) $scoreRent = 20;
            else $scoreRent = 5;
        }

        $scoreLiq = 30;
        $liqWarning = false;
        foreach($this->years as $y) {
            for($m=1; $m<=12; $m++) {
                if(($this->totals[$y][$m]['end'] ?? 0) < 0) {
                    $scoreLiq = 0;
                    $liqWarning = true;
                    break 2;
                }
            }
        }

        $scoreMarge = 0;
        if (isset($this->rentabilitaet[2027])) {
            $umsatz = $this->rentabilitaet[2027]['umsatz'];
            $gewinn = $this->rentabilitaet[2027]['gewinn'];
            if ($umsatz > 0) {
                $marge = ($gewinn / $umsatz) * 100;
                if ($marge >= 20) $scoreMarge = 30;
                elseif ($marge >= 10) $scoreMarge = 20;
                elseif ($marge > 0) $scoreMarge = 10;
            }
        }

        $totalScore = $scoreRent + $scoreLiq + $scoreMarge;
        $breakEvenDate = 'Oktober ' . ($this->years[0] ?? 2026);

        return [
            'total' => $totalScore,
            'avgSales' => $avgSalesAfterSubsidy,
            'liqWarning' => $liqWarning,
            'breakEvenDate' => $breakEvenDate,
            'details' => [
                ['label' => 'Umsatz-Kosten-Relation (Cashflow)', 'score' => $scoreRent, 'max' => 40, 'color' => '#10b981', 'desc' => 'Bewertet ob die Umsätze nach der Förderung die laufenden Kosten & Entnahmen decken.'],
                ['label' => 'Liquiditäts-Sicherheit (Kassenbestand)', 'score' => $scoreLiq, 'max' => 30, 'color' => '#3b82f6', 'desc' => $liqWarning ? 'KRITISCH: Kontostand rutscht in mindestens einem Monat ins Minus (Insolvenzgefahr).' : 'Der Kassenbestand bleibt über den gesamten Planungszeitraum positiv.'],
                ['label' => 'Gewinn-Marge (Skalierbarkeit)', 'score' => $scoreMarge, 'max' => 30, 'color' => '#8b5cf6', 'desc' => 'Bewertet den prozentualen Gewinn am Umsatz im ersten vollen Geschäftsjahr.'],
            ]
        ];
    }

    private function generateChartData()
    {
        $labels = [];
        $balances = [];

        foreach ($this->years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $labels[] = sprintf('%02d.%02d', $month, $year % 100);
                $balances[] = $this->totals[$year][$month]['end'];
            }
        }

        return ['labels' => $labels, 'balances' => $balances];
    }

    public function exportPdf()
    {
        $timestamp = now()->format('Y-m-d_H-i');
        $filename = 'Liquiditaetsplanung_Seelenfunke_' . $timestamp . '.pdf';

        $scoreData = $this->getDetailedScore();

        $pdf = Pdf::loadView('global.pdf.liquidity-plan', [
            'years' => $this->years,
            'data' => $this->data,
            'totals' => $this->totals,
            'rentabilitaet' => $this->rentabilitaet,
            'kapitalbedarf' => $this->kapitalbedarf,
            'scoreData' => $scoreData,
            'rentRows' => $this->rentRows,
            'receiptRows' => $this->receiptRows,
            'expenseRows' => $this->expenseRows,
            'adjustmentRows' => $this->adjustmentRows,
            'startBalance' => $this->startBalance,
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function render()
    {
        return view('livewire.shop.financial.financial-liquidity-planner');
    }
}
