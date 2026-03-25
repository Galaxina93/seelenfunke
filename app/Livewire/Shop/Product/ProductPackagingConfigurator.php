<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductPackaging;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;

class ProductPackagingConfigurator extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Produkte';

    public $selectedProductId = '';

    // Form fields for adding new material
    public $newType = '';
    public $newWeightGrams = null;

    // Form fields for editing existing material
    public $editId = null;
    public $editWeightGrams = null;

    protected $rules = [
        'newType' => 'required|string',
        'newWeightGrams' => 'required|numeric|min:1',
    ];

    public function getProductProperty()
    {
        return $this->selectedProductId ? Product::find($this->selectedProductId) : null;
    }

    public function updatedSelectedProductId()
    {
        $this->reset(['newType', 'newWeightGrams', 'editId', 'editWeightGrams']);
    }

    public function addMaterial()
    {
        $this->validate();

        $product = $this->product;
        if (!$product) return;

        // Check if material already exists to prevent duplicates - if so, just add weight.
        $existing = $product->packagings()->where('material_type', $this->newType)->first();

        if ($existing) {
            $existing->increment('weight_grams', $this->newWeightGrams);
            $this->dispatch('toast', message: 'Gewicht zum bestehenden Material addiert.', type: 'info');
        } else {
            $product->packagings()->create([
                'material_type' => $this->newType,
                'weight_grams' => $this->newWeightGrams,
            ]);
            $this->dispatch('toast', message: 'Verpackungsmaterial erfolgreich hinzugefügt.', type: 'success');
        }

        $this->reset(['newType', 'newWeightGrams']);
    }

    public function startEdit($id, $currentWeight)
    {
        $this->editId = $id;
        $this->editWeightGrams = $currentWeight;
    }

    public function saveEdit()
    {
        $this->validate([
            'editWeightGrams' => 'required|numeric|min:1'
        ]);

        $product = $this->product;
        if (!$product) return;

        $packaging = $product->packagings()->findOrFail($this->editId);
        $packaging->update(['weight_grams' => $this->editWeightGrams]);

        $this->editId = null;
        $this->editWeightGrams = null;
        
        $this->dispatch('toast', message: 'Gewicht aktualisiert.', type: 'success');
    }

    public function cancelEdit()
    {
        $this->editId = null;
        $this->editWeightGrams = null;
    }

    public function deleteMaterial($id)
    {
        $product = $this->product;
        if (!$product) return;

        $product->packagings()->where('id', $id)->delete();
        $this->dispatch('toast', message: 'Material entfernt.', type: 'info');
    }

    public function render()
    {
        return view('livewire.shop.product.product-packaging-configurator', [
            'products' => Product::where('status', '!=', 'archived')
                                 ->where('type', 'physical')
                                 ->orderBy('name')
                                 ->get(),
            'packagings' => $this->product ? $this->product->packagings()->orderBy('material_type')->get() : collect(),
            'availableTypes' => ProductPackaging::getMaterialTypes(),
        ]);
    }
}
