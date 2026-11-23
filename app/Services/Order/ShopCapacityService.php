<?php

namespace App\Services\Order;

use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use App\Services\System\SystemAlertTicketService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ShopCapacityService
{
    public const EXPRESS_BLOCK_MIN_LEVEL = 3;

    /**
     * Verbindliche 4 Manufaktur-Lieferzeit-Stufen (100 % identisch für UI, Seeder & Autopilot).
     *
     * @var array<string, array{min_days: int, max_days: int, color: string, description: string}>
     */
    public const CANONICAL_PROFILES = [
        'Standard' => [
            'min_days' => 3,
            'max_days' => 5,
            'color' => 'green',
            'description' => 'Da jedes Seelenstück ein Unikat ist, setzt sich diese Zeit aus der individuellen Fertigung in der Manufaktur und dem anschließenden Postweg zusammen.',
        ],
        'Erhöhtes Aufkommen' => [
            'min_days' => 5,
            'max_days' => 8,
            'color' => 'yellow',
            'description' => 'Aufgrund vieler Bestellungen benötigen wir aktuell etwas länger für die sorgfältige Handfertigung deines Unikats. Danke für deine Geduld.',
        ],
        'Hohe Auslastung' => [
            'min_days' => 10,
            'max_days' => 14,
            'color' => 'red',
            'description' => 'Wir fertigen auf Hochtouren. Bitte beachte die verlängerte Bearbeitungszeit durch die aktuell sehr hohe Nachfrage.',
        ],
        'Extreme Auslastung' => [
            'min_days' => 16,
            'max_days' => 21,
            'color' => 'red',
            'description' => 'Aufgrund maximaler Manufaktur-Auslastung verlängert sich die Fertigungszeit aktuell deutlich. Dein Unikat wird mit größter Sorgfalt gefertigt.',
        ],
    ];

    public static function getCurrentLevel(): int
    {
        return (int) Cache::get('shop_capacity_level', shop_setting('shop_capacity_level', 0));
    }

    public static function isExpressAvailable(): bool
    {
        return self::getCurrentLevel() < self::EXPRESS_BLOCK_MIN_LEVEL;
    }

    public static function isAutopilotEnabled(): bool
    {
        $raw = Cache::get('shop_capacity_autopilot');
        if ($raw === null && Schema::hasTable('shop_settings')) {
            $raw = SystemSetting::where('key', 'shop_capacity_autopilot')->value('value');
        }

        return filter_var($raw ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setAutopilotEnabled(bool $enabled): void
    {
        Cache::put('shop_capacity_autopilot', $enabled);
        if (Schema::hasTable('shop_settings')) {
            SystemSetting::updateOrCreate(
                ['key' => 'shop_capacity_autopilot'],
                ['value' => $enabled ? 'true' : 'false']
            );
        }
        Cache::forget('global_shop_settings');
    }

    public static function resolveTargetProfileNameForLevel(int $level): string
    {
        return match (true) {
            $level <= 0 => 'Standard',
            $level === 1 => 'Erhöhtes Aufkommen',
            $level === 2 || $level === 3 => 'Hohe Auslastung',
            default => 'Extreme Auslastung',
        };
    }

    /**
     * Stellt sicher, dass alle 4 Standard-Lieferzeit-Profile mit Farbe und Beschreibung vorhanden sind,
     * ohne individuell angepasste Tageswerte bestehender Zeilen ungewollt zu überschreiben.
     */
    public function ensureCanonicalDeliveryProfiles(bool $removeUnknownProfiles = false): void
    {
        if (!Schema::hasTable('delivery_times')) {
            return;
        }

        foreach (self::CANONICAL_PROFILES as $name => $meta) {
            $existing = DeliveryTime::where('name', $name)->first();
            if (!$existing) {
                DeliveryTime::create([
                    'name' => $name,
                    'min_days' => $meta['min_days'],
                    'max_days' => $meta['max_days'],
                    'color' => $meta['color'],
                    'description' => $meta['description'],
                    'is_active' => false,
                ]);
                continue;
            }

            $updates = [];
            if (empty($existing->color)) {
                $updates['color'] = $meta['color'];
            }
            if (empty($existing->description)) {
                $updates['description'] = $meta['description'];
            }
            if ($updates !== []) {
                $existing->update($updates);
            }
        }

        if ($removeUnknownProfiles) {
            DeliveryTime::whereNotIn('name', array_keys(self::CANONICAL_PROFILES))->delete();
        }

        $activeRows = DeliveryTime::where('is_active', true)->orderBy('id')->get();
        if ($activeRows->isEmpty()) {
            $std = DeliveryTime::where('name', 'Standard')->first() ?? DeliveryTime::first();
            $std?->update(['is_active' => true]);
        } elseif ($activeRows->count() > 1) {
            $keepId = $activeRows->first()->id;
            DeliveryTime::where('id', '!=', $keepId)->update(['is_active' => false]);
        }
    }

    /**
     * Berechnet die Manufaktur-Auslastung nach exakt einer einheitlichen Formel für Dashboard & Engine.
     *
     * @return array{
     *   daily_working_hours: float,
     *   minutes_per_order: float,
     *   capacity_buffer: int,
     *   threshold_1: int,
     *   threshold_2: int,
     *   threshold_3: int,
     *   raw_orders_count: int,
     *   active_orders: int,
     *   max_capacity: int,
     *   percentage: float,
     *   capped_percentage: int,
     *   level: int,
     *   autopilot_enabled: bool,
     *   express_available: bool,
     *   target_profile_name: string
     * }
     */
    public function evaluateCapacitySnapshot(bool $preferCacheSettings = false): array
    {
        $readSetting = static function (string $key, mixed $default) use ($preferCacheSettings): mixed {
            if ($preferCacheSettings && Cache::has($key)) {
                return Cache::get($key);
            }
            if (Schema::hasTable('shop_settings')) {
                $dbVal = SystemSetting::where('key', $key)->value('value');
                if ($dbVal !== null && $dbVal !== '') {
                    return $dbVal;
                }
            }
            return Cache::get($key, $default);
        };

        $dailyHours = (float) $readSetting('shop_daily_working_hours', 7.0);
        if ($dailyHours <= 0) {
            $dailyHours = 7.0;
        }

        $minutesPerOrder = (float) $readSetting('shop_minutes_per_order', 10.0);
        if ($minutesPerOrder <= 0) {
            $minutesPerOrder = 10.0;
        }

        $rawBuffer = $readSetting('shop_capacity_buffer', null);
        $buffer = ($rawBuffer !== null && $rawBuffer !== '') ? max(0, (int) $rawBuffer) : 12;

        $t1 = max(1, min(97, (int) ($readSetting('shop_capacity_threshold_1', 60) ?: 60)));
        $t2 = max($t1 + 1, min(98, (int) ($readSetting('shop_capacity_threshold_2', 85) ?: 85)));
        $t3 = max($t2 + 1, min(99, (int) ($readSetting('shop_capacity_threshold_3', 90) ?: 90)));

        $theoreticalLimit = ($dailyHours * 60.0) / max(1.0, $minutesPerOrder);
        $maxCapacity = max(1, (int) round($theoreticalLimit) - $buffer);

        $rawOrdersCount = 0;
        $totalMinutesRequired = 0.0;

        if (Schema::hasTable('order_orders')) {
            $orders = OrderOrder::whereIn('status', ['pending', 'processing'])
                ->with('items.product')
                ->get();

            $rawOrdersCount = $orders->count();
            foreach ($orders as $order) {
                $totalMinutesRequired += $minutesPerOrder;
                foreach ($order->items as $item) {
                    $laserRuntime = ($item->product && is_numeric($item->product->laser_runtime_minutes))
                        ? (float) $item->product->laser_runtime_minutes
                        : 0.0;
                    $runtimePerItem = $laserRuntime > 0 ? $laserRuntime : 2.0;
                    $totalMinutesRequired += ((int) $item->quantity) * $runtimePerItem;
                }
            }
        }

        $activeOrders = (int) ceil($totalMinutesRequired / max(1.0, $minutesPerOrder));
        $percentage = round(($activeOrders / max(1, $maxCapacity)) * 100.0, 1);
        $cappedPercentage = min(100, (int) round($percentage));

        if ($percentage < $t1) {
            $level = 0;
        } elseif ($percentage < $t2) {
            $level = 1;
        } elseif ($percentage < $t3) {
            $level = 2;
        } elseif ($percentage < 100.0) {
            $level = 3;
        } else {
            $level = 4;
        }

        $autoPilot = self::isAutopilotEnabled();

        return [
            'daily_working_hours' => $dailyHours,
            'minutes_per_order' => $minutesPerOrder,
            'capacity_buffer' => $buffer,
            'threshold_1' => $t1,
            'threshold_2' => $t2,
            'threshold_3' => $t3,
            'raw_orders_count' => $rawOrdersCount,
            'active_orders' => $activeOrders,
            'max_capacity' => $maxCapacity,
            'percentage' => $percentage,
            'capped_percentage' => $cappedPercentage,
            'level' => $level,
            'autopilot_enabled' => $autoPilot,
            'express_available' => $level < self::EXPRESS_BLOCK_MIN_LEVEL,
            'target_profile_name' => self::resolveTargetProfileNameForLevel($level),
        ];
    }

    /**
     * Berechnet Kapazitäts-Level, persistiert es in Cache & DB und schaltet bei aktivem Autopilot
     * sofort das passende Standard-Lieferzeit-Profil im Shop scharf.
     *
     * @return array<string, mixed>
     */
    public function syncAndApplyAutopilot(): array
    {
        $snapshot = $this->evaluateCapacitySnapshot(false);
        $level = $snapshot['level'];
        $cappedPercentage = $snapshot['capped_percentage'];
        $activeOrders = $snapshot['active_orders'];
        $maxCapacity = $snapshot['max_capacity'];
        $autoPilot = $snapshot['autopilot_enabled'];
        $targetDeliveryName = $snapshot['target_profile_name'];

        Cache::put('shop_capacity_level', $level);
        Cache::put('shop_capacity_percentage', $snapshot['percentage']);

        if (Schema::hasTable('shop_settings')) {
            SystemSetting::updateOrCreate(
                ['key' => 'shop_capacity_level'],
                ['value' => (string) $level]
            );
        }
        Cache::forget('global_shop_settings');

        $deliverySetting = Schema::hasTable('delivery_settings') ? DeliverySetting::first() : null;
        $isVacationOrSick = (Schema::hasTable('shop_settings') && filter_var(SystemSetting::where('key', 'is_vacation_mode')->value('value') ?? false, FILTER_VALIDATE_BOOLEAN))
            || (bool) ($deliverySetting?->is_vacation_mode ?? false)
            || (bool) ($deliverySetting?->is_sick_mode ?? false);

        $mutation = 'autopilot_off';
        if ($isVacationOrSick) {
            $mutation = 'skipped_vacation_or_sick';
        } elseif ($autoPilot && Schema::hasTable('delivery_times')) {
            $this->ensureCanonicalDeliveryProfiles(false);
            $activeDelivery = DeliveryTime::where('is_active', true)->first();

            if (!$activeDelivery || $activeDelivery->name !== $targetDeliveryName || DeliveryTime::where('is_active', true)->count() > 1) {
                DeliveryTime::query()->update(['is_active' => false]);
                DeliveryTime::where('name', $targetDeliveryName)->update(['is_active' => true]);
                $mutation = 'switched';
            } else {
                $mutation = 'already_optimal';
            }
        }

        if ($level >= 4) {
            SystemAlertTicketService::upsertSystemTicket(
                alertKey: 'shop_capacity:lockdown_level_4',
                title: 'Werkstatt-Kapazität am Limit (100 % Auslastung / Stufe 4)',
                markdownBody: "Die `ShopCapacityEngine` meldet aktuell **{$cappedPercentage} % Manufaktur-Auslastung** ({$activeOrders} Auftrags-Äquivalente bei Tageslimit {$maxCapacity}). Express-Optionen sind gedrosselt"
                    . ($autoPilot ? ' und die Lieferzeit wurde automatisch auf **Extreme Auslastung (16 bis 21 Werktage)** angepasst.' : ' (Lieferzeit-Autopilot ist aktuell deaktiviert).'),
                departmentName: 'Bestellungen',
                agentName: 'Bestelli',
                ticketType: 'capacity_alert',
                priority: 'high',
                assignToAntigravity: false
            );
        } else {
            SystemAlertTicketService::autoResolveByAlertPrefix(
                'shop_capacity:lockdown_level_4',
                "Werkstatt-Auslastung wieder unter 100 % gesunken (aktuell {$cappedPercentage} %, Stufe {$level}).",
                'Bestelli'
            );
        }

        return array_merge($snapshot, [
            'mutation' => $mutation,
            'is_vacation_or_sick' => $isVacationOrSick,
        ]);
    }
}
