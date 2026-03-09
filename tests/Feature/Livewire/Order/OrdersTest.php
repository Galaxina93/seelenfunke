<?php

namespace Tests\Feature\Livewire\Order;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Livewire\Shop\Order\Orders;
use App\Models\Invoice;
use App\Models\Order\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class OrdersTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        \App\Models\Role::firstOrCreate(['name' => 'admin']);
        
        $admin = \App\Models\Admin\Admin::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Hans',
            'last_name' => 'Wurst',
            'password' => bcrypt('password')
        ]);
        
        $this->actingAs($admin);
        
        // Dummy Customer erstellen
        $this->customer = \App\Models\Customer\Customer::create([
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => 'test@kunde.de',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'password' => bcrypt('password')
        ]);
    }

    private function createOrder()
    {
        return Order::create([
            'order_number' => 'TEST-001',
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
        Invoice::create([
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
}
