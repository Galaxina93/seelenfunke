<?php

namespace App\Livewire\Shop\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartIcon extends Component
{
    public const DEFAULT_TOTALS = [
        'subtotal' => 0,
        'subtotal_gross' => 0,
        'tax' => 0,
        'taxes_breakdown' => [],
        'discount' => 0,
        'discount_amount' => 0,
        'shipping' => 0,
        'total' => 0,
        'item_count' => 0,
    ];

    // Wir speichern das komplette Array mit allen erwarteten Standard-Schlüsseln
    public array $totals = self::DEFAULT_TOTALS;

    public bool $suppressPreview = false;

    public function mount(CartService $service): void
    {
        $this->suppressPreview = request()->routeIs('cart', 'checkout*') || request()->is('cart*', 'checkout*');
        $this->updateStats($service);
    }

    public function hydrate(): void
    {
        $this->totals = $this->normalizeTotals($this->totals);
    }

    public function updated(string $property): void
    {
        if ($property === 'totals' || str_starts_with($property, 'totals.')) {
            try {
                $this->refreshCart(app(CartService::class));
            } catch (\Throwable) {
                $this->totals = self::DEFAULT_TOTALS;
            }
        }
    }

    #[On('cart-updated')]
    public function updateStats(CartService $service): void
    {
        $this->refreshCart($service);
    }

    public function refreshCart(CartService $service): void
    {
        $totals = $service->getTotals();
        $totals = is_array($totals) ? $totals : [];

        $subtotal = is_numeric($totals['subtotal_gross'] ?? null)
            ? (int) $totals['subtotal_gross']
            : (is_numeric($totals['subtotal'] ?? null) ? (int) $totals['subtotal'] : 0);

        $discountAmount = is_numeric($totals['discount_amount'] ?? null)
            ? (int) $totals['discount_amount']
            : (is_numeric($totals['discount'] ?? null) ? (int) $totals['discount'] : 0);

        $this->totals = $this->normalizeTotals([
            'subtotal' => $subtotal,
            'subtotal_gross' => $subtotal,
            'tax' => $totals['tax'] ?? 0,
            'taxes_breakdown' => $totals['taxes_breakdown'] ?? [],
            'discount' => $discountAmount,
            'discount_amount' => $discountAmount,
            'shipping' => $totals['shipping'] ?? 0,
            'total' => $totals['total'] ?? 0,
            'item_count' => $totals['item_count'] ?? 0,
        ]);
    }

    protected function normalizeTotals(mixed $totals): array
    {
        $merged = array_merge(
            self::DEFAULT_TOTALS,
            is_array($totals) ? array_intersect_key($totals, self::DEFAULT_TOTALS) : []
        );

        $subtotal = is_numeric($merged['subtotal'] ?? null)
            ? (int) $merged['subtotal']
            : (is_numeric($merged['subtotal_gross'] ?? null) ? (int) $merged['subtotal_gross'] : 0);

        $discountAmount = is_numeric($merged['discount_amount'] ?? null)
            ? (int) $merged['discount_amount']
            : (is_numeric($merged['discount'] ?? null) ? (int) $merged['discount'] : 0);

        $rawBreakdown = is_array($merged['taxes_breakdown'] ?? null) ? $merged['taxes_breakdown'] : [];
        $safeBreakdown = [];
        foreach ($rawBreakdown as $rate => $amount) {
            if ((is_string($rate) || is_int($rate)) && is_numeric($amount)) {
                $safeBreakdown[(string) $rate] = (int) $amount;
            }
        }

        return [
            'subtotal' => $subtotal,
            'subtotal_gross' => is_numeric($merged['subtotal_gross'] ?? null) ? (int) $merged['subtotal_gross'] : $subtotal,
            'tax' => is_numeric($merged['tax'] ?? null) ? (int) $merged['tax'] : 0,
            'taxes_breakdown' => $safeBreakdown,
            'discount' => $discountAmount,
            'discount_amount' => $discountAmount,
            'shipping' => is_numeric($merged['shipping'] ?? null) ? (int) $merged['shipping'] : 0,
            'total' => is_numeric($merged['total'] ?? null) ? (int) $merged['total'] : 0,
            'item_count' => max(0, is_numeric($merged['item_count'] ?? null) ? (int) $merged['item_count'] : 0),
        ];
    }

    public function render()
    {
        $this->totals = $this->normalizeTotals($this->totals);

        return view('livewire.shop.cart.cart-icon');
    }
}
