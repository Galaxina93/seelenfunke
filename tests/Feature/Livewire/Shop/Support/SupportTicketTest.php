<?php

namespace Tests\Feature\Livewire\Shop\Support;

use App\Livewire\Shop\Support\SupportTicket;
use App\Models\Customer\Customer;
use App\Models\Support\SupportTicket as SupportTicketModel;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        
        config(['livewire.temporary_file_upload.disk' => 'local']);
        config(['broadcasting.default' => 'log']);
        Storage::fake('local');
        
        $this->customer = Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => bcrypt('password')
        ]);

        $this->ticket = SupportTicketModel::create([
            'ticket_number' => 'TKT-1000',
            'customer_id' => $this->customer->id,
            'subject' => 'Account Issue',
            'category' => 'general',
            'status' => 'open'
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $this->ticket->id,
            'sender_type' => 'customer',
            'message' => 'Help with account',
            'is_read_by_admin' => false,
            'created_at' => now()->subMinutes(15)
        ]);
    }

    #[Test]
    public function it_renders_the_tickets_component_and_displays_open_tickets()
    {
        Livewire::test(SupportTicket::class)
            ->assertStatus(200)
            ->assertSet('filterStatus', 'open')
            ->assertSee('TKT-1000');
    }

    #[Test]
    public function it_can_search_for_a_ticket_by_number_and_customer_name()
    {
        Livewire::test(SupportTicket::class)
            ->set('search', 'TKT-1000')
            ->assertSee('TKT-1000');

        Livewire::test(SupportTicket::class)
            ->set('search', 'John')
            ->assertSee('TKT-1000');

        Livewire::test(SupportTicket::class)
            ->set('search', 'Unknown')
            ->assertDontSee('TKT-1000');
    }

    #[Test]
    public function it_can_select_a_ticket_and_mark_messages_as_read()
    {
        Livewire::test(SupportTicket::class)
            ->call('selectTicket', $this->ticket->id)
            ->assertSet('activeTicketId', $this->ticket->id)
            ->assertDispatched('ticket-message-received')
            ->assertDispatched('clear-admin-ticket-badge');

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $this->ticket->id,
            'is_read_by_admin' => 1
        ]);
    }

    #[Test]
    public function it_can_handle_incoming_broadcast_events()
    {
        Livewire::test(SupportTicket::class)
            // Selecting it first so activeTicketId matches
            ->call('selectTicket', $this->ticket->id)
            ->dispatch('echo-private:admin.tickets,.TicketMessageSent', [
                'message' => ['support_ticket_id' => $this->ticket->id]
            ])
            ->assertDispatched('ticket-message-received')
            ->assertDispatched('clear-admin-ticket-badge');
    }

    #[Test]
    public function it_validates_empty_replies()
    {
        Livewire::test(SupportTicket::class)
            ->call('selectTicket', $this->ticket->id)
            ->call('sendReply')
            ->assertHasErrors(['replyMessage' => 'required']);
    }

    #[Test]
    public function it_sends_a_reply_and_sends_email_notification_when_offline()
    {
        Mail::fake();

        Livewire::test(SupportTicket::class)
            ->call('selectTicket', $this->ticket->id)
            ->set('replyMessage', 'This is a test reply.')
            ->call('sendReply')
            ->assertHasNoErrors()
            ->assertSet('filterStatus', 'answered')
            ->assertSet('replyMessage', '') // Make sure inputs reset
            ->assertDispatched('ticket-message-received');

        // Check the database
        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $this->ticket->id,
            'sender_type' => 'admin',
            'message' => 'This is a test reply.',
            'is_read_by_customer' => 0
        ]);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $this->ticket->id,
            'status' => 'answered'
        ]);

        // Since the user is offline (no recent msg cache/profile), email must be sent
        Mail::assertQueued(\App\Mail\SupportTicketUpdateMailToCustomer::class, function ($mail) {
            return $mail->hasTo('john.doe@example.com');
        });
    }

    #[Test]
    public function it_sends_a_reply_but_suppresses_email_if_customer_is_online()
    {
        Mail::fake();

        // Simulate customer being online
        Cache::put('customer-online-' . $this->customer->id, true, now()->addMinutes(5));

        Livewire::test(SupportTicket::class)
            ->call('selectTicket', $this->ticket->id)
            ->set('replyMessage', 'Are you there?')
            ->call('sendReply');

        // Assert email was not sent because they are in the chat!
        Mail::assertNotQueued(\App\Mail\SupportTicketUpdateMailToCustomer::class);
    }



    #[Test]
    public function it_can_close_a_ticket()
    {
        Livewire::test(SupportTicket::class)
            ->call('selectTicket', $this->ticket->id)
            ->call('closeTicket')
            ->assertSet('filterStatus', 'closed')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('support_tickets', [
            'id' => $this->ticket->id,
            'status' => 'closed'
        ]);
    }
}
