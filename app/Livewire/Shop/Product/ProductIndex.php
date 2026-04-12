<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\ProductCategory;
use App\Models\Product\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = 'all'; // all, physical, digital, service
    public $filterPersonalizable = 'all'; // all, yes, no
    public $filterCategory = '';
    public $filterHoliday = '';

    // Wir speichern den Query-String in der URL für besseres Teilen von Links
    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => 'all'],
        'filterPersonalizable' => ['except' => 'all'],
        'filterCategory' => ['except' => ''],
        'filterHoliday' => ['except' => ''],
    ];

    public function updatedSearch()
    {
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
        $this->reset(['search', 'filterType', 'filterPersonalizable', 'filterCategory', 'filterHoliday']);
        $this->resetPage();
    }

    #[Title('Unsere Kollektion - Mein Seelenfunke')]
    public function render()
    {
        // Wartungsmodus Check
        if (shop_setting('maintenance_mode', false)) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        // Dynamisches Laden der Kategorien, die auch Produkte enthalten
        $categories = ProductCategory::whereHas('products', function ($query) {
            $query->where('status', 'active');
        })->orderBy('name')->get();

        // Haupt-Query aufbauen
        $query = Product::where('status', 'active');

        // 1. Suche nach Name oder Beschreibung
        if (!empty($this->search)) {
            $query->where(function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('short_description', 'like', '%' . $this->search . '%');
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

        // 4. Filter nach Anlass / Feiertag (Template Projection Feature)
        if (!empty($this->filterHoliday)) {
            $query->whereHas('templates', function($q) {
                $q->where('holiday', $this->filterHoliday)->where('is_active', true);
            });
            // Eager Load Templates for the thumbnail swap in blade view
            $query->with(['templates' => function($q) {
                $q->where('holiday', $this->filterHoliday)->where('is_active', true);
            }]);
        }

        $products = $query->latest()->paginate(12);

        return view('livewire.shop.product.product-index', [
            'products' => $products,
            'categories' => $categories,
        ])->layout('components.layouts.frontend_layout');
    }
}
