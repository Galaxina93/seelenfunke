<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Order\Order;
use App\Models\System\SystemSetting;
use App\Models\Delivery\DeliveryTime;

class ShopCapacityEngine extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:capacity-engine';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Berechnet die aktuelle Shop-Auslastung und passt ggf. Liefergeschwindigkeiten autonom an.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starte Shop Capacity Engine...");

        $dailyHours = (float) SystemSetting::where('key', 'shop_daily_working_hours')->value('value') ?: 7.0;
        $minutesPerOrder = (int) SystemSetting::where('key', 'shop_minutes_per_order')->value('value') ?: 10;
        $buffer = (int) SystemSetting::where('key', 'shop_capacity_buffer')->value('value') ?: 12;

        $t1 = (int) SystemSetting::where('key', 'shop_capacity_threshold_1')->value('value') ?: 60;
        $t2 = (int) SystemSetting::where('key', 'shop_capacity_threshold_2')->value('value') ?: 85;
        $t3 = (int) SystemSetting::where('key', 'shop_capacity_threshold_3')->value('value') ?: 90;

        if ($minutesPerOrder <= 0) $minutesPerOrder = 10;

        // 2. Errechne 100% Limit
        $maxCapacity = max(1, (int)floor((($dailyHours * 60) / $minutesPerOrder) - $buffer));

        // 3. Hole aktuelle Last (Pending + Processing)
        $orders = \App\Models\Order\OrderOrder::whereIn('status', ['pending', 'processing'])
            ->with('items.product')
            ->get();
            
        $totalMinutesRequired = 0;
        foreach ($orders as $order) {
            $totalMinutesRequired += $minutesPerOrder;
            foreach ($order->items as $item) {
                $laserRuntime = 0;
                if ($item->product && is_numeric($item->product->laser_runtime_minutes)) {
                    $laserRuntime = (float)$item->product->laser_runtime_minutes;
                }
                $runtimePerItem = $laserRuntime > 0 ? $laserRuntime : 2;
                $totalMinutesRequired += ($item->quantity * $runtimePerItem);
            }
        }
        $orderEquivalents = $totalMinutesRequired / max(1, $minutesPerOrder);
        $activeOrders = (int) ceil($orderEquivalents);

        // 4. Kalkuliere Level
        $percentage = min(100, (int)round(($activeOrders / max(1, $maxCapacity)) * 100));
        
        $level = 0; // Normal (0 - t1)
        if ($percentage >= $t1 && $percentage < $t2) {
            $level = 1; // Erhöhtes Aufkommen
        } elseif ($percentage >= $t2 && $percentage < $t3) {
            $level = 2; // Hohe Auslastung (Rot)
        } elseif ($percentage >= $t3 && $percentage < 100) {
            $level = 3; // Hohe Auslastung + Express Off
        } elseif ($percentage >= 100) {
            $level = 4; // Lockdown
        }

        $this->info("Current Load: {$activeOrders} orders (Limit: {$maxCapacity})");
        $this->info("Percentage: {$percentage}% => Level {$level}");

        // Speichere das Level für den globalen Zugriff cache/DB:
        Cache::put('shop_capacity_level', $level);
        SystemSetting::updateOrCreate(
            ['key' => 'shop_capacity_level'],
            ['value' => $level]
        );

        // 5. Autopilot Eingriffe
        $autoPilot = filter_var(Cache::get('shop_capacity_autopilot', SystemSetting::where('key', 'shop_capacity_autopilot')->value('value') ?? false), FILTER_VALIDATE_BOOLEAN);

        if ($autoPilot) {
            $this->info("Autopilot is ON. Adjusting delivery times...");
            
            // Definiere die 4 Profile
            $profiles = [
                'Standard' => ['min_days' => 5, 'max_days' => 7],
                'Erhöhtes Aufkommen' => ['min_days' => 7, 'max_days' => 10],
                'Hohe Auslastung' => ['min_days' => 10, 'max_days' => 14],
                'Extreme Auslastung' => ['min_days' => 16, 'max_days' => 21],
            ];

            // Stelle sicher, dass diese Profile existieren
            foreach ($profiles as $name => $days) {
                DeliveryTime::firstOrCreate(
                    ['name' => $name],
                    [
                        'min_days' => $days['min_days'],
                        'max_days' => $days['max_days'],
                        'is_active' => false
                    ]
                );
            }

            $targetDeliveryName = 'Standard';
            if ($level === 1) {
                $targetDeliveryName = 'Erhöhtes Aufkommen';
            } elseif ($level === 2 || $level === 3) {
                $targetDeliveryName = 'Hohe Auslastung';
            } elseif ($level >= 4) {
                $targetDeliveryName = 'Extreme Auslastung';
            }

            // Aktive Lieferzeit updaten (falls abweichend)
            $activeDelivery = DeliveryTime::where('is_active', true)->first();
            
            if (!$activeDelivery || $activeDelivery->name !== $targetDeliveryName) {
                DeliveryTime::query()->update(['is_active' => false]);
                DeliveryTime::where('name', $targetDeliveryName)->update(['is_active' => true]);
                $this->info("Switched active delivery time to: {$targetDeliveryName}");
            } else {
                $this->info("Delivery time already optimal ({$targetDeliveryName}).");
            }
        } else {
            $this->warn("Autopilot is OFF. No data mutated.");
        }

        $this->info("Shop Capacity Engine run completed.");
    }
}
