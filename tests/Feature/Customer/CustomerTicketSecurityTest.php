<?php

namespace Tests\Feature\Customer;

use App\Livewire\Customer\CustomerTicketsComponent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerTicketSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer1;
    private Customer $customer2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer1 = Customer::create([
            'first_name' => 'Alice',
            'last_name' => 'Wonderland',
            'email' => 'alice@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->customer2 = Customer::create([
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'bob@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_customer_cannot_link_foreign_order_to_ticket(): void
    {
        $this->actingAs($this->customer1, 'customer');

        // Foreign order belonging to Bob
        $foreignOrder = OrderOrder::create([
            'order_number' => 'ORD-FOREIGN-999',
            'customer_id' => $this->customer2->id,
            'email' => $this->customer2->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
            'billing_address' => ['first_name' => 'Bob', 'last_name' => 'Builder'],
            'shipping_address' => ['first_name' => 'Bob', 'last_name' => 'Builder'],
        ]);

        Livewire::test(CustomerTicketsComponent::class)
            ->set('newSubject', 'Meine Anfrage zu fremder Bestellung')
            ->set('newCategory', 'support')
            ->set('newOrderId', (string) $foreignOrder->id)
            ->set('newMessage', 'Hier ist ein wichtiges Anliegen zur Bestellung.')
            ->call('createTicket');

        $ticket = SupportTicket::where('customer_id', $this->customer1->id)->latest()->first();
        $this->assertNotNull($ticket);
        // The foreign order ID must NOT be assigned to Alice's ticket
        $this->assertNull($ticket->order_id);
    }

    public function test_customer_can_link_own_order_to_ticket(): void
    {
        $this->actingAs($this->customer1, 'customer');

        $ownOrder = OrderOrder::create([
            'order_number' => 'ORD-OWN-111',
            'customer_id' => $this->customer1->id,
            'email' => $this->customer1->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'total_price' => 1190,
            'billing_address' => ['first_name' => 'Alice', 'last_name' => 'Wonderland'],
            'shipping_address' => ['first_name' => 'Alice', 'last_name' => 'Wonderland'],
        ]);

        Livewire::test(CustomerTicketsComponent::class)
            ->set('newSubject', 'Frage zu meiner eigenen Bestellung')
            ->set('newCategory', 'support')
            ->set('newOrderId', (string) $ownOrder->id)
            ->set('newMessage', 'Wann wird das Paket geliefert?')
            ->call('createTicket');

        $ticket = SupportTicket::where('customer_id', $this->customer1->id)->latest()->first();
        $this->assertNotNull($ticket);
        $this->assertEquals((string) $ownOrder->id, (string) $ticket->order_id);
    }

    public function test_bug_spark_reward_is_granted_only_once_per_day(): void
    {
        $this->actingAs($this->customer1, 'customer');

        $gamification = CustomerGamification::firstOrCreate(
            ['customer_id' => $this->customer1->id],
            ['funken_balance' => 0]
        );

        // 1st bug ticket: +5 sparks
        Livewire::test(CustomerTicketsComponent::class)
            ->set('newSubject', 'Erster Bug Report im Shop')
            ->set('newCategory', 'bug')
            ->set('newMessage', 'Hier ist ein UI Fehler aufgetreten.')
            ->call('createTicket');

        $this->assertEquals(5, $gamification->fresh()->funken_balance);

        // 2nd bug ticket on same day: no additional sparks
        Livewire::test(CustomerTicketsComponent::class)
            ->set('newSubject', 'Zweiter Bug Report am gleichen Tag')
            ->set('newCategory', 'bug')
            ->set('newMessage', 'Noch ein Fehler gefunden.')
            ->call('createTicket');

        $this->assertEquals(5, $gamification->fresh()->funken_balance);
    }
}
