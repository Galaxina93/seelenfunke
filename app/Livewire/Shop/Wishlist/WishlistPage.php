<?php

namespace App\Livewire\Shop\Wishlist;

use App\Models\Product\Product;
use App\Services\CartService;
use App\Services\WishlistService;
use Livewire\Attributes\On;
use Livewire\Component;

class WishlistPage extends Component
{
    public string $sharedSlugsRaw = '';
    public bool $isSharedView = false;

    public function mount(): void
    {
        $wl = trim((string) request()->query('wl', ''));
        if ($wl !== '') {
            $this->sharedSlugsRaw = $wl;
            $this->isSharedView = true;
        }
    }

    public function removeItem(string $productId, WishlistService $wishlistService): void
    {
        $wishlistService->remove($productId);
        $this->dispatch('wishlist-updated');
    }

    public function toggleWishlist(string $productId, WishlistService $wishlistService): void
    {
        $wishlistService->toggle($productId);
        $this->dispatch('wishlist-updated');
    }

    public function importSharedWishlist(WishlistService $wishlistService): void
    {
        $slugs = $this->parseSharedSlugs();
        if (empty($slugs)) {
            return;
        }

        $products = Product::where('status', 'active')
            ->whereIn('slug', $slugs)
            ->pluck('id');

        foreach ($products as $pid) {
            if (!$wishlistService->has((string) $pid)) {
                $wishlistService->toggle((string) $pid);
            }
        }

        $this->isSharedView = false;
        $this->sharedSlugsRaw = '';
        $this->dispatch('wishlist-updated');
    }

    public function addToCart(string $productId, CartService $cartService): void
    {
        $product = Product::find($productId);
        if (!$product || $product->status !== 'active') {
            return;
        }

        if ($product->isPersonalizable()) {
            $this->redirect(route('product.show', $product->slug), navigate: true);
            return;
        }

        $cartService->addItem($product, 1);
        $this->dispatch('cart-updated', openPreview: true);
    }

    protected function parseSharedSlugs(): array
    {
        if ($this->sharedSlugsRaw === '') {
            return [];
        }

        $parts = array_filter(array_map(function ($s) {
            return preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($s));
        }, explode(',', $this->sharedSlugsRaw)));

        return array_slice(array_values(array_unique($parts)), 0, 25);
    }

    #[On('wishlist-updated')]
    public function render(WishlistService $wishlistService)
    {
        $ownProductIds = $wishlistService->getProductIds();

        if ($this->isSharedView) {
            $sharedSlugs = $this->parseSharedSlugs();
            $products = empty($sharedSlugs)
                ? collect()
                : Product::where('status', 'active')
                    ->whereIn('slug', $sharedSlugs)
                    ->with(['categories', 'tierPrices'])
                    ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->where('status', 'approved')])
                    ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->where('status', 'approved')], 'rating')
                    ->get();
        } else {
            $products = empty($ownProductIds)
                ? collect()
                : Product::where('status', 'active')
                    ->whereIn('id', $ownProductIds)
                    ->with(['categories', 'tierPrices'])
                    ->withCount(['reviews as approved_reviews_count' => fn ($q) => $q->where('status', 'approved')])
                    ->withAvg(['reviews as approved_reviews_avg' => fn ($q) => $q->where('status', 'approved')], 'rating')
                    ->get();
        }

        $shareSlugs = $products->pluck('slug')->filter()->values()->all();
        $shareUrl = !empty($shareSlugs)
            ? route('wishlist', ['wl' => implode(',', $shareSlugs)])
            : route('wishlist');

        return view('livewire.shop.wishlist.wishlist-page', [
            'products' => $products,
            'wishlistProductIds' => $ownProductIds,
            'shareUrl' => $shareUrl,
        ])->layout('components.layouts.frontend_layout');
    }
}
