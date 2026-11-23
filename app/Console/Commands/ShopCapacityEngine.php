<?php

namespace App\Console\Commands;

use App\Services\Order\ShopCapacityService;
use Illuminate\Console\Command;

class ShopCapacityEngine extends Command
{
    protected $signature = 'shop:capacity-engine';

    protected $description = 'Berechnet die aktuelle Shop-Auslastung und passt ggf. Liefergeschwindigkeiten autonom an.';

    public function handle(ShopCapacityService $capacityService): int
    {
        $this->info('Starte Shop Capacity Engine...');

        $result = $capacityService->syncAndApplyAutopilot();

        $this->info("Current Load: {$result['active_orders']} orders (Limit: {$result['max_capacity']})");
        $this->info("Percentage: {$result['capped_percentage']}% => Level {$result['level']}");

        if (!empty($result['is_vacation_or_sick'])) {
            $this->warn('Vacation mode is ON. Skipping delivery time mutations.');
        } elseif (!empty($result['autopilot_enabled'])) {
            $this->info('Autopilot is ON. Adjusting delivery times...');
            if (($result['mutation'] ?? '') === 'switched') {
                $this->info("Switched active delivery time to: {$result['target_profile_name']}");
            } else {
                $this->info("Delivery time already optimal ({$result['target_profile_name']}).");
            }
        } else {
            $this->warn('Autopilot is OFF. No data mutated.');
        }

        $this->info('Shop Capacity Engine run completed.');

        return self::SUCCESS;
    }
}
