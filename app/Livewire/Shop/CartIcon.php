<?php

namespace App\Livewire\Shop;

use App\Services\CartService;

use Livewire\Component;
use Livewire\Attributes\On;

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

    public function mount(CartService $service)
    {
        $this->updateStats($service);
    }

    #[On('cart-updated')]
    public function updateStats(CartService $service)
    {
        $totals = $service->getTotals();

        // Mapping auf die Properties der Komponente
        $this->totals = [
            'subtotal' => $totals['subtotal_gross'], // Für Anzeige im Tooltip
            'tax' => $totals['tax'],
            'shipping' => $totals['shipping'],
            'total' => $totals['total'],
            'item_count' => $totals['item_count']
        ];
    }

    public function render()
    {
        return view('livewire.shop.cart-icon');
    }
}
