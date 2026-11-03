<?php

namespace App\Livewire\Shop\Wishlist;

use App\Services\WishlistService;
use Livewire\Attributes\On;
use Livewire\Component;

class WishlistIcon extends Component
{
    public int $count = 0;
    public array $previewItems = [];

    public function mount(WishlistService $service): void
    {
        $this->updateStats($service);
    }

    #[On('wishlist-updated')]
    public function updateStats(WishlistService $service): void
    {
        $items = $service->getItems();
        $this->count = $items->count();

        $this->previewItems = $items->take(4)->map(function ($item) {
            $product = $item->product;
            $resolvedMeta = $product->resolveMainImageMeta();
            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'formatted_price' => $product->formatted_price,
                'preview_url' => $resolvedMeta['url'] ?? $product->preview_url,
            ];
        })->all();
    }

    public function removeItem(string $productId, WishlistService $service): void
    {
        $service->remove($productId);
        $this->updateStats($service);
        $this->dispatch('wishlist-updated');
    }

    public function render()
    {
        return view('livewire.shop.wishlist.wishlist-icon');
    }
}
