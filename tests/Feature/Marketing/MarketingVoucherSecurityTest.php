<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Marketing\MarketingVoucher;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingVoucher as VoucherModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingVoucherSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_and_customers_cannot_mount_marketing_voucher_component(): void
    {
        Livewire::test(MarketingVoucher::class)
            ->assertStatus(403);

        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(MarketingVoucher::class)
            ->assertStatus(403);
    }

    public function test_percent_coupon_cannot_exceed_100_percent(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingVoucher::class)
            ->set('manual_code', 'TESTPERCENT150')
            ->set('manual_type', 'percent')
            ->set('manual_value', 150)
            ->call('saveManualCoupon')
            ->assertHasErrors(['manual_value']);

        $this->assertDatabaseMissing('marketing_vouchers', [
            'code' => 'TESTPERCENT150',
        ]);
    }

    public function test_negative_min_order_value_and_usage_limit_are_rejected(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MarketingVoucher::class)
            ->set('manual_code', 'TESTINVALID')
            ->set('manual_type', 'fixed')
            ->set('manual_value', 10)
            ->set('manual_min_order_value', -5)
            ->set('manual_usage_limit', -1)
            ->call('saveManualCoupon')
            ->assertHasErrors(['manual_min_order_value', 'manual_usage_limit']);
    }

    public function test_admin_can_create_valid_manual_fixed_and_percent_coupons(): void
    {
        $admin = Admin::factory()->create();

        // 1. Fixed coupon
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingVoucher::class)
            ->set('manual_code', 'RABATT10')
            ->set('manual_type', 'fixed')
            ->set('manual_value', 10)
            ->set('manual_min_order_value', 30)
            ->set('manual_usage_limit', 50)
            ->call('saveManualCoupon')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('marketing_vouchers', [
            'code' => 'RABATT10',
            'type' => 'fixed',
            'value' => 1000, // 10 Euro in Cent
            'min_order_value' => 3000,
            'usage_limit' => 50,
        ]);

        // 2. Percent coupon (max 100)
        Livewire::actingAs($admin, 'admin')
            ->test(MarketingVoucher::class)
            ->set('manual_code', 'SOMMER20')
            ->set('manual_type', 'percent')
            ->set('manual_value', 20)
            ->call('saveManualCoupon')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('marketing_vouchers', [
            'code' => 'SOMMER20',
            'type' => 'percent',
            'value' => 20,
        ]);
    }
}
