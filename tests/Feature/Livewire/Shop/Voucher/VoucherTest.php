<?php

namespace Tests\Feature\Livewire\Shop\Voucher;

use App\Livewire\Shop\Voucher\Voucher;
use App\Models\Voucher as VoucherModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_component_in_auto_mode_by_default()
    {
        Livewire::test(Voucher::class)
            ->assertSet('voucherSectionMode', 'auto')
            ->assertSet('isCreatingManual', false)
            ->assertStatus(200);
    }

    #[Test]
    public function it_can_toggle_section_mode()
    {
        Livewire::test(Voucher::class)
            ->call('toggleVoucherSectionMode')
            ->assertSet('voucherSectionMode', 'manual')
            ->call('toggleVoucherSectionMode')
            ->assertSet('voucherSectionMode', 'auto');
    }

    #[Test]
    public function it_can_toggle_voucher_status()
    {
        $voucher = VoucherModel::create([
            'code' => 'TEST10',
            'title' => 'Test',
            'type' => 'fixed',
            'value' => 1000,
            'is_active' => true,
            'mode' => 'manual',
        ]);

        Livewire::test(Voucher::class)
            ->call('toggleVoucherStatus', $voucher->id);

        $this->assertFalse((bool) $voucher->fresh()->is_active);

        Livewire::test(Voucher::class)
            ->call('toggleVoucherStatus', $voucher->id);

        $this->assertTrue((bool) $voucher->fresh()->is_active);
    }

    #[Test]
    public function it_can_open_create_manual_coupon_form()
    {
        Livewire::test(Voucher::class)
            ->call('createManualCoupon')
            ->assertSet('isCreatingManual', true)
            ->assertSet('isEditingManual', false)
            // manual_code should be automatically seeded with a random string
            ->assertNotSet('manual_code', '');
    }

    #[Test]
    public function it_validates_manual_coupon_creation()
    {
        Livewire::test(Voucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', '') // Invalid code
            ->set('manual_value', 'abc') // Invalid value
            ->call('saveManualCoupon')
            ->assertHasErrors(['manual_code' => 'required', 'manual_value' => 'numeric']);
    }

    #[Test]
    public function it_creates_a_fixed_discount_manual_coupon_and_converts_to_cents()
    {
        Livewire::test(Voucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', 'FIXED10')
            ->set('manual_type', 'fixed')
            ->set('manual_value', 10.50) // 10.50 Euros
            ->set('manual_min_order_value', 50.00) // 50 Euros Minimum
            ->set('manual_usage_limit', 100)
            ->call('saveManualCoupon')
            ->assertSet('isCreatingManual', false);

        // Fixed type should multiply by 100 for cents storage in database
        $this->assertDatabaseHas('voucher', [
            'code' => 'FIXED10',
            'type' => 'fixed',
            'value' => 1050, 
            'min_order_value' => 5000,
            'usage_limit' => 100,
            'mode' => 'manual'
        ]);
    }

    #[Test]
    public function it_creates_a_percent_discount_manual_coupon_without_cent_conversion()
    {
        Livewire::test(Voucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', 'PERCENT15')
            ->set('manual_type', 'percent')
            ->set('manual_value', 15) // 15%
            ->set('manual_min_order_value', null)
            ->call('saveManualCoupon');

        // Percent type should NOT multiply by 100
        $this->assertDatabaseHas('voucher', [
            'code' => 'PERCENT15',
            'type' => 'percent',
            'value' => 15,
            'min_order_value' => null,
            'mode' => 'manual'
        ]);
    }

    #[Test]
    public function it_can_edit_a_manual_coupon_and_converts_from_cents()
    {
        $voucher = VoucherModel::create([
            'code' => 'TOEDIT5',
            'title' => 'Test Edit',
            'type' => 'fixed',
            'value' => 500, // 5 Euros
            'min_order_value' => 2000, // 20 Euros Minimum
            'is_active' => true,
            'mode' => 'manual',
            'valid_from' => now(),
        ]);

        Livewire::test(Voucher::class)
            ->call('editManualCoupon', $voucher->id)
            ->assertSet('isEditingManual', true)
            ->assertSet('manual_code', 'TOEDIT5')
            ->assertSet('manual_type', 'fixed')
            ->assertSet('manual_value', 5.0) // Must reverse cent conversion
            ->assertSet('manual_min_order_value', 20.0) // Must reverse cent conversion
            ->set('manual_value', 10.0) // Change value to 10 Euros
            ->call('saveManualCoupon');

        $this->assertDatabaseHas('voucher', [
            'id' => $voucher->id,
            'value' => 1000 // Saved back as cents successfully
        ]);
    }

    #[Test]
    public function it_can_delete_a_manual_coupon()
    {
        $voucher = VoucherModel::create([
            'code' => 'TODELETE',
            'title' => 'Test Delete',
            'type' => 'fixed',
            'value' => 100,
            'is_active' => true,
            'mode' => 'manual',
        ]);

        Livewire::test(Voucher::class)
            ->call('deleteManualCoupon', $voucher->id);

        $this->assertDatabaseMissing('voucher', [
            'id' => $voucher->id
        ]);
    }

    #[Test]
    public function it_generates_monthly_auto_vouchers_via_seeder()
    {
        // Execute the seeder
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'MonthlyVoucherSeeder']);

        $year = date('Y');

        // Verify that 12 auto vouchers were created
        $this->assertDatabaseCount('voucher', 12);

        // Verify a specific one (e.g. Christmas)
        $this->assertDatabaseHas('voucher', [
            'code' => "XMAS-$year",
            'mode' => 'auto',
            'type' => 'percent',
            'value' => 5, // 5% Discount
            'usage_limit' => 20, // Limited to 20 usages
            'min_order_value' => 2000, // 20.00 EUR
            'is_active' => true,
        ]);

        // Verify another specific one (e.g. Start)
        $this->assertDatabaseHas('voucher', [
            'code' => "START-$year",
            'mode' => 'auto',
            'type' => 'percent',
            'value' => 5,
            'usage_limit' => 20,
        ]);
    }
}
