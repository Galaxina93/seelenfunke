<?php

namespace Tests\Feature\Livewire\Shop\Support;

use App\Livewire\Shop\Support\SupportContactFormComponent;
use App\Models\Support\SupportContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class SupportContactFormComponentTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_successfully()
    {
        Livewire::test(SupportContactFormComponent::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.shop.support.support-contact-form-component')
            ->assertSee('Kontaktanfragen Inbox')
            ->assertSee('Alle Status');
    }

    public function test_can_search_requests_by_ticket_number()
    {
        SupportContactRequest::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'subject' => 'Where is my order?',
            'ticket_number' => 'REQ-26-XXXXXX',
            'status' => 'new',
            'message' => 'Help me'
        ]);

        SupportContactRequest::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'subject' => 'Payment issue',
            'ticket_number' => 'REQ-26-YYYYYY',
            'status' => 'new',
            'message' => 'Payment failing'
        ]);

        Livewire::test(SupportContactFormComponent::class)
            ->set('search', 'XXXXXX')
            ->assertSee('John')
            ->assertDontSee('Jane');
    }

    public function test_can_filter_by_status()
    {
        SupportContactRequest::create([
            'first_name' => 'Alex',
            'last_name' => 'Test',
            'email' => 'alex@example.com',
            'subject' => 'Product defect',
            'status' => 'in_progress',
            'message' => 'Broken item'
        ]);

        SupportContactRequest::create([
            'first_name' => 'Bob',
            'last_name' => 'Test',
            'email' => 'bob@example.com',
            'subject' => 'Refund',
            'status' => 'resolved',
            'message' => 'I want a refund'
        ]);

        Livewire::test(SupportContactFormComponent::class)
            ->set('statusFilter', 'resolved')
            ->assertSee('Bob')
            ->assertDontSee('Alex');
    }

    public function test_opening_new_request_changes_status_to_in_progress()
    {
        $request = SupportContactRequest::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'subject' => 'Hello',
            'status' => 'new',
            'message' => 'Is this working?'
        ]);

        $this->assertEquals('new', $request->fresh()->status);

        Livewire::test(SupportContactFormComponent::class)
            ->call('openRequest', $request->id)
            ->assertSet('selectedRequestId', $request->id)
            ->assertSee('Max Mustermann')
            ->assertSee($request->ticket_number);

        $this->assertEquals('in_progress', $request->fresh()->status);
    }

    public function test_closing_request_resets_selected_properties()
    {
        $request = SupportContactRequest::create([
            'first_name' => 'Test',
            'last_name' => 'Sub',
            'email' => 'sub@example.com',
            'subject' => 'Hello',
            'status' => 'in_progress',
            'message' => 'Hello word'
        ]);

        Livewire::test(SupportContactFormComponent::class)
            ->call('openRequest', $request->id)
            ->assertSet('selectedRequestId', $request->id)
            ->call('closeRequest')
            ->assertSet('selectedRequestId', null)
            ->assertSet('replyMessage', '');
    }

    public function test_inserting_canned_response_macros()
    {
        $request = SupportContactRequest::create([
            'first_name' => 'Alina',
            'last_name' => 'Funke',
            'email' => 'alina@example.com',
            'subject' => 'Business Offer',
            'status' => 'in_progress',
            'message' => 'Offer details pls'
        ]);

        $component = Livewire::test(SupportContactFormComponent::class)
            ->call('openRequest', $request->id)
            ->call('insertCannedResponse', 'busy');

        $this->assertStringContainsString('Aufgrund eines sehr hohen Aufkommens', $component->get('replyMessage'));

        $component->call('insertCannedResponse', 'details');
        $this->assertStringContainsString('benötigen wir noch ein paar kleine Details', $component->get('replyMessage'));

        $component->call('insertCannedResponse', 'calculator');
        $this->assertStringContainsString('/kalkulator', $component->get('replyMessage'));
    }

    public function test_can_send_reply_and_create_message_thread()
    {
        Mail::fake();

        $request = SupportContactRequest::create([
            'first_name' => 'Emma',
            'last_name' => 'Watson',
            'email' => 'emma@example.com',
            'subject' => 'Order delay',
            'status' => 'in_progress',
            'message' => 'Why is it late?'
        ]);

        Livewire::test(SupportContactFormComponent::class)
            ->call('openRequest', $request->id)
            ->set('replyMessage', 'Sorry for the delay Emma!')
            ->call('sendReply')
            ->assertDispatched('saved-reply')
            ->assertSet('replyMessage', '');

        $this->assertEquals('waiting_for_customer', $request->fresh()->status);
        $this->assertCount(1, $request->messages);
        
        $message = $request->messages->first();
        $this->assertEquals('admin', $message->sender_type);
        $this->assertEquals('Sorry for the delay Emma!', $message->message);
    }

    public function test_can_mark_request_as_resolved()
    {
        $request = SupportContactRequest::create([
            'first_name' => 'Pete',
            'last_name' => 'Parker',
            'email' => 'pete@example.com',
            'subject' => 'Web shooters',
            'status' => 'in_progress',
            'message' => 'Broken'
        ]);

        Livewire::test(SupportContactFormComponent::class)
            ->call('openRequest', $request->id)
            ->call('markResolved')
            ->assertDispatched('request-resolved')
            ->assertSet('selectedRequestId', null);

        $this->assertEquals('resolved', $request->fresh()->status);
    }
}
