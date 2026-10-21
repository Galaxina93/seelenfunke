<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoice as InvoiceModel;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingGiftVoucher;
use App\Models\Marketing\MarketingVoucher;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingInvoiceCancellationOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = Admin::create([
            'first_name' => 'Finance',
            'last_name' => 'Admin',
            'email' => 'finance@seelenfunke.de',
            'password' => Hash::make('password123'),
        ]);

        $this->customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_cancelling_invoice_syncs_order_cancellation_and_honors_laser_stock_rule(): void
    {
        $this->actingAs($this->admin, 'admin');

        // Standard product: quantity 10
        $stdProduct = Product::create([
            'name' => 'Standard Wooden Box',
            'slug' => 'std-box',
            'sku' => 'SKU-STD-BOX',
            'price' => 2000,
            'quantity' => 10,
            'track_quantity' => true,
            'is_personalizable' => false,
            'type' => 'physical',
            'status' => 'active',
        ]);

        // Laser personalized product: quantity 5
        $laserProduct = Product::create([
            'name' => 'Laser Engraved Keepsake',
            'slug' => 'laser-keepsake',
            'sku' => 'SKU-LASER-01',
            'price' => 5000,
            'quantity' => 5,
            'track_quantity' => true,
            'is_personalizable' => true,
            'type' => 'physical',
            'status' => 'active',
        ]);

        // Gift voucher used in order: 50 EUR balance, 20 EUR used
        $giftVoucher = MarketingGiftVoucher::create([
            'code' => 'GIFT-INVOICE-TEST',
            'initial_value' => 5000,
            'current_balance' => 3000,
            'recipient_name' => 'Max Mustermann',
            'delivery_method' => 'email',
            'is_active' => true,
        ]);

        // Promo voucher used in order
        $promoVoucher = MarketingVoucher::create([
            'title' => 'Test Promo Voucher',
            'code' => 'PROMO-INVOICE-TEST',
            'type' => 'percent',
            'value' => 10,
            'used_count' => 1,
            'is_active' => true,
        ]);

        // Create Order
        $order = OrderOrder::create([
            'order_number' => 'ORD-SYNC-001',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'coupon_code' => 'GIFT-INVOICE-TEST',
            'discount_amount' => 2000,
            'subtotal_price' => 7000,
            'tax_amount' => 1330,
            'total_price' => 5000,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'country' => 'DE'],
        ]);

        // Add standard item (qty 2)
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $stdProduct->id,
            'product_name' => $stdProduct->name,
            'quantity' => 2,
            'unit_price' => 2000,
            'total_price' => 4000,
            'configuration' => [],
        ]);

        // Add laser personalized item (qty 1)
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $laserProduct->id,
            'product_name' => $laserProduct->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'total_price' => 5000,
            'configuration' => [
                'is_personalized' => true,
                'engraving_text' => 'For Sarah with Love',
            ],
        ]);

        // Create Invoice for Order
        $invoice = InvoiceModel::create([
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-SYNC-001',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'subtotal' => 7000,
            'tax_amount' => 1330,
            'shipping_cost' => 0,
            'discount_amount' => 2000,
            'total' => 5000,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'max@example.com'
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE'
            ],
        ]);

        // Cancel Invoice via Livewire AccountingInvoice
        Livewire::test(AccountingInvoice::class)
            ->call('cancelInvoice', (string) $invoice->id)
            ->assertHasNoErrors();

        // 1. Order status must be cancelled
        $this->assertEquals('cancelled', $order->fresh()->status);

        // 2. Standard product stock MUST be restored (+2 => 12)
        $this->assertEquals(12, $stdProduct->fresh()->quantity);

        // 3. Laser/personalized product stock MUST NEVER be restored (remains 5 as scrap)
        $this->assertEquals(5, $laserProduct->fresh()->quantity);

        // 4. Gift voucher balance restored (+2000 => 5000)
        $this->assertEquals(5000, $giftVoucher->fresh()->current_balance);
    }
}
