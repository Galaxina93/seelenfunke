<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingHerzensprojekte;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingHerzensprojekteSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_customers_cannot_mount_herzensprojekte_component(): void
    {
        // 1. Guest
        Livewire::test(MarketingHerzensprojekte::class)
            ->assertStatus(403);

        // 2. Customer
        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(MarketingHerzensprojekte::class)
            ->assertStatus(403);
    }

    public function test_admin_can_save_settings_and_persists_to_system_setting_with_cache_cleared(): void
    {
        $admin = Admin::factory()->create();

        Cache::put('global_shop_settings', ['herzensprojekt_cents_per_order' => 150]);

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingHerzensprojekte::class)
            ->set('centsPerOrder', '350')
            ->call('saveSettings')
            ->assertDispatched('notify');

        $this->assertEquals('350', SystemSetting::where('key', 'herzensprojekt_cents_per_order')->value('value'));
        $this->assertEquals(350, shop_setting('herzensprojekt_cents_per_order'));
    }

    public function test_invalid_deposit_amount_is_rejected(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingHerzensprojekte::class)
            ->set('depositAmountEuro', '-25')
            ->call('saveDeposit')
            ->assertHasErrors(['depositAmountEuro']);
    }
}
