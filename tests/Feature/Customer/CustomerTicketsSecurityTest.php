<?php

namespace Tests\Feature\Customer;

use App\Livewire\Customer\CustomerTicketsComponent;
use App\Models\Customer\Customer;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerTicketsSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customerA;
    protected Customer $customerB;
    protected SupportTicket $ticketA;
    protected SupportTicket $ticketB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customerA = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'customer_ticket_a@example.com',
            'first_name' => 'TicketUser',
            'last_name' => 'A',
            'password' => bcrypt('secret123'),
        ]);

        $this->customerB = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'customer_ticket_b@example.com',
            'first_name' => 'TicketUser',
            'last_name' => 'B',
            'password' => bcrypt('secret123'),
        ]);

        $this->ticketA = SupportTicket::create([
            'id' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-AAAAAA',
            'customer_id' => $this->customerA->id,
            'subject' => 'Ticket A Thema',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->ticketB = SupportTicket::create([
            'id' => Str::uuid()->toString(),
            'ticket_number' => 'TCK-BBBBBB',
            'customer_id' => $this->customerB->id,
            'subject' => 'Ticket B Vertraulich',
            'category' => 'order',
            'status' => 'open',
            'priority' => 'high',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $this->ticketB->id,
            'sender_type' => 'employee',
            'message' => 'Geheime Support-Mitteilung an Kunde B',
            'is_read_by_customer' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_cannot_open_or_read_another_customers_ticket()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $this->ticketB->id)
            ->assertSet('activeTicketId', null)
            ->assertSet('viewMode', 'list');

        // Verify the message on ticket B was NOT marked as read
        $msg = SupportTicketMessage::where('support_ticket_id', $this->ticketB->id)->first();
        $this->assertFalse($msg->is_read_by_customer);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function customer_can_open_their_own_ticket()
    {
        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $this->ticketA->id)
            ->assertSet('activeTicketId', $this->ticketA->id)
            ->assertSet('viewMode', 'chat');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_attachments_with_disallowed_extensions()
    {
        $maliciousFile = UploadedFile::fake()->create('exploit.exe', 100);

        Livewire::actingAs($this->customerA, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->set('newAttachments', [$maliciousFile])
            ->assertSet('uploadError', 'Ungültiges Dateiformat (.exe). Erlaubt sind: jpg, jpeg, png, webp, gif, pdf, txt, docx.')
            ->assertCount('attachments', 0);
    }
}
