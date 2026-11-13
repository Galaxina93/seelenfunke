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
    public string $collectionWorld = 'manufaktur'; // manufaktur, atelier, voucher
    public $filterType = 'all'; // all, physical, digital, service (legacy URL compatibility)
    public $filterPersonalizable = 'all'; // all, yes, no
    public $filterSale = false; // Nur reduzierte Angebote (compare_at_price > price)
    public $filterCategory = '';
    public $filterHoliday = '';

    // Wir speichern den Query-String in der URL für besseres Teilen von Links
    protected $queryString = [
        'search' => ['except' => ''],
        'collectionWorld' => ['except' => 'manufaktur'],
        'filterType' => ['except' => 'all'],
        'filterPersonalizable' => ['except' => 'all'],
        'filterSale' => ['except' => false],
        'filterCategory' => ['except' => ''],
        'filterHoliday' => ['except' => ''],
    ];

    public function mount(): void
    {
        if ($this->filterType === 'digital') {
            $this->collectionWorld = 'voucher';
        } elseif (!in_array($this->collectionWorld, ['manufaktur', 'atelier', 'voucher'], true)) {
            $this->collectionWorld = 'manufaktur';
        }
    }

    public function setCollectionWorld(string $world): void
    {
        if (!in_array($world, ['manufaktur', 'atelier', 'voucher'], true)) {
            $world = 'manufaktur';
        }
        $this->collectionWorld = $world;
        $this->filterType = 'all';
        $this->filterCategory = '';
        if ($world === 'voucher') {
            $this->filterHoliday = '';
            $this->filterPersonalizable = 'all';
        }
        $this->resetPage();
    }

    public function updatedCollectionWorld(): void
    {
        if (!in_array($this->collectionWorld, ['manufaktur', 'atelier', 'voucher'], true)) {
            $this->collectionWorld = 'manufaktur';
        }
        $this->filterCategory = '';
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
        if ($this->filterType === 'digital') {
            $this->collectionWorld = 'voucher';
        } elseif ($this->filterType === 'physical') {
            $this->collectionWorld = 'manufaktur';
        }
        $this->resetPage();
    }

    public function updatedFilterPersonalizable()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterType', 'filterPersonalizable', 'filterSale', 'filterCategory', 'filterHoliday']);
        $this->resetPage();
    }

    public function toggleWishlist(string $productId, \App\Services\WishlistService $wishlistService): void
    {
        $wishlistService->toggle($productId);
        $this->dispatch('wishlist-updated');
    }

    #[Title('Unsere Kollektion - Mein Seelenfunke')]
    public function render()
    {
        // Wartungsmodus Check (Admins dürfen trotz Wartungsmodus testen)
        if (shop_setting('maintenance_mode', false) && !\Illuminate\Support\Facades\Auth::guard('admin')->check()) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        if (!in_array($this->collectionWorld, ['manufaktur', 'atelier', 'voucher'], true)) {
            $this->collectionWorld = 'manufaktur';
        }

        $hasCollectionLineCol = \Illuminate\Support\Facades\Schema::hasColumn('products', 'collection_line');

        // Anzahl pro Kollektions-Welt ermitteln (für saubere Trennung von Manufaktur, Atelier Echtgold/Silber & Gutscheinen)
        $manufakturCount = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->where('type', 'physical')
            ->when($hasCollectionLineCol, fn($q) => $q->where(fn($sub) => $sub->whereNull('collection_line')->orWhere('collection_line', '!=', 'atelier')))
            ->count();

        $atelierCount = $hasCollectionLineCol
            ? Product::where('status', 'active')
                ->where('slug', '!=', 'seelenfunke-ingame-artifact')
                ->where('type', 'physical')
                ->where('collection_line', 'atelier')
                ->count()
            : 0;

        $voucherCount = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->where(function ($q) use ($hasCollectionLineCol) {
                $q->where('type', 'digital');
                if ($hasCollectionLineCol) {
                    $q->orWhere('collection_line', 'voucher');
                }
            })
            ->count();

        // Dynamisch ermitteln, welche Produkttypen tatsächlich in aktiven Produkten vorhanden sind
        $availableTypes = Product::where('status', 'active')
            ->distinct()
            ->pluck('type')
            ->toArray();

        if ($this->filterType !== 'all' && !in_array($this->filterType, $availableTypes, true)) {
            $this->filterType = 'all';
        }

        $activeWorld = $this->collectionWorld;

        // Dynamisches Laden der Kategorien, die in der aktuell gewählten Kollektions-Welt aktive Produkte enthalten
        $categoryQuery = ProductCategory::whereHas('products', function ($query) use ($activeWorld, $hasCollectionLineCol) {
            $query->where('status', 'active')
                ->where('slug', '!=', 'seelenfunke-ingame-artifact');

            if ($activeWorld === 'atelier') {
                $query->where('type', 'physical');
                if ($hasCollectionLineCol) {
                    $query->where('collection_line', 'atelier');
                }
            } elseif ($activeWorld === 'voucher') {
                $query->where(function ($sub) use ($hasCollectionLineCol) {
                    $sub->where('type', 'digital');
                    if ($hasCollectionLineCol) {
                        $sub->orWhere('collection_line', 'voucher');
                    }
                });
            } else {
                $query->where('type', 'physical');
                if ($hasCollectionLineCol) {
                    $query->where(fn($sub) => $sub->whereNull('collection_line')->orWhere('collection_line', '!=', 'atelier'));
                }
            }
        })->orderBy('name');
        $categories = $categoryQuery->get();

        if (!empty($this->filterCategory) && !$categories->contains('id', (int)$this->filterCategory)) {
            $this->filterCategory = '';
        }

        // Dynamisches Laden der Anlässe (Holidays), die tatsächlich bei aktiven Vorlagen aktiver Produkte existieren
        $rawHolidays = ProductTemplate::where('is_active', true)
            ->whereNotNull('holiday')
            ->whereNotIn('holiday', ['', 'kein feiertag', 'Kein Feiertag', 'kein_feiertag', 'none', 'null'])
            ->whereHas('product', function ($q) use ($activeWorld, $hasCollectionLineCol) {
                $q->where('status', 'active');
                if ($activeWorld === 'atelier' && $hasCollectionLineCol) {
                    $q->where('collection_line', 'atelier');
                } elseif ($activeWorld === 'manufaktur' && $hasCollectionLineCol) {
                    $q->where('type', 'physical')
                      ->where(fn($sub) => $sub->whereNull('collection_line')->orWhere('collection_line', '!=', 'atelier'));
                }
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
            $trimmed = trim($h);
            $lower = strtolower($trimmed);
            $label = $holidayLabels[$lower] ?? ucfirst($trimmed);
            $availableHolidays[$trimmed] = $label;
        }

        if (!empty($this->filterHoliday)) {
            $holidayExists = collect($availableHolidays)->contains(function ($label, $val) {
                return strtolower($val) === strtolower($this->filterHoliday);
            });
            if (!$holidayExists) {
                $this->filterHoliday = '';
            }
        }

        // Haupt-Query aufbauen (verstecktes Ingame-Shop-Systemprodukt aus dem regulären Shop-Katalog ausblenden)
        $query = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->with(['categories', 'tierPrices'])
            ->withCount(['reviews as approved_reviews_count' => function ($q) {
                $q->where('status', 'approved');
            }])
            ->withAvg(['reviews as approved_reviews_avg' => function ($q) {
                $q->where('status', 'approved');
            }], 'rating');

        // 1. Strikte Trennung nach 3 Kollektions-Welten (Manufaktur vs. Atelier Echtgold/Silber vs. Geschenkgutscheine)
        if ($activeWorld === 'atelier') {
            $query->where('type', 'physical');
            if ($hasCollectionLineCol) {
                $query->where('collection_line', 'atelier');
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($activeWorld === 'voucher') {
            $query->where(function ($q) use ($hasCollectionLineCol) {
                $q->where('type', 'digital');
                if ($hasCollectionLineCol) {
                    $q->orWhere('collection_line', 'voucher');
                }
            });
        } else {
            // Standard: Manufaktur-Kollektion (ohne digitale Gutscheine, ohne Service, ohne hochpreisige Atelier-Echtgold-Stücke)
            $query->where('type', 'physical');
            if ($hasCollectionLineCol) {
                $query->where(function ($q) {
                    $q->whereNull('collection_line')
                      ->orWhere('collection_line', '!=', 'atelier');
                });
            }
        }

        // 2. Suche nach Name oder Beschreibung
        if (!empty($this->search)) {
            $escapedSearch = addcslashes((string) $this->search, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('short_description', 'like', '%' . $escapedSearch . '%');
            });
        }

        // 3. Filter nach Kategorie (Relation)
        if (!empty($this->filterCategory)) {
            $query->whereHas('categories', function($q) {
                $q->where('product_categories.id', $this->filterCategory);
            });
        }

        // 3b. Filter nach Personalisierbarkeit (Prüfen, ob Standard-Produkte in dieser Kollektions-Welt existieren)
        $standardProductsQuery = Product::where('status', 'active')
            ->where('slug', '!=', 'seelenfunke-ingame-artifact')
            ->where('is_personalizable', false);

        if ($activeWorld === 'atelier') {
            $standardProductsQuery->where('type', 'physical');
            if ($hasCollectionLineCol) {
                $standardProductsQuery->where('collection_line', 'atelier');
            } else {
                $standardProductsQuery->whereRaw('1 = 0');
            }
        } elseif ($activeWorld === 'voucher') {
            $standardProductsQuery->where(function ($q) use ($hasCollectionLineCol) {
                $q->where('type', 'digital');
                if ($hasCollectionLineCol) {
                    $q->orWhere('collection_line', 'voucher');
                }
            });
        } else {
            $standardProductsQuery->where('type', 'physical');
            if ($hasCollectionLineCol) {
                $standardProductsQuery->where(function ($q) {
                    $q->whereNull('collection_line')
                      ->orWhere('collection_line', '!=', 'atelier');
                });
            }
        }

        $hasStandardProducts = $standardProductsQuery->exists();

        if (!$hasStandardProducts && $this->filterPersonalizable === 'no') {
            $this->filterPersonalizable = 'all';
        }

        if ($this->filterPersonalizable === 'yes') {
            $query->where('is_personalizable', true);
        } elseif ($this->filterPersonalizable === 'no') {
            $query->where('is_personalizable', false);
        }

        // 3c. Filter nach Angeboten (Sale: compare_at_price > price)
        if ($this->filterSale) {
            $query->whereNotNull('compare_at_price')
                  ->whereColumn('compare_at_price', '>', 'price');
        }

        $saleCount = Product::where('status', 'active')
            ->whereNotNull('compare_at_price')
            ->whereColumn('compare_at_price', '>', 'price')
            ->count();

        // 4. Filter nach Anlass / Feiertag (Template Projection Feature)
        if (!empty($this->filterHoliday)) {
            $query->whereHas('templates', function($q) {
                $q->where(function($sub) {
                    $sub->where('holiday', $this->filterHoliday)
                        ->orWhere('holiday', strtolower($this->filterHoliday))
                        ->orWhere('holiday', ucfirst($this->filterHoliday));
                })->where('is_active', true);
            });
            // Eager Load Templates for the thumbnail swap in blade view
            $query->with(['templates' => function($q) {
                $q->where(function($sub) {
                    $sub->where('holiday', $this->filterHoliday)
                        ->orWhere('holiday', strtolower($this->filterHoliday))
                        ->orWhere('holiday', ucfirst($this->filterHoliday));
                })->where('is_active', true);
            }]);
        } else {
            $query->with(['templates' => function($q) {
                $q->where('is_active', true);
            }]);
        }

        $products = $query->latest()->paginate(12);

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
        $shopTitle = "Unsere Kollektion – Personalisierte Geschenke & Lasergravur | {$companyName}";
        $shopMetaDesc = 'Entdecke handveredelte Unikate mit präziser Lasergravur: K9-Kristallglas-Trophäen, Edelstahl-Schlüsselanhänger, Hundemarken & Kristallgläser – live im 3D-Konfigurator gestalten.';
        $shopCanonical = route('shop');

        if ($activeWorld === 'atelier') {
            $shopTitle = "Atelier Echtgold & Silber – Exklusive Unikate mit Gravur | {$companyName}";
            $shopMetaDesc = "Meisterstücke aus echtem 585er & 333er Gold sowie 925er Sterling Silber – mit weißen Handschuhen individuell in unserer Manufaktur in {$city} veredelt.";
            $shopCanonical = route('shop', ['collectionWorld' => 'atelier']);
        } elseif ($activeWorld === 'voucher') {
            $shopTitle = "Geschenkgutscheine – Sofort-Freude & Edel-Umschlag | {$companyName}";
            $shopMetaDesc = "Verschenke den Seelenfunke-Geschenkgutschein digital als Sofort-PDF oder als lasergravierte Aluminiumkarte im schwarzen Edel-Umschlag mit Wachssiegel.";
            $shopCanonical = route('shop', ['collectionWorld' => 'voucher']);
        } elseif (!empty($this->filterHoliday)) {
            $normH = mb_strtolower(trim((string) $this->filterHoliday));
            $hLabel = $holidayLabels[$normH] ?? ucfirst($normH);
            $shopTitle = "{$hLabel} Geschenke personalisiert mit Lasergravur | {$companyName}";
            $shopMetaDesc = "Besondere Geschenke zu {$hLabel}: Handveredelte Unikate aus K9-Kristallglas, Edelstahl & Metall mit eigener Wunschgravur im 2D/3D-Konfigurator gestalten.";
            $shopCanonical = route('shop', ['filterHoliday' => $normH]);
        } elseif (!empty($this->filterCategory)) {
            $selectedCat = $categories->firstWhere('id', (int) $this->filterCategory);
            if ($selectedCat) {
                $shopTitle = "{$selectedCat->name} – Personalisiert mit Lasergravur | {$companyName}";
                $shopMetaDesc = "Entdecke personalisierbare Unikate in der Kategorie {$selectedCat->name} – individuell graviert in unserer deutschen Manufaktur in {$city}.";
            }
        } elseif ($this->filterSale) {
            $shopTitle = "Angebote & Reduzierte Geschenkideen mit Gravur | {$companyName}";
            $shopMetaDesc = "Sichere dir personalisierte Geschenke mit Lasergravur zum Aktionspreis – handveredelt in unserer Manufaktur in {$city}.";
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
            'hasStandardProducts' => $hasStandardProducts,
            'manufakturCount' => $manufakturCount,
            'atelierCount' => $atelierCount,
            'voucherCount' => $voucherCount,
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
