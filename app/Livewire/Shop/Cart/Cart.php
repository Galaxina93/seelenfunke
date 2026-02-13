<?php

namespace App\Livewire\Shop\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;

class Cart extends Component
{
    // Wir injizieren den Service
    protected CartService $cartService;

    // State für das Bearbeiten (Inline in der Kachel)
    public $editingItemId = null;

    // NEU: Input für Gutschein
    public $couponCodeInput = '';

    public function boot(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    // --- Actions ---

    public function edit($itemId)
    {
        // Wenn bereits offen, dann schließen, sonst öffnen (Toggle-Logik)
        if ($this->editingItemId === $itemId) {
            $this->closeModal();
        } else {
            $this->editingItemId = $itemId;
        }
    }

    #[On('close-modal')] // Listener für Configurator
    public function closeModal()
    {
        $this->editingItemId = null;
    }

    public function increment($itemId)
    {
        // Hole aktuelle Quantity
        $item = \App\Models\Cart\CartItem::find($itemId);
        if ($item) {
            $this->cartService->updateQuantity($itemId, $item->quantity + 1);
            $this->dispatch('cart-updated'); // Aktualisiert das Icon im Header
        }
    }

    public function decrement($itemId)
    {
        $item = \App\Models\Cart\CartItem::find($itemId);
        if ($item) {
            $this->cartService->updateQuantity($itemId, $item->quantity - 1);
            $this->dispatch('cart-updated');
        }
    }

    public function remove($itemId)
    {
        $this->cartService->removeItem($itemId);
        $this->dispatch('cart-updated');
    }

    // Listener für "In den Warenkorb" Buttons von anderen Komponenten (z.B. Konfigurator)
    #[On('add-to-cart')]
    public function addToCartHandler($productId, $qty = 1, $config = null)
    {
        $product = \App\Models\Product\Product::find($productId);
        if($product) {
            $this->cartService->addItem($product, $qty, $config);

            // Events feuern
            $this->dispatch('cart-updated');
            session()->flash('success', 'Produkt hinzugefügt!');
        }
    }

    public function applyCoupon()
    {
        $this->validate(['couponCodeInput' => 'required|string']);

        $result = $this->cartService->applyCoupon($this->couponCodeInput);

        if ($result['success']) {
            $this->couponCodeInput = ''; // Input leeren
            $this->dispatch('cart-updated'); // UI neu laden
            session()->flash('success', $result['message']);
        } else {
            $this->addError('couponCodeInput', $result['message']);
        }
    }

    public function removeCoupon()
    {
        $this->cartService->removeCoupon();
        $this->dispatch('cart-updated');
        session()->flash('success', 'Gutschein entfernt.');
    }

    public function render()
    {
        $cart = $this->cartService->getCart();
        $totals = $this->cartService->getTotals();

        // KORREKTUR: Wir laden nur die Relation 'product'.
        // 'media_gallery' ist ein Attribut (array cast) im Product-Model, keine Relation.
        $items = $cart
            ? $cart->items()->with('product')->get()
            : collect();

        return view('livewire.shop.cart.cart', [
            'cart' => $cart,
            'items' => $items,
            'totals' => $totals
        ]);
    }
}
