<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Order\OrderOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

#[Layout('components.layouts.backend_layout')]
class AccountingLiquidity extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    public array $years = [];
    public int $activeYear;
    public array $data = [];
    public array $totals = [];
    public array $rentabilitaet = [];
    public array $kapitalbedarf = [];
    public array $taxCalculations = [];
    public float $startBalance = 0.0;

    // -- KONFIGURATION DYNAMIK --
    public int $configStartYear = 2026;
    public int $configStartMonth = 8;
    public float $configInterestRate = 8.0;
    public int $configRepaymentMonths = 60;
    public bool $configLoadDemoData = true;

    public array $receiptRows = [
        'sales' => ['label' => 'aus Forderungseingängen', 'tooltip' => 'Bezahlte Rechnungen (Shop-Bestellungen und Marktplätze)'],
        'cash' => ['label' => 'bar (-> Sofortzahlung)', 'tooltip' => 'Direkte Verkäufe (z.B. auf Märkten)'],
        'tax_refund' => ['label' => 'Vorsteuererstattung', 'tooltip' => 'Vom Finanzamt erstattete Vorsteuer'],
        'subsidy' => ['label' => 'Zuschüsse (ALG1 / Gründungsz.)', 'tooltip' => 'Staatliche Förderungen in der Anlaufphase (Monate 4-9)'],
        'private_in' => ['label' => 'Private Einnahmen', 'tooltip' => 'Gehalt, Kindergeld und sonstige private Zuflüsse'],
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
        $config = shop_setting('liquidity_planner_config');
        if (is_array($config)) {
            $this->configStartYear = (int)($config['start_year'] ?? 2026);
            $this->configStartMonth = (int)($config['start_month'] ?? 8);
            $this->configInterestRate = (float)($config['interest_rate'] ?? 8.0);
            $this->configRepaymentMonths = (int)($config['repayment_months'] ?? 60);
            $this->configLoadDemoData = (bool)($config['load_demo_data'] ?? true);
        }

        // Geschäftsstart basiert nun dynamisch auf der User-Konfiguration
        $currentYear = $this->configStartYear;
        $this->years = [$currentYear, $currentYear + 1, $currentYear + 2];
        $this->activeYear = $currentYear;

        $this->initEmptyData();
        $this->injectSeelenfunkeBusinessPlan();
        $this->injectLiveData();
        $this->calculate();
    }

    public function updatePlanConfig()
    {
        $config = [
            'start_year' => (int) $this->configStartYear,
            'start_month' => (int) $this->configStartMonth,
            'interest_rate' => (float) $this->configInterestRate,
            'repayment_months' => (int) $this->configRepaymentMonths,
            'load_demo_data' => (bool) $this->configLoadDemoData,
        ];
        
        \App\Models\System\SystemSetting::updateOrCreate(
            ['key' => 'liquidity_planner_config'],
            ['value' => json_encode($config)]
        );
        
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        
        // Vollständigen Re-Init durchführen, da Startjahr oder Demo-Daten geändert worden sein können
        $this->years = [$this->configStartYear, $this->configStartYear + 1, $this->configStartYear + 2];
        $this->activeYear = $this->configStartYear;
        
        $this->initEmptyData();
        $this->injectSeelenfunkeBusinessPlan();
        $this->injectLiveData();
        $this->calculate();
        
        // Neu laden der Chart-Instanz forcieren im Frontend
        $this->dispatch('planner-config-updated');
        $this->dispatch('notify', 'Liquiditätseinstellungen erfolgreich gespeichert.', 'success');
    }

    public function updated($propertyName)
    {
        if (str_starts_with($propertyName, 'config')) {
            $this->updatePlanConfig();
            return;
        }
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
        if (!$this->configLoadDemoData) {
            return; // Komplette Demo-Daten abschalten, wenn der User "Blanko" planen möchte
        }

        $y1 = $this->configStartYear;
        $y2 = $y1 + 1;
        $y3 = $y1 + 2;
        $sm = $this->configStartMonth;

        // Jahr 1 - Start im festgelegten $sm
        if (in_array($y1, $this->years)) {
            $this->data[$y1][$sm]['out']['investments'] = 7815;
            $this->data[$y1][$sm]['out']['other_out'] = 600;

            // ALG 1 Zuschüsse und dazugehörige private Entnahmen
            // werden NICHT mehr hartgecodet, sondern in injectLiveData() dynamisch
            // anhand der "ALG 1 + GZ" Kostenstelle ermittelt.

            // Basis-Privatentnahme für Monate nach ALG 1 (falls ALG 1 schon im y1 endet)
            for ($m = $sm; $m <= 12; $m++) {
                $this->data[$y1][$m]['out']['private'] = 1600;
            }

            // Start Sales im $sm
            if ($sm <= 8) { $this->data[$y1][8]['in']['sales'] = 800;  $this->data[$y1][8]['out']['goods'] = 120; }
            if ($sm <= 9) { $this->data[$y1][9]['in']['sales'] = 1200; $this->data[$y1][9]['out']['goods'] = 180; }
            if ($sm <= 10) { $this->data[$y1][10]['in']['sales'] = 1800; $this->data[$y1][10]['out']['goods'] = 270; }
            if ($sm <= 11) { $this->data[$y1][11]['in']['sales'] = 2000; $this->data[$y1][11]['out']['goods'] = 300; }
            if ($sm <= 12) { $this->data[$y1][12]['in']['sales'] = 2500; $this->data[$y1][12]['out']['goods'] = 375; }
        }

        // Jahr 2
        if (in_array($y2, $this->years)) {
            $salesY2 = [1=>3200, 2=>3800, 3=>2800, 4=>3200, 5=>4200, 6=>2500, 7=>2400, 8=>2500, 9=>3000, 10=>3500, 11=>5200, 12=>5800];
            foreach($salesY2 as $m => $val) {
                $this->data[$y2][$m]['in']['sales'] = $val;
                $this->data[$y2][$m]['out']['goods'] = $val * 0.15;
                $this->data[$y2][$m]['out']['marketing'] = 150;
                $this->data[$y2][$m]['out']['private'] = 1800;
            }
        }

        // Jahr 3
        if (in_array($y3, $this->years)) {
            $salesY3 = [1=>2800, 2=>4500, 3=>3200, 4=>3800, 5=>5000, 6=>3000, 7=>2800, 8=>3000, 9=>3500, 10=>4200, 11=>6000, 12=>6500];
            foreach($salesY3 as $m => $val) {
                $this->data[$y3][$m]['in']['sales'] = $val;
                $this->data[$y3][$m]['out']['goods'] = $val * 0.15;
                $this->data[$y3][$m]['out']['marketing'] = 200;
                $this->data[$y3][$m]['out']['private'] = 2000;
            }
        }

    }

    private function injectLiveData()
    {
        $adminId = Auth::guard('admin')->id();
        $dbData = [];

        $orders = OrderOrder::where('payment_status', 'paid')->get();
        foreach ($orders as $order) {
            $y = $order->created_at->year;
            $m = $order->created_at->month;
            
            if (in_array($y, $this->years)) {
                $dbData[$y][$m]['in']['sales'] = ($dbData[$y][$m]['in']['sales'] ?? 0) + ($order->total_price / 100);
            }
        }

        $groups = AccountingGroup::with('items')->where('admin_id', $adminId)->get();
        foreach ($groups as $group) {
            foreach ($group->items as $item) {
                // Dynamische Behandlung von "ALG / Gründerzuschuss" (maximal robustes Keyword Matching)
                $nameForCheck = mb_strtolower($item->name . ' ' . $group->name . ' ' . (is_string($item->tags) ? $item->tags : json_encode($item->tags)));
                
                if (Str::contains($nameForCheck, ['alg', 'gz', 'gründerzuschuss', 'zuschuss', 'arbeitslos', 'förderung', 'amt', 'agentur'])) {
                    $start = Carbon::parse($item->first_payment_date);
                    $end = $item->last_payment_date ? Carbon::parse($item->last_payment_date) : $start;

                    foreach ($this->years as $y) {
                        for ($m = 1; $m <= 12; $m++) {
                            $checkDate = Carbon::createFromDate($y, $m, 1)->startOfMonth();
                            if ($checkDate->betweenIncluded($start->copy()->startOfMonth(), $end->copy()->startOfMonth())) {
                                $amt = abs($item->amount);
                                $dbData[$y][$m]['in']['subsidy'] = ($dbData[$y][$m]['in']['subsidy'] ?? 0) + $amt;
                            }
                        }
                    }
                    continue; // Skip the standard mapping for this item
                }

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

        $specials = AccountingSpecialIssue::where('admin_id', $adminId)->get();
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
                        } elseif ($key === 'private' && $isFuture && $dbData[$y][$m]['out'][$key] == 0) {
                            // Behalte Businessplan Entnahmen, wenn keine Livedaten vorhanden sind
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
                return ['type' => 'in', 'key' => 'private_in'];
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

    public array $autoInjected = [];

    public function calculate()
    {
        // 1. Reset all auto-injections from previous calculation cycles before recalculating
        foreach ($this->years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                if (isset($this->autoInjected[$year][$month]['adj']['loan'])) {
                    $this->data[$year][$month]['adj']['loan'] = max(0, ($this->data[$year][$month]['adj']['loan'] ?? 0) - $this->autoInjected[$year][$month]['adj']['loan']);
                }
                if (isset($this->autoInjected[$year][$month]['out']['interest'])) {
                    $this->data[$year][$month]['out']['interest'] = max(0, ($this->data[$year][$month]['out']['interest'] ?? 0) - $this->autoInjected[$year][$month]['out']['interest']);
                }
                if (isset($this->autoInjected[$year][$month]['out']['repayment'])) {
                    $this->data[$year][$month]['out']['repayment'] = max(0, ($this->data[$year][$month]['out']['repayment'] ?? 0) - $this->autoInjected[$year][$month]['out']['repayment']);
                }
            }
        }
        $this->autoInjected = []; // Reset for this cycle

        $lastEnd = $this->startBalance;
        $this->totals = [];
        $remainingAutoLoanBalance = 0;
        $monthlyAutoRepaymentRate = 0;

        foreach ($this->years as $year) {
            $this->totals[$year] = [];

            for ($month = 1; $month <= 12; $month++) {
                // A) Vorhandene Restschuld bedienen: Zinsen & Tilgung anwenden
                $autoInterest = 0;
                $autoRepayment = 0;

                if ($remainingAutoLoanBalance > 0) {
                    $autoInterest = $remainingAutoLoanBalance * (($this->configInterestRate / 100) / 12);
                    $autoRepayment = min($monthlyAutoRepaymentRate, $remainingAutoLoanBalance);

                    if ($autoInterest > 0 || $autoRepayment > 0) {
                        $this->data[$year][$month]['out']['interest'] = ($this->data[$year][$month]['out']['interest'] ?? 0) + $autoInterest;
                        $this->data[$year][$month]['out']['repayment'] = ($this->data[$year][$month]['out']['repayment'] ?? 0) + $autoRepayment;

                        $this->autoInjected[$year][$month]['out']['interest'] = $autoInterest;
                        $this->autoInjected[$year][$month]['out']['repayment'] = $autoRepayment;

                        $remainingAutoLoanBalance -= $autoRepayment;
                    }
                }

                $sumIn = 0; $sumOut = 0; $sumAdj = 0;

                foreach ($this->receiptRows as $key => $row) { $sumIn += (float) ($this->data[$year][$month]['in'][$key] ?? 0); }
                foreach ($this->expenseRows as $key => $row) { $sumOut += (float) ($this->data[$year][$month]['out'][$key] ?? 0); }
                foreach ($this->adjustmentRows as $key => $row) { $sumAdj += (float) ($this->data[$year][$month]['adj'][$key] ?? 0); }

                // LOGIK: Smarte Trennung des privaten Cashflows vom Firmen-Cashflow
                $privIn = (float)($this->data[$year][$month]['in']['private_in'] ?? 0) + (float)($this->data[$year][$month]['in']['subsidy'] ?? 0);
                $privOut = (float)($this->data[$year][$month]['out']['private'] ?? 0);
                
                $businessNet = ($sumIn - $privIn) - ($sumOut - $privOut);
                $privateNet = $privIn - $privOut;

                // LOGIK: "Für Doofe" - Einfach und Transparent
                // Wir fügen eine pauschale, realistische Lebenshaltung (Essen, Auto, Freizeit) von 450€ hinzu.
                $this->data[$year][$month]['out']['private'] = ($this->data[$year][$month]['out']['private'] ?? 0) + 450;
                $privOut = $this->data[$year][$month]['out']['private'];
                
                // Ab 2027/28 muss die Firma als echte Lebensgrundlage ein festes Mindestgehalt abwerfen
                if ($year == 2027 && $privOut < 1800) {
                    $this->data[$year][$month]['out']['private'] += (1800 - $privOut);
                } else if ($year >= 2028 && $privOut < 2000) {
                    $this->data[$year][$month]['out']['private'] += (2000 - $privOut);
                }

                $sumIn = 0; $sumOut = 0; $sumAdj = 0;

                foreach ($this->receiptRows as $key => $row) { $sumIn += (float) ($this->data[$year][$month]['in'][$key] ?? 0); }
                foreach ($this->expenseRows as $key => $row) { $sumOut += (float) ($this->data[$year][$month]['out'][$key] ?? 0); }
                foreach ($this->adjustmentRows as $key => $row) { $sumAdj += (float) ($this->data[$year][$month]['adj'][$key] ?? 0); }

                $net = $sumIn - $sumOut;
                $preEnd = $lastEnd + $net + $sumAdj;

                // B) Automatische Darlehens-Injection inkl. Iterationsschleife zur Absicherung der 1. Rate
                $addedLoanThisMonth = 0;
                while ($preEnd < 0 && ($year > $this->configStartYear || ($year == $this->configStartYear && $month >= $this->configStartMonth))) {
                    $neededLoan = abs($preEnd);
                    $increment = max(ceil($neededLoan / 1000) * 1000, 3000); // Minimum 3000€
                    
                    if ($addedLoanThisMonth > 0) {
                        $increment = max(ceil($neededLoan / 1000) * 1000, 1000); // Wenn nach der 1. Rate noch Geld fehlt
                    }

                    $addedLoanThisMonth += $increment;
                    $this->data[$year][$month]['adj']['loan'] = ($this->data[$year][$month]['adj']['loan'] ?? 0) + $increment;
                    $sumAdj += $increment;
                    $this->autoInjected[$year][$month]['adj']['loan'] = ($this->autoInjected[$year][$month]['adj']['loan'] ?? 0) + $increment;

                    // IHK Konform: Zinsen und Tilgung fangen direkt ab dem Startmonat an!
                    $newInterest = $increment * (($this->configInterestRate / 100) / 12); // Dynamischer Zins
                    $newRepayment = $this->configRepaymentMonths > 0 ? $increment / $this->configRepaymentMonths : 0; // Dynamische Regeltilgung

                    $this->data[$year][$month]['out']['interest'] = ($this->data[$year][$month]['out']['interest'] ?? 0) + $newInterest;
                    $this->data[$year][$month]['out']['repayment'] = ($this->data[$year][$month]['out']['repayment'] ?? 0) + $newRepayment;
                    $sumOut += ($newInterest + $newRepayment);

                    $this->autoInjected[$year][$month]['out']['interest'] = ($this->autoInjected[$year][$month]['out']['interest'] ?? 0) + $newInterest;
                    $this->autoInjected[$year][$month]['out']['repayment'] = ($this->autoInjected[$year][$month]['out']['repayment'] ?? 0) + $newRepayment;

                    // Restschuld für kommende Monate aktualisieren (Neue Schuld abzgl. 1. im gleichen Monat gezahlter Rate)
                    $remainingAutoLoanBalance += ($increment - $newRepayment);
                    $monthlyAutoRepaymentRate += $newRepayment;

                    // Werte iterieren und neu prüfen, ob der Liquiditäts-Puffer nach der ersten Rate noch ausreichend ist
                    $net = $sumIn - $sumOut;
                    $preEnd = $lastEnd + $net + $sumAdj;
                }

                $this->totals[$year][$month] = [
                    'start' => $lastEnd,
                    'in'  => $sumIn,
                    'out' => $sumOut,
                    'adj' => $sumAdj,
                    'net' => $net,
                    'end' => $preEnd
                ];

                $lastEnd = $this->totals[$year][$month]['end'];
            }
        }

        $this->calculateRentabilitaet();
        $this->calculateKapitalbedarf();
        $this->calculateTaxes();
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
            $sm = $this->configStartMonth;
            
            $this->kapitalbedarf['investitionen']['maschinen'] = (float) ($this->data[$firstY][$sm]['out']['investments'] ?? 0);
            $nextMonth = $sm < 12 ? $sm + 1 : 12;
            $this->kapitalbedarf['investitionen']['waren'] = (float) ($this->data[$firstY][$sm]['out']['goods'] ?? 0) + (float) ($this->data[$firstY][$nextMonth]['out']['goods'] ?? 0);
            $this->kapitalbedarf['gruendung']['werbung'] = (float) ($this->data[$firstY][$sm]['out']['marketing'] ?? 0);
            $this->kapitalbedarf['gruendung']['beratung'] = (float) ($this->data[$firstY][$sm]['out']['other_out'] ?? 0);
            
            $totalPrivateIn = 0;
            $totalLoan = 0;
            for ($m = 1; $m <= 12; $m++) {
                $totalPrivateIn += (float) ($this->data[$firstY][$m]['in']['private_in'] ?? 0);
                $totalLoan += (float) ($this->data[$firstY][$m]['adj']['loan'] ?? 0);
            }
            
            $this->kapitalbedarf['finanzierung']['eigenmittel'] += $totalPrivateIn;
            $this->kapitalbedarf['finanzierung']['darlehen'] = $totalLoan;
        }
    }

    private function calculateTaxes()
    {
        $this->taxCalculations = [
            'vat' => [],
            'trade_tax' => [],
            'income_tax' => []
        ];

        foreach ($this->years as $year) {
            $yearVatSum = 0;
            $this->taxCalculations['vat'][$year] = [];

            for ($m = 1; $m <= 12; $m++) {
                // Umsatzsteuer (USt) Kalkulation:
                // Zu vereinfachungszwecken rechnen wir mit dem Standardsteuersatz aus Sales (brutto = Einnahmen) USt.
                // Und Vorsteuer aus ausgaben, die idR. Standardsteuer haben (Goods, Investments, Marketing, Office, RoomExtra)
                $taxRate = (float)shop_setting('default_tax_rate', 19.0);
                $taxFactor = 1 + ($taxRate / 100);

                $bruttoSales = (float) ($this->data[$year][$m]['in']['sales'] ?? 0);
                $ustKunde = $bruttoSales - ($bruttoSales / $taxFactor);

                $bruttoAusgaben = (float) ($this->data[$year][$m]['out']['goods'] ?? 0)
                    + (float) ($this->data[$year][$m]['out']['investments'] ?? 0)
                    + (float) ($this->data[$year][$m]['out']['marketing'] ?? 0)
                    + (float) ($this->data[$year][$m]['out']['office'] ?? 0)
                    + (float) ($this->data[$year][$m]['out']['room_extra'] ?? 0);

                $vorsteuer = $bruttoAusgaben - ($bruttoAusgaben / $taxFactor);
                $zahllast = $ustKunde - $vorsteuer;

                $this->taxCalculations['vat'][$year][$m] = [
                    'ust' => $ustKunde,
                    'vorsteuer' => $vorsteuer,
                    'zahllast' => $zahllast
                ];
                $yearVatSum += $zahllast;
            }

            $this->taxCalculations['vat'][$year]['total'] = $yearVatSum;

            // Gewerbesteuer (GewSt) Kalkulation:
            // Gewinn aus der Rentabilitätsvorschau
            $gewinn = $this->rentabilitaet[$year]['gewinn'] ?? 0;

            // Freibetrag 24.500 EUR
            $gewerbeErtrag = max(0, $gewinn - 24500);
            
            // Steuermessbetrag (3,5%)
            $messbetrag = $gewerbeErtrag * 0.035;

            // Hebesatz Gifhorn (ca. 380%)
            $gewerbesteuer = $messbetrag * 3.8;

            $this->taxCalculations['trade_tax'][$year] = [
                'gewinn' => $gewinn,
                'freibetrag' => 24500,
                'ertrag' => $gewerbeErtrag,
                'steuer' => $gewerbesteuer
            ];

            // Einkommensteuer (ESt) Kalkulation:
            // Vereinfachte Formel mit Progressionsvorbehalt
            $subsidy = 0;
            for ($m = 1; $m <= 12; $m++) {
                $subsidy += (float) ($this->data[$year][$m]['in']['subsidy'] ?? 0);
            }

            $zuVersteuerndesEinkommen = max(0, $gewinn - $gewerbesteuer);
            $progressionsBasis = $zuVersteuerndesEinkommen + $subsidy;

            // Vereinfachter ESt-Tarif (Grundtabelle 2024+ Basis)
            $estSatz = 0;
            if ($progressionsBasis > 11604) {
               if ($progressionsBasis <= 66760) {
                   $estSatz = 0.14 + (($progressionsBasis - 11604) / 55156) * 0.28; // Ansteigend 14% bis 42%
               } else {
                   $estSatz = 0.42;
               }
            }

            $einkommensteuer = $zuVersteuerndesEinkommen * $estSatz;

            $this->taxCalculations['income_tax'][$year] = [
                'zvE' => $zuVersteuerndesEinkommen,
                'subsidy' => $subsidy,
                'steuersatz' => $estSatz * 100, // in %
                'steuer' => $einkommensteuer
            ];
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
            if ($ratio >= 1.3) $scoreRent = 40;
            elseif ($ratio >= 1.1) $scoreRent = 30;
            elseif ($ratio >= 0.9) $scoreRent = 20;
            else $scoreRent = 10;
        }

        $scoreLiq = 30;
        $liqWarning = false;
        $preLaunchDeficit = false;

        foreach($this->years as $y) {
            for($m=1; $m<=12; $m++) {
                if(($this->totals[$y][$m]['end'] ?? 0) < -10) { // Toleranz von 10€
                    if ($y == 2026 && $m < 8) {
                        $preLaunchDeficit = true;
                    } else {
                        $scoreLiq = 0;
                        $liqWarning = true;
                        break 2;
                    }
                }
            }
        }

        if ($preLaunchDeficit && !$liqWarning) {
            $scoreLiq = 20; // 10 Punkte Abzug für Vorfinanzierung, aber keine Insolvenzgefahr
        }

        $liqColor = '#3b82f6';
        if ($liqWarning) {
            $liqDesc = 'KRITISCH: Kontostand rutscht nach der eigentlichen Gründung ins Minus (Insolvenzgefahr). Anpassung der Finanzierungsbausteine (z.B. Erhöhung Darlehen) zwingend erforderlich!';
            $liqColor = '#ef4444';
        } elseif ($preLaunchDeficit) {
            $liqDesc = 'HINWEIS: Leichte Unterdeckung in der Vorgründungsphase aufgrund von Erstinvestitionen/Rüstkosten (Vorfinanzierung aus privaten Rücklagen). Dies ist in der E-Commerce Vorbereitungsphase branchenüblich und unkritisch, da sich der Kassenbestand zum offiziellen Start stabilisiert.';
            $liqColor = '#f59e0b';
        } else {
            $liqDesc = 'SEHR GUT: Der Kassenbestand bleibt über den gesamten Planungs- und Vorbereitungszeitraum durchgehend im positiven Bereich. Die Zahlungsfähigkeit (Liquidität) ist jederzeit gewährleistet.';
            $liqColor = '#10b981';
        }

        $scoreMarge = 0;
        if (isset($this->rentabilitaet[2027])) {
            $umsatz = $this->rentabilitaet[2027]['umsatz'];
            $gewinn = $this->rentabilitaet[2027]['gewinn'];
            if ($umsatz > 0) {
                $marge = ($gewinn / $umsatz) * 100;
                if ($marge >= 15) $scoreMarge = 30;
                elseif ($marge >= 5) $scoreMarge = 20;
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
                ['label' => 'Umsatz-Kosten-Relation', 'score' => $scoreRent, 'max' => 40, 'color' => '#10b981', 'desc' => 'Bewertet ob die Einnahmen nach Auslauf der Gründungsszuschüsse die laufenden Geschäftskosten decken (Positiver Cashflow).'],
                ['label' => 'Liquiditäts-Sicherheit', 'score' => $scoreLiq, 'max' => 30, 'color' => $liqColor, 'desc' => $liqDesc],
                ['label' => 'Gewinn-Marge (Skalierbarkeit)', 'score' => $scoreMarge, 'max' => 30, 'color' => '#8b5cf6', 'desc' => 'Bewertet den prozentualen Gewinn am Umsatz im ersten vollen Geschäftsjahr (ab 15% sehr gut).'],
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

        $taxCharts = [
            'years' => [],
            'vat' => [],
            'trade' => [],
            'income' => []
        ];

        foreach ($this->years as $year) {
            $taxCharts['years'][] = (string) $year;
            $taxCharts['vat'][] = $this->taxCalculations['vat'][$year]['total'] ?? 0;
            $taxCharts['trade'][] = $this->taxCalculations['trade_tax'][$year]['steuer'] ?? 0;
            $taxCharts['income'][] = $this->taxCalculations['income_tax'][$year]['steuer'] ?? 0;
        }

        return [
            'labels' => $labels, 
            'balances' => $balances,
            'taxCharts' => $taxCharts
        ];
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
            'taxCalculations' => $this->taxCalculations,
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
        return view('livewire.shop.accounting.accounting-liquidity');
    }
}
