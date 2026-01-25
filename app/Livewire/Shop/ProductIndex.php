<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;

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

        return view('livewire.shop.product-index', [
            'products' => $products
        ]);
    }
}
