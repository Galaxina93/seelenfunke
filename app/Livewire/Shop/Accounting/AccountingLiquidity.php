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
    public int $configStartMonth = 11;
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
        'goods' => ['label' => 'Wareneinsatz & Logistik (30%)', 'tooltip' => 'Material/Rohlinge 15% + Verpackung/Versand 15%'],
        'personnel' => ['label' => 'Personalkosten', 'tooltip' => 'Löhne, Sozialabgaben (Minijobber ab 2029)'],
        'room' => ['label' => 'Raumkosten (Dachboden)', 'tooltip' => 'Miete für Produktionsräume (0 € im Eigenheim)'],
        'room_extra' => ['label' => 'Raumnebenkosten', 'tooltip' => 'Strom für Laser, Heizung'],
        'vehicle' => ['label' => 'Fahrzeugkosten', 'tooltip' => 'Benzin, Leasing, Instandhaltung'],
        'office' => ['label' => 'Büro- & Softwarekosten', 'tooltip' => 'Billbee, Sevdesk, IT, Domains'],
        'marketing/marketing/marketing' => ['label' => 'Marketing & Vertrieb', 'tooltip' => 'Fixes Werbebudget (Ads) + Marktplatzgebühren (Etsy 15%)'],
        'insurance' => ['label' => 'Versicherungen & Beiträge', 'tooltip' => 'Betriebshaftpflicht, IHK, Verpackungslizenz (LUCID)'],
        'taxes' => ['label' => 'Ertragssteuern', 'tooltip' => 'Gewerbesteuer, Einkommensteuer Vorauszahlungen'],
        'interest' => ['label' => 'Zinsen (Darlehen: 0 €)', 'tooltip' => 'Zinsen für Darlehen (0 € bei 100% Eigenmitteln)'],
        'repayment' => ['label' => 'Tilgungen (Darlehen: 0 €)', 'tooltip' => 'Tilgungsraten von Krediten (0 €)'],
        'tax_payment' => ['label' => 'Umsatzsteuer', 'tooltip' => 'Umsatzsteuerzahllast an das Finanzamt'],
        'private' => ['label' => 'Private Ausgaben (Lebenshaltung)', 'tooltip' => 'Planmäßige Privatentnahmen aus erwirtschaftetem Gewinn'],
        'other_out' => ['label' => 'Sonstige Kosten', 'tooltip' => 'Instandhaltung Laser (30 €) + Steuerberater (120 €)'],
    ];

    public array $adjustmentRows = [
        'loan' => ['label' => 'Darlehen', 'tooltip' => 'Auszahlung eines Bankkredits'],
        'overdraft' => ['label' => 'Kontokorrentkredit', 'tooltip' => 'Nutzung des Dispos'],
    ];

    public array $rentRows = [
        'brutto_umsatz' => 'Umsatzerlöse (Brutto inkl. 19% USt)',
        'ust' => 'abzüglich 19% Umsatzsteuer',
        'umsatz' => 'Umsatzerlöse (Netto)',
        'material' => 'Material- & Wareneinsatz (15% Netto)',
        'versand' => 'Versand- & Verpackungskosten (15% Netto)',
        'gebuehren' => 'Vertriebs- & Marktplatzgebühren (15% Netto)',
        'rohertrag' => 'Rohertrag (Deckungsbeitrag I, 55% Netto)',
        'personal' => 'Personalkosten (Minijobber ab 2029)',
        'raumneben' => 'Raumnebenkosten (Energie Laser, Heizung)',
        'buero' => 'Büro- & Softwarekosten (Billbee, Sevdesk, IT)',
        'werbung' => 'Marketing & Werbung (Werbebudget Ads)',
        'versicherung' => 'Versicherungen & LUCID-Lizenz',
        'sonstige' => 'Sonstige Kosten (Instandhaltung 30 € + Steuerberater 120 €)',
        'abschreibung' => 'Abschreibungen (lineare AfA Maschinen)',
        'betriebsergebnis' => 'Betriebsergebnis (EÜR vor Steuern)',
        'zinsen' => 'Zinsaufwendungen (0 € bei 100% Eigenmitteln)',
        'gewinn' => 'Gewerblicher Jahresgewinn (EÜR vor Ertragsteuern)',
        'cashflow' => 'Cash-Flow (Gewinn + Abschreibungen)',
    ];

    public function mount()
    {
        $config = shop_setting('liquidity_planner_config');
        if (is_array($config)) {
            $this->configStartYear = (int)($config['start_year'] ?? 2026);
            $this->configStartMonth = (int)($config['start_month'] ?? 11);
            $this->configInterestRate = (float)($config['interest_rate'] ?? 8.0);
            $this->configRepaymentMonths = (int)($config['repayment_months'] ?? 60);
            $this->configLoadDemoData = (bool)($config['load_demo_data'] ?? true);
        }

        // Geschäftsstart basiert nun dynamisch auf der User-Konfiguration
        $currentYear = $this->configStartYear;
        $this->years = [$currentYear, $currentYear + 1, $currentYear + 2, $currentYear + 3];
        $this->activeYear = $currentYear;

        $this->initEmptyData();
        if ($this->configLoadDemoData) {
            $this->injectSeelenfunkeBusinessPlan();
        } else {
            $this->injectLiveData();
        }
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
        $this->years = [$this->configStartYear, $this->configStartYear + 1, $this->configStartYear + 2, $this->configStartYear + 3];
        $this->activeYear = $this->configStartYear;
        
        $this->initEmptyData();
        if ($this->configLoadDemoData) {
            $this->injectSeelenfunkeBusinessPlan();
        } else {
            $this->injectLiveData();
        }
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
        if ($this->configLoadDemoData) {
            $this->injectSeelenfunkeBusinessPlan();
        } else {
            $this->injectLiveData();
        }
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
        if ($this->configLoadDemoData) {
            $this->injectSeelenfunkeBusinessPlan();
        } else {
            $this->injectLiveData();
        }
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

        $this->startBalance = 7815.00; // 100 % vorhandene Eigenmittel auf dem Geschäftskonto

        $y1 = $this->configStartYear;     // 2026
        $y2 = $y1 + 1;                    // 2027
        $y3 = $y1 + 2;                    // 2028
        $y4 = $y1 + 3;                    // 2029
        $sm = $this->configStartMonth;    // 11 (November)

        // Jahr 1 (2026) - Start im festgelegten $sm (November)
        if (in_array($y1, $this->years)) {
            // Investitionen Start: Maschinen 5.000 € (Laser 4.550 + Lüfter 200 + Labeldrucker 250) + Einrichtung 665 € (Arbeitsplatz 450 + Schutzbrille 65 + Dachboden 150) = 5.665 €
            $this->data[$y1][$sm]['out']['investments'] = 5665;
            $this->data[$y1][$sm]['out']['goods'] = 1000; // Warenerstbestand / Muster-Rohlinge
            $this->data[$y1][$sm]['out']['other_out'] = 600; // Schulung Laserschutzbeauftragter nach OStrV
            // Sicherheitsreserve 550 € verbleibt im Kassenbestand

            // 100% Eigenmittelfinanzierung aus vorhandenen Rücklagen (0 € Bankdarlehen)
            $this->data[$y1][$sm]['adj']['loan'] = 0;

            // Soft-Launch / Weihnachtsgeschäft im November und Dezember 2026
            if ($sm <= 11) {
                $this->data[$y1][11]['in']['sales'] = 2000;
                $this->data[$y1][11]['out']['goods'] = 600; // 30% Material & Versand
                $this->data[$y1][11]['out']['marketing/marketing/marketing'] = 400; // 100 € Ads + 300 € (15% Etsy)
                $this->data[$y1][11]['out']['room_extra'] = 20;
                $this->data[$y1][11]['out']['office'] = 45;
                $this->data[$y1][11]['out']['insurance'] = 45;
                $this->data[$y1][11]['out']['other_out'] = ($this->data[$y1][11]['out']['other_out'] ?? 0) + 150; // StB 120 + Instandhaltung 30
            }
            if ($sm <= 12) {
                $this->data[$y1][12]['in']['sales'] = 2500;
                $this->data[$y1][12]['out']['goods'] = 750; // 30% Material & Versand
                $this->data[$y1][12]['out']['marketing/marketing/marketing'] = 475; // 100 € Ads + 375 € (15% Etsy)
                $this->data[$y1][12]['out']['room_extra'] = 20;
                $this->data[$y1][12]['out']['office'] = 45;
                $this->data[$y1][12]['out']['insurance'] = 45;
                $this->data[$y1][12]['out']['other_out'] = 150;
            }

            // Private Entnahmen im Nov/Dez 2026: 0 € nötig, da Gründungszuschuss (2.106,30 €) + Mieteinnahmen (620 €) den privaten Bedarf (ca. 1.850 €) voll decken
            for ($m = $sm; $m <= 12; $m++) {
                $this->data[$y1][$m]['out']['private'] = 0;
            }
        }

        // Jahr 2 (2027) - 1. volles Geschäftsjahr (Etablierung & Kundenstamm) - 41.100 €
        if (in_array($y2, $this->years)) {
            $salesY2 = [1=>2200, 2=>3800, 3=>2800, 4=>3200, 5=>4200, 6=>2500, 7=>2400, 8=>2500, 9=>3000, 10=>3500, 11=>5200, 12=>5800];
            foreach($salesY2 as $m => $val) {
                $this->data[$y2][$m]['in']['sales'] = $val;
                // Wareneinsatz & Verpackung (15%) + Versandlogistik (15%) = 30%
                $this->data[$y2][$m]['out']['goods'] = round($val * 0.30, 2);
                // Fixes Marketing (100 € Ads) + Marktplatzgebühren (15% Etsy = round($val * 0.15, 2))
                $this->data[$y2][$m]['out']['marketing/marketing/marketing'] = 100 + round($val * 0.15, 2);
                $this->data[$y2][$m]['out']['room_extra'] = 20;
                $this->data[$y2][$m]['out']['office'] = 45;
                $this->data[$y2][$m]['out']['insurance'] = 45;
                $this->data[$y2][$m]['out']['other_out'] = 150; // Instandhaltung 30 € + StB 120 €
                // Bis April 2027 läuft Gründungszuschuss Phase 1 (2.106,30 €) -> keine Entnahme nötig. Ab Mai 2027: 1.100 € Entnahme.
                $this->data[$y2][$m]['out']['private'] = ($m <= 4) ? 0 : 1100;
            }
        }

        // Jahr 3 (2028) - 2. Geschäftsjahr (Skalierung & Stammkunden) - 48.300 €
        if (in_array($y3, $this->years)) {
            $salesY3 = [1=>2800, 2=>4500, 3=>3200, 4=>3800, 5=>5000, 6=>3000, 7=>2800, 8=>3000, 9=>3500, 10=>4200, 11=>6000, 12=>6500];
            foreach($salesY3 as $m => $val) {
                $this->data[$y3][$m]['in']['sales'] = $val;
                $this->data[$y3][$m]['out']['goods'] = round($val * 0.30, 2);
                $this->data[$y3][$m]['out']['marketing/marketing/marketing'] = 150 + round($val * 0.15, 2);
                $this->data[$y3][$m]['out']['room_extra'] = 20;
                $this->data[$y3][$m]['out']['office'] = 45;
                $this->data[$y3][$m]['out']['insurance'] = 45;
                $this->data[$y3][$m]['out']['other_out'] = 150;
                $this->data[$y3][$m]['out']['private'] = 1300;
            }
        }

        // Jahr 4 (2029) - 3. Geschäftsjahr (Optimierung, B2B & Personalvariante) - 53.600 €
        if (in_array($y4, $this->years)) {
            $salesY4 = [1=>3200, 2=>4900, 3=>3600, 4=>4100, 5=>5400, 6=>3500, 7=>3100, 8=>3200, 9=>3900, 10=>4800, 11=>6800, 12=>7100];
            foreach($salesY4 as $m => $val) {
                $this->data[$y4][$m]['in']['sales'] = $val;
                $this->data[$y4][$m]['out']['goods'] = round($val * 0.30, 2);
                // Personalvariante: 520 € Minijobber / Aushilfe für Packstraße & Versand
                $this->data[$y4][$m]['out']['personnel'] = 520;
                $this->data[$y4][$m]['out']['marketing/marketing/marketing'] = 200 + round($val * 0.15, 2);
                $this->data[$y4][$m]['out']['room_extra'] = 25;
                $this->data[$y4][$m]['out']['office'] = 55;
                $this->data[$y4][$m]['out']['insurance'] = 45;
                $this->data[$y4][$m]['out']['other_out'] = 160;
                $this->data[$y4][$m]['out']['private'] = 1000;
            }
        }

    }

    private function injectLiveData()
    {
        $adminId = Auth::guard('admin')->id() ?? \Illuminate\Support\Facades\DB::table('admins')->value('id');
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

                $rowType = ($group->type === 'income') ? 'receipt' : 'expense';
                $mapping = $this->mapItemToRow($item->name, $group->name, $item->is_business, $rowType, $item->tags);
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
                        if ($key === 'goods' && ($y > $this->configStartYear || ($y == $this->configStartYear && $m >= $this->configStartMonth))) {
                            // Behalte berechneten Wareneinsatz des Businessplans
                            continue;
                        }
                        if (in_array($key, ['goods', 'marketing/marketing/marketing', 'investments', 'other_out', 'office', 'room_extra', 'insurance']) && $isFuture && $dbData[$y][$m]['out'][$key] == 0) {
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

        if (Str::contains($text, ['verpackungslizenz', 'lizenzero', 'lucid'])) {
            return ['type' => 'out', 'key' => 'insurance'];
        }

        if (Str::contains($tagsString, ['versicherung', 'versicherungen']) || Str::contains($text, ['versicherung', 'beitrag', 'haftpflicht', 'arbeitslos'])) {
            return ['type' => 'out', 'key' => 'insurance'];
        }

        if (Str::contains($text, ['investition', 'anschaffung', 'laser', 'technik'])) return ['type' => 'out', 'key' => 'investments'];
        if (Str::contains($text, ['waren', 'rohling', 'material', 'verpackung', 'glas', 'schiefer', 'porto', 'dhl', 'logistik'])) return ['type' => 'out', 'key' => 'goods'];
        if (Str::contains($text, ['lohn', 'gehalt', 'personal'])) return ['type' => 'out', 'key' => 'personnel'];
        if (Str::contains($text, ['miete', 'raum'])) return ['type' => 'out', 'key' => 'room'];
        if (Str::contains($text, ['büro', 'telefon', 'internet', 'software', 'lizenz', 'server', 'hosting'])) return ['type' => 'out', 'key' => 'office'];
        if (Str::contains($text, ['marketing/marketing/marketing', 'werbung', 'ads', 'tiktok', 'instagram'])) return ['type' => 'out', 'key' => 'marketing/marketing/marketing'];
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
                if (isset($this->autoInjected[$year][$month]['out']['private_extra'])) {
                    $this->data[$year][$month]['out']['private'] = max(0, ($this->data[$year][$month]['out']['private'] ?? 0) - $this->autoInjected[$year][$month]['out']['private_extra']);
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

                        $this->autoInjected[$year][$month]['out']['interest'] = ($this->autoInjected[$year][$month]['out']['interest'] ?? 0) + $autoInterest;
                        $this->autoInjected[$year][$month]['out']['repayment'] = ($this->autoInjected[$year][$month]['out']['repayment'] ?? 0) + $autoRepayment;

                        $remainingAutoLoanBalance -= $autoRepayment;
                    }
                }

                // A2) Neues/Manuelles Darlehen in diesem Monat (z.B. Start-Darlehen für Maschinen)
                $baseLoan = max(0, (float)($this->data[$year][$month]['adj']['loan'] ?? 0) - (float)($this->autoInjected[$year][$month]['adj']['loan'] ?? 0));
                if ($baseLoan > 0) {
                    $baseInterest = $baseLoan * (($this->configInterestRate / 100) / 12);
                    $baseRepayment = $this->configRepaymentMonths > 0 ? $baseLoan / $this->configRepaymentMonths : 0;

                    $this->data[$year][$month]['out']['interest'] = ($this->data[$year][$month]['out']['interest'] ?? 0) + $baseInterest;
                    $this->data[$year][$month]['out']['repayment'] = ($this->data[$year][$month]['out']['repayment'] ?? 0) + $baseRepayment;

                    $this->autoInjected[$year][$month]['out']['interest'] = ($this->autoInjected[$year][$month]['out']['interest'] ?? 0) + $baseInterest;
                    $this->autoInjected[$year][$month]['out']['repayment'] = ($this->autoInjected[$year][$month]['out']['repayment'] ?? 0) + $baseRepayment;

                    $remainingAutoLoanBalance += ($baseLoan - $baseRepayment);
                    $monthlyAutoRepaymentRate += $baseRepayment;
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

                // LOGIK: Lebenshaltungskosten
                // Während der 6-monatigen Gründungszuschuss-Phase (11/2026 bis 04/2027) deckt der Zuschuss (2.106,30 €) + Mieteinnahmen (595 €) den privaten Lebensunterhalt (ca. 1.850 €) zu 100% ab.
                // Ab Mai 2027 (Auslauf Phase 1) erwirtschaftet die Firma die Lebenshaltung der Gründerin (mind. 1.600 € in 2027, 1.800 € in 2028, 2.000 € in 2029).
                $isSubsidyPhase = ($year == 2026 && $month >= 11) || ($year == 2027 && $month <= 4);
                $extraPrivate = 0;
                $currentPrivOut = (float)($this->data[$year][$month]['out']['private'] ?? 0);

                if ($isSubsidyPhase) {
                    $extraPrivate = 0;
                } elseif ($year == $this->configStartYear && $month < $this->configStartMonth) {
                    $extraPrivate = 0; // Vor der Gründung keine Firmenentnahmen
                } elseif ($year == 2027) {
                    if ($currentPrivOut < 1600) {
                        $extraPrivate = 1600 - $currentPrivOut;
                    }
                } elseif ($year == 2028) {
                    if ($currentPrivOut < 1800) {
                        $extraPrivate = 1800 - $currentPrivOut;
                    }
                } elseif ($year >= 2029) {
                    if ($currentPrivOut < 2000) {
                        $extraPrivate = 2000 - $currentPrivOut;
                    }
                }

                $this->data[$year][$month]['out']['private'] = $currentPrivOut + $extraPrivate;
                $this->autoInjected[$year][$month]['out']['private_extra'] = $extraPrivate;
                $privOut = $this->data[$year][$month]['out']['private'];

                // Re-sum out nach Berücksichtigung des privaten Mindestbedarfs
                $sumOut = 0;
                foreach ($this->expenseRows as $key => $row) { $sumOut += (float) ($this->data[$year][$month]['out'][$key] ?? 0); }

                $net = $sumIn - $sumOut;
                $preEnd = $lastEnd + $net + $sumAdj;

                // B) Automatische Darlehens-Injection inkl. Iterationsschleife zur Absicherung der 1. Rate (Notfall)
                $addedLoanThisMonth = 0;
                while (!$this->configLoadDemoData && $preEnd < 0 && ($year > $this->configStartYear || ($year == $this->configStartYear && $month >= $this->configStartMonth))) {
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
            $bruttoUmsatz = 0;
            $personal = 0; $raumneben = 0; $buero = 0; $versicherung = 0;
            $sonstige = 0; $zinsen = 0;

            for ($m = 1; $m <= 12; $m++) {
                $bruttoUmsatz += (float) ($this->data[$year][$m]['in']['sales'] ?? 0);
                $personal += (float) ($this->data[$year][$m]['out']['personnel'] ?? 0);
                $raumneben += (float) ($this->data[$year][$m]['out']['room_extra'] ?? 0);
                $buero += (float) ($this->data[$year][$m]['out']['office'] ?? 0);
                $versicherung += (float) ($this->data[$year][$m]['out']['insurance'] ?? 0);
                $sonstige += (float) ($this->data[$year][$m]['out']['other_out'] ?? 0);
                $zinsen += (float) ($this->data[$year][$m]['out']['interest'] ?? 0);
            }

            // Fixes Werbebudget (Ads) getrennt von variablen Marktplatzgebühren
            if ($year == 2026) $werbungFix = 200.00;
            elseif ($year == 2027) $werbungFix = 1200.00;
            elseif ($year == 2028) $werbungFix = 1800.00;
            elseif ($year == 2029) $werbungFix = 2400.00;
            else $werbungFix = 0.00;

            // AfA linear über 5 Jahre (Anschaffungskosten Maschinen/Technik = 5.665 €)
            if ($year == $this->configStartYear) {
                $abschreibung = round((5665 / 5) * (2 / 12), 2); // 188,83 € für Nov/Dez 2026
            } else {
                $abschreibung = 1133.00; // 5.665 € / 5 Jahre = 1.133,00 € p.a.
            }

            // Exakte Netto-Berechnung nach § 4 Abs. 3 EStG (EÜR)
            $ust = round($bruttoUmsatz - ($bruttoUmsatz / 1.19), 2);
            $nettoUmsatz = round($bruttoUmsatz / 1.19, 2);

            // Variable Kosten: 15% Material, 15% Versand, 15% Marktplatzgebühren (jeweils vom Nettoumsatz)
            $material = round($nettoUmsatz * 0.15, 2);
            $versand = round($nettoUmsatz * 0.15, 2);
            $gebuehren = round($nettoUmsatz * 0.15, 2);
            $variableGesamt = $material + $versand + $gebuehren;

            $rohertrag = round($nettoUmsatz - $variableGesamt, 2); // 55% Deckungsbeitrag
            $fixkostenGesamt = $personal + $raumneben + $buero + $werbungFix + $versicherung + $abschreibung + $sonstige;
            
            $betriebsergebnis = round($rohertrag - $fixkostenGesamt, 2);
            $ergebnis_vor_steuern = round($betriebsergebnis - $zinsen, 2);
            $gewinn = $ergebnis_vor_steuern; // EÜR vor persönlichen Ertragsteuern
            $cashflow = round($gewinn + $abschreibung, 2);

            $this->rentabilitaet[$year] = [
                'brutto_umsatz' => $bruttoUmsatz,
                'ust' => $ust,
                'umsatz' => $nettoUmsatz,
                'material' => $material,
                'versand' => $versand,
                'gebuehren' => $gebuehren,
                'variable_gesamt' => $variableGesamt,
                'rohertrag' => $rohertrag,
                'personal' => $personal,
                'raum' => 0.00,
                'raumneben' => $raumneben,
                'fahrzeug' => 0.00,
                'buero' => $buero,
                'werbung' => $werbungFix,
                'versicherung' => $versicherung,
                'abschreibung' => $abschreibung,
                'sonstige' => $sonstige,
                'betriebsergebnis' => $betriebsergebnis,
                'zinsen' => $zinsen,
                'ergebnis_vor_steuern' => $ergebnis_vor_steuern,
                'steuern' => 0.00,
                'gewinn' => $gewinn,
                'cashflow' => $cashflow,
            ];
        }
    }

    private function calculateKapitalbedarf()
    {
        $this->kapitalbedarf = [
            'investitionen' => [
                'grundstueck' => 0.00,
                'gebaeude' => 0.00,
                'umbau' => 150.00, // Dachboden-Anpassung
                'einrichtung' => 515.00, // Profi-Arbeitsplatz (450 €) + Laserschutzbrille (65 €)
                'maschinen' => 5000.00, // xTool Laser (4.550 €) + Ventilator (200 €) + Labeldrucker (250 €)
                'waren' => 1000.00, // Warenerstbestand & Muster-Rohlinge
                'fahrzeuge' => 0.00,
                'unternehmenswert' => 0.00,
                'sonstiges' => 0.00,
            ],
            'gruendung' => [
                'werbung' => 0.00,
                'beratung' => 600.00, // Schulung Laserschutzbeauftragter nach OStrV
                'anmeldungen' => 0.00,
                'handelsregister' => 0.00,
                'notar' => 0.00,
                'sonstiges' => 550.00, // Eiserne Sicherheitsreserve / Liquiditätspuffer
            ],
            'finanzierung' => [
                'eigenmittel' => 7815.00, // 100 % aus vorhandenen privaten Ersparnissen / Rücklagen
                'kontokorrent' => 0.00,
                'darlehen' => 0.00,
                'puffer' => 0.00,
            ]
        ];
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
                    + (float) ($this->data[$year][$m]['out']['marketing/marketing/marketing'] ?? 0)
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

    public function getDetailedScore()
    {
        $avgSalesAfterSubsidy = 0;
        $avgCostsAfterSubsidy = 0;
        if (isset($this->data[2027])) {
            $yearSales = 0;
            $yearCosts = 0;
            for ($m = 1; $m <= 12; $m++) {
                $yearSales += $this->data[2027][$m]['in']['sales'] ?? 0;
                $yearCosts += ($this->data[2027][$m]['out']['goods'] ?? 0) + 
                              ($this->data[2027][$m]['out']['marketing/marketing/marketing'] ?? 0) + 
                              ($this->data[2027][$m]['out']['office'] ?? 0) + 
                              ($this->data[2027][$m]['out']['insurance'] ?? 0) + 
                              ($this->data[2027][$m]['out']['other_out'] ?? 0) + 
                              ($this->data[2027][$m]['out']['private'] ?? 0);
            }
            $avgSalesAfterSubsidy = round($yearSales / 12, 2);
            $avgCostsAfterSubsidy = round($yearCosts / 12, 2);
        } elseif (isset($this->data[2026])) {
            $q4Sales = ($this->data[2026][11]['in']['sales'] ?? 0) + ($this->data[2026][12]['in']['sales'] ?? 0);
            $avgSalesAfterSubsidy = $q4Sales / 2;
            $q4Costs = ($this->data[2026][11]['out']['goods'] ?? 0) + ($this->data[2026][12]['out']['goods'] ?? 0);
            $avgCostsAfterSubsidy = $q4Costs / 2;
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
                    if ($y == $this->configStartYear && $m < $this->configStartMonth) {
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
