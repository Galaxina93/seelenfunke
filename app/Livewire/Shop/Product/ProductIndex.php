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
        // Wir zeigen nur aktive Produkte an, sortiert nach dem Neuesten
        $products = Product::where('status', 'active')
            ->latest()
            ->paginate(12); // 12 Produkte pro Seite

        return view('livewire.shop.product.product-index', [
            'products' => $products
        ])->layout('components.layouts.frontend_layout');
    }
}
