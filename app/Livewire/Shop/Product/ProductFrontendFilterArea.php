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
        $query = Product::where('status', 'active');

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
        }

        $products = $query->latest()->paginate(12);

        return view('livewire.shop.product.product-frontend-filter-area', [
            'products' => $products,
            'categories' => $categories,
            'availableTypes' => $availableTypes,
            'availableHolidays' => $availableHolidays,
            'saleCount' => $saleCount,
        ])->layout('components.layouts.frontend_layout');
    }
}
