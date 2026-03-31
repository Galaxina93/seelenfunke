<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use App\Models\Delivery\DeliveryTime;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ShopCapacityEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Grundkonfiguration für Capacity Limits
        SystemSetting::create(['key' => 'shop_daily_working_hours', 'value' => 7]);
        SystemSetting::create(['key' => 'shop_minutes_per_order', 'value' => 10]);
        SystemSetting::create(['key' => 'shop_capacity_buffer', 'value' => 12]);
        SystemSetting::create(['key' => 'shop_capacity_threshold_1', 'value' => 60]);
        SystemSetting::create(['key' => 'shop_capacity_threshold_2', 'value' => 85]);
        SystemSetting::create(['key' => 'shop_capacity_threshold_3', 'value' => 90]);

        // Liefereinstellungen
        DeliveryTime::create(['name' => 'Standard', 'is_active' => true, 'min_days' => 3, 'max_days' => 5]);
        DeliveryTime::create(['name' => 'Erhöhtes Aufkommen', 'is_active' => false, 'min_days' => 5, 'max_days' => 8]);
        DeliveryTime::create(['name' => 'Hohe Auslastung', 'is_active' => false, 'min_days' => 10, 'max_days' => 14]);
        
        // Limits: (7 * 60) / 10 - 12 = 420 / 10 - 12 = 42 - 12 = 30 Orders = 100% capacity
    }
    
    private function seedOrders($count, $status)
    {
        for($i = 0; $i < $count; $i++) {
            OrderOrder::create([
                'order_number' => 'ORD-' . uniqid() . '-' . $i,
                'status' => $status,
                'email' => 'test@example.com',
                'payment_status' => 'paid',
                'billing_address' => ['first_name' => 'Test', 'last_name' => 'User'],
                'subtotal_price' => 1000,
                'tax_amount' => 190,
                'total_price' => 1190,
                'shipping_price' => 0
            ]);
        }
    }

    #[Test]
    public function it_calculates_level_0_when_there_are_no_orders()
    {
        Cache::put('shop_capacity_autopilot', false);
        
        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Percentage: 0% => Level 0')
             ->assertSuccessful();

        $this->assertEquals(0, Cache::get('shop_capacity_level'));
        $this->assertEquals(0, (int)SystemSetting::where('key', 'shop_capacity_level')->value('value'));
    }

    #[Test]
    public function it_calculates_level_1_for_t1_to_t2()
    {
        // 30 max capacity -> 60% = 18 orders
        $this->seedOrders(18, 'pending');

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Level 1')
             ->assertSuccessful();

        $this->assertEquals(1, Cache::get('shop_capacity_level'));
    }

    #[Test]
    public function it_calculates_level_2_for_t2_to_t3()
    {
        // 30 max capacity -> 85% = 26 orders
        $this->seedOrders(26, 'pending');

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Level 2')
             ->assertSuccessful();

        $this->assertEquals(2, Cache::get('shop_capacity_level'));
    }

    #[Test]
    public function it_calculates_level_3_for_t3_to_100()
    {
        // 30 max capacity -> 90% = 27 orders
        $this->seedOrders(27, 'pending');

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Level 3')
             ->assertSuccessful();

        $this->assertEquals(3, Cache::get('shop_capacity_level'));
    }

    #[Test]
    public function it_calculates_level_4_lockdown_for_100_percent_or_more_load()
    {
        // 30 max capacity -> 100% = 30 orders
        $this->seedOrders(31, 'processing');

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Level 4')
             ->assertSuccessful();

        $this->assertEquals(4, Cache::get('shop_capacity_level'));
    }

    #[Test]
    public function it_does_not_change_delivery_times_if_autopilot_is_off()
    {
        $this->seedOrders(30, 'pending'); // Level 4

        Cache::put('shop_capacity_autopilot', false);

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Autopilot is OFF. No data mutated.')
             ->assertSuccessful();
             
        $activeDelivery = DeliveryTime::where('is_active', true)->first();
        $this->assertEquals('Standard', $activeDelivery->name);
    }

    #[Test]
    public function it_changes_delivery_times_based_on_capacity_if_autopilot_is_on()
    {
        $this->seedOrders(26, 'pending'); // Level 2 (Rot / Hohe Auslastung)

        Cache::put('shop_capacity_autopilot', true);

        $this->artisan('shop:capacity-engine')
             ->expectsOutputToContain('Autopilot is ON')
             ->expectsOutputToContain('Switched active delivery time to: Hohe Auslastung')
             ->assertSuccessful();
             
        $activeDelivery = DeliveryTime::where('is_active', true)->first();
        $this->assertEquals('Hohe Auslastung', $activeDelivery->name);
    }
}
