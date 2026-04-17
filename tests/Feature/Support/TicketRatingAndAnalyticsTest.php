<?php

namespace Tests\Feature\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Livewire\Livewire;
use App\Models\Customer\Customer;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemUser;
use App\Livewire\Customer\CustomerTicketsComponent;
use App\Livewire\Shop\Support\SupportAnalytics;

class TicketRatingAndAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_rate_a_closed_ticket()
    {
        $customer = Customer::factory()->create();
        
        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-TEST-123',
            'customer_id' => $customer->id,
            'subject' => 'Hilfe benötigt',
            'category' => 'support',
            'status' => 'closed',
            'priority' => 'normal',
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $ticket->id)
            ->assertSet('rating', 0)
            ->call('setRating', 5)
            ->assertSet('rating', 5)
            ->set('feedbackText', 'Super!')
            ->call('submitRating')
            ->assertSet('ratingSubmitted', true);

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'rating' => 5,
            'feedback_text' => 'Super!',
        ]);
    }

    public function test_admin_can_view_analytics_with_rating_kpis()
    {
        $admin = \App\Models\Admin\Admin::factory()->create();
        $customer = Customer::factory()->create();
        
        // Erzeuge ein Ticket mit schlechter Bewertung
        SupportTicket::create([
            'ticket_number' => 'MSF-TEST-444',
            'customer_id' => $customer->id,
            'subject' => 'Ticket 1',
            'category' => 'support',
            'status' => 'closed',
            'priority' => 'normal',
            'rating' => 1, // 1 Star
        ]);

        // Erzeuge ein Ticket mit guter Bewertung
        SupportTicket::create([
            'ticket_number' => 'MSF-TEST-555',
            'customer_id' => $customer->id,
            'subject' => 'Ticket 2',
            'category' => 'support',
            'status' => 'closed',
            'priority' => 'normal',
            'rating' => 5, // 5 Stars
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(SupportAnalytics::class)
            ->assertSet('kpiTicketsClosed', 2)
            ->assertSet('kpiAvgTicketRating', 3.0); // (1+5)/2
    }

    public function test_customer_can_close_ticket_with_reason()
    {
        $customer = Customer::factory()->create();
        
        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-TEST-999',
            'customer_id' => $customer->id,
            'subject' => 'Ticket to close',
            'category' => 'support',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $ticket->id)
            ->set('closeReason', 'Hat sich von selbst gelöst')
            ->call('closeTicket')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('support_tickets', [
            'id' => $ticket->id,
            'status' => 'closed',
            'close_reason' => 'Hat sich von selbst gelöst',
        ]);
        
        // Also verify validation
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', $ticket->id)
            ->set('closeReason', 'kurz') // less than 5 characters
            ->call('closeTicket')
            ->assertHasErrors(['closeReason' => 'min']);
    }
}
