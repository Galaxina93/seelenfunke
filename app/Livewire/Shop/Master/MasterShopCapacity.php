<?php

namespace App\Livewire\Shop\Master;

use Livewire\Component;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class MasterShopCapacity extends Component
{
    public int $activeOrders = 0;
    public int $rawOrdersCount = 0;
    // Konfigurierbare Werte
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

    // Log-Einträge für die UI
    public array $actionLog = [];

    public function mount()
    {
        // Settings laden
        $this->dailyWorkingHours = (float) Cache::get('shop_daily_working_hours', SystemSetting::where('key', 'shop_daily_working_hours')->value('value') ?? 7);
        $this->minutesPerOrder = (float) Cache::get('shop_minutes_per_order', SystemSetting::where('key', 'shop_minutes_per_order')->value('value') ?? 10);
        $this->capacityBuffer = (int) Cache::get('shop_capacity_buffer', SystemSetting::where('key', 'shop_capacity_buffer')->value('value') ?? 12);
        
        $this->threshold1 = (int) Cache::get('shop_capacity_threshold_1', SystemSetting::where('key', 'shop_capacity_threshold_1')->value('value') ?? 60);
        $this->threshold2 = (int) Cache::get('shop_capacity_threshold_2', SystemSetting::where('key', 'shop_capacity_threshold_2')->value('value') ?? 85);
        $this->threshold3 = (int) Cache::get('shop_capacity_threshold_3', SystemSetting::where('key', 'shop_capacity_threshold_3')->value('value') ?? 90);

        $this->autoPilotEnabled = filter_var(Cache::get('shop_capacity_autopilot', SystemSetting::where('key', 'shop_capacity_autopilot')->value('value') ?? false), FILTER_VALIDATE_BOOLEAN);

        $this->calculateCapacity();
    }

    #[On('echo-private:shop,.OrderScopeUpdated')]
    #[On('echo-private:shop,.SalesDataUpdated')]
    public function recalculateOnEvent()
    {
        $this->calculateCapacity();
    }

    public function calculateCapacity()
    {
        // 1. Hole alle relevanten "offenen" Bestellungen inkl. Artikel für echtes Workload-Scoring
        $orders = OrderOrder::whereIn('status', ['pending', 'processing'])
            ->with('items.product')
            ->get();
            
        $this->rawOrdersCount = $orders->count();

        $totalMinutesRequired = 0;

        foreach ($orders as $order) {
            // Grundzeit pro Bestellung für Handling, Verpacken, Etikettieren etc.
            $totalMinutesRequired += $this->minutesPerOrder;

            foreach ($order->items as $item) {
                $laserRuntime = 0;
                if ($item->product && is_numeric($item->product->laser_runtime_minutes)) {
                    $laserRuntime = (float)$item->product->laser_runtime_minutes;
                }
                
                // Falls keine Laserzeit hinterlegt, rechne 2 Min Fallback pro Artikel
                $runtimePerItem = $laserRuntime > 0 ? $laserRuntime : 2; 
                $totalMinutesRequired += ($item->quantity * $runtimePerItem);
            }
        }

        // Wandle die gesammelte Workload in "Paket-Äquivalente" zurück, damit Slider & Settings 1:1 kompatibel bleiben
        $orderEquivalents = $totalMinutesRequired / max(1, $this->minutesPerOrder);
        $this->activeOrders = (int) ceil($orderEquivalents);

        // 2. Berechne Limit und Auslastung
        $theoreticalLimit = ($this->dailyWorkingHours * 60) / max(1, $this->minutesPerOrder);
        $this->maxCapacity = max(1, (int) round($theoreticalLimit) - $this->capacityBuffer);

        $this->percentage = round(($this->activeOrders / max(1, $this->maxCapacity)) * 100, 1);

        // 3. Bestimme das aktuelle Level (0-4)
        if ($this->percentage < $this->threshold1) {
            $this->level = 0;
        } elseif ($this->percentage < $this->threshold2) {
            $this->level = 1;
        } elseif ($this->percentage < $this->threshold3) {
            $this->level = 2;
        } elseif ($this->percentage < 100) {
            $this->level = 3;
        } else {
            $this->level = 4;
        }

        $this->generateActionLog();
    }

    public function updatedDailyWorkingHours()
    {
        if ($this->dailyWorkingHours <= 0) $this->dailyWorkingHours = 1;
        SystemSetting::updateOrCreate(['key' => 'shop_daily_working_hours'], ['value' => $this->dailyWorkingHours]);
        Cache::put('shop_daily_working_hours', $this->dailyWorkingHours);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
    }

    public function updatedMinutesPerOrder()
    {
        if ($this->minutesPerOrder <= 0) $this->minutesPerOrder = 1;
        SystemSetting::updateOrCreate(['key' => 'shop_minutes_per_order'], ['value' => $this->minutesPerOrder]);
        Cache::put('shop_minutes_per_order', $this->minutesPerOrder);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
    }

    public function updatedCapacityBuffer()
    {
        if ($this->capacityBuffer < 0) $this->capacityBuffer = 0;
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_buffer'], ['value' => $this->capacityBuffer]);
        Cache::put('shop_capacity_buffer', $this->capacityBuffer);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
    }

    public function updateThresholds($t1, $t2, $t3)
    {
        $this->threshold1 = max(1, min(100, (int)$t1));
        $this->threshold2 = max($this->threshold1 + 1, min(100, (int)$t2));
        $this->threshold3 = max($this->threshold2 + 1, min(100, (int)$t3));

        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_1'], ['value' => $this->threshold1]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_2'], ['value' => $this->threshold2]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_3'], ['value' => $this->threshold3]);

        Cache::put('shop_capacity_threshold_1', $this->threshold1);
        Cache::put('shop_capacity_threshold_2', $this->threshold2);
        Cache::put('shop_capacity_threshold_3', $this->threshold3);
        Cache::forget('global_shop_settings');
        $this->calculateCapacity();
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
    }

    public function toggleAutoPilot()
    {
        $this->autoPilotEnabled = !$this->autoPilotEnabled;
        Cache::put('shop_capacity_autopilot', $this->autoPilotEnabled);
        SystemSetting::updateOrCreate(
            ['key' => 'shop_capacity_autopilot'],
            ['value' => $this->autoPilotEnabled ? 'true' : 'false']
        );

        $this->calculateCapacity();
        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
        $state = $this->autoPilotEnabled ? 'AKTIVIERT' : 'DEAKTIVIERT';
        session()->flash('message', "Auto-Pilot ist nun $state.");
    }

    private function generateActionLog()
    {
        $this->actionLog = [];

        // Level 0
        if ($this->level === 0) {
            $this->actionLog[] = ['type' => 'success', 'msg' => 'Produktion läuft mit idealer Geschwindigkeit.'];
            $this->actionLog[] = ['type' => 'info', 'msg' => 'Standard Lieferzeit (3-5 Tage) ist aktiv. Express ist verfügbar.'];
        }

        // Level 1: Gelb
        if ($this->level >= 1) {
            $this->actionLog[] = ['type' => 'warning', 'msg' => 'Erhöhte Auslastung (Drossel 1) erreicht.'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot drosselt: Lieferprofil auf "Erhöhtes Aufkommen" (Gelb) gewechselt.'];
            }
        }

        // Level 2: Rot
        if ($this->level >= 2) {
            $this->actionLog[] = ['type' => 'danger', 'msg' => 'Strenge Auslastung (Drossel 2) erreicht.'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot drosselt: Lieferprofil "Hohe Auslastung" (Rot) gewechselt.'];
            }
        }

        // Level 3: Express Off
        if ($this->level >= 3) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Kritische Auslastung (Drossel 3) erreicht.'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot greift ein: Express-Versand im Checkout blockiert.'];
            }
        }

        // Level 4: Extreme Auslastung
        if ($this->level === 4) {
            $this->actionLog[] = ['type' => 'critical', 'msg' => 'Absolutes Tageslimit überschritten -> Extreme Auslastung!'];
            if ($this->autoPilotEnabled) {
                $this->actionLog[] = ['type' => 'system', 'msg' => 'Autopilot greift ein: Lieferprofil "Extreme Auslastung" (Schwarz) aktiv.'];
            }
        }
    }

    public function render()
    {
        return view('livewire.shop.master.master-shop-capacity');
    }
}
