<?php

namespace App\Livewire\Shop\Product;

use App\Models\Category;
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
    public $filterCategory = '';

    // Wir speichern den Query-String in der URL für besseres Teilen von Links
    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => 'all'],
        'filterCategory' => ['except' => ''],
    ];

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterType()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterType', 'filterCategory']);
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
        $categories = Category::whereHas('products', function ($query) {
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
                $q->where('categories.id', $this->filterCategory);
            });
        }

        $products = $query->latest()->paginate(12);

        return view('livewire.shop.product.product-index', [
            'products' => $products,
            'categories' => $categories,
        ])->layout('components.layouts.frontend_layout');
    }
}
