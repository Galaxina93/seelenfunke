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
    public $filterType = 'all'; // all, physical, digital, service
    public $filterPersonalizable = 'all'; // all, yes, no
    public $filterSale = false; // Nur reduzierte Angebote (compare_at_price > price)
    public $filterCategory = '';
    public $filterHoliday = '';

    // Wir speichern den Query-String in der URL für besseres Teilen von Links
    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => 'all'],
        'filterPersonalizable' => ['except' => 'all'],
        'filterSale' => ['except' => false],
        'filterCategory' => ['except' => ''],
        'filterHoliday' => ['except' => ''],
    ];

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

    #[Title('Unsere Kollektion - Mein Seelenfunke')]
    public function render()
    {
        // Wartungsmodus Check
        if (shop_setting('maintenance_mode', false)) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        // Dynamisch ermitteln, welche Produkttypen tatsächlich in aktiven Produkten vorhanden sind
        $availableTypes = Product::where('status', 'active')
            ->distinct()
            ->pluck('type')
            ->toArray();

        // Falls filterType gesetzt ist, dieser Typ aber gar nicht mehr existiert, zurücksetzen
        if ($this->filterType !== 'all' && !in_array($this->filterType, $availableTypes, true)) {
            $this->filterType = 'all';
        }

        // Dynamisches Laden der Kategorien, die auch aktive Produkte enthalten
        $categoryQuery = ProductCategory::whereHas('products', function ($query) {
            $query->where('status', 'active');
        })->orderBy('name');
        $categories = $categoryQuery->get();

        if (!empty($this->filterCategory) && !$categories->contains('id', (int)$this->filterCategory)) {
            $this->filterCategory = '';
        }

        // Dynamisches Laden der Anlässe (Holidays), die tatsächlich bei aktiven Vorlagen aktiver Produkte existieren
        $rawHolidays = ProductTemplate::where('is_active', true)
            ->whereNotNull('holiday')
            ->whereNotIn('holiday', ['', 'kein feiertag', 'Kein Feiertag', 'kein_feiertag', 'none', 'null'])
            ->whereHas('product', function ($q) {
                $q->where('status', 'active');
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

        // Haupt-Query aufbauen
        $query = Product::where('status', 'active')
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
            $query->where(function($q) use ($escapedSearch) {
                $q->where('name', 'like', '%' . $escapedSearch . '%')
                    ->orWhere('short_description', 'like', '%' . $escapedSearch . '%');
            });
        }

        // 2. Filter nach Typ (Physisch, Digital, Service)
        if ($this->filterType !== 'all') {
            $query->where('type', $this->filterType);
        }

        // 3. Filter nach Kategorie (Relation)
        if (!empty($this->filterCategory)) {
            $query->whereHas('categories', function($q) {
                $q->where('product_categories.id', $this->filterCategory);
            });
        }

        // 3b. Filter nach Personalisierbarkeit
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

        $shopSchema = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => route('shop') . '#breadcrumb',
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
                            'item' => route('shop'),
                        ],
                    ],
                ],
                [
                    '@type' => 'CollectionPage',
                    '@id' => route('shop') . '#collection',
                    'url' => route('shop'),
                    'name' => 'Unsere Kollektion – Personalisierte Geschenke & Lasergravur | Mein Seelenfunke',
                    'description' => 'Handveredelte Unikate aus K9-Kristallglas, Naturschiefer und Metall. Direkt im interaktiven 2D- & 3D-Online-Konfigurator personalisieren.',
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

        $shopMetaDesc = 'Entdecke handveredelte Unikate mit präziser Lasergravur: K9-Kristallglas-Trophäen, Glasfotos, Naturschiefer & Edelmetall – live im 2D/3D-Konfigurator gestalten.';

        return view('livewire.shop.product.product-frontend-filter-area', [
            'products' => $products,
            'categories' => $categories,
            'availableTypes' => $availableTypes,
            'availableHolidays' => $availableHolidays,
            'saleCount' => $saleCount,
            'heroBanner' => $heroBanner,
            'shopSchema' => $shopSchema,
        ])->layout('components.layouts.frontend_layout', [
            'meta_description' => $shopMetaDesc,
            'og_description' => $shopMetaDesc,
            'canonical' => route('shop'),
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
