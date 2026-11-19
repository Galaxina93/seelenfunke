<?php

namespace App\Services;

use App\Models\Cart\WishlistItem;
use App\Models\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

class WishlistService
{
    public function getCustomerId(): ?string
    {
        return Auth::guard('customer')->id();
    }

    public function getSessionId(): string
    {
        return (string) Session::getId();
    }

    /**
     * Fuehrt Gast-Wunschliste automatisch und dublettenfrei mit dem Kundenkonto zusammen, sobald der Kunde eingeloggt ist.
     */
    protected function syncSessionToCustomerIfAuthenticated(): void
    {
        $customerId = $this->getCustomerId();
        $sessionId = $this->getSessionId();

        if (!$customerId || $sessionId === '' || !Schema::hasTable('wishlist_items')) {
            return;
        }

        $guestItems = WishlistItem::where('session_id', $sessionId)
            ->whereNull('customer_id')
            ->orderBy('id')
            ->get();

        if ($guestItems->isEmpty()) {
            return;
        }

        $seenGuestProductIds = [];
        foreach ($guestItems as $guestItem) {
            $pid = (string) $guestItem->product_id;
            if ($pid === '' || isset($seenGuestProductIds[$pid])) {
                $guestItem->delete();
                continue;
            }
            $seenGuestProductIds[$pid] = true;

            $exists = WishlistItem::where('customer_id', $customerId)
                ->where('product_id', $pid)
                ->exists();

            if ($exists) {
                $guestItem->delete();
            } else {
                $guestItem->update([
                    'customer_id' => $customerId,
                ]);
            }
        }
    }

    protected function baseQuery()
    {
        $this->syncSessionToCustomerIfAuthenticated();

        $customerId = $this->getCustomerId();
        $sessionId = $this->getSessionId();

        return WishlistItem::query()->where(function ($q) use ($customerId, $sessionId) {
            if ($customerId) {
                $q->where('customer_id', $customerId);
            } else {
                $q->where('session_id', $sessionId)->whereNull('customer_id');
            }
        });
    }

    public function getProductIds(): array
    {
        if (!Schema::hasTable('wishlist_items')) {
            return [];
        }

        return $this->getItems()
            ->pluck('product_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    public function getItems(): Collection
    {
        if (!Schema::hasTable('wishlist_items')) {
            return collect();
        }

        return $this->baseQuery()
            ->with('product')
            ->latest()
            ->get()
            ->filter(fn (WishlistItem $item) => $item->product !== null && $item->product->status === 'active')
            ->unique(fn (WishlistItem $item) => (string) $item->product_id)
            ->values();
    }

    public function getCount(): int
    {
        if (!Schema::hasTable('wishlist_items')) {
            return 0;
        }

        return $this->getItems()->count();
    }

    public function has(string $productId): bool
    {
        if (!Schema::hasTable('wishlist_items') || $productId === '') {
            return false;
        }

        return $this->baseQuery()
            ->where('product_id', $productId)
            ->whereHas('product', fn ($q) => $q->where('status', 'active'))
            ->exists();
    }

    /**
     * Setzt den Wunschlisten-Zustand idempotent (oder toggelt bei null) und bereinigt evtl. Dubletten restlos.
     */
    public function setWishlisted(string $productId, ?bool $shouldBeWishlisted = null): bool
    {
        if (!Schema::hasTable('wishlist_items') || $productId === '') {
            return false;
        }

        if ($shouldBeWishlisted === false) {
            $this->baseQuery()
                ->where('product_id', $productId)
                ->delete();

            return false;
        }

        $isActiveProduct = Product::where('id', $productId)
            ->where('status', 'active')
            ->exists();

        if ($shouldBeWishlisted === true) {
            if (!$isActiveProduct) {
                $this->baseQuery()
                    ->where('product_id', $productId)
                    ->delete();

                return false;
            }

            $existingRows = $this->baseQuery()
                ->where('product_id', $productId)
                ->orderBy('id')
                ->get();

            if ($existingRows->isEmpty()) {
                WishlistItem::create([
                    'customer_id' => $this->getCustomerId(),
                    'session_id' => $this->getSessionId(),
                    'product_id' => $productId,
                ]);
            } elseif ($existingRows->count() > 1) {
                $keepId = $existingRows->first()->id;
                $this->baseQuery()
                    ->where('product_id', $productId)
                    ->where('id', '!=', $keepId)
                    ->delete();
            }

            return true;
        }

        $exists = $this->baseQuery()
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            $this->baseQuery()
                ->where('product_id', $productId)
                ->delete();

            return false;
        }

        if (!$isActiveProduct) {
            return false;
        }

        WishlistItem::create([
            'customer_id' => $this->getCustomerId(),
            'session_id' => $this->getSessionId(),
            'product_id' => $productId,
        ]);

        return true;
    }

    /**
     * Toggles a product on the wishlist.
     *
     * @return bool true if added, false if removed
     */
    public function toggle(string $productId): bool
    {
        return $this->setWishlisted($productId, null);
    }

    public function remove(string $productId): void
    {
        $this->setWishlisted($productId, false);
    }

    /**
     * Liefert das konsistente JSON-/Initial-State-Payload fuer Header-Icons und Herz-Buttons.
     *
     * @return array{count: int, product_ids: array<int, string>, preview_items: array<int, array<string, string|null>>}
     */
    public function buildStatePayload(): array
    {
        $items = $this->getItems();
        $productIds = $items->pluck('product_id')->map(fn ($id) => (string) $id)->values()->all();

        $previewItems = $items->take(4)->map(function (WishlistItem $item): array {
            $product = $item->product;
            $resolvedMeta = $product->resolveMainImageMeta();

            return [
                'product_id' => (string) $product->id,
                'name' => (string) $product->name,
                'slug' => (string) $product->slug,
                'url' => route('product.show', $product->slug),
                'formatted_price' => (string) $product->formatted_price,
                'preview_url' => $resolvedMeta['url'] ?? $product->preview_url,
            ];
        })->values()->all();

        return [
            'count' => count($productIds),
            'product_ids' => $productIds,
            'preview_items' => $previewItems,
        ];
    }
}
