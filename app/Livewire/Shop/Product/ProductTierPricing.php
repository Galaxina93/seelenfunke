<?php

namespace App\Livewire\Shop\Product;

use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ProductTierPricing extends Component
{
    #[\Livewire\Attributes\Locked]
    public Product $product;

    #[Reactive]
    public $currentPrice = 0;

    public $tiers = [];

    // NEU: Hilfetexte für das UI
    public $infoTexts = [
        'tier_pricing' => 'Definieren Sie Mengenrabatte für dieses Produkt. Der Preis reduziert sich automatisch im Warenkorb, sobald die angegebene Stückzahl erreicht wird.',
    ];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }
    }

    public function mount(Product $product)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

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
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        if ($this->product->tierPrices()->count() >= 50) {
            return;
        }

        $this->product->tierPrices()->create([
            'qty' => 1,
            'percent' => 0
        ]);

        $this->refreshTiers();
    }

    public function removeTier($tierId)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        $this->product->tierPrices()->where('id', $tierId)->delete();
        $this->refreshTiers();
    }

    public function updateTier($tierId)
    {
        if (!Auth::guard('admin')->check() && !Auth::guard('employee')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403);
        }

        if (!isset($this->tiers[$tierId])) {
            return;
        }

        $tierData = $this->tiers[$tierId];
        $qty = max(1, min(100000, (int)($tierData['qty'] ?? 1)));
        $percent = max(0, min(100, (float)($tierData['percent'] ?? 0)));

        $this->product->tierPrices()->where('id', $tierId)->update([
            'qty' => $qty,
            'percent' => $percent
        ]);
        $this->refreshTiers();
    }

    public function render()
    {
        return view('livewire.shop.product.product-tier-pricing');
    }
}
