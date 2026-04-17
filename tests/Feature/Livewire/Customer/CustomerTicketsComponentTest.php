<?php

namespace Tests\Feature\Livewire\Customer;

use App\Livewire\Customer\CustomerTicketsComponent;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CustomerTicketsComponentTest extends TestCase
{
    use RefreshDatabase;

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = Customer::create([
            'first_name' => 'Anna',
            'last_name' => 'Muster',
            'email' => 'anna@example.com',
            'password' => bcrypt('password')
        ]);
        
        config(['broadcasting.default' => 'log']);
    }

    #[Test]
    public function it_creates_a_ticket_with_auto_greeting_and_dispatches_email()
    {
        Mail::fake();

        Livewire::actingAs($this->customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->set('newSubject', 'My test issue')
            ->set('newCategory', 'general')
            ->set('newMessage', 'Please help me with this test issue.')
            ->call('createTicket')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        // Verify ticket in DB
        $this->assertDatabaseHas('support_tickets', [
            'customer_id' => $this->customer->id,
            'subject' => 'My test issue',
            'category' => 'general',
            'status' => 'open'
        ]);

        $ticket = \App\Models\Support\SupportTicket::where('subject', 'My test issue')->first();

        // Verify customer's first message
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => 'Please help me with this test issue.'
        ]);

        // Verify admin's auto-greeting message starts with "Hallo Anna,"
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
        ]);
        
        $adminMessage = \App\Models\Support\SupportTicketMessage::where('support_ticket_id', $ticket->id)
            ->where('sender_type', 'admin')
            ->first();
            
        $this->assertStringContainsString('Hallo Anna,', $adminMessage->message);
        $this->assertStringContainsString($ticket->ticket_number, $adminMessage->message);

        // Verify creation email queued
        Mail::assertQueued(\App\Mail\SupportTicketCreatedMailToCustomer::class, function ($mail) {
            return $mail->hasTo('anna@example.com');
        });
    }
}
