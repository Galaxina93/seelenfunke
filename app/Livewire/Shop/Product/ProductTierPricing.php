<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProductTierPricing extends Component
{
    public Product $product;

    #[Reactive]
    public $currentPrice = 0;

    public $tiers = [];

    // NEU: Hilfetexte für das UI
    public $infoTexts = [
        'tier_pricing' => 'Definieren Sie Mengenrabatte für dieses Produkt. Der Preis reduziert sich automatisch im Warenkorb, sobald die angegebene Stückzahl erreicht wird.',
    ];

    public function mount(Product $product)
    {
        $this->product = $product;
        $this->refreshTiers();
    }

    public function refreshTiers()
    {
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

    public function updateTier($tierId)
    {
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
        return view('livewire.shop.product.product-tier-pricing');
    }
}
