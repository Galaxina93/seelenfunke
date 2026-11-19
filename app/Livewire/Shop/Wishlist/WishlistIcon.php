<?php

namespace App\Livewire\Shop\Wishlist;

use App\Services\WishlistService;
use Livewire\Component;

class WishlistIcon extends Component
{
    public int $count = 0;
    public array $productIds = [];
    public array $previewItems = [];

    public function mount(WishlistService $service): void
    {
        $this->updateStats($service);
    }

    public function updateStats(WishlistService $service): void
    {
        $payload = $service->buildStatePayload();
        $this->count = $payload['count'];
        $this->productIds = $payload['product_ids'];
        $this->previewItems = $payload['preview_items'];
    }

    public function removeItem(string $productId, WishlistService $service): void
    {
        $service->setWishlisted($productId, false);
        $this->updateStats($service);
        $this->dispatch(
            'wishlist-updated',
            count: $this->count,
            productIds: $this->productIds
        );
    }

    public function render()
    {
        return view('livewire.shop.wishlist.wishlist-icon');
    }
}
