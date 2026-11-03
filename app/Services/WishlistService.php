<?php

namespace App\Services;

use App\Models\Cart\WishlistItem;
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
     * Führt Gast-Wunschliste automatisch mit dem Kundenkonto zusammen, sobald der Kunde eingeloggt ist.
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
            ->get();

        foreach ($guestItems as $guestItem) {
            $exists = WishlistItem::where('customer_id', $customerId)
                ->where('product_id', $guestItem->product_id)
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

        return $this->baseQuery()
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
            ->exists();
    }

    /**
     * Toggles a product on the wishlist.
     *
     * @return bool true if added, false if removed
     */
    public function toggle(string $productId): bool
    {
        if (!Schema::hasTable('wishlist_items') || $productId === '') {
            return false;
        }

        $existing = $this->baseQuery()
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        WishlistItem::create([
            'customer_id' => $this->getCustomerId(),
            'session_id' => $this->getSessionId(),
            'product_id' => $productId,
        ]);

        return true;
    }

    public function remove(string $productId): void
    {
        if (!Schema::hasTable('wishlist_items') || $productId === '') {
            return;
        }

        $this->baseQuery()
            ->where('product_id', $productId)
            ->delete();
    }
}
