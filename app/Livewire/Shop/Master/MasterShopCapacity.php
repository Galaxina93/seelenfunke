<?php

namespace App\Livewire\Shop\Master;

use App\Models\System\SystemSetting;
use App\Services\Order\ShopCapacityService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class MasterShopCapacity extends Component
{
    public int $activeOrders = 0;
    public int $rawOrdersCount = 0;

    public float $dailyWorkingHours = 7;
    public float $minutesPerOrder = 10;
    public int $capacityBuffer = 12;
    public int $threshold1 = 60;
    public int $threshold2 = 85;
    public int $threshold3 = 90;

    public int $maxCapacity = 0;
    public float $percentage = 0.0;
    public int $level = 0;
    public bool $autoPilotEnabled = false;

    public array $actionLog = [];

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->dailyWorkingHours = (float) Cache::get('shop_daily_working_hours', SystemSetting::where('key', 'shop_daily_working_hours')->value('value') ?? 7);
        $this->minutesPerOrder = (float) Cache::get('shop_minutes_per_order', SystemSetting::where('key', 'shop_minutes_per_order')->value('value') ?? 10);
        $rawBuf = Cache::get('shop_capacity_buffer', SystemSetting::where('key', 'shop_capacity_buffer')->value('value'));
        $this->capacityBuffer = ($rawBuf !== null && $rawBuf !== '') ? max(0, (int) $rawBuf) : 12;

        $this->threshold1 = (int) Cache::get('shop_capacity_threshold_1', SystemSetting::where('key', 'shop_capacity_threshold_1')->value('value') ?? 60);
        $this->threshold2 = (int) Cache::get('shop_capacity_threshold_2', SystemSetting::where('key', 'shop_capacity_threshold_2')->value('value') ?? 85);
        $this->threshold3 = (int) Cache::get('shop_capacity_threshold_3', SystemSetting::where('key', 'shop_capacity_threshold_3')->value('value') ?? 90);

        $this->autoPilotEnabled = ShopCapacityService::isAutopilotEnabled();

        $this->calculateCapacity();
    }

    #[On('echo-private:shop,.OrderScopeUpdated')]
    #[On('echo-private:shop,.SalesDataUpdated')]
    public function recalculateOnEvent(): void
    {
        app(ShopCapacityService::class)->syncAndApplyAutopilot();
        $this->autoPilotEnabled = ShopCapacityService::isAutopilotEnabled();
        $this->calculateCapacity();
    }

    public function calculateCapacity(): void
    {
        $service = app(ShopCapacityService::class);
        $snap = $service->evaluateCapacitySnapshot(false);

        $this->rawOrdersCount = $snap['raw_orders_count'];
        $this->activeOrders = $snap['active_orders'];
        $this->maxCapacity = $snap['max_capacity'];
        $this->percentage = $snap['percentage'];
        $this->level = $snap['level'];

        Cache::put('shop_capacity_percentage', $this->percentage);
        Cache::put('shop_capacity_level', $this->level);

        $this->generateActionLog();
    }

    public function updatedDailyWorkingHours(): void
    {
        if ($this->dailyWorkingHours <= 0) {
            $this->dailyWorkingHours = 1;
        } elseif ($this->dailyWorkingHours > 24) {
            $this->dailyWorkingHours = 24;
        }
        SystemSetting::updateOrCreate(['key' => 'shop_daily_working_hours'], ['value' => $this->dailyWorkingHours]);
        Cache::put('shop_daily_working_hours', $this->dailyWorkingHours);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        Artisan::call('shop:capacity-engine');
    }

    public function updatedMinutesPerOrder(): void
    {
        if ($this->minutesPerOrder <= 0) {
            $this->minutesPerOrder = 1;
        } elseif ($this->minutesPerOrder > 1440) {
            $this->minutesPerOrder = 1440;
        }
        SystemSetting::updateOrCreate(['key' => 'shop_minutes_per_order'], ['value' => $this->minutesPerOrder]);
        Cache::put('shop_minutes_per_order', $this->minutesPerOrder);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        Artisan::call('shop:capacity-engine');
    }

    public function updatedCapacityBuffer(): void
    {
        if ($this->capacityBuffer < 0) {
            $this->capacityBuffer = 0;
        } elseif ($this->capacityBuffer > 1000) {
            $this->capacityBuffer = 1000;
        }
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_buffer'], ['value' => $this->capacityBuffer]);
        Cache::put('shop_capacity_buffer', $this->capacityBuffer);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        Artisan::call('shop:capacity-engine');
    }

    public function updateThresholds($t1, $t2, $t3): void
    {
        $this->threshold1 = max(1, min(97, (int) $t1));
        $this->threshold2 = max($this->threshold1 + 1, min(98, (int) $t2));
        $this->threshold3 = max($this->threshold2 + 1, min(99, (int) $t3));

        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_1'], ['value' => $this->threshold1]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_2'], ['value' => $this->threshold2]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_3'], ['value' => $this->threshold3]);

        Cache::put('shop_capacity_threshold_1', $this->threshold1);
        Cache::put('shop_capacity_threshold_2', $this->threshold2);
        Cache::put('shop_capacity_threshold_3', $this->threshold3);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        Artisan::call('shop:capacity-engine');
    }

    public function toggleAutoPilot(): void
    {
        $this->autoPilotEnabled = !$this->autoPilotEnabled;
        ShopCapacityService::setAutopilotEnabled($this->autoPilotEnabled);

        $this->calculateCapacity();
        Artisan::call('shop:capacity-engine');
        $state = $this->autoPilotEnabled ? 'AKTIVIERT' : 'DEAKTIVIERT';
        session()->flash('message', "Auto-Pilot ist nun {$state}.");
    }

    private function generateActionLog(): void
    {
        $this->actionLog = [];

        if ($this->level === 0) {
            $this->actionLog[] = ['type' => 'success', 'msg' => 'Produktion läuft mit idealer Geschwindigkeit.'];
            $this->actionLog[] = ['type' => 'info', 'msg' => 'Standard Lieferzeit (3-5 Werktage) ist aktiv. Express ist verfügbar.'];
        }

        if ($this->level >= 1) {
            $this->actionLog[] = ['type' => 'warning', 'msg' => 'Erhöhte Auslastung (Drossel 1) erreicht.'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot drosselt: Lieferprofil auf "Erhöhtes Aufkommen" (5-8 Werktage) gewechselt.'];
            }
        }

        if ($this->level >= 2) {
            $this->actionLog[] = ['type' => 'danger', 'msg' => 'Hohe Auslastung (Drossel 2) erreicht.'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot drosselt: Lieferprofil "Hohe Auslastung" (10-14 Werktage) gewechselt.'];
            }
        }

        if ($this->level >= 3) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Kritische Auslastung (Drossel 3) erreicht.'];
            $this->actionLog[] = ['type' => 'system', 'msg' => 'Express-Fertigung im Konfigurator, Warenkorb und Checkout gesperrt.'];
        }

        if ($this->level === 4) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Absolutes Tageslimit überschritten: Extreme Auslastung!'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot greift ein: Lieferprofil "Extreme Auslastung" (16-21 Werktage) aktiv.'];
            }
        }
    }

    public function render()
    {
        return view('livewire.shop.master.master-shop-capacity');
    }
}
