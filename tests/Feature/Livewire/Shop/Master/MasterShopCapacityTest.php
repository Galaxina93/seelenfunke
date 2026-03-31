<?php

namespace Tests\Feature\Livewire\Shop\Master;

use App\Livewire\Shop\Master\MasterShopCapacity;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MasterShopCapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        SystemSetting::truncate();

        SystemSetting::updateOrCreate(['key' => 'shop_daily_working_hours'], ['value' => 7]);
        SystemSetting::updateOrCreate(['key' => 'shop_minutes_per_order'], ['value' => 10]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_buffer'], ['value' => 12]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_1'], ['value' => 60]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_2'], ['value' => 85]);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_threshold_3'], ['value' => 90]);
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
    public function it_loads_initial_configuration_from_settings()
    {
        Livewire::test(MasterShopCapacity::class)
            ->assertSet('dailyWorkingHours', 7.0)
            ->assertSet('minutesPerOrder', 10.0)
            ->assertSet('capacityBuffer', 12)
            ->assertSet('maxCapacity', 30); // 7h = 420m / 10 = 42 - 12 = 30
    }

    #[Test]
    public function it_calculates_capacity_correctly_on_mount_and_sets_level()
    {
        $this->seedOrders(20, 'processing'); // 20 / 30 = 66.6% -> Level 1

        $component = Livewire::test(MasterShopCapacity::class);
        
        $component->assertSet('activeOrders', 20)
            ->assertSet('percentage', 66.7)
            ->assertSet('level', 1);
    }

    #[Test]
    public function it_calculates_level_4_lockdown()
    {
        $this->seedOrders(30, 'pending'); // 30 / 30 = 100% -> Level 4

        $component = Livewire::test(MasterShopCapacity::class)
            ->assertSet('level', 4);

        // Action log message for level 4 should contain "Absolutes Tageslimit überschritten"
        $logs = $component->get('actionLog');
        $this->assertNotEmpty($logs);
        
        $foundLockdown = false;
        foreach ($logs as $log) {
            if (str_contains($log['msg'], 'Absolutes Tageslimit')) {
                $foundLockdown = true;
                break;
            }
        }
        $this->assertTrue($foundLockdown);
    }

    #[Test]
    public function it_toggles_autopilot_and_caches_value()
    {
        $this->assertFalse(Cache::get('shop_capacity_autopilot', false));

        Livewire::test(MasterShopCapacity::class)
            ->call('toggleAutoPilot')
            ->assertSet('autoPilotEnabled', true);

        $this->assertTrue(Cache::get('shop_capacity_autopilot'));
    }

    #[Test]
    public function it_updates_settings_when_properties_change()
    {
        Livewire::test(MasterShopCapacity::class)
            ->set('dailyWorkingHours', 8)
            ->set('minutesPerOrder', 15)
            ->set('capacityBuffer', 5);

        // Limits: 8h = 480m / 15 = 32 - 5 = 27
        $this->assertEquals(8, SystemSetting::where('key', 'shop_daily_working_hours')->value('value'));
        $this->assertEquals(15, SystemSetting::where('key', 'shop_minutes_per_order')->value('value'));
        $this->assertEquals(5, SystemSetting::where('key', 'shop_capacity_buffer')->value('value'));
    }

    #[Test]
    public function it_updates_thresholds_via_method_correctly()
    {
        Livewire::test(MasterShopCapacity::class)
            ->call('updateThresholds', 55, 75, 95)
            ->assertSet('threshold1', 55)
            ->assertSet('threshold2', 75)
            ->assertSet('threshold3', 95);

        $this->assertEquals(55, SystemSetting::where('key', 'shop_capacity_threshold_1')->value('value'));
        $this->assertEquals(75, SystemSetting::where('key', 'shop_capacity_threshold_2')->value('value'));
        $this->assertEquals(95, SystemSetting::where('key', 'shop_capacity_threshold_3')->value('value'));
    }
}
