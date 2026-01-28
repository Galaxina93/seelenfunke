<?php

namespace App\Livewire\Shop;

use App\Models\Product;
use App\Models\ProductTierPrice;
use Livewire\Component;
use Livewire\Attributes\Reactive;

class ProductTierPricing extends Component
{
    public Product $product;

    #[Reactive]
    public $currentPrice = 0;

    public $tiers = [];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->refreshTiers();
    }

    public function refreshTiers()
    {
        // 1. Laden
        // 2. Sortieren (created_at ist am stabilsten beim Tippen)
        // 3. keyBy('id') -> Das ist der entscheidende Fix!
        $this->tiers = $this->product->tierPrices()
            ->orderBy('created_at', 'asc')
            ->get()
            ->keyBy('id')
            ->toArray();
    }

    public function addTier()
    {
        $this->product->tierPrices()->create([
            'qty' => 0,
            'percent' => 0
        ]);

        $this->refreshTiers();
    }

    public function removeTier($tierId)
    {
        ProductTierPrice::destroy($tierId);
        $this->refreshTiers();
    }

    // Update empfängt jetzt die ID (String), nicht mehr den Index (Int)
    public function updateTier($tierId)
    {
        // Sicherheitscheck, falls ID nicht im Array (sollte nicht passieren)
        if (!isset($this->tiers[$tierId])) {
            return;
        }

        $tierData = $this->tiers[$tierId];

        ProductTierPrice::where('id', $tierId)->update([
            'qty' => $tierData['qty'],
            'percent' => $tierData['percent']
        ]);
    }

    public function render()
    {
        return view('livewire.shop.product-tier-pricing');
    }
}
