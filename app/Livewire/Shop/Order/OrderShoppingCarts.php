<?php

namespace App\Livewire\Shop\Order;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Computed;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Mail\AbandonedCartReminder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\System\SystemSetting;
use App\Services\CartService;

#[Layout('components.layouts.backend_layout')]
class OrderShoppingCarts extends Component
{
    use WithPagination;

    public $search = '';
    public $detailCartId = null;
    public $quantityUpdates = [];
    public $editingItemId = null;
    
    public $cartYellowLimit = 3;
    public $cartRedLimit = 24;
    public $cartRequireConsent = true;

    public function mount()
    {
        $this->cartYellowLimit = (int) shop_setting('cart_abandoned_yellow_hours', 3);
        $this->cartRedLimit = (int) shop_setting('cart_abandoned_red_hours', 24);
        $this->cartRequireConsent = (bool) shop_setting('cart_abandoned_require_consent', true);
    }

    public function saveSettings()
    {
        $this->validate([
            'cartYellowLimit' => 'required|numeric|min:1',
            'cartRedLimit' => 'required|numeric|min:1|gt:cartYellowLimit',
            'cartRequireConsent' => 'boolean',
        ]);

        SystemSetting::updateOrCreate(
            ['key' => 'cart_abandoned_yellow_hours'],
            ['value' => $this->cartYellowLimit]
        );
        
        SystemSetting::updateOrCreate(
            ['key' => 'cart_abandoned_red_hours'],
            ['value' => $this->cartRedLimit]
        );

        SystemSetting::updateOrCreate(
            ['key' => 'cart_abandoned_require_consent'],
            ['value' => $this->cartRequireConsent ? '1' : '0']
        );

        Cache::forget('global_shop_settings');
        session()->flash('success', 'Warenkorb-Ampeln und Rechtssicherheits-Einstellungen erfolgreich aktualisiert.');
    }

    // Reset pagination when search changes
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function viewDetails($id)
    {
        $this->detailCartId = $id;
        $this->quantityUpdates = []; // Reset potential quantity changes
        
        $cart = Cart::with('items')->find($id);
        if ($cart) {
            foreach ($cart->items as $item) {
                $this->quantityUpdates[$item->id] = $item->quantity;
            }
        }
    }

    public function closeDetails()
    {
        $this->detailCartId = null;
        $this->quantityUpdates = [];
    }

    #[On('cart-updated')]
    public function refreshDetails()
    {
        if ($this->detailCartId) {
            $this->viewDetails($this->detailCartId);
        }
    }

    public function edit($itemId)
    {
        $this->editingItemId = $itemId;
    }

    #[On('close-modal')]
    public function closeModal()
    {
        $this->editingItemId = null;
    }

    public function removeItem($itemId, CartService $cartService)
    {
        $item = CartItem::find($itemId);
        if ($item) {
            $cart = Cart::find($item->cart_id);
            $cartService->removeItem($itemId);
            
            if ($cart && $cart->items()->count() === 0) {
                $cart->delete();
                $this->closeDetails();
                session()->flash('success', 'Letztes Item gelöscht. Warenkorb wurde entfernt.');
                return;
            }
            
            $this->viewDetails($cart->id);
            session()->flash('success', 'Artikel aus dem Warenkorb entfernt.');
        }
    }

    public function increment($itemId, CartService $cartService)
    {
        $item = CartItem::find($itemId);
        if ($item) {
            $cartService->updateQuantity($itemId, $item->quantity + 1);
            $this->viewDetails($item->cart_id);
        }
    }

    public function decrement($itemId, CartService $cartService)
    {
        $item = CartItem::find($itemId);
        if ($item && $item->quantity > 1) {
            $cartService->updateQuantity($itemId, $item->quantity - 1);
            $this->viewDetails($item->cart_id);
        }
    }

    // Erhält die manuelle Eingabe aus Input Feldern
    public function updateQuantity($itemId, CartService $cartService)
    {
        $item = CartItem::find($itemId);
        $newQuantity = $this->quantityUpdates[$itemId] ?? null;

        if ($item && $newQuantity !== null && $newQuantity > 0) {
            $cartService->updateQuantity($itemId, $newQuantity);
            $this->viewDetails($item->cart_id);
            session()->flash('success', 'Menge erfolgreich aktualisiert.');
        }
    }

    public function sendReminderEmail($cartId)
    {
        $cart = Cart::with(['customer.profile', 'items.product'])->find($cartId);

        if (!$cart || !$cart->customer || !$cart->customer->email) {
            session()->flash('error', 'Dieser Warenkorb hat keinen hinterlegten Kunden mit E-Mail-Adresse.');
            return;
        }

        // Rechtlicher Schutz: Wurde widersprochen? (§ 7 Abs. 3 UWG / Art. 21 DSGVO)
        if ($cart->customer->profile?->cart_reminder_unsubscribed_at !== null) {
            session()->flash('error', 'Rechtlicher Schutz nach § 7 UWG: Dieser Kunde hat dem Erhalt von Warenkorb-Erinnerungen widersprochen (Opt-Out am ' . $cart->customer->profile->cart_reminder_unsubscribed_at->format('d.m.Y H:i') . ' Uhr). Ein erneuter Versand ist untersagt.');
            return;
        }

        try {
            Mail::to($cart->customer->email)->send(new AbandonedCartReminder($cart));
            $cart->update(['reminder_email_sent_at' => now()]);
            session()->flash('success', 'Erinnerungsmail wurde erfolgreich an ' . $cart->customer->email . ' gesendet.');
            unset($this->carts); // Clear Computed property cache so UI updates instantly
        } catch (\Exception $e) {
            Log::error('Failed to send abandoned cart reminder: ' . $e->getMessage());
            session()->flash('error', 'Fehler beim Senden der Mail: ' . $e->getMessage());
        }
    }
    
    public function deleteCart($cartId)
    {
        $cart = Cart::find($cartId);
        if ($cart) {
            $cart->delete(); // Cascades on DB level or model events should handle items
            if ($this->detailCartId === $cartId) {
                $this->closeDetails();
            }
            session()->flash('success', 'Warenkorb restlos gelöscht.');
        }
    }

    #[Computed]
    public function carts()
    {
        return Cart::with(['customer.profile', 'items.product'])
            ->when($this->search, function ($query) {
                $query->where('session_id', 'like', '%' . $this->search . '%')
                    ->orWhereHas('customer', function ($q) {
                        $q->where('first_name', 'like', '%' . $this->search . '%')
                          ->orWhere('last_name', 'like', '%' . $this->search . '%')
                          ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
    }
    
    public function getDetailCartProperty()
    {
        if (!$this->detailCartId) return null;
        return Cart::with(['customer.profile', 'items.product.tierPrices'])->find($this->detailCartId);
    }

    #[Computed]
    public function detailTotals()
    {
        if (!$this->detailCartId) return null;
        $cart = Cart::with(['items.product.tierPrices'])->find($this->detailCartId);
        if (!$cart) return null;
        
        $countryCode = $cart->customer?->profile?->country ?? 'DE';
        return app(CartService::class)->calculateTotals($cart, $countryCode);
    }

    public function render()
    {
        return view('livewire.shop.order.order-shopping-carts', [
            'detailCart' => $this->detailCart
        ]);
    }
}
