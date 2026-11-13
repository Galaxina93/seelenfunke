<?php

namespace Tests\Feature\Livewire\Shop\Order;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Livewire\Shop\Order\OrderOverview as Orders;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OrderOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
        

        $admin = \App\Models\Admin\Admin::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Wurst',
            'password' => bcrypt('password')
        ]);
        
        $this->actingAs($admin, 'admin');
        
        // Dummy Customer erstellen
        $this->customer = \App\Models\Customer\Customer::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'test@kunde.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('password')
        ]);
    }

    private function createOrder(string $orderNumber = 'TEST-001')
    {
        return OrderOrder::create([
            'order_number' => $orderNumber,
            'email' => 'test@kunde.de',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'payment_status' => 'open',
            'payment_method' => 'bank_transfer',
            'total_price' => 100,
            'subtotal_price' => 100,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Max', 
                'last_name' => 'Mustermann',
                'address' => 'Teststraße 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ],
            'shipping_address' => [
                'first_name' => 'Max', 
                'last_name' => 'Mustermann',
                'address' => 'Teststraße 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ],
        ]);
    }

    public function test_mark_as_paid_dispatches_job_when_no_invoice_exists()
    {
        Queue::fake();

        $order = $this->createOrder();

        Livewire::test(Orders::class)
            ->call('markAsPaid', $order->id);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });
    }

    public function test_save_status_dispatches_job_when_payment_status_changes_to_paid_and_no_invoice_exists()
    {
        Queue::fake();

        $order = $this->createOrder();

        Livewire::test(Orders::class)
            ->call('openDetail', $order->id)
            ->set('payment_status', 'paid')
            ->call('saveStatus');

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });
    }

    public function test_mark_as_paid_does_not_dispatch_job_when_invoice_already_exists()
    {
        Queue::fake();

        $order = $this->createOrder();
        
        // Simuliere vorhandene Rechnung
        AccountingInvoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'RE-2024-1000',
            'type' => 'invoice',
            'status' => 'open',
            'invoice_date' => now(),
            'delivery_date' => now(),
            'due_date' => now(),
            'due_days' => 7,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'billing_address' => ['first_name' => 'Max'],
        ]);

        Livewire::test(Orders::class)
            ->call('markAsPaid', $order->id);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);

        Queue::assertNotPushed(ProcessOrderDocumentsAndMails::class);
    }

    public function test_priority_order_tip_for_digital_products()
    {
        $order = $this->createOrder();

        // Digitales Produkt anlegen
        $digitalProduct = \App\Models\Product\Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Test Digital PDF',
            'slug' => 'test-digital-pdf',
            'description' => 'eBook PDF.',
            'price' => 1999,
            'status' => 'active',
            'type' => 'digital',
            'digital_download_path' => 'downloads/test.pdf'
        ]);

        // OrderOrderItem hinzufügen
        $order->items()->create([
            'product_id' => $digitalProduct->id,
            'product_name' => $digitalProduct->name,
            'quantity' => 1,
            'unit_price' => $digitalProduct->price,
            'total_price' => $digitalProduct->price,
        ]);

        Livewire::test(Orders::class)
            ->assertSeeHtml('⚡ DIGITALE BEREITSTELLUNG');
    }

    public function test_generate_dhl_labels_blocks_digital_orders()
    {
        $order = $this->createOrder();

        // Digitales Produkt anlegen
        $digitalProduct = \App\Models\Product\Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Test Digital PDF',
            'slug' => 'test-digital-pdf',
            'description' => 'eBook PDF.',
            'price' => 1999,
            'status' => 'active',
            'type' => 'digital',
            'digital_download_path' => 'downloads/test.pdf'
        ]);

        // OrderOrderItem hinzufügen
        $order->items()->create([
            'product_id' => $digitalProduct->id,
            'product_name' => $digitalProduct->name,
            'quantity' => 1,
            'unit_price' => $digitalProduct->price,
            'total_price' => $digitalProduct->price,
        ]);

        Livewire::test(Orders::class)
            ->call('openDhlModal', $order->id)
            ->call('generateDhlLabels')
            ->assertSet('dhlError', 'Für rein digitale Bestellungen können keine DHL-Versandlabels erstellt werden.');
    }

    public function test_unpaid_order_can_be_permanently_deleted_with_items_and_orphaned_uploads_cleaned_up()
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $order = $this->createOrder();
        $order->update([
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $exclusiveUpload = 'bestellungen/cart-uploads/exclusive_test_upload.png';
        $exclusiveSnapshot = 'system/snapshots/exclusive_test_snap.png';
        $sharedUpload = 'bestellungen/cart-uploads/shared_customer_logo.png';

        \Illuminate\Support\Facades\Storage::disk('public')->put($exclusiveUpload, 'img-data-1');
        \Illuminate\Support\Facades\Storage::disk('public')->put($exclusiveSnapshot, 'img-data-2');
        \Illuminate\Support\Facades\Storage::disk('public')->put($sharedUpload, 'img-data-shared');

        $product = \App\Models\Product\Product::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => 'Laser Talisman',
            'slug' => 'laser-talisman-del-test',
            'description' => 'Test.',
            'price' => 3490,
            'status' => 'active',
            'type' => 'physical',
        ]);

        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3490,
            'total_price' => 3490,
            'configuration' => [
                'files' => [$exclusiveUpload, $sharedUpload],
                'snapshot_path' => ['front' => $exclusiveSnapshot],
            ],
        ]);

        // Zweite Bestellung nutzt $sharedUpload weiter -> Datei darf beim Löschen der ersten Bestellung NICHT gelöscht werden
        $otherOrder = OrderOrder::create([
            'order_number' => 'TEST-002-KEEP',
            'email' => 'test@kunde.de',
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'total_price' => 3490,
            'subtotal_price' => 3490,
            'tax_amount' => 0,
            'shipping_price' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'address' => 'Str 1', 'postal_code' => '12345', 'city' => 'Ort', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann', 'address' => 'Str 1', 'postal_code' => '12345', 'city' => 'Ort', 'country' => 'DE'],
        ]);
        $otherOrder->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3490,
            'total_price' => 3490,
            'configuration' => [
                'files' => [$sharedUpload],
            ],
        ]);

        $this->assertTrue($order->fresh()->canBePermanentlyDeleted());

        $component = Livewire::test(Orders::class)
            ->call('openDetail', $order->id)
            ->assertSee('Bestellung unwiderruflich löschen')
            ->call('delete', $order->id);

        // Order und Items müssen echt (forceDelete) aus der Datenbank entfernt sein
        $this->assertNull(OrderOrder::withTrashed()->find($order->id));
        $this->assertSame(0, \App\Models\Order\OrderOrderItem::where('order_id', $order->id)->count());
        $this->assertNull(\App\Models\Order\OrderOrderItem::find($item->id));

        // Exklusive Dateien wurden gelöscht, geteilte Datei bleibt erhalten
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($exclusiveUpload);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($exclusiveSnapshot);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($sharedUpload);

        // Audit-Log im SystemLog wurde angelegt
        $this->assertTrue(
            \App\Models\System\SystemLog::where('action_id', 'admin_order_force_delete')
                ->where('title', 'LIKE', '%TEST-001%')
                ->exists()
        );
    }

    public function test_gobd_protected_orders_cannot_be_deleted_and_hide_delete_button()
    {
        // 1. Bezahlte Bestellung (paid)
        $paidOrder = $this->createOrder('TEST-PAID-01');
        $paidOrder->update(['payment_status' => 'paid', 'status' => 'pending']);
        $this->assertFalse($paidOrder->fresh()->canBePermanentlyDeleted());

        Livewire::test(Orders::class)
            ->call('openDetail', $paidOrder->id)
            ->assertDontSee('Bestellung unwiderruflich löschen')
            ->call('delete', $paidOrder->id);

        $this->assertNotNull(OrderOrder::find($paidOrder->id));

        // 2. Erstattete Bestellung (refunded)
        $refundedOrder = $this->createOrder('TEST-REF-01');
        $refundedOrder->update(['payment_status' => 'refunded', 'status' => 'cancelled']);
        $this->assertFalse($refundedOrder->fresh()->canBePermanentlyDeleted());

        Livewire::test(Orders::class)
            ->call('delete', $refundedOrder->id);
        $this->assertNotNull(OrderOrder::find($refundedOrder->id));

        // 3. Versendete Bestellung (shipped)
        $shippedOrder = $this->createOrder('TEST-SHP-01');
        $shippedOrder->update(['payment_status' => 'unpaid', 'status' => 'shipped']);
        $this->assertFalse($shippedOrder->fresh()->canBePermanentlyDeleted());

        Livewire::test(Orders::class)
            ->call('delete', $shippedOrder->id);
        $this->assertNotNull(OrderOrder::find($shippedOrder->id));

        // 4. Unbezahlte Bestellung, zu der aber bereits eine GoBD-Rechnung existiert
        $invoicedOrder = $this->createOrder('TEST-INV-01');
        $invoicedOrder->update(['payment_status' => 'unpaid', 'status' => 'pending']);
        AccountingInvoice::create([
            'order_id' => $invoicedOrder->id,
            'invoice_number' => 'RE-2026-9999',
            'type' => 'invoice',
            'status' => 'open',
            'invoice_date' => now(),
            'delivery_date' => now(),
            'due_date' => now(),
            'due_days' => 7,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
            'billing_address' => ['first_name' => 'Max'],
        ]);
        $this->assertFalse($invoicedOrder->fresh()->canBePermanentlyDeleted());

        Livewire::test(Orders::class)
            ->call('openDetail', $invoicedOrder->id)
            ->assertDontSee('Bestellung unwiderruflich löschen')
            ->call('delete', $invoicedOrder->id);

        $this->assertNotNull(OrderOrder::find($invoicedOrder->id));
    }
}


