<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\ProductCategory;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

class ProductFrontendFilterArea extends Component
{
    use WithPagination;

    public $search = '';
    public string $collectionWorld = 'all'; // Abwärtskompatibilität für alte Lesezeichen (manufaktur, atelier, voucher -> all)
    public $filterType = 'all'; // Legacy URL-Kompatibilität (wird immer auf 'all' normalisiert)
    public $filterPersonalizable = 'all'; // Legacy URL-Kompatibilität (wird immer auf 'all' normalisiert)
    public $filterSale = false; // Nur reduzierte Angebote (compare_at_price > price)
    public $filterCategory = '';
    public $filterHoliday = '';

    // Wir speichern den Query-String in der URL für besseres Teilen von Links
    protected $queryString = [
        'search' => ['except' => ''],
        'collectionWorld' => ['except' => 'all'],
        'filterType' => ['except' => 'all'],
        'filterPersonalizable' => ['except' => 'all'],
        'filterSale' => ['except' => false],
        'filterCategory' => ['except' => ''],
        'filterHoliday' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->collectionWorld = 'all';
        $this->filterType = 'all';
        $this->filterPersonalizable = 'all';
    }

    public function setCollectionWorld(string $world = 'all'): void
    {
        $this->collectionWorld = 'all';
        $this->filterType = 'all';
        $this->filterPersonalizable = 'all';
        $this->resetPage();
    }

    public function updatedCollectionWorld(): void
    {
        $this->collectionWorld = 'all';
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterSale()
    {
        $this->resetPage();
    }

    public function toggleSaleFilter()
    {
        $this->filterSale = !$this->filterSale;
        $this->resetPage();
    }

    public function updatedFilterHoliday()
    {
        $this->resetPage();
    }

    public function updatedFilterType()
    {
        $this->filterType = 'all';
        $this->resetPage();
    }

    public function updatedFilterPersonalizable()
    {
        $this->filterPersonalizable = 'all';
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'collectionWorld', 'filterType', 'filterPersonalizable', 'filterSale', 'filterCategory', 'filterHoliday']);
        $this->resetPage();
    }

    public function toggleWishlist(string $productId, \App\Services\WishlistService $wishlistService, ?bool $shouldBeWishlisted = null): void
    {
        $wishlistService->setWishlisted($productId, $shouldBeWishlisted);
        $this->dispatch(
            'wishlist-updated',
            count: $wishlistService->getCount(),
            productIds: $wishlistService->getProductIds()
        );
    }

    public function addToCart(string $productId, \App\Services\CartService $cartService): bool
    {
        if (filter_var(shop_setting('maintenance_mode', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $product = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->where('id', $productId)
            ->first();

        if (!$product || $product->is_personalizable || $product->slug === 'geschenkgutschein' || !$product->isAvailable()) {
            return false;
        }

        $cartService->addItem($product, 1, []);
        $this->dispatch('cart-updated');

        return true;
    }

    #[Title('Unsere Kollektion · Mein Seelenfunke')]
    public function render()
    {

        $this->collectionWorld = 'all';
        $this->filterType = 'all';
        $this->filterPersonalizable = 'all';

        // Dynamisch ermitteln, welche Produkttypen tatsächlich in aktiven Produkten vorhanden sind
        $availableTypes = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->distinct()
            ->pluck('type')
            ->toArray();

        // Dynamisches Laden aller Kategorien, die mindestens 1 aktives Produkt im gemeinsamen Schaufenster besitzen
        $categories = ProductCategory::whereHas('products', function ($query) {
            $query->where('status', 'active')
                ->where('slug', '!=', 'seelenfunke-ingame-artifact');
        })->orderBy('name')->get();

        if (!empty($this->filterCategory) && !$categories->contains('id', (int) $this->filterCategory)) {
            $this->filterCategory = '';
        }

        // Dynamisches Laden der Anlässe (Holidays) über alle aktiven Produkte hinweg
        $rawHolidays = ProductTemplate::where('is_active', true)
            ->whereNotNull('holiday')
            ->whereNotIn('holiday', ['', 'kein feiertag', 'Kein Feiertag', 'kein_feiertag', 'none', 'null'])
            ->whereHas('product', function ($q) {
                $q->where('status', 'active')
                  ->where('slug', '!=', 'seelenfunke-ingame-artifact');
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
            'halloween' => 'Halloween',
            'silvester' => 'Silvester',
            'neujahr' => 'Neujahr',
            'weltfrauentag' => 'Weltfrauentag',
            'geburtstag' => 'Geburtstag',
            'hochzeit' => 'Hochzeit',
            'taufe' => 'Taufe',
            'einschulung' => 'Einschulung',
            'jubilaeum' => 'Jubiläum',
            'jubiläum' => 'Jubiläum',
            'trauer' => 'Trauer',
        ];

        $activeFilterLower = mb_strtolower(trim((string) $this->filterHoliday));
        $filteredHolidays = [];
        $seenLower = [];
        foreach ($rawHolidays as $index => $h) {
            $trimmed = trim((string) $h);
            $lower = mb_strtolower($trimmed);
            if ($lower === '' || isset($seenLower[$lower])) {
                continue;
            }
            $isActiveUrlFilter = ($activeFilterLower !== '' && $lower === $activeFilterLower);
            if (!$isActiveUrlFilter && !ProductTemplate::isHolidaySlotCurrentlyRelevant($trimmed)) {
                continue;
            }
            $seenLower[$lower] = true;
            $filteredHolidays[] = [
                'key' => $trimmed,
                'label' => $holidayLabels[$lower] ?? ucfirst($trimmed),
                'priority' => ProductTemplate::resolveHolidaySlotPriority($trimmed),
                'index' => $index,
            ];
        }
        usort($filteredHolidays, static fn (array $a, array $b): int => [$a['priority'], $a['index']] <=> [$b['priority'], $b['index']]);

        $availableHolidays = [];
        foreach ($filteredHolidays as $entry) {
            $availableHolidays[$entry['key']] = $entry['label'];
        }

        if (!empty($this->filterHoliday)) {
            $holidayExists = collect($availableHolidays)->contains(function ($label, $val) {
                return mb_strtolower((string) $val) === mb_strtolower(trim((string) $this->filterHoliday));
            });
            if (!$holidayExists) {
                $this->filterHoliday = '';
            }
        }

        // Gemeinsames Haupt-Schaufenster für alle aktiven Produkte (Manufaktur, 925er Atelier & Geschenkgutschein)
        $query = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->with(['categories', 'tierPrices'])
            ->withCount(['reviews as approved_reviews_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->withAvg(['reviews as approved_reviews_avg' => function ($q) {
                $q->where('status', 'approved');
            }], 'rating');

        // 1. Suche nach Name oder Beschreibung
        if (!empty($this->search)) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function ($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('short_description', 'like', '%' . $escapedSearch . '%');
            });
        }

        // 2. Filter nach Kategorie (Relation)
        if (!empty($this->filterCategory)) {
            $query->whereHas('categories', function ($q) {
                $q->where('product_categories.id', $this->filterCategory);
            });
        }

        // 3. Filter nach Angeboten (Sale: compare_at_price > price)
        if ($this->filterSale) {
            $query->whereNotNull('compare_at_price')
                  ->whereColumn('compare_at_price', '>', 'price');
        }

        $saleCount = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->count();

        // 4. Filter nach Anlass / Feiertag (Template Projection Feature)
        if (!empty($this->filterHoliday)) {
            $query->whereHas('templates', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('holiday', $this->filterHoliday)
                        ->orWhere('holiday', strtolower($this->filterHoliday))
                        ->orWhere('holiday', ucfirst($this->filterHoliday));
                })->where('is_active', true);
            });
            // Eager Load Templates for the thumbnail swap in blade view
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

        // Wertige Sortierung: zuerst physische Unikate (Manufaktur & Atelier), ganz am Ende digitale Gutscheine
        $products = $query
            ->orderByRaw("CASE WHEN type = 'digital' OR slug = 'geschenkgutschein' THEN 1 ELSE 0 END ASC")
            ->orderByRaw("CASE slug
                WHEN 'seelen-kristall' THEN 1
                WHEN 'goldenes-seelen-herz' THEN 2
                WHEN 'seelen-anhaenger' THEN 3
                WHEN 'id-armband-silber-herz' THEN 4
                WHEN 'id-armband-gold-herz' THEN 5
                WHEN 'seelen-pfote-hundemarke' THEN 6
                WHEN 'seelen-talisman' THEN 7
                ELSE 50 END ASC")
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc')
            ->paginate(12);

        $heroBanner = $this->resolveHeroBanner((string) $this->filterHoliday, $saleCount);

        $itemListElements = [];
        foreach ($products->items() as $idx => $prod) {
            $itemListElements[] = [
                '@type' => 'ListItem',
                'position' => $idx + 1,
                'url' => route('product.show', $prod->slug),
                'name' => $prod->name,
            ];
        }

        $companyName = shop_setting('company_name', shop_setting('owner_name', 'Mein Seelenfunke'));
        $city = shop_setting('company_city', shop_setting('owner_city', 'Gifhorn'));
        $shopTitle = "Unsere Kollektion · Personalisierte Geschenke & Lasergravur | {$companyName}";
        $shopMetaDesc = 'Entdecke handveredelte Unikate mit präziser Lasergravur: K9-Kristallglas-Trophäen, 925er Silber- & vergoldeter Edelstahl-Schmuck, Schlüsselanhänger & Hundemarken live im 3D-Konfigurator gestalten.';
        $shopCanonical = route('shop');

        if (!empty($this->filterHoliday)) {
            $normH = mb_strtolower(trim((string) $this->filterHoliday));
            $hLabel = $holidayLabels[$normH] ?? ucfirst($normH);
            $shopTitle = "{$hLabel} Geschenke personalisiert mit Lasergravur | {$companyName}";
            $shopMetaDesc = "Besondere Geschenke zu {$hLabel}: Handveredelte Unikate aus K9-Kristallglas, 925er Silber & Edelstahl mit eigener Wunschgravur im 2D/3D-Konfigurator gestalten.";
            $shopCanonical = route('shop', ['filterHoliday' => $normH]);
        } elseif (!empty($this->filterCategory)) {
            $selectedCat = $categories->firstWhere('id', (int) $this->filterCategory);
            if ($selectedCat) {
                $cleanCatName = str_replace([' ' . "\u{2013}" . ' ', ' ' . "\u{2014}" . ' '], ' · ', (string) $selectedCat->name);
                $shopTitle = "{$cleanCatName} · Personalisiert mit Lasergravur | {$companyName}";
                $shopMetaDesc = "Entdecke personalisierbare Unikate in der Kategorie {$cleanCatName}, individuell graviert in unserer deutschen Manufaktur in {$city}.";
            }
        } elseif ($this->filterSale) {
            $shopTitle = "Angebote & Reduzierte Geschenkideen mit Gravur | {$companyName}";
            $shopMetaDesc = "Sichere dir personalisierte Geschenke mit Lasergravur zum Aktionspreis, handveredelt in unserer Manufaktur in {$city}.";
        }

        $shopSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $shopCanonical . '#breadcrumb',
                    'itemListElement' => [
                        [
                            '@type' => 'ListItem',
                            'position' => 1,
                            'name' => 'Startseite',
                            'item' => route('home'),
                        ],
                        [
                            '@type' => 'ListItem',
                            'position' => 2,
                            'name' => 'Shop & Kollektion',
                            'item' => $shopCanonical,
                        ],
                    ],
                ],
                [
                    '@type' => 'CollectionPage',
                    '@id' => $shopCanonical . '#collection',
                    'url' => $shopCanonical,
                    'name' => $shopTitle,
                    'description' => $shopMetaDesc,
                    'isPartOf' => [
                        '@id' => url('/#website'),
                    ],
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'numberOfItems' => count($itemListElements),
                        'itemListElement' => $itemListElements,
                    ],
                ],
            ],
        ];

        return view('livewire.shop.product.product-frontend-filter-area', [
            'products' => $products,
            'categories' => $categories,
            'availableTypes' => $availableTypes,
            'availableHolidays' => $availableHolidays,
            'saleCount' => $saleCount,
            'heroBanner' => $heroBanner,
            'shopSchema' => $shopSchema,
            'wishlistProductIds' => app(\App\Services\WishlistService::class)->getProductIds(),
        ])
            ->title($shopTitle)
            ->layout('components.layouts.frontend_layout', [
                'title' => $shopTitle,
                'meta_description' => $shopMetaDesc,
                'og_title' => $shopTitle,
                'og_description' => $shopMetaDesc,
                'canonical' => $shopCanonical,
            ]);
    }

    /**
     * Ermittelt das passende saisonale / Feiertags-Hero-Banner für den Shop-Header hinter "Unsere Kollektion".
     */
    protected function resolveHeroBanner(string $filterHoliday = '', int $saleCount = 0): array
    {
        $seasonKey = app(\App\Services\ConfiguratorService::class)->getActiveSeasonKey();
        $seasonBannerMap = [
            'ostern' => ['file' => 'fruehling.webp', 'label' => 'Frühlings-Edition'],
            'sommer' => ['file' => 'sommer.webp',    'label' => 'Sommer-Edition'],
            'herbst' => ['file' => 'herbst.webp',    'label' => 'Herbst-Edition'],
            'winter' => ['file' => 'winter.webp',    'label' => 'Winter-Edition'],
        ];

        $default = $seasonBannerMap[$seasonKey] ?? $seasonBannerMap['herbst'];

        // 1. Expliziter Anlass-Filter im Shop hat höchste Priorität
        if ($filterHoliday !== '') {
            $norm = mb_strtolower(trim($filterHoliday));
            $filteredBanner = match ($norm) {
                'valentinstag', 'valentines' => 'winter_valentinstag.webp',
                'ostern', 'easter', 'taufe', 'einschulung' => 'fruehling_ostern.webp',
                'muttertag', 'vatertag', 'hochzeit', 'jubilaeum', 'jubiläum', 'weltfrauentag' => 'fruehling_muttertag.webp',
                'weihnachten', 'advent', 'nikolaus' => 'winter_weihnachten.webp',
                'halloween', 'herbst', 'trauer' => 'herbst_halloween.webp',
                'geburtstag', 'neujahr', 'silvester' => 'winter_neujahr.webp',
                default => $default['file'],
            };

            return [
                'url'        => asset('shop/frontend/banners/' . $filteredBanner),
                'badge_text' => null,
            ];
        }

        // 2. Aktives Feiertags-Fenster aus dem Angebots-Autopiloten prüfen
        $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
        $activeWindow = $autopilot->resolveActiveOfferWindow();

        if ($activeWindow) {
            $eventBanner = match ($activeWindow['event_key']) {
                'valentines'  => 'winter_valentinstag.webp',
                'womens_day', 'mothers_day', 'fathers_day' => 'fruehling_muttertag.webp',
                'easter'      => 'fruehling_ostern.webp',
                'sale_summer' => 'sommer.webp',
                'halloween'   => 'herbst_halloween.webp',
                'advent_1', 'christmas' => 'winter_weihnachten.webp',
                'new_year'    => 'winter_neujahr.webp',
                'sale_winter' => 'winter.webp',
                default       => $default['file'],
            };

            return [
                'url'        => asset('shop/frontend/banners/' . $eventBanner),
                'badge_text' => $saleCount > 0 ? ($activeWindow['badge_text'] ?? $activeWindow['name']) : null,
            ];
        }

        // 3. Fallback: Aktive Jahreszeit (aus /admin/configuration -> Jahreszeiten)
        return [
            'url'        => asset('shop/frontend/banners/' . $default['file']),
            'badge_text' => null,
        ];
    }
}
