<?php

namespace App\Livewire\Customer;

use App\Models\Customer\CustomerFunkiItem;
use App\Models\Funki\FunkiItem;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Services\Gamification\FunkiShopService;
use App\Services\Gamification\GamificationService;
use App\Services\Gamification\GameConfig;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Carbon\Carbon;

class FunkiShopComponent extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = 'all';
    public $filterRarity = 'all';

    public $hasOptedIn = false;
    public $currentRankName = 'Funken-Novize';
    public $level;
    public $balance;
    public $upgradeCost;
    public $isMaxLevel;
    public $canUpgrade;

    public $modelPath;
    public $imagePath;
    public $titlesData;
    public $isSeelengott;
    public $progressPercentage;

    public $searchOrder = '';
    public $selectedOrderId = null;
    public $previewItemId = null;

    public object $user;

    // NEU: Energie und Gutschein Eigenschaften
    public int $energyBalance = 5;
    public int $maxEnergy = 5;
    public int $nextVoucherLevel = 5; // Startwert
    public array $voucherMilestones = [5, 10, 15, 20, 30, 40, 50, 60, 80];

    public function updatingSearchOrder()
    {
        $this->resetPage();
    }

    public function mount(GamificationService $gameService)
    {
        $this->user = Auth::guard('customer')->user();
        if (!$this->user) {
            redirect()->route('login');
            return;
        }

        $profile = $gameService->getProfile($this->user);
        $this->hasOptedIn = $profile->is_active;

        if ($this->hasOptedIn) {
            $this->checkEnergyRefill($profile);
            $this->loadGamificationData($gameService, $profile);
        }
    }

    // NEU: Methode zum Prüfen und Auffüllen der Energie (z.B. täglich)
    private function checkEnergyRefill($profile)
    {
        $now = Carbon::now();
        $lastRefill = $profile->last_energy_refill_at ? Carbon::parse($profile->last_energy_refill_at) : null;

        // Wenn noch nie aufgefüllt wurde oder der letzte Refill vor Mitternacht war
        if (!$lastRefill || !$lastRefill->isSameDay($now)) {
            $profile->energy_balance = $this->maxEnergy;
            $profile->last_energy_refill_at = $now;
            $profile->save();
        }
    }

    // NEU: Methode zum Verbrauchen von Energie vor einem Spiel
    public function consumeEnergy()
    {
        $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $this->user->id)->first();

        if ($profile && $profile->energy_balance > 0) {
            $profile->energy_balance -= 1;
            $profile->save();
            $this->energyBalance = $profile->energy_balance;
            return true;
        }

        $this->dispatch('notify', ['type' => 'error', 'message' => 'Nicht genug Seelen-Energie!']);
        return false;
    }

    // NEU: Methode zum Hinzufügen von erspielten Funken
    public function rewardGameSparks($amount)
    {
        // Sicherheits-Check: Maximal 50 Funken pro Spielrunde erlauben, um Missbrauch zu verhindern
        $safeAmount = min(intval($amount), 50);

        if ($safeAmount > 0) {
            $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $this->user->id)->first();
            if($profile) {
                $profile->funken_balance += $safeAmount;
                $profile->funken_total_earned += $safeAmount;
                $profile->save();

                $this->balance = $profile->funken_balance;
                $this->canUpgrade = !$this->isMaxLevel && ($this->balance >= $this->upgradeCost);

                if ($this->isMaxLevel) {
                    $this->progressPercentage = 100;
                } else {
                    $this->progressPercentage = min(100, round(($this->balance / $this->upgradeCost) * 100));
                }
            }
        }
    }

    public function optIn(GamificationService $gameService)
    {
        $profile = $gameService->getProfile($this->user);
        $profile->is_active = true;

        if ($profile->funken_balance === 0 && $profile->level === 1) {
            $profile->funken_balance = 15;
            $profile->energy_balance = 5;
            $profile->last_energy_refill_at = Carbon::now();
        }
        $profile->save();
        $this->hasOptedIn = true;

        $this->loadGamificationData($gameService, $profile);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Willkommen in der Manufaktur der Magie!']);
    }

    public function loadGamificationData(GamificationService $gameService, $profile = null)
    {
        if (!$profile) {
            $profile = $gameService->getProfile($this->user);
        }

        $this->level = $profile->level ?? 1;
        $this->balance = $profile->funken_balance;
        $this->energyBalance = $profile->energy_balance ?? 5; // Energie laden

        $this->isMaxLevel = $this->level >= GameConfig::MAX_LEVEL;
        $this->upgradeCost = $gameService->getUpgradeCost($this->level);
        $this->canUpgrade = !$this->isMaxLevel && ($this->balance >= $this->upgradeCost);

        if ($this->isMaxLevel) {
            $this->progressPercentage = 100;
        } else {
            $this->progressPercentage = min(100, round(($this->balance / $this->upgradeCost) * 100));
        }

        $milestones = GameConfig::getAppearanceMilestones();
        $currentModelName = 'funki_lvl_1_rags';
        foreach ($milestones as $milestoneLevel => $modelName) {
            if ($this->level >= $milestoneLevel) {
                $currentModelName = $modelName;
            } else {
                break;
            }
        }

        $this->modelPath = asset('storage/funki/models/' . $currentModelName . '.glb');
        $this->imagePath = asset('storage/funki/models/images/' . $currentModelName . '.png');
        $this->currentRankName = $this->getRankName($this->level);

        $evaluation = $gameService->evaluateTitles($profile);
        $this->titlesData = $evaluation['titles'];
        $this->isSeelengott = $evaluation['is_seelengott'];

        // Berechne nächstes Gutschein-Level
        $this->calculateNextVoucherLevel();
    }

    private function calculateNextVoucherLevel()
    {
        foreach($this->voucherMilestones as $milestone) {
            if ($this->level < $milestone) {
                $this->nextVoucherLevel = $milestone;
                return;
            }
        }
        $this->nextVoucherLevel = end($this->voucherMilestones); // Maximales Level erreicht
    }

    private function getRankName(int $level): string
    {
        if ($level >= 10) return 'Seelengott';
        if ($level >= 8) return 'Meister';
        if ($level >= 6) return 'Kunsthandwerker';
        if ($level >= 4) return 'Lehrling';
        if ($level >= 2) return 'Helfer';
        return 'Funken-Novize';
    }

    public function upgrade(GamificationService $gameService)
    {
        $result = $gameService->upgradeLevel($this->user);
        if ($result['success']) {
            $this->loadGamificationData($gameService);
            $this->dispatch('funki-level-up', ['level' => $result['new_level'], 'reward' => $result['reward'], 'newModelPath' => $this->modelPath, 'newImagePath' => $this->imagePath]);
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Level Up!']);

            // Gutschein Check
            if (in_array($result['new_level'], $this->voucherMilestones)) {
                // HIER LOGIK EINFÜGEN UM GUTSCHEIN ZU GENERIEREN
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Du hast einen neuen Rabatt-Gutschein freigeschaltet!']);
            }
        }
    }

    public function buyWithFunken($itemId, FunkiShopService $shopService, GamificationService $gameService)
    {
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->buyWithFunken($this->user, $item);

        if ($result['success']) {
            $this->dispatch('notify', ['type' => 'success', 'message' => $result['message']]);
            $this->loadGamificationData($gameService);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
        }
    }

    public function buyWithMoney($itemId, FunkiShopService $shopService)
    {
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->createStripeCheckout($this->user, $item);

        if ($result['success']) {
            return redirect($result['url']);
        } else {
            $this->dispatch('notify', ['type' => 'error', 'message' => $result['message']]);
        }
    }

    public function toggleEquip($itemId, FunkiShopService $shopService, GamificationService $gameService)
    {
        $item = FunkiItem::findOrFail($itemId);
        $result = $shopService->toggleEquipItem($this->user, $item);

        if ($result['success']) {
            $this->dispatch('notify', ['type' => 'success', 'message' => $result['message']]);
            $this->dispatch('cosmetics-updated');
        }
    }

    public function showOrder($id)
    {
        $this->selectedOrderId = $id;
        $this->previewItemId = null;
    }

    public function resetOrderView()
    {
        $this->selectedOrderId = null;
        $this->previewItemId = null;
    }

    public function openPreview($itemId)
    {
        if ($this->previewItemId == $itemId) {
            $this->previewItemId = null;
        } else {
            $this->previewItemId = $itemId;
        }
    }

    #[Computed]
    public function previewItem()
    {
        if (!$this->previewItemId) return null;
        return OrderItem::with('product')->find($this->previewItemId);
    }

    public function render()
    {
        $selectedOrder = null;
        if ($this->selectedOrderId) {
            $selectedOrder = Order::with(['items.product', 'invoices'])->where('customer_id', $this->user->id)->find($this->selectedOrderId);
            if (!$selectedOrder) {
                $this->selectedOrderId = null;
            }
        }

        $ordersQuery = Order::where('customer_id', $this->user->id);
        if ($this->searchOrder) {
            $ordersQuery->where('order_number', 'like', '%' . $this->searchOrder . '%');
        }
        $orders = $ordersQuery->latest()->paginate(10);

        // Profil Fortschritt Checker - "Profil Informationen" zusammengefasst
        $profileSteps = [];
        $p = $this->user->profile;

        $needsProfileInfo = empty($this->user->first_name) || empty($this->user->last_name) ||
            empty($p->street) || empty($p->city) || empty($p->house_number) || empty($p->postal);

        if ($needsProfileInfo) {
            $profileSteps[] = ['label' => 'Profil Informationen', 'action' => "\$dispatch('open-profile-modal', {tab: 'profile'})"];
        }

        if (!$this->hasOptedIn) {
            return view('livewire.customer.funki-shop-component', [
                'items' => collect([]),
                'orders' => $orders,
                'selectedOrder' => $selectedOrder,
                'ownedItemIds' => [],
                'profileSteps' => $profileSteps,
            ]);
        }

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

        $items = $query->orderByRaw("FIELD(rarity, 'legendary', 'epic', 'rare', 'common')")->orderBy('price_funken')->get();
        $ownedItemIds = CustomerFunkiItem::where('customer_id', $this->user->id)->pluck('funki_item_id')->toArray();
        $profile = \App\Models\Customer\CustomerGamification::where('customer_id', $this->user->id)->first();

        return view('livewire.customer.funki-shop-component', [
            'items' => $items,
            'ownedItemIds' => $ownedItemIds,
            'activeBg' => $profile->active_background_id ?? null,
            'activeFrame' => $profile->active_frame_id ?? null,
            'activeSkin' => $profile->active_skin_id ?? null,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'profileSteps' => $profileSteps,
        ]);
    }
}
