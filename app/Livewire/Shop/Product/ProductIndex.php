<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

class ProductIndex extends Component
{
    use WithPagination;

    #[Title('Unsere Kollektion - Mein Seelenfunke')]
    public function render()
    {
        // Wartungsmodus Check
        if (shop_setting('maintenance_mode', false)) {
            return view('global.errors.503_fragment')->layout('components.layouts.frontend_layout');
        }

        $products = Product::where('status', 'active')
            ->latest()
            ->paginate(12);

        return view('livewire.shop.product.product-index', [
            'products' => $products
        ])->layout('components.layouts.frontend_layout');
    }
}
