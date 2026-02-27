<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerFunkiItem;
use App\Models\Customer\CustomerGamification;
use App\Models\Funki\FunkiItem;
use App\Services\Gamification\FunkiShopService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

#[Layout('components.layouts.customer_layout')]
class FunkiShopComponent extends Component
{
    use WithPagination;

    // Such- & Filterfunktionen für den Shop
    public $search = '';
    public $filterType = 'all';
    public $filterRarity = 'all';

    // Guthaben (wird für die Buttons im View benötigt)
    public $balance = 0;

    // Pagination zurücksetzen, wenn gesucht/gefiltert wird
    public function updatingSearch() { $this->resetPage(); }
    public function updatingFilterType() { $this->resetPage(); }
    public function updatingFilterRarity() { $this->resetPage(); }

    public function mount()
    {
        $user = Auth::guard('customer')->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        // Wenn der Kunde noch nicht am Gamification-Programm teilnimmt, leiten wir ihn zur Zentrale
        if (!$profile || !$profile->is_active) {
            return redirect()->route('customer.dashboard');
        }

        $this->balance = $profile->funken_balance;
    }

    public function buyWithFunken($itemId, FunkiShopService $shopService)
    {
        $user = Auth::guard('customer')->user();
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->buyWithFunken($user, $item);

        if ($result['success']) {
            $this->dispatch('notify', ['type' => 'success', 'message' => $result['message']]);

            // Kontostand aktualisieren & Header mitteilen
            $profile = CustomerGamification::where('customer_id', $user->id)->first();
            $this->balance = $profile->funken_balance;
            $this->dispatch('sparks-awarded');
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
        }
    }

    public function buyWithMoney($itemId, FunkiShopService $shopService)
    {
        $user = Auth::guard('customer')->user();
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->createStripeCheckout($user, $item);

        if ($result['success']) {
            return redirect($result['url']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
        }
    }

    public function toggleEquip($itemId, FunkiShopService $shopService)
    {
        $user = Auth::guard('customer')->user();
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->toggleEquipItem($user, $item);

        if ($result['success']) {
            $this->dispatch('notify', ['type' => 'success', 'message' => $result['message']]);
            // Ein Event auslösen, falls andere Komponenten (z.B. 3D Model) sich updaten müssen
            $this->dispatch('cosmetics-updated');
        }
    }

    public function render()
    {
        $user = Auth::guard('customer')->user();

        // 1. Items abfragen
        $query = FunkiItem::where('is_active', true);

        if ($this->search !== '') {
            $query->where('name', 'like', '%' . $this->search . '%');
        }
        if ($this->filterType !== 'all') {
            $query->where('type', $this->filterType);
        }
        if ($this->filterRarity !== 'all') {
            $query->where('rarity', $this->filterRarity);
        }

        // Sortierung: Seltenheit und dann Preis
        $items = $query->orderByRaw("FIELD(rarity, 'legendary', 'epic', 'rare', 'common')")
            ->orderBy('price_funken')
            ->paginate(12);

        // 2. Benutzerdaten laden (welche Items hat er, was ist ausgerüstet?)
        $ownedItemIds = CustomerFunkiItem::where('customer_id', $user->id)->pluck('funki_item_id')->toArray();
        $profile = CustomerGamification::where('customer_id', $user->id)->first();

        return view('livewire.customer.funki-shop-component', [
            'items' => $items,
            'ownedItemIds' => $ownedItemIds,
            'activeBg' => $profile->active_background_id ?? null,
            'activeFrame' => $profile->active_frame_id ?? null,
            'activeSkin' => $profile->active_skin_id ?? null,
        ])->layout('components.layouts.customer_layout');
    }
}
