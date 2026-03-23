<?php

namespace App\Livewire\Shop\Cart;

use App\Services\CartService;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Models\Global\GlobalLog;

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
        try {
            DB::transaction(function () use ($itemId) {
                $item = \App\Models\Cart\CartItem::find($itemId);
                if ($item) {
                    $this->cartService->updateQuantity($itemId, $item->quantity + 1);
                    $this->dispatch('cart-updated'); // Aktualisiert das Icon im Header
                }
            });
        } catch (\Exception $e) {
            $this->logCartError('increment', $e, ['item_id' => $itemId]);
            session()->flash('error', 'Menge konnte nicht aktualisiert werden.');
        }
    }

    public function decrement($itemId)
    {
        try {
            DB::transaction(function () use ($itemId) {
                $item = \App\Models\Cart\CartItem::find($itemId);
                if ($item) {
                    $this->cartService->updateQuantity($itemId, $item->quantity - 1);
                    $this->dispatch('cart-updated');
                }
            });
        } catch (\Exception $e) {
            $this->logCartError('decrement', $e, ['item_id' => $itemId]);
            session()->flash('error', 'Menge konnte nicht aktualisiert werden.');
        }
    }

    public function remove($itemId)
    {
        try {
            DB::transaction(function () use ($itemId) {
                $this->cartService->removeItem($itemId);
                $this->dispatch('cart-updated');
            });
        } catch (\Exception $e) {
            $this->logCartError('remove', $e, ['item_id' => $itemId]);
            session()->flash('error', 'Artikel konnte nicht entfernt werden.');
        }
    }

    // Listener für "In den Warenkorb" Buttons von anderen Komponenten (z.B. Konfigurator)
    #[On('add-to-cart')]
    public function addToCartHandler($productId, $qty = 1, $config = null)
    {
        try {
            DB::transaction(function () use ($productId, $qty, $config) {
                $product = \App\Models\Product\Product::find($productId);
                if($product) {
                    $this->cartService->addItem($product, $qty, $config);

                    // Events feuern
                    $this->dispatch('cart-updated');
                    session()->flash('success', 'Produkt hinzugefügt!');
                }
            });
        } catch (\Exception $e) {
            $this->logCartError('add_to_cart', $e, ['product_id' => $productId, 'qty' => $qty]);
            session()->flash('error', 'Produkt konnte nicht in den Warenkorb gelegt werden.');
        }
    }

    public function applyCoupon()
    {
        $this->validate(['couponCodeInput' => 'required|string']);

        try {
            DB::transaction(function () {
                $result = $this->cartService->applyCoupon($this->couponCodeInput);

                if ($result['success']) {
                    $this->couponCodeInput = ''; // Input leeren
                    $this->dispatch('cart-updated'); // UI neu laden
                    session()->flash('success', $result['message']);
                } else {
                    $this->addError('couponCodeInput', $result['message']);
                }
            });
        } catch (\Exception $e) {
            $this->logCartError('apply_coupon', $e, ['coupon' => $this->couponCodeInput]);
            session()->flash('error', 'Fehler beim Anwenden des Gutscheins.');
        }
    }

    public function removeCoupon()
    {
        try {
            DB::transaction(function () {
                $this->cartService->removeCoupon();
                $this->dispatch('cart-updated');
                session()->flash('success', 'Gutschein entfernt.');
            });
        } catch (\Exception $e) {
            $this->logCartError('remove_coupon', $e, []);
            session()->flash('error', 'Fehler beim Entfernen des Gutscheins.');
        }
    }

    private function logCartError($action, \Exception $e, $payload = [])
    {
        GlobalLog::create([
            'type' => 'error',
            'agent_id' => null,
            'action_id' => 'cart_manipulation',
            'message' => "Fehler bei Cart Aktion ($action): " . $e->getMessage(),
            'details' => json_encode([
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'payload' => $payload,
                'trace' => $e->getTraceAsString()
            ])
        ]);
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
        ])->layout('components.layouts.frontend_layout');
    }
}
