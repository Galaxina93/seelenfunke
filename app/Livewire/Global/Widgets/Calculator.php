<?php

namespace App\Livewire\Global\Widgets;

use App\Mail\CalcCustomer;
use App\Mail\CalcInput;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestItem;
use App\Models\ShippingZone; // Wichtig für die DB-Versandlogik
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;

class Calculator extends Component
{
    use WithFileUploads;

    // --- STEPS ---
    public $step = 0;

    // --- STATE ---
    public $editingIndex = -1;
    public $cartItems = [];
    public $currentConfig = [];
    public $currentProduct = null;

    // --- PREISE & GEWICHT ---
    public $gesamtKosten = 0;
    public $totalNetto = 0;
    public $totalMwst = 0;
    public $totalBrutto = 0;
    public $shippingCost = 0;
    public $totalWeight = 0; // Neu: Für gewichtsbasierte Berechnung

    // --- FORMULAR ---
    public $isExpress = false;
    public $deadline = '';
    public $form = [
        'vorname' => '',
        'nachname' => '',
        'firma' => '',
        'email' => '',
        'telefon' => '',
        'anmerkung' => '',
        'country' => 'DE' // Standard: Deutschland
    ];

    public $dbProducts = [];

    protected $rules = [
        'form.vorname' => 'required|string|max:100',
        'form.nachname' => 'required|string|max:100',
        'form.email' => 'required|email',
        'form.country' => 'required|string|size:2',
        'cartItems' => 'required|array|min:1',
    ];

    public function mount()
    {
        $this->loadProducts();

        if (session()->has('calc_cart')) {
            $this->cartItems = session('calc_cart');
            $this->validateCartItems();

            // Session Form laden
            if (session()->has('calc_form')) {
                $this->form = array_merge($this->form, session('calc_form'));
            }

            $this->calculateTotal();
            if(count($this->cartItems) > 0) $this->step = 1;
        }
    }

    private function validateCartItems()
    {
        $validItems = [];
        foreach($this->cartItems as $item) {
            if(isset($this->dbProducts[$item['product_id']])) {
                $validItems[] = $item;
            }
        }
        if(count($validItems) !== count($this->cartItems)) {
            $this->cartItems = $validItems;
            $this->persist();
        }
    }

    public function loadProducts()
    {
        $products = Product::with('tierPrices')->where('status', 'active')->get();

        $this->dbProducts = $products->map(function($p) {
            $previewPath = $p->preview_image_path;

            if (empty($previewPath) && !empty($p->media_gallery)) {
                foreach($p->media_gallery as $media) {
                    if (($media['type'] ?? '') === 'image') {
                        $previewPath = $media['path'];
                        break;
                    }
                }
            }

            $rawPrice = $p->price / 100;
            $rate = $p->tax_rate ? (float)$p->tax_rate : 19.00;
            $isGross = (bool)$p->tax_included;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'desc' => $p->short_description ?? 'Artikel',
                'price' => $rawPrice,
                'price_cents' => $p->price,
                'display_price' => $rawPrice,
                'tax_rate' => $rate,
                'tax_included' => $isGross,
                'weight' => $p->weight ?? 0,
                'tier_pricing' => $p->tierPrices->map(fn($t) => [
                    'qty' => $t->qty,
                    'percent' => $t->percent
                ])->toArray(),
                'image' => !empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null,
                'preview_image' => $previewPath ? 'storage/'.$previewPath : null,
                'allow_logo' => $p->configurator_settings['allow_logo'] ?? true,
            ];
        })->keyBy('id')->toArray();
    }

    // --- NAVIGATION ---
    public function startCalculator() { $this->step = 1; }

    public function openConfig($productId)
    {
        $this->editingIndex = -1;
        $this->currentProduct = $this->dbProducts[$productId] ?? null;
        if(!$this->currentProduct) return;
        $this->currentConfig = [];
        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function editItem($index)
    {
        if(!isset($this->cartItems[$index])) return;
        $item = $this->cartItems[$index];
        $this->editingIndex = $index;
        $this->currentProduct = $this->dbProducts[$item['product_id']] ?? null;
        if(!$this->currentProduct) return;
        $this->currentConfig = $item['configuration'] ?? [];
        $this->currentConfig['qty'] = $item['qty'];
        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function cancelConfig() {
        $this->currentConfig = [];
        $this->step = 1;
        $this->dispatch('scroll-top');
    }

    #[On('calculator-save')]
    public function saveItemFromConfigurator($data)
    {
        $product = $this->dbProducts[$data['product_id']];
        $rowId = ($this->editingIndex >= 0) ? $this->cartItems[$this->editingIndex]['row_id'] : Str::uuid()->toString();

        $itemData = [
            'row_id' => $rowId,
            'product_id' => $product['id'],
            'name' => $product['name'],
            'image_ref' => $product['image'],
            'qty' => $data['qty'],
            'text' => $data['text'] ?? '',
            'configuration' => $data,
            'preview_ref' => $product['preview_image'] ?? $product['image']
        ];

        if ($this->editingIndex >= 0) {
            $this->cartItems[$this->editingIndex] = array_merge($this->cartItems[$this->editingIndex], $itemData);
        } else {
            $this->cartItems[] = $itemData;
        }

        $this->step = 1;
        $this->dispatch('scroll-top');
        $this->calculateTotal();
        $this->persist();
    }

    public function removeItem($index) {
        unset($this->cartItems[$index]);
        $this->cartItems = array_values($this->cartItems);
        $this->calculateTotal();
        $this->persist();
    }

    // --- KERNLOGIK: PREIS & VERSAND ---
    public function calculateTotal()
    {
        $quantitiesPerProduct = [];
        $this->totalWeight = 0;

        // 1. Mengen und Gewicht summieren
        foreach ($this->cartItems as $item) {
            $pid = $item['product_id'];
            if (!isset($quantitiesPerProduct[$pid])) $quantitiesPerProduct[$pid] = 0;
            $quantitiesPerProduct[$pid] += $item['qty'];

            $product = $this->dbProducts[$pid] ?? null;
            if ($product) {
                // Gewicht addieren (Menge * Einzelgewicht)
                $this->totalWeight += ($product['weight'] * $item['qty']);
            }
        }

        $sumNetto = 0;
        $sumMwst = 0;
        $cartSubtotalGross = 0; // Wichtig für 50€ Grenze (DE)

        // 2. Artikelpreise berechnen
        foreach ($this->cartItems as $index => $item) {
            $product = $this->dbProducts[$item['product_id']] ?? null;

            if(!$product) {
                $this->cartItems[$index]['calculated_single_price'] = 0;
                $this->cartItems[$index]['calculated_total'] = 0;
                continue;
            }

            $totalQty = $quantitiesPerProduct[$item['product_id']];
            $unitPriceCents = $this->getTierPriceCents($product, $totalQty);

            $rate = $product['tax_rate'];
            $isGross = $product['tax_included'];

            $lineTotalCents = $unitPriceCents * $item['qty'];

            if ($isGross) {
                // Brutto -> Netto
                $lineGross = $lineTotalCents;
                $lineNet  = $lineGross / (1 + ($rate / 100));
                $lineTax  = $lineGross - $lineNet;
                // Für Versandgrenze brauchen wir Brutto in Euro
                $cartSubtotalGross += ($lineGross / 100);
            } else {
                // Netto -> Brutto
                $lineNet = $lineTotalCents;
                $lineTax = $lineNet * ($rate / 100);
                // Brutto berechnen für Versandgrenze
                $cartSubtotalGross += (($lineNet + $lineTax) / 100);
            }

            // Speichern für View (Anzeige in Euro)
            $this->cartItems[$index]['calculated_single_price'] = $unitPriceCents / 100;
            $this->cartItems[$index]['calculated_total'] = ($isGross ? $lineTotalCents : round($lineNet + $lineTax)) / 100;

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        // --- 3. VERSANDKOSTEN ---
        $this->shippingCost = 0;
        $countryCode = $this->form['country'];

        // FALL A: DEUTSCHLAND (Pauschal-Regel)
        if ($countryCode === 'DE') {
            // Regel: Kostenfrei ab 50,00 €, sonst 4,90 €
            if ($cartSubtotalGross >= 50.00 || count($this->cartItems) === 0) {
                $this->shippingCost = 0;
            } else {
                $this->shippingCost = 4.90;
            }
        }
        // FALL B: AUSLAND (Datenbank-basiert)
        else {
            // Zone suchen
            $zone = ShippingZone::whereHas('countries', function($q) use ($countryCode) {
                $q->where('country_code', $countryCode);
            })->with('rates')->first();

            // Fallback: Wenn keine Zone gefunden, versuche "Weltweit"
            if (!$zone) {
                $zone = ShippingZone::where('name', 'Weltweit')->with('rates')->first();
            }

            if ($zone && count($this->cartItems) > 0) {
                // Rate finden (Gewicht oder Preis)
                $shippingRate = $zone->rates()
                    ->where(function($q) {
                        $q->where('min_weight', '<=', $this->totalWeight)
                            ->where(function($sub) {
                                $sub->where('max_weight', '>=', $this->totalWeight)
                                    ->orWhereNull('max_weight');
                            });
                    })
                    ->orderBy('price', 'asc') // Günstigste Rate
                    ->first();

                if ($shippingRate) {
                    $this->shippingCost = $shippingRate->price / 100;
                } else {
                    // Fallback, wenn zu schwer oder nicht konfiguriert
                    $this->shippingCost = 29.90; // Standard Auslandspreis Fallback
                }
            } elseif(count($this->cartItems) > 0) {
                // Kein Versand möglich oder konfiguriert -> Fallback
                $this->shippingCost = 29.90;
            }
        }

        // 4. Versandkosten aufaddieren (Steuerlogik)
        if ($this->shippingCost > 0) {
            $shippingCents = $this->shippingCost * 100;

            // Liste der EU-Länder
            $euCountries = ['DE', 'AT', 'FR', 'NL', 'BE', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI', 'GR', 'PT', 'IE', 'LU', 'HU', 'SI', 'SK', 'EE', 'LV', 'LT', 'CY', 'MT', 'HR', 'BG', 'RO'];

            if (in_array($countryCode, $euCountries)) {
                // EU: 19% MwSt auf Versand (Standard)
                // Wir rechnen aus Brutto zurück
                $shippingNet = $shippingCents / 1.19;
                $shippingTax = $shippingCents - $shippingNet;
            } else {
                // Drittland (Export): Steuerfrei (Netto = Brutto)
                $shippingNet = $shippingCents;
                $shippingTax = 0;
            }

            $sumNetto += $shippingNet;
            $sumMwst += $shippingTax;
        }

        // 5. Express-Option
        if ($this->isExpress && count($this->cartItems) > 0) {
            $expressGross = 2500; // 25 € Brutto
            // Express wird steuerlich wie Versand behandelt
            if (in_array($countryCode, $euCountries)) {
                $expressNet = $expressGross / 1.19;
                $expressTax = $expressGross - $expressNet;
            } else {
                $expressNet = $expressGross;
                $expressTax = 0;
            }

            $sumNetto += $expressNet;
            $sumMwst += $expressTax;
        }

        $this->totalNetto = round($sumNetto) / 100;
        $this->totalMwst = round($sumMwst) / 100;
        $this->totalBrutto = round($sumNetto + $sumMwst) / 100; // Gesamtsumme inkl. allem
        $this->gesamtKosten = $this->totalBrutto;
    }

    private function getTierPriceCents($product, $qty)
    {
        $basePrice = $product['price_cents'];
        $tiers = $product['tier_pricing'] ?? [];

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']); // Absteigend sortieren
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    return (int) round($basePrice - $discount);
                }
            }
        }
        return $basePrice;
    }

    // Wenn Land geändert wird -> Neu berechnen
    public function updatedForm($value, $key) {
        // Bei Änderung des Landes oder nested Keys
        if($key === 'country' || $key === 'form.country') {
            $this->calculateTotal();
        }
        $this->persist();
    }

    public function updatedIsExpress() { $this->calculateTotal(); $this->persist(); }

    public function goNext() {
        if (count($this->cartItems) == 0) {
            $this->addError('cart', 'Bitte wählen Sie Produkte aus.');
            return;
        }
        $this->step = 3;
        $this->persist();
        $this->dispatch('scroll-top');
    }

    public function goBack() { $this->step = 1; $this->dispatch('scroll-top'); }

    public function submit()
    {
        $this->validate();
        $this->validateCartItems();
        $this->calculateTotal();

        if (count($this->cartItems) == 0) {
            $this->addError('cart', 'Der Warenkorb ist leer.');
            return;
        }

        $existingCustomer = Customer::where('email', $this->form['email'])->first();
        $cleanDeadline = ($this->isExpress && !empty($this->deadline)) ? $this->deadline : null;

        $quote = QuoteRequest::create([
            'quote_number' => 'AN-' . date('Y') . '-' . strtoupper(Str::random(5)),
            'email' => $this->form['email'],
            'first_name' => $this->form['vorname'],
            'last_name' => $this->form['nachname'],
            'company' => $this->form['firma'] ?? null,
            'phone' => $this->form['telefon'] ?? null,
            'customer_id' => $existingCustomer ? $existingCustomer->id : null,
            'status' => 'open',
            'net_total' => (int) round($this->totalNetto * 100),
            'tax_total' => (int) round($this->totalMwst * 100),
            'gross_total' => (int) round($this->totalBrutto * 100),
            'is_express' => $this->isExpress,
            'deadline' => $cleanDeadline,
            'admin_notes' => $this->form['anmerkung'] ?? null,
        ]);

        foreach($this->cartItems as $item) {
            $conf = $item['configuration'] ?? [];
            QuoteRequestItem::create([
                'quote_request_id' => $quote->id,
                'product_id' => $item['product_id'],
                'product_name' => $item['name'],
                'quantity' => $item['qty'],
                'unit_price' => (int) round($item['calculated_single_price'] * 100),
                'total_price' => (int) round($item['calculated_total'] * 100),
                'configuration' => $conf,
            ]);
        }

        $finalItems = [];
        foreach($this->cartItems as $item) {
            $conf = $item['configuration'] ?? [];
            $finalItems[] = [
                'name' => $item['name'],
                'quantity' => $item['qty'],
                'single_price' => number_format($item['calculated_single_price'], 2, ',', '.'),
                'total_price' => number_format($item['calculated_total'], 2, ',', '.'),
                'config' => [
                    'text' => $conf['text'] ?? '',
                    'notes' => $conf['notes'] ?? '',
                    'font' => $conf['font'] ?? '',
                    'align' => $conf['align'] ?? '',
                    'text_pos_label' => $this->getPositionLabel($conf['text_x'] ?? 50, $conf['text_y'] ?? 50),
                    'logo_pos_label' => $this->getPositionLabel($conf['logo_x'] ?? 50, $conf['logo_y'] ?? 30),
                    'logo_storage_path' => $conf['logo_path'] ?? null,
                    'product_image_path' => $item['preview_ref'] ?? null
                ]
            ];
        }

        // Versandzeile für PDF
        if ($this->shippingCost > 0) {
            $finalItems[] = [
                'name' => 'Versand & Verpackung (' . ($this->form['country'] === 'DE' ? 'Deutschland' : $this->form['country']) . ')',
                'quantity' => 1,
                'single_price' => number_format($this->shippingCost, 2, ',', '.'),
                'total_price' => number_format($this->shippingCost, 2, ',', '.'),
                'config' => []
            ];
        }

        // Express Zeile für PDF
        if ($this->isExpress) {
            $finalItems[] = [
                'name' => 'Express-Service',
                'quantity' => 1,
                'single_price' => '25,00',
                'total_price' => '25,00',
                'config' => []
            ];
        }

        $data = [
            'contact' => $this->form,
            'items' => $finalItems,
            'total_netto' => number_format($this->totalNetto, 2, ',', '.'),
            'total_vat' => number_format($this->totalMwst, 2, ',', '.'),
            'total_gross' => number_format($this->totalBrutto, 2, ',', '.'),
            'express' => $this->isExpress,
            'deadline' => $cleanDeadline,
            'quote_number' => $quote->quote_number,
            'quote_token' => $quote->token,
            'quote_expiry' => $quote->expires_at->format('d.m.Y'),
        ];

        $pdf = Pdf::loadView('global.mails.calculation_pdf', ['data' => $data]);
        $filename = 'Angebot_' . Str::slug($this->form['firma'] ?: $this->form['nachname']) . '_' . time() . '.pdf';
        $path = storage_path('app/public/tmp/' . $filename);

        if (!file_exists(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $pdf->output());

        try {
            Mail::to($this->form['email'])->send(new CalcCustomer($data, $path));
            Mail::to('kontakt@mein-seelenfunke.de')->send(new CalcInput($data, $path));
        } catch (\Exception $e) {
            \Log::error('Calculator Mail Error: ' . $e->getMessage());
        }

        @unlink($path);

        session()->forget(['calc_cart', 'calc_form']);
        $this->cartItems = [];
        $this->gesamtKosten = 0;
        $this->step = 4;
        $this->dispatch('scroll-top');
    }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'deadline', 'step', 'gesamtKosten', 'shippingCost', 'totalWeight']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    private function getPositionLabel($x, $y) {
        $x = (float)$x; $y = (float)$y;
        $h = ($x < 35) ? 'Links' : (($x > 65) ? 'Rechts' : 'Mitte');
        $v = ($y < 35) ? 'Oben' : (($y > 65) ? 'Unten' : 'Mitte');
        if ($h === 'Mitte' && $v === 'Mitte') return 'Zentriert';
        if ($h === 'Mitte') return $v . ' Zentriert';
        if ($v === 'Mitte') return 'Mitte ' . $h;
        return "$v $h";
    }

    public function render()
    {
        return view('livewire.widgets.calculator', ['dbProducts' => $this->dbProducts]);
    }
}
