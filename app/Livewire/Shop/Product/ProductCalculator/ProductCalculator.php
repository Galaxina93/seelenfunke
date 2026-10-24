<?php

namespace App\Livewire\Shop\Product\ProductCalculator;

use App\Mail\NewCalcMailToAdmin;
use App\Mail\NewCalcMailToCustomer;
use App\Models\Customer\Customer;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemLog;
use App\Services\ProductTrackingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductCalculator extends Component
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
    public $taxBreakdown = [];
    public $shippingCost = 0;
    public $expressCost = 0;
    public $totalWeight = 0;
    public $volumeDiscount = 0;

    // --- FORMULAR ---
    public $isExpress = false;
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

    #[\Livewire\Attributes\Locked]
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
        } else {
            ProductTrackingService::trackCalculatorView();
        }
    }

    private function validateCartItems()
    {
        $validItems = [];
        $cartService = app(\App\Services\CartService::class);
        foreach($this->cartItems as $item) {
            if(isset($item['product_id']) && isset($this->dbProducts[$item['product_id']])) {
                $dbProd = $this->dbProducts[$item['product_id']];
                $rawQty = isset($item['qty']) && is_numeric($item['qty']) ? (int)$item['qty'] : 1;
                $item['qty'] = max(1, min(10000, $rawQty));
                $item['name'] = $dbProd['name'];
                $item['image_ref'] = $dbProd['image'];
                $item['preview_ref'] = $dbProd['preview_image'] ?? $dbProd['image'];
                if (isset($item['configuration']) && is_array($item['configuration'])) {
                    $productModel = Product::find($item['product_id']);
                    if ($productModel) {
                        $item['configuration'] = $cartService->sanitizeConfiguration($productModel, $item['configuration']) ?? [];
                    }
                } else {
                    $item['configuration'] = [];
                }
                $validItems[] = $item;
            }
        }
        if(count($validItems) !== count($this->cartItems) || $validItems !== $this->cartItems) {
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

            $isSmallBusiness = (bool)shop_setting('is_small_business', false);
            $rawPrice = $p->price / 100;
            $rate = $isSmallBusiness ? 0.0 : ($p->tax_rate !== null ? (float)$p->tax_rate : (float)shop_setting('default_tax_rate', 19.0));
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
                'variants_data' => $p->variants_data ?? [],
                'image' => !empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null,
                'preview_image' => $previewPath ? 'storage/'.$previewPath : null,
                'allow_logo' => $p->configurator_settings['allow_logo'] ?? true,
                'is_personalizable' => (bool) $p->is_personalizable, // NEU
            ];
        })->keyBy('id')->toArray();
    }

    public function startCalculator()
    {
        if (!$this->agb_accepted) {
            $this->addError('agb', 'Bitte akzeptieren Sie die Hinweise zur Konfiguration und die AGB, um fortzufahren.');
            ProductTrackingService::trackCalculatorValidationFailed(0, ['agb' => 'not_accepted']);
            return;
        }
        $this->step = 1;
        ProductTrackingService::trackCalculatorStarted();
    }

    // --- NEU: Vorlagen Logik Start ---
    public function openConfig($productId)
    {
        $this->editingIndex = -1;
        $this->currentProduct = $this->dbProducts[$productId] ?? null;
        if(!$this->currentProduct) return;

        ProductTrackingService::trackCalculatorProductOpened($productId);

        $this->currentConfig = [];

        // --- B2B BYPASS: Wenn nicht personalisiert, direkt in Cart legen! ---
        if (!$this->currentProduct['is_personalizable']) {
            $data = [
                'product_id' => $productId,
                'qty' => 1,
            ];
            $this->saveItemFromConfigurator($data);
            return;
        }

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
        $currentProductId = $this->currentProduct['id'] ?? null;
        $template = $currentProductId
            ? ProductTemplate::where('product_id', $currentProductId)->where('is_active', true)->find($templateId)
            : null;

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
        ProductTrackingService::trackCalculatorItemCanceled($this->currentProduct['id'] ?? null);
        $this->currentConfig = [];
        $this->showTemplateSelection = false;
        $this->showTemplatesList = false;
        $this->step = 1;
        $this->dispatch('scroll-top');
    }

    #[On('calculator-save')]
    public function saveItemFromConfigurator($data)
    {
        $productId = $data['product_id'] ?? null;
        if (!$productId || !isset($this->dbProducts[$productId])) {
            return;
        }

        $product = $this->dbProducts[$productId];
        $productModel = Product::find($productId);
        if ($productModel && is_array($data)) {
            $data = app(\App\Services\CartService::class)->sanitizeConfiguration($productModel, $data) ?? $data;
        }
        $rowId = ($this->editingIndex >= 0 && isset($this->cartItems[$this->editingIndex])) ? $this->cartItems[$this->editingIndex]['row_id'] : Str::uuid()->toString();

        $rawQty = isset($data['qty']) && is_numeric($data['qty']) ? (int)$data['qty'] : 1;
        $qty = max(1, min(10000, $rawQty));

        $itemData = [
            'row_id' => $rowId,
            'product_id' => $product['id'],
            'name' => $product['name'],
            'image_ref' => $product['image'],
            'qty' => $qty,
            'text' => $data['text'] ?? '',
            'configuration' => $data,
            'preview_ref' => $product['preview_image'] ?? $product['image']
        ];

        if ($this->editingIndex >= 0 && isset($this->cartItems[$this->editingIndex])) {
            $this->cartItems[$this->editingIndex] = array_merge($this->cartItems[$this->editingIndex], $itemData);
        } else {
            $this->cartItems[] = $itemData;
        }

        ProductTrackingService::trackCalculatorItemSaved(
            $product['id'],
            $qty,
            (int) round(($itemData['configuration']['totalPrice'] ?? $product['price']) * 100)
        );

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
        $hasExpressItem = collect($this->cartItems)->contains(function ($item) {
            return isset($item['configuration']['is_express']) && filter_var($item['configuration']['is_express'], FILTER_VALIDATE_BOOLEAN);
        });
        $this->isExpress = $hasExpressItem;
        $shopLevel = (int)\Illuminate\Support\Facades\Cache::get('shop_capacity_level', \App\Models\System\SystemSetting::where('key', 'shop_capacity_level')->value('value') ?? 0);
        if ($shopLevel >= 2 && $this->isExpress) {
            $this->isExpress = false;
        }

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
        $taxesBreakdownCents = [];
        $isSmallBusiness = (bool)shop_setting('is_small_business', false);

        foreach ($this->cartItems as $index => $item) {
            $product = $this->dbProducts[$item['product_id']] ?? null;

            if(!$product) {
                $this->cartItems[$index]['calculated_single_price'] = 0;
                $this->cartItems[$index]['calculated_total'] = 0;
                continue;
            }

            $totalQty = $quantitiesPerProduct[$item['product_id']];
            $itemConfig = is_array($item['configuration'] ?? null) ? $item['configuration'] : [];

            // Rabattierter Preis und Originalpreis abrufen (inkl. Varianten-Preis)
            $unitPriceCents = $this->getTierPriceCents($product, $totalQty, $itemConfig);
            $basePriceCents = $this->getTierPriceCents($product, 1, $itemConfig); // NEU: Originalpreis (inkl. Variante)

            $rate = $isSmallBusiness ? 0.0 : (float)$product['tax_rate'];
            $isGross = $product['tax_included'];

            $lineTotalCents = $unitPriceCents * $item['qty'];
            $lineOriginalCents = $basePriceCents * $item['qty']; // NEU: Original Zeilenwert

            if ($isSmallBusiness) {
                $lineGross = $lineTotalCents;
                $lineNet = $lineGross;
                $lineTax = 0;

                $cartSubtotalGross += ($lineGross / 100);
                $originalSubtotalGross += ($lineOriginalCents / 100);
            } elseif ($isGross) {
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

            if (!$isSmallBusiness) {
                $strRate = number_format($rate, 0);
                if (!isset($taxesBreakdownCents[$strRate])) {
                    $taxesBreakdownCents[$strRate] = 0;
                }
                $taxesBreakdownCents[$strRate] += $lineTax;
            }
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
            $zone = \App\Models\Logistics\LogisticsShippingZone::where('is_active', true)->whereHas('countries', function($q) use ($countryCode) {
                $q->where('country_code', $countryCode);
            })->with('rates')->first();

            if (!$zone) {
                $zone = \App\Models\Logistics\LogisticsShippingZone::where('is_active', true)->where('name', 'Weltweit')->with('rates')->first();
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
            $divisor = $isSmallBusiness ? 1.0 : (1 + ($taxRate / 100));

            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $shippingNet = $shippingCents / $divisor;
                $shippingTax = $shippingCents - $shippingNet;

                $strRate = number_format($taxRate, 0);
                if (!isset($taxesBreakdownCents[$strRate])) {
                    $taxesBreakdownCents[$strRate] = 0;
                }
                $taxesBreakdownCents[$strRate] += $shippingTax;
            } else {
                $shippingNet = $shippingCents;
                $shippingTax = 0;
            }

            $sumNetto += $shippingNet;
            $sumMwst += $shippingTax;
        }

        $this->expressCost = 0;

        if ($this->isExpress && count($this->cartItems) > 0) {
            $defaultTaxRate = (float)shop_setting('default_tax_rate', 19);
            $divisor = $isSmallBusiness ? 1.0 : (1 + ($defaultTaxRate / 100));
            $euCountries = ['DE', 'AT', 'FR', 'NL', 'BE', 'IT', 'ES', 'PL', 'CZ', 'DK', 'SE', 'FI', 'GR', 'PT', 'IE', 'LU', 'HU', 'SI', 'SK', 'EE', 'LV', 'LT', 'CY', 'MT', 'HR', 'BG', 'RO'];

            $expressPercent = (float)shop_setting('express_surcharge_percent', 20.0);
            $expressMin = (int)shop_setting('express_surcharge_min', 500);
            $calculatedExpress = (int) round(($cartSubtotalGross * 100) * ($expressPercent / 100));
            $expressGross = max($expressMin, $calculatedExpress);
            $this->expressCost = $expressGross;
            $expressBaseNet = $expressGross / $divisor;

            if (in_array($countryCode, $euCountries) && !$isSmallBusiness) {
                $expressNet = $expressBaseNet;
                $expressTax = $expressGross - $expressNet;

                $strRate = number_format($defaultTaxRate, 0);
                if (!isset($taxesBreakdownCents[$strRate])) {
                    $taxesBreakdownCents[$strRate] = 0;
                }
                $taxesBreakdownCents[$strRate] += $expressTax;
            } else {
                $expressNet = $expressBaseNet;
                $expressTax = 0;
            }

            $sumNetto += $expressNet;
            $sumMwst += $expressTax;
        }

        if ($isSmallBusiness) {
            $this->totalMwst = 0.0;
            $this->totalBrutto = round($cartSubtotalGross + $this->shippingCost + ($this->expressCost / 100), 2);
            $this->totalNetto = $this->totalBrutto;
        } else {
            $this->totalNetto = round($sumNetto) / 100;
            $this->totalMwst = round($sumMwst) / 100;
            $this->totalBrutto = round($sumNetto + $sumMwst) / 100;
        }
        $this->gesamtKosten = $this->totalBrutto;

        $this->taxBreakdown = [];
        if (!$isSmallBusiness) {
            foreach ($taxesBreakdownCents as $rate => $cents) {
                if ($cents > 0 || floatval($rate) == 0) {
                    $this->taxBreakdown[$rate] = round($cents) / 100;
                }
            }
        }
    }

    private function getTierPriceCents($product, $qty, array $configuration = [])
    {
        $basePrice = $product['price_cents'];
        if (!empty($configuration['variant_id']) && !empty($product['variants_data']) && is_array($product['variants_data'])) {
            $variant = collect($product['variants_data'])->firstWhere('id', $configuration['variant_id']);
            if ($variant && !empty($variant['price'])) {
                $basePrice = (int) round((float) $variant['price'] * 100);
            }
        }
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
        $this->step = 3;
        ProductTrackingService::trackCalculatorFormReached(
            count($this->cartItems),
            (int) round($this->totalBrutto * 100)
        );
        $this->persist();
        $this->dispatch('scroll-top');
    }

    public function goBack() { $this->step = 1; $this->dispatch('scroll-top'); }

    public function submit()
    {
        $throttleKey = 'calculator-submit:' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('cart', "Zu viele Anfragen. Bitte warte {$seconds} Sekunden.");
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            ProductTrackingService::trackCalculatorValidationFailed(3, $e->errors());
            throw $e;
        }

        $this->loadProducts();
        $this->validateCartItems();
        $this->calculateTotal();

        if (count($this->cartItems) == 0) {
            $this->addError('cart', 'Bitte wählen Sie Produkte aus.');
            return;
        }

        if ($this->totalBrutto < 0) {
            $this->addError('cart', 'Ungültiger Gesamtbetrag.');
            return;
        }

        RateLimiter::hit($throttleKey, 600); // 10 minutes decay

        $authenticatedCustomer = \Illuminate\Support\Facades\Auth::guard('customer')->user();
        $existingCustomer = ($authenticatedCustomer && strtolower(trim($authenticatedCustomer->email)) === strtolower(trim((string) $this->form['email'])))
            ? $authenticatedCustomer
            : null;

        try {
            DB::transaction(function () use ($existingCustomer) {
                $quote = OrderQuoteRequest::create([
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
                    'express_price' => $this->expressCost,
                    'volume_discount' => (int) round($this->volumeDiscount * 100),
                    'is_express' => $this->isExpress,
                    'admin_notes' => $this->form['anmerkung'] ?? null,
                ]);

                foreach($this->cartItems as $item) {
                    $conf = $item['configuration'] ?? [];
                    OrderQuoteRequestItem::create([
                        'quote_request_id' => $quote->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['name'],
                        'quantity' => $item['qty'],
                        'unit_price' => (int) round($item['calculated_single_price'] * 100),
                        'tax_rate' => ((bool)shop_setting('is_small_business', false)) ? 0.0 : ($this->dbProducts[$item['product_id']]['tax_rate'] ?? shop_setting('default_tax_rate', 19.0)),
                        'total_price' => (int) round($item['calculated_total'] * 100),
                        'configuration' => $conf,
                    ]);
                }

                $data = $quote->toFormattedArray();

                try {
                    Mail::to($this->form['email'])->send(new NewCalcMailToCustomer($data));
                    $owner_mail = shop_setting('owner_email', 'kontakt@mein-seelenfunke.de');
                    Mail::to($owner_mail)->send(new NewCalcMailToAdmin($data));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Calculator Mail Error: ' . $e->getMessage());
                }

                ProductTrackingService::trackCalculatorQuoteSubmitted(
                    $quote->quote_number,
                    count($this->cartItems),
                    (int) round($this->totalNetto * 100),
                    (int) round($this->totalBrutto * 100),
                    (bool) $this->isExpress
                );

                session()->forget(['calc_cart', 'calc_form']);
                $this->cartItems = [];
                $this->gesamtKosten = 0;
                $this->step = 4;
                $this->dispatch('scroll-top');
            });
        } catch (\Exception $e) {
            SystemLog::create([
                'type' => 'error',
                'agent_id' => null,
                'action_id' => 'calculator_quote_creation',
                'message' => 'Kritischer Fehler bei der Anfrage-Erstellung: ' . $e->getMessage(),
                'details' => json_encode([
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $this->form['email'] ?? null
                ])
            ]);

            session()->flash('error', 'Ihre Anfrage konnte nicht verarbeitet werden. Bitte versuchen Sie es später erneut.');
        }
    }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'step', 'gesamtKosten', 'shippingCost', 'totalWeight', 'showTemplateSelection', 'showTemplatesList', 'productTemplates']);
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

        return view('livewire.shop.product.product-calculator.product-calculator', [
            'dbProducts' => $this->dbProducts
        ]);
    }
}
