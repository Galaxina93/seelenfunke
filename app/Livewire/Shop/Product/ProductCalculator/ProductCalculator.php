<?php

namespace App\Livewire\Shop\Product\ProductCalculator;

use App\Mail\NewCalcMailToAdmin;
use App\Mail\NewCalcMailToCustomer;
use App\Models\Customer\Customer;
use App\Models\Order\OrderQuoteRequest;
use App\Models\Order\OrderQuoteRequestItem;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
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
use Livewire\WithPagination;

class ProductCalculator extends Component
{
    use WithFileUploads, WithPagination;

    // --- SORTIMENT FILTER, ANSICHT & PAGINIERUNG ---
    public string $search = '';
    public string $filterPersonalizable = 'all'; // all, yes, no
    public bool $filterSale = false;
    public bool $filterTierPricing = false;
    public string $filterCategory = '';
    public string $filterHoliday = '';
    public string $sortBy = 'default'; // default, price_asc, price_desc, name_asc
    public string $viewMode = 'grid'; // grid, list
    public int $perPage = 6; // 6, 12, 24

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
    public string $lastQuoteNumber = '';

    #[\Livewire\Attributes\Locked]
    public string $lastQuoteToken = '';

    #[\Livewire\Attributes\Locked]
    public $dbProducts = [];

    protected function rules()
    {
        $allowedCountries = implode(',', array_keys(shop_setting('active_countries', ['DE' => 'Deutschland'])));

        return [
            'form.vorname' => 'required|string|max:100',
            'form.nachname' => 'required|string|max:100',
            'form.email' => 'required|email',
            'form.country' => 'required|string|size:2|in:' . $allowedCountries,
            'cartItems' => 'required|array|min:1',
        ];
    }

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

            $mainMediaMeta = $p->resolveMainMediaMeta();
            $resolvedImageUrl = $mainMediaMeta['poster_url'] ?? $mainMediaMeta['url'] ?? (!empty($p->media_gallery[0]['path']) ? 'storage/'.$p->media_gallery[0]['path'] : null);

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
                'configurator_settings' => array_merge($p->configurator_settings ?? [], [
                    'addons' => $p->getEffectiveAddons(true),
                ]),
                'image' => $resolvedImageUrl,
                'is_ai_generated' => (bool) ($mainMediaMeta['is_ai_generated'] ?? false),
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
    public function openConfig($productId, $templateId = null)
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

        if ($templateId) {
            $this->selectTemplate($templateId);
            $this->dispatch('scroll-top');
            return;
        }

        // Prüfen, ob es aktive Vorlagen für dieses Produkt gibt (nur im Studio-Modus relevant)
        $personalizationMode = $this->currentProduct['configurator_settings']['personalization_mode'] ?? 'studio';
        if ($personalizationMode === 'custom_fields') {
            $this->startCustomConfig();
            $this->dispatch('scroll-top');
            return;
        }

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
        $shopLevel = (int) \Illuminate\Support\Facades\Cache::get('shop_capacity_level', shop_setting('shop_capacity_level', 0));
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

            // Rabattierter Preis und Originalpreis abrufen (inkl. Varianten-Preis, Addons separat als Brutto-Cents)
            $productUnitCents = $this->getTierPriceCents($product, $totalQty, $itemConfig, false);
            $productBaseCents = $this->getTierPriceCents($product, 1, $itemConfig, false);
            $addonGrossCents = $this->getAddonPriceCents($product, $itemConfig);

            $rate = $isSmallBusiness ? 0.0 : (float)$product['tax_rate'];
            $isGross = $product['tax_included'];

            $unitPriceCents = $productUnitCents + $addonGrossCents;
            $basePriceCents = $productBaseCents + $addonGrossCents;

            $lineTotalCents = $unitPriceCents * $item['qty'];
            $lineOriginalCents = $basePriceCents * $item['qty']; // NEU: Original Zeilenwert

            if ($isSmallBusiness) {
                $lineGross = $lineTotalCents;
                $lineNet = $lineGross;
                $lineTax = 0;

                $cartSubtotalGross += ($lineGross / 100);
                $originalSubtotalGross += ($lineOriginalCents / 100);
                $grossUnitCents = $unitPriceCents;
            } elseif ($isGross) {
                $lineGross = $lineTotalCents;
                $lineNet  = $lineGross / (1 + ($rate / 100));
                $lineTax  = $lineGross - $lineNet;

                $cartSubtotalGross += ($lineGross / 100);
                $originalSubtotalGross += ($lineOriginalCents / 100); // NEU
                $grossUnitCents = $unitPriceCents;
            } else {
                $productLineNet = $productUnitCents * $item['qty'];
                $productLineTax = $productLineNet * ($rate / 100);

                $addonLineGross = $addonGrossCents * $item['qty'];
                $addonLineNet = $rate > 0 ? ($addonLineGross / (1 + ($rate / 100))) : $addonLineGross;
                $addonLineTax = $addonLineGross - $addonLineNet;

                $lineNet = $productLineNet + $addonLineNet;
                $lineTax = $productLineTax + $addonLineTax;

                $cartSubtotalGross += (($lineNet + $lineTax) / 100);

                $origProductNet = $productBaseCents * $item['qty'];
                $origProductTax = $origProductNet * ($rate / 100);
                $originalSubtotalGross += (($origProductNet + $origProductTax + $addonLineGross) / 100); // NEU

                $grossUnitCents = (int) round($productUnitCents * (1 + ($rate / 100))) + $addonGrossCents;
            }

            $this->cartItems[$index]['calculated_single_price'] = $grossUnitCents / 100;
            $this->cartItems[$index]['calculated_total'] = (($isGross || $isSmallBusiness) ? $lineTotalCents : ($grossUnitCents * $item['qty'])) / 100;

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
            $freeShippingEuro = ((int) shop_setting('shipping_free_threshold', 5000)) / 100;
            $standardShippingEuro = ((int) shop_setting('shipping_cost', 490)) / 100;
            if ($cartSubtotalGross >= $freeShippingEuro || count($this->cartItems) === 0) {
                $this->shippingCost = 0;
            } else {
                $this->shippingCost = $standardShippingEuro;
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
                    ->where('min_price', '<=', (int) round($cartSubtotalGross * 100))
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

    private function getTierPriceCents($product, $qty, array $configuration = [], bool $includeAddons = true)
    {
        $basePrice = $product['price_cents'];
        if (!empty($configuration['variant_id']) && !empty($product['variants_data']) && is_array($product['variants_data'])) {
            $variant = collect($product['variants_data'])->firstWhere('id', $configuration['variant_id']);
            if ($variant && isset($variant['price']) && $variant['price'] !== '') {
                $variantCents = (int) round(((float) str_replace(',', '.', (string) $variant['price'])) * 100);
                if ($variantCents > 0) {
                    $basePrice = $variantCents;
                }
            }
        }
        $tiers = $product['tier_pricing'] ?? [];

        $unitPrice = $basePrice;
        if (!empty($tiers) && is_array($tiers)) {
            usort($tiers, fn($a, $b) => $b['qty'] <=> $a['qty']);
            foreach ($tiers as $tier) {
                if ($qty >= $tier['qty']) {
                    $discount = $basePrice * ($tier['percent'] / 100);
                    $unitPrice = (int) round($basePrice - $discount);
                    break;
                }
            }
        }

        if ($includeAddons) {
            $addonCents = $this->getAddonPriceCents($product, $configuration);
            if ($addonCents > 0) {
                $isSmallBusiness = (bool) shop_setting('is_small_business', false);
                $isGross = (bool) ($product['tax_included'] ?? true);
                if (!$isSmallBusiness && !$isGross) {
                    $rate = (float) ($product['tax_rate'] ?? shop_setting('default_tax_rate', 19.0));
                    $addonCents = $rate > 0 ? (int) round($addonCents / (1 + ($rate / 100))) : $addonCents;
                }
                $unitPrice += $addonCents;
            }
        }

        return $unitPrice;
    }

    private function getAddonPriceCents($product, array $configuration = []): int
    {
        $addonTotal = 0;

        // Aufpreis-Extras (z. B. Geschenkbox / Verpackung) sind bereits in Brutto-Cents hinterlegt
        if (!empty($configuration['addons_summary']) && is_array($configuration['addons_summary'])) {
            foreach ($configuration['addons_summary'] as $addon) {
                if (is_array($addon) && isset($addon['price_cents'])) {
                    $addonTotal += max(0, (int) $addon['price_cents']);
                }
            }
        } elseif (!empty($configuration['selected_addons']) && is_array($configuration['selected_addons'])) {
            $productAddons = is_array($product['effective_addons'] ?? null)
                ? $product['effective_addons']
                : (is_array($product['configurator_settings']['addons'] ?? null) ? $product['configurator_settings']['addons'] : []);
            $selectedIds = array_map(fn($item) => is_array($item) ? (string) ($item['id'] ?? '') : (string) $item, $configuration['selected_addons']);
            foreach ($productAddons as $addon) {
                if (!empty($addon['id']) && (($addon['is_active'] ?? true) != false) && in_array((string) $addon['id'], $selectedIds, true)) {
                    $addonCents = isset($addon['price_cents'])
                        ? max(0, (int) $addon['price_cents'])
                        : max(0, (int) round(((float) str_replace(',', '.', (string) ($addon['price'] ?? 0))) * 100));
                    $addonTotal += $addonCents;
                }
            }
        }

        // Aufpreis für ausgefüllte dynamische Schnell-Personalisierungsfelder
        if (!empty($configuration['custom_fields_data']) && is_array($configuration['custom_fields_data'])) {
            foreach ($configuration['custom_fields_data'] as $cfRow) {
                if (is_array($cfRow) && trim((string) ($cfRow['value'] ?? '')) !== '' && !empty($cfRow['surcharge_cents'])) {
                    $addonTotal += max(0, (int) $cfRow['surcharge_cents']);
                }
            }
        }

        return $addonTotal;
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
            $this->dispatch('quote-submit-failed');
            return;
        }

        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            ProductTrackingService::trackCalculatorValidationFailed(3, $e->errors());
            $this->dispatch('quote-submit-failed');
            throw $e;
        }

        $this->loadProducts();
        $this->validateCartItems();
        $this->calculateTotal();

        if (count($this->cartItems) == 0) {
            $this->addError('cart', 'Bitte wählen Sie Produkte aus.');
            $this->dispatch('quote-submit-failed');
            return;
        }

        if ($this->totalBrutto < 0) {
            $this->addError('cart', 'Ungültiger Gesamtbetrag.');
            $this->dispatch('quote-submit-failed');
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
                    $conf = is_array($item['configuration'] ?? null) ? $item['configuration'] : [];
                    $productModel = Product::find($item['product_id']);
                    if ($productModel) {
                        $conf = app(\App\Services\CartService::class)->sanitizeConfiguration($productModel, $conf) ?? $conf;
                    }
                    $conf['quote_country'] = $this->form['country'] ?? 'DE';
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
                    $owner_mail = shop_setting('company_email', shop_setting('owner_email', 'kontakt@mein-seelenfunke.de'));
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
                $this->lastQuoteNumber = (string) $quote->quote_number;
                $this->lastQuoteToken = (string) ($quote->token ?? '');
                $this->cartItems = [];
                $this->gesamtKosten = 0;
                $this->step = 4;
                $this->dispatch('scroll-top');
                $this->dispatch('quote-submitted');
            });
        } catch (\Throwable $e) {
            $this->dispatch('quote-submit-failed');
            SystemLog::create([
                'title' => 'Kalkulator Anfragefehler',
                'type' => 'system',
                'status' => 'error',
                'action_id' => 'calculator_quote_creation',
                'message' => 'Kritischer Fehler bei der Anfrage-Erstellung: ' . $e->getMessage(),
                'payload' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace_excerpt' => mb_substr($e->getTraceAsString(), 0, 2000),
                    'email' => $this->form['email'] ?? null,
                ],
            ]);

            session()->flash('error', 'Ihre Anfrage konnte nicht verarbeitet werden. Bitte versuchen Sie es später erneut.');
        }
    }

    public function restartCalculator()
    {
        $this->reset(['cartItems', 'form', 'isExpress', 'step', 'gesamtKosten', 'shippingCost', 'totalWeight', 'showTemplateSelection', 'showTemplatesList', 'productTemplates', 'lastQuoteNumber', 'lastQuoteToken']);
        session()->forget(['calc_cart', 'calc_form']);
        $this->step = 1;
        $this->dispatch('quote-restarted');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPersonalizable(): void
    {
        if (!in_array($this->filterPersonalizable, ['all', 'yes', 'no'], true)) {
            $this->filterPersonalizable = 'all';
        }
        $this->resetPage();
    }

    public function updatedFilterSale(): void
    {
        $this->resetPage();
    }

    public function toggleSaleFilter(): void
    {
        $this->filterSale = !$this->filterSale;
        $this->resetPage();
    }

    public function updatedFilterTierPricing(): void
    {
        $this->resetPage();
    }

    public function toggleTierPricingFilter(): void
    {
        $this->filterTierPricing = !$this->filterTierPricing;
        $this->resetPage();
    }

    public function updatedFilterCategory(): void
    {
        $this->resetPage();
    }

    public function updatedFilterHoliday(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        if (!in_array($this->sortBy, ['default', 'price_asc', 'price_desc', 'name_asc'], true)) {
            $this->sortBy = 'default';
        }
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (!in_array((int) $this->perPage, [6, 12, 24], true)) {
            $this->perPage = 6;
        }
        $this->resetPage();
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['grid', 'list'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function setPerPage(int $perPage): void
    {
        if (in_array($perPage, [6, 12, 24], true)) {
            $this->perPage = $perPage;
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'filterPersonalizable',
            'filterSale',
            'filterTierPricing',
            'filterCategory',
            'filterHoliday',
            'sortBy',
        ]);
        $this->resetPage();
    }

    public function persist()
    {
        session()->put('calc_cart', $this->cartItems);
        session()->put('calc_form', $this->form);
    }

    public function render()
    {
        if (shop_setting('maintenance_mode', false) && !\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        // Kategorien mit aktiven physischen Produkten laden
        $categories = ProductCategory::whereHas('products', function ($query) {
            $query->where('status', 'active')->where('type', 'physical');
        })->orderBy('name')->get();

        if (!empty($this->filterCategory) && !$categories->contains('id', (int) $this->filterCategory)) {
            $this->filterCategory = '';
        }

        // Verfügbare Anlässe (Holidays) aus aktiven Vorlagen aktiver physischer Produkte
        $rawHolidays = ProductTemplate::where('is_active', true)
            ->whereNotNull('holiday')
            ->whereNotIn('holiday', ['', 'kein feiertag', 'Kein Feiertag', 'kein_feiertag', 'none', 'null'])
            ->whereHas('product', function ($q) {
                $q->where('status', 'active')->where('type', 'physical');
            })
            ->distinct()
            ->pluck('holiday')
            ->toArray();

        $holidayLabels = [
            'muttertag' => 'Muttertag',
            'valentinstag' => 'Valentinstag',
            'weihnachten' => 'Weihnachten',
            'vatertag' => 'Vatertag',
            'ostern' => 'Ostern',
            'geburtstag' => 'Geburtstag',
            'hochzeit' => 'Hochzeit',
            'taufe' => 'Taufe',
            'einschulung' => 'Einschulung',
            'jubilaeum' => 'Jubiläum',
            'trauer' => 'Trauer',
        ];

        $availableHolidays = [];
        foreach ($rawHolidays as $h) {
            $trimmed = trim((string) $h);
            $lower = strtolower($trimmed);
            $availableHolidays[$trimmed] = $holidayLabels[$lower] ?? ucfirst($trimmed);
        }

        if (!empty($this->filterHoliday)) {
            $holidayExists = collect($availableHolidays)->contains(function ($label, $val) {
                return strtolower((string) $val) === strtolower((string) $this->filterHoliday);
            });
            if (!$holidayExists) {
                $this->filterHoliday = '';
            }
        }

        $saleCount = Product::where('status', 'active')
            ->where('type', 'physical')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->count();

        $tierPricingCount = Product::where('status', 'active')
            ->where('type', 'physical')
            ->whereHas('tierPrices')
            ->count();

        $query = Product::where('status', 'active')
            ->where('type', 'physical')
            ->with(['categories', 'tierPrices'])
            ->withCount(['reviews as approved_reviews_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->withAvg(['reviews as approved_reviews_avg' => function ($q) {
                $q->where('status', 'approved');
            }], 'rating');

        if (trim($this->search) !== '') {
            $escapedSearch = addcslashes(trim($this->search), '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('short_description', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('sku', 'like', '%' . $escapedSearch . '%');
            });
        }

        if (!empty($this->filterCategory)) {
            $query->whereHas('categories', function ($q) {
                $q->where('product_categories.id', $this->filterCategory);
            });
        }

        if ($this->filterPersonalizable === 'yes') {
            $query->where('is_personalizable', true);
        } elseif ($this->filterPersonalizable === 'no') {
            $query->where('is_personalizable', false);
        }

        if ($this->filterSale) {
            $query->whereNotNull('compare_at_price')
                ->whereColumn('compare_at_price', '>', 'price');
        }

        if ($this->filterTierPricing) {
            $query->whereHas('tierPrices');
        }

        if (!empty($this->filterHoliday)) {
            $query->whereHas('templates', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('holiday', $this->filterHoliday)
                        ->orWhere('holiday', strtolower($this->filterHoliday))
                        ->orWhere('holiday', ucfirst($this->filterHoliday));
                })->where('is_active', true);
            });
            $query->with(['templates' => function ($q) {
                $q->where(function ($sub) {
                    $sub->where('holiday', $this->filterHoliday)
                        ->orWhere('holiday', strtolower($this->filterHoliday))
                        ->orWhere('holiday', ucfirst($this->filterHoliday));
                })->where('is_active', true);
            }]);
        } else {
            $query->with(['templates' => function ($q) {
                $q->where('is_active', true);
            }]);
        }

        match ($this->sortBy) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            default => $query->latest(),
        };

        $safePerPage = in_array((int) $this->perPage, [6, 12, 24], true) ? (int) $this->perPage : 6;
        $catalogProducts = $query->paginate($safePerPage);

        return view('livewire.shop.product.product-calculator.product-calculator', [
            'dbProducts' => $this->dbProducts,
            'catalogProducts' => $catalogProducts,
            'categories' => $categories,
            'availableHolidays' => $availableHolidays,
            'saleCount' => $saleCount,
            'tierPricingCount' => $tierPricingCount,
        ]);
    }
}
