<?php

namespace App\Livewire\Shop\Product;

use App\Models\Category;
use App\Models\Product\Product;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductCategories extends Component
{
    public Product $product;
    public $selectedCategories = [];

    // Daten für die Ansicht
    public array $availableCategories = [];

    // Suche & Modus
    public $search = '';
    public $isManaging = false; // Schaltet zwischen Auswahl- und Verwaltungsmodus um

    // Formular-Felder für Verwaltung
    public $newCategoryName = '';
    public $editingCategoryId = null;
    public $editingCategoryName = '';

    public function mount(Product $product)
    {
        $this->product = $product;

        // IDs laden (Mit Fix für Ambiguous Column)
        $this->selectedCategories = $this->product->categories()->pluck('categories.id')->toArray();

        $this->loadCategories();
    }

    public function loadCategories()
    {
        $query = Category::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        // Als Array laden für Performance und Morph-Map Vermeidung
        $this->availableCategories = $query->orderBy('name')->get()->toArray();
    }

    public function updatedSearch()
    {
        $this->loadCategories();
    }

    // --- AUSWAHL LOGIK ---

    public function toggleCategory($categoryId)
    {
        // Im Management Modus verhindert Klick die Auswahl (optional, hier erlaubt)
        if($this->isManaging) return;

        $categoryId = (int) $categoryId;

        if (in_array($categoryId, $this->selectedCategories)) {
            $this->selectedCategories = array_diff($this->selectedCategories, [$categoryId]);
            $this->product->categories()->detach($categoryId);
        } else {
            $this->selectedCategories[] = $categoryId;
            $this->product->categories()->attach($categoryId);
        }

        $this->selectedCategories = array_values($this->selectedCategories);
    }

    // --- VERWALTUNGS LOGIK ---

    public function toggleManageMode()
    {
        $this->isManaging = !$this->isManaging;
        $this->resetInput();
        $this->loadCategories();
    }

    public function createCategory()
    {
        $this->validate([
            'newCategoryName' => 'required|min:2|unique:categories,name'
        ]);

        Category::create([
            'name' => $this->newCategoryName,
            'slug' => Str::slug($this->newCategoryName),
            'type' => $this->product->type, // Übernimmt den Typ des aktuellen Produkts
            'color' => 'bg-gray-100 text-gray-800' // Standard Farbe
        ]);

        $this->newCategoryName = '';
        session()->flash('success', 'Kategorie erstellt.');
        $this->loadCategories();
    }

    public function startEditing($id, $name)
    {
        $this->editingCategoryId = $id;
        $this->editingCategoryName = $name;
    }

    public function cancelEditing()
    {
        $this->resetInput();
    }

    public function updateCategory()
    {
        $this->validate([
            'editingCategoryName' => 'required|min:2|unique:categories,name,' . $this->editingCategoryId
        ]);

        $category = Category::find($this->editingCategoryId);
        if($category) {
            $category->update([
                'name' => $this->editingCategoryName,
                'slug' => Str::slug($this->editingCategoryName)
            ]);
        }

        $this->resetInput();
        $this->loadCategories();
    }

    public function deleteCategory($id)
    {
        $category = Category::find($id);

        if($category) {
            // Erst Beziehung lösen falls ausgewählt
            if(in_array($id, $this->selectedCategories)) {
                $this->product->categories()->detach($id);
                $this->selectedCategories = array_diff($this->selectedCategories, [$id]);
                $this->selectedCategories = array_values($this->selectedCategories);
            }

            $category->delete();
        }

        $this->loadCategories();
    }

    private function resetInput()
    {
        $this->newCategoryName = '';
        $this->editingCategoryId = null;
        $this->editingCategoryName = '';
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.shop.product.product-categories');
    }
}
