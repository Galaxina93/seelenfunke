<?php

namespace App\Livewire\Shop\Calculator;

use App\Mail\CalcMailToAdmin;
use App\Mail\CalcMailToCustomer;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\Quote\QuoteRequest;
use App\Models\Quote\QuoteRequestItem;
use App\Models\Shipping\ShippingZone;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

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

    // --- VORLAGEN (NEU) ---
    public $showTemplateSelection = false;
    public $showTemplatesList = false;
    public $productTemplates = [];

    // --- PREISE & GEWICHT ---
    public $gesamtKosten = 0;
    public $totalNetto = 0;
    public $totalMwst = 0;
    public $totalBrutto = 0;
    public $shippingCost = 0;
    public $totalWeight = 0;
    public $volumeDiscount = 0;

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
        'country' => 'DE'
    ];

    public $agb_accepted = false;
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
        $products = Product::with('tierPrices')
            ->where('status', 'active')
            ->where('type', 'physical')
            ->get();

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

    public function startCalculator()
    {
        if (!$this->agb_accepted) {
            $this->addError('agb', 'Bitte akzeptieren Sie die Hinweise zur Konfiguration und die AGB, um fortzufahren.');
            return;
        }
        $this->step = 1;
    }

    // --- NEU: Vorlagen Logik Start ---
    public function openConfig($productId)
    {
        $this->editingIndex = -1;
        $this->currentProduct = $this->dbProducts[$productId] ?? null;
        if(!$this->currentProduct) return;

        $this->currentConfig = [];

        // Prüfen, ob es aktive Vorlagen für dieses Produkt gibt
        $templates = ProductTemplate::where('product_id', $productId)
            ->where('is_active', true)
            ->get();

        if ($templates->isNotEmpty()) {
            $this->productTemplates = $templates->toArray();
            $this->showTemplateSelection = true;
            $this->showTemplatesList = false;
            $this->step = 2;
        } else {
            // Keine Vorlagen -> Direkt in den nackten Konfigurator
            $this->startCustomConfig();
        }

        $this->dispatch('scroll-top');
    }

    public function openTemplatesList()
    {
        $this->showTemplateSelection = false;
        $this->showTemplatesList = true;
    }

    public function startCustomConfig()
    {
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->currentConfig = [];
        $this->step = 2;
    }

    public function selectTemplate($templateId)
    {
        $template = ProductTemplate::find($templateId);

        if ($template) {
            $this->currentConfig = $template->configuration ?? [];
        } else {
            $this->currentConfig = [];
        }

        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->step = 2;
    }
    // --- Vorlagen Logik Ende ---

    public function editItem($index)
    {
        if(!isset($this->cartItems[$index])) return;
        $item = $this->cartItems[$index];
        $this->editingIndex = $index;
        $this->currentProduct = $this->dbProducts[$item['product_id']] ?? null;
        if(!$this->currentProduct) return;

        $this->currentConfig = $item['configuration'] ?? [];
        $this->currentConfig['qty'] = $item['qty'];

        // Beim Bearbeiten eines bestehenden Artikels keine Vorlagenauswahl anzeigen
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;

        $this->step = 2;
        $this->dispatch('scroll-top');
    }

    public function cancelConfig() {
        $this->currentConfig = [];
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
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

    public function calculateTotal()
    {
        $quantitiesPerProduct = [];
        $this->totalWeight = 0;

        foreach ($this->cartItems as $item) {
            $pid = $item['product_id'];
            if (!isset($quantitiesPerProduct[$pid])) $quantitiesPerProduct[$pid] = 0;
            $quantitiesPerProduct[$pid] += $item['qty'];

            $product = $this->dbProducts[$pid] ?? null;
            if ($product) {
                $this->totalWeight += ($product['weight'] * $item['qty']);
            }
        }

        $sumNetto = 0;
        $sumMwst = 0;
        $cartSubtotalGross = 0;
        $originalSubtotalGross = 0; // NEU: Speichert den Original-Bruttowert (ohne Rabatt)

        foreach ($this->cartItems as $index => $item) {
            $product = $this->dbProducts[$item['product_id']] ?? null;

            if(!$product) {
                $this->cartItems[$index]['calculated_single_price'] = 0;
                $this->cartItems[$index]['calculated_total'] = 0;
                continue;
            }

            $totalQty = $quantitiesPerProduct[$item['product_id']];

            // Rabattierter Preis und Originalpreis abrufen
            $unitPriceCents = $this->getTierPriceCents($product, $totalQty);
            $basePriceCents = $product['price_cents']; // NEU: Originalpreis

            $rate = $product['tax_rate'];
            $isGross = $product['tax_included'];

            $lineTotalCents = $unitPriceCents * $item['qty'];
            $lineOriginalCents = $basePriceCents * $item['qty']; // NEU: Original Zeilenwert

            if ($isGross) {
                $lineGross = $lineTotalCents;
                $lineNet  = $lineGross / (1 + ($rate / 100));
                $lineTax  = $lineGross - $lineNet;

                $cartSubtotalGross += ($lineGross / 100);
                $originalSubtotalGross += ($lineOriginalCents / 100); // NEU
            } else {
                $lineNet = $lineTotalCents;
                $lineTax = $lineNet * ($rate / 100);

                $cartSubtotalGross += (($lineNet + $lineTax) / 100);

                $origNet = $lineOriginalCents;
                $origTax = $origNet * ($rate / 100);
                $originalSubtotalGross += (($origNet + $origTax) / 100); // NEU
            }

            $this->cartItems[$index]['calculated_single_price'] = $unitPriceCents / 100;
            $this->cartItems[$index]['calculated_total'] = ($isGross ? $lineTotalCents : round($lineNet + $lineTax)) / 100;

            $sumNetto += $lineNet;
            $sumMwst += $lineTax;
        }

        // NEU: Mengenrabatt als Differenz zwischen Originalwert und tatsächlichem Wert berechnen
        $this->volumeDiscount = max(0, $originalSubtotalGross - $cartSubtotalGross);

        $this->shippingCost = 0;
        $countryCode = $this->form['country'];

        if ($countryCode === 'DE') {
            if ($cartSubtotalGross >= 50.00 || count($this->cartItems) === 0) {
                $this->shippingCost = 0;
            } else {
                $this->shippingCost = 4.90;
            }
        } else {
            $zone = \App\Models\Shipping\ShippingZone::whereHas('countries', function($q) use ($countryCode) {
                $q->where('country_code', $countryCode);
            })->with('rates')->first();

            if (!$zone) {
                $zone = \App\Models\Shipping\ShippingZone::where('name', 'Weltweit')->with('rates')->first();
            }

            if ($zone && count($this->cartItems) > 0) {
                $shippingRate = $zone->rates()
                    ->where(function($q) {
                        $q->where('min_weight', '<=', $this->totalWeight)
                            ->where(function($sub) {
                                $sub->where('max_weight', '>=', $this->totalWeight)
                                    ->orWhereNull('max_weight');
                            });
                    })
                    ->orderBy('price', 'asc')
                    ->first();

                if ($shippingRate) {
                    $this->shippingCost = $shippingRate->price / 100;
                } else {
                    $this->shippingCost = 29.90;
                }
            } elseif(count($this->cartItems) > 0) {
                $this->shippingCost = 29.90;
            }
        }

        if ($this->shippingCost > 0) {
            $shippingCents = $this->shippingCost * 100;
            $euCountries = ['DE', 'AT', 'FR', 'NL', 'BE', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI', 'GR', 'PT', 'IE', 'LU', 'HU', 'SI', 'SK', 'EE', 'LV', 'LT', 'CY', 'MT', 'HR', 'BG', 'RO'];

            $taxRate = (float)shop_setting('default_tax_rate', 19);
            $isSmallBusiness = (bool)shop_setting('is_small_business', false);
            $divisor = $isSmallBusiness ? 1.0 : (1 + ($taxRate / 100));

            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $shippingNet = $shippingCents / $divisor;
                $shippingTax = $shippingCents - $shippingNet;
            } else {
                $shippingNet = $shippingCents;
                $shippingTax = 0;
            }

            $sumNetto += $shippingNet;
            $sumMwst += $shippingTax;
        }

        if ($this->isExpress && count($this->cartItems) > 0) {
            $defaultTaxRate = (float)shop_setting('default_tax_rate', 19);
            $isSmallBusiness = (bool)shop_setting('is_small_business', false);
            $divisor = $isSmallBusiness ? 1.0 : (1 + ($defaultTaxRate / 100));
            $euCountries = ['DE', 'AT', 'FR', 'NL', 'BE', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI', 'GR', 'PT', 'IE', 'LU', 'HU', 'SI', 'SK', 'EE', 'LV', 'LT', 'CY', 'MT', 'HR', 'BG', 'RO'];

            $expressGross = (int)shop_setting('express_surcharge', 2500);
            $expressBaseNet = $expressGross / $divisor;

            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $expressNet = $expressBaseNet;
                $expressTax = $expressGross - $expressNet;
            } else {
                $expressNet = $expressBaseNet;
                $expressTax = 0;
            }

            $sumNetto += $expressNet;
            $sumMwst += $expressTax;
        }

        $this->totalNetto = round($sumNetto) / 100;
        $this->totalMwst = round($sumMwst) / 100;
        $this->totalBrutto = round($sumNetto + $sumMwst) / 100;
        $this->gesamtKosten = $this->totalBrutto;
    }

    private function getTierPriceCents($product, $qty)
    {
        $basePrice = $product['price_cents'];
        $tiers = $product['tier_pricing'] ?? [];

        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    return (int) round($basePrice - $discount);
                }
            }
        }
        return $basePrice;
    }

    public function updatedForm($value, $key) {
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

        if ($this->isExpress) {
            $this->validate([
                'deadline' => 'required|date|after:today',
            ], [
                'deadline.required' => 'Bitte wählen Sie einen Wunschtermin für die Express-Lieferung.',
                'deadline.date' => 'Bitte geben Sie ein gültiges Datum ein.',
                'deadline.after' => 'Der Termin muss in der Zukunft liegen.',
            ]);
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
            $this->addError('cart', 'Bitte wählen Sie Produkte aus.');
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
            'shipping_price' => (int) round($this->shippingCost * 100),
            'volume_discount' => (int) round($this->volumeDiscount * 100),
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

        $data = $quote->toFormattedArray();

        try {
            Mail::to($this->form['email'])->send(new CalcMailToCustomer($data));
            $owner_mail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
            Mail::to($owner_mail)->send(new CalcMailToAdmin($data));
            \Illuminate\Support\Facades\Log::info('Calculator: Mails erfolgreich versendet für ' . $quote->quote_number);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Calculator Mail Error: ' . $e->getMessage());
        }

        session()->forget(['calc_cart', 'calc_form']);
        $this->cartItems = [];
        $this->gesamtKosten = 0;
        $this->step = 4;
        $this->dispatch('scroll-top');
    }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'deadline', 'step', 'gesamtKosten', 'shippingCost', 'totalWeight', 'showTemplateSelection', 'showTemplatesList', 'productTemplates']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    public function render()
    {
        if (shop_setting('maintenance_mode', false)) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        return view('livewire.shop.calculator.calculator', [
            'dbProducts' => $this->dbProducts
        ]);
    }
}
