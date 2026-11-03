<?php

namespace App\Livewire\Shop\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class CartIcon extends Component
{
    // Wir speichern das komplette Array, um Zugriff auf alle Werte zu haben
    public array $totals = [
        'subtotal' => 0,
        'tax' => 0,
        'shipping' => 0,
        'total' => 0,
        'item_count' => 0
    ];

    public bool $suppressPreview = false;

    public function mount(CartService $service)
    {
        $this->suppressPreview = request()->routeIs('cart', 'checkout*') || request()->is('cart*', 'checkout*');
        $this->updateStats($service);
    }

    #[On('cart-updated')]
    public function updateStats(CartService $service)
    {
        $totals = $service->getTotals();

        // Mapping auf die Properties der Komponente
        $this->totals = [
            'subtotal' => $totals['subtotal_gross'],
            'tax' => $totals['tax'],

            // --- NEU HINZUFÜGEN: ---
            'taxes_breakdown' => $totals['taxes_breakdown'] ?? [],
            'discount_amount' => $totals['discount_amount'] ?? 0,
            // -----------------------

            'shipping' => $totals['shipping'],
            'total' => $totals['total'],
            'item_count' => $totals['item_count']
        ];
    }

    public function render()
    {
        return view('livewire.shop.cart.cart-icon');
    }
}
