<?php

namespace Tests\Feature\Livewire\Shop\Marketing;

use App\Livewire\Shop\Marketing\MarketingVoucher;
use App\Models\Marketing\MarketingVoucher as VoucherModel;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarketingVoucherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_the_component_in_auto_mode_by_default()
    {
        Livewire::test(MarketingVoucher::class)
            ->assertSet('voucherSectionMode', 'auto')
            ->assertSet('isCreatingManual', false)
            ->assertStatus(200);
    }

    #[Test]
    public function it_can_toggle_section_mode()
    {
        Livewire::test(MarketingVoucher::class)
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

        Livewire::test(MarketingVoucher::class)
            ->call('toggleVoucherStatus', $voucher->id);

        $this->assertFalse((bool) $voucher->fresh()->is_active);

        Livewire::test(MarketingVoucher::class)
            ->call('toggleVoucherStatus', $voucher->id);

        $this->assertTrue((bool) $voucher->fresh()->is_active);
    }

    #[Test]
    public function it_can_open_create_manual_coupon_form()
    {
        Livewire::test(MarketingVoucher::class)
            ->call('createManualCoupon')
            ->assertSet('isCreatingManual', true)
            ->assertSet('isEditingManual', false)
            // manual_code should be automatically seeded with a random string
            ->assertNotSet('manual_code', '');
    }

    #[Test]
    public function it_validates_manual_coupon_creation()
    {
        Livewire::test(MarketingVoucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', '') // Invalid code
            ->set('manual_value', 'abc') // Invalid value
            ->call('saveManualCoupon')
            ->assertHasErrors(['manual_code' => 'required', 'manual_value' => 'numeric']);
    }

    #[Test]
    public function it_creates_a_fixed_discount_manual_coupon_and_converts_to_cents()
    {
        Livewire::test(MarketingVoucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', 'FIXED10')
            ->set('manual_type', 'fixed')
            ->set('manual_value', 10.50) // 10.50 Euros
            ->set('manual_min_order_value', 50.00) // 50 Euros Minimum
            ->set('manual_usage_limit', 100)
            ->call('saveManualCoupon')
            ->assertSet('isCreatingManual', false);

        // Fixed type should multiply by 100 for cents storage in database
        $this->assertDatabaseHas('marketing_vouchers', [
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
        Livewire::test(MarketingVoucher::class)
            ->call('createManualCoupon')
            ->set('manual_code', 'PERCENT15')
            ->set('manual_type', 'percent')
            ->set('manual_value', 15) // 15%
            ->set('manual_min_order_value', null)
            ->call('saveManualCoupon');

        // Percent type should NOT multiply by 100
        $this->assertDatabaseHas('marketing_vouchers', [
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

        Livewire::test(MarketingVoucher::class)
            ->call('editManualCoupon', $voucher->id)
            ->assertSet('isEditingManual', true)
            ->assertSet('manual_code', 'TOEDIT5')
            ->assertSet('manual_type', 'fixed')
            ->assertSet('manual_value', 5.0) // Must reverse cent conversion
            ->assertSet('manual_min_order_value', 20.0) // Must reverse cent conversion
            ->set('manual_value', 10.0) // Change value to 10 Euros
            ->call('saveManualCoupon');

        $this->assertDatabaseHas('marketing_vouchers', [
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

        Livewire::test(MarketingVoucher::class)
            ->call('deleteManualCoupon', $voucher->id);

        $this->assertDatabaseMissing('marketing_vouchers', [
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
        $this->assertDatabaseCount('marketing_vouchers', 12);

        // Verify a specific one (e.g. Christmas)
        $this->assertDatabaseHas('marketing_vouchers', [
            'code' => "XMAS-$year",
            'mode' => 'auto',
            'type' => 'percent',
            'value' => 5, // 5% Discount
            'usage_limit' => 20, // Limited to 20 usages
            'min_order_value' => 2000, // 20.00 EUR
            'is_active' => true,
        ]);

        // Verify another specific one (e.g. Start)
        $this->assertDatabaseHas('marketing_vouchers', [
            'code' => "START-$year",
            'mode' => 'auto',
            'type' => 'percent',
            'value' => 5,
            'usage_limit' => 20,
        ]);
    }

    #[Test]
    public function it_can_filter_and_sort_sold_gift_vouchers_with_realtime_stats()
    {
        // 1. Create a dummy order and gift vouchers
        $order1 = OrderOrder::create([
            'order_number' => 'ORD-100',
            'email' => 'buyer1@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_price' => 5000,
            'subtotal_price' => 5000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'billing_address' => [
                'first_name' => 'Alice',
                'last_name' => 'Buyer',
            ]
        ]);

        $order2 = OrderOrder::create([
            'order_number' => 'ORD-200',
            'email' => 'buyer2@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_price' => 10000,
            'subtotal_price' => 10000,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'billing_address' => [
                'first_name' => 'Bob',
                'last_name' => 'Customer',
            ]
        ]);

        $gv1 = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-1111-2222',
            'initial_value' => 5000, // 50.00 EUR
            'current_balance' => 5000, // Full
            'recipient_name' => 'Recipient One',
            'recipient_email' => 'recipient1@example.com',
            'personal_message' => 'Liebe Grüße von Alice!',
            'delivery_method' => 'email',
            'is_active' => true,
            'valid_until' => now()->addYears(3),
        ]);
        $gv1->created_at = now()->subDays(5);
        $gv1->save();

        $gv2 = \App\Models\Marketing\MarketingGiftVoucher::create([
            'code' => 'SEELENFUNKE-3333-4444',
            'initial_value' => 10000, // 100.00 EUR
            'current_balance' => 3000, // Partial (used 70 EUR)
            'recipient_name' => 'Recipient Two',
            'recipient_email' => 'recipient2@example.com',
            'personal_message' => 'Für dich!',
            'delivery_method' => 'post',
            'is_active' => true,
            'valid_until' => now()->subDays(1), // Expired
        ]);
        $gv2->created_at = now()->subDays(2);
        $gv2->save();

        // Manually link the first voucher to order1's item structure for relationship searches
        $product = Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Gutschein',
            'slug' => 'gutschein',
            'price' => 5000,
            'status' => 'active',
            'type' => 'digital',
        ]);
        $item1 = $order1->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
        ]);
        $gv1->update(['order_item_id' => $item1->id]);

        $item2 = $order2->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 10000,
            'total_price' => 10000,
        ]);
        $gv2->update(['order_item_id' => $item2->id]);

        // 2. Test Livewire Search & Filtering
        Livewire::test(MarketingVoucher::class)
            ->set('voucherSectionMode', 'gift')
            // Assert all default values
            ->assertSet('searchCode', '')
            ->assertSet('filterDelivery', 'all')
            ->assertSet('filterBalance', 'all')
            ->assertSet('filterStatus', 'all')
            
            // Check dynamic stats with all vouchers
            ->assertViewHas('giftVoucherStats', function ($stats) {
                return $stats['count'] === 2 
                    && (float)$stats['sum_initial'] === 150.0
                    && (float)$stats['sum_current'] === 80.0
                    && (float)$stats['sum_used'] === 70.0;
            })

            // Search by Recipient Name
            ->set('searchCode', 'Recipient One')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-1111-2222';
            })
            ->assertViewHas('giftVoucherStats', function ($stats) {
                return $stats['count'] === 1 
                    && (float)$stats['sum_initial'] === 50.0
                    && (float)$stats['sum_current'] === 50.0;
            })

            // Search by Buyer Name
            ->set('searchCode', 'Alice')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-1111-2222';
            })

            // Search by Personal Message
            ->set('searchCode', 'Grüße')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-1111-2222';
            })

            // Reset search and test Delivery Filter
            ->set('searchCode', '')
            ->set('filterDelivery', 'post')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test Balance Filter: full
            ->set('filterDelivery', 'all')
            ->set('filterBalance', 'full')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-1111-2222';
            })

            // Test Balance Filter: partial
            ->set('filterBalance', 'partial')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test Status Filter: expired
            ->set('filterBalance', 'all')
            ->set('filterStatus', 'expired')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test Initial Value Range
            ->set('filterStatus', 'all')
            ->set('filterMinInitialValue', 60)
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test Current Balance Range
            ->set('filterMinInitialValue', '')
            ->set('filterMaxCurrentBalance', 40)
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test Created Date Range
            ->set('filterMaxCurrentBalance', '')
            ->set('filterCreatedAtTo', now()->subDays(3)->format('Y-m-d'))
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-1111-2222';
            })

            // Test Expiry Date Range
            ->set('filterCreatedAtTo', '')
            ->set('filterValidUntilTo', now()->format('Y-m-d'))
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 1 && $vouchers->first()->code === 'SEELENFUNKE-3333-4444';
            })

            // Test sorting: Balance Ascending
            ->set('filterValidUntilTo', '')
            ->set('sortOrder', 'current_balance_asc')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                $v = $vouchers->items();
                return count($v) === 2 
                    && $v[0]->code === 'SEELENFUNKE-3333-4444' 
                    && $v[1]->code === 'SEELENFUNKE-1111-2222';
            })

            // Test sorting: Balance Descending
            ->set('sortOrder', 'current_balance_desc')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                $v = $vouchers->items();
                return count($v) === 2 
                    && $v[0]->code === 'SEELENFUNKE-1111-2222' 
                    && $v[1]->code === 'SEELENFUNKE-3333-4444';
            })

            // Test reset
            ->call('clearGiftFilters')
            ->assertSet('filterDelivery', 'all')
            ->assertSet('filterBalance', 'all')
            ->assertSet('filterStatus', 'all')
            ->assertSet('sortOrder', 'created_at_desc')
            ->assertViewHas('giftVouchers', function ($vouchers) {
                return $vouchers->count() === 2;
            });
    }
}
