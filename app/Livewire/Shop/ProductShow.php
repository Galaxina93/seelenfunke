<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Component;

class ProductShow extends Component
{
    public Product $product;

    public function mount(Product $product)
    {
        // 1. Status Check: Nur aktive Produkte anzeigen
        // (Nutzer mit Admin-Rechten könnten hier theoretisch ausgenommen werden)
        if ($product->status !== 'active') {
            abort(404);
        }

        $this->product = $product;
    }

    // Dynamischer Seitentitel für den Browser-Tab
    public function render()
    {
        return view('livewire.shop.product-show')
            ->title($this->product->seo_title ?? $this->product->name)
            ->with([
                'meta_description' => $this->product->seo_description ?? Str::limit($this->product->short_description, 160)
            ]);
    }
}
