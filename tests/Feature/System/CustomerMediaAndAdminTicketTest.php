<?php

namespace Tests\Feature\System;

use App\Livewire\Customer\CustomerOrdersComponent;
use App\Livewire\Customer\CustomerTicketsComponent;
use App\Livewire\Shop\Support\SupportTicket as SupportTicketComponent;
use App\Livewire\Shop\System\SystemUserManagement;
use App\Mail\SupportTicketUpdateMailToCustomer;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportTicket;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerMediaAndAdminTicketTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdmin(): Admin
    {
        return Admin::first() ?? Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-test-' . Str::random(5) . '@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);
    }

    private function createCustomer(array $overrides = []): Customer
    {
        return Customer::create(array_merge([
            'first_name' => 'Kunde',
            'last_name' => 'Test',
            'email' => 'kunde-' . Str::random(6) . '@example.test',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_admin_can_switch_tabs_and_upload_and_delete_customer_documents_in_user_management(): void
    {
        Storage::fake('local');

        $admin = Admin::first() ?? Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-test-' . Str::random(5) . '@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Clara',
            'last_name' => 'Stern',
            'email' => 'clara-' . Str::random(5) . '@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $otherCustomer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Fremd',
            'email' => 'max-' . Str::random(5) . '@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'MSF-TEST-' . strtoupper(Str::random(5)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'invoice',
            'billing_address' => ['first_name' => 'Clara', 'last_name' => 'Stern', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Clara', 'last_name' => 'Stern', 'address' => 'Ring 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price' => 4900,
            'shipping_price' => 0,
            'tax_amount' => 782,
            'total_price' => 4900,
        ]);

        $pdfFile = UploadedFile::fake()->create('manufaktur-zertifikat.pdf', 120, 'application/pdf');

        $component = Livewire::actingAs($admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', $customer->id, 'customer')
            ->assertSet('activeEditTab', 'account')
            ->call('setEditTab', 'profile')
            ->assertSet('activeEditTab', 'profile')
            ->call('setEditTab', 'media')
            ->assertSet('activeEditTab', 'media')
            ->call('setCustomerUploadPresetTitle', 'Echtheitszertifikat')
            ->assertSet('customerUploadTitle', 'Echtheitszertifikat')
            ->set('customerUploadOrderId', $order->id)
            ->set('customerUploadFiles', [$pdfFile])
            ->assertSet('customerMediaSavedText', 'Gespeichert')
            ->assertSee('Gespeichert')
            ->assertSee('Echtheitszertifikat');

        $docs = $customer->fresh()->getAdminUploadedDocuments();
        $this->assertCount(1, $docs);
        $this->assertSame('Echtheitszertifikat', $docs[0]['title']);
        $this->assertSame((string) $order->id, $docs[0]['order_id']);
        $this->assertSame($order->order_number, $docs[0]['order_number']);
        Storage::disk('local')->assertExists($docs[0]['path']);

        // Customer sees the document in their order portal under "Dokumente"
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerOrdersComponent::class)
            ->call('showOrder', $order->id)
            ->assertSee('Echtheitszertifikat')
            ->assertSee('Atelier-Dokument');

        // Customer can download their document (log out admin guard first to test pure customer auth)
        auth()->guard('admin')->logout();

        $downloadUrl = route('customer-document.download', [
            'customer' => $customer->id,
            'doc' => $docs[0]['id'],
        ]);

        $this->actingAs($customer, 'customer')
            ->get($downloadUrl)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        // Foreign customer is blocked (403)
        $this->actingAs($otherCustomer, 'customer')
            ->get($downloadUrl)
            ->assertForbidden();

        // Admin can delete the uploaded document
        auth()->guard('customer')->logout();
        auth()->guard('admin')->login($admin);
        $component->call('removeCustomerUploadedDocument', $docs[0]['id']);
        $this->assertCount(0, $customer->fresh()->getAdminUploadedDocuments());
        Storage::disk('local')->assertMissing($docs[0]['path']);
    }

    public function test_admin_can_initiate_new_support_ticket_directly_for_customer(): void
    {
        Mail::fake();

        $admin = Admin::first() ?? Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-support-' . Str::random(5) . '@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        $customer = Customer::create([
            'first_name' => 'Lukas',
            'last_name' => 'Gold',
            'email' => 'lukas-' . Str::random(5) . '@example.com',
            'password' => bcrypt('password123'),
            'email_verified_at' => now(),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'MSF-ORD-' . strtoupper(Str::random(5)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'invoice',
            'billing_address' => ['first_name' => 'Lukas', 'last_name' => 'Gold', 'address' => 'Weg 2', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Lukas', 'last_name' => 'Gold', 'address' => 'Weg 2', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price' => 3900,
            'shipping_price' => 0,
            'tax_amount' => 623,
            'total_price' => 3900,
        ]);

        Livewire::actingAs($admin, 'admin')
            ->withQueryParams(['createForCustomer' => $customer->id])
            ->test(SupportTicketComponent::class)
            ->assertSet('isCreatingTicket', true)
            ->assertSet('newTicketCustomerId', $customer->id)
            ->set('newTicketOrderId', $order->id)
            ->set('newTicketCategory', 'support')
            ->set('newTicketSubject', 'Kurze Rückfrage zu deiner Wunschgravur')
            ->set('newTicketMessage', 'Hallo Lukas, sollen wir das Datum zentriert unter dem Namen gravieren?')
            ->call('createAdminTicket')
            ->assertSet('isCreatingTicket', false);

        $ticket = SupportTicket::where('customer_id', $customer->id)
            ->where('subject', 'Kurze Rückfrage zu deiner Wunschgravur')
            ->first();

        $this->assertNotNull($ticket);
        $this->assertSame((string) $order->id, (string) $ticket->order_id);
        $this->assertSame('open', $ticket->status);

        $firstMsg = $ticket->messages()->first();
        $this->assertNotNull($firstMsg);
        $this->assertSame('admin', $firstMsg->sender_type);
        $this->assertTrue((bool) $firstMsg->is_read_by_admin);
        $this->assertFalse((bool) $firstMsg->is_read_by_customer);

        Mail::assertSent(SupportTicketUpdateMailToCustomer::class, function ($mail) use ($customer) {
            return $mail->hasTo($customer->email);
        });

        // Customer sees the admin-initiated ticket in their portal
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->assertSee($ticket->ticket_number)
            ->assertSee('Kurze Rückfrage zu deiner Wunschgravur');
    }

    public function test_opening_ticket_immediately_clears_navigation_badge_in_admin_and_customer_portal(): void
    {
        $admin = $this->createAdmin();
        $customer = $this->createCustomer(['first_name' => 'Elena', 'last_name' => 'Vogel']);

        $ticket = SupportTicket::create([
            'ticket_number' => 'MSF-26-BADGE',
            'customer_id' => $customer->id,
            'subject' => 'Frage zur Lieferung',
            'category' => 'support',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // Unread message from customer for Admin
        $customerMsg = \App\Models\Support\SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'customer',
            'message' => 'Wann wird mein Paket versendet?',
            'is_read_by_admin' => false,
            'is_read_by_customer' => true,
        ]);

        // Verify x-forms.list-item renders valid Alpine x-on listener (not escaped @{{)
        $renderedNavHtml = \Illuminate\Support\Facades\Blade::render(
            '<x-forms.list-item route="/admin/support-tickets" title="Tickets" pageName="support-tickets" icon="ticket" dotState="hasUnreadSupport" dotEvent="admin-ticket-badge-update" dotClearEvent="clear-admin-ticket-badge" />'
        );
        $this->assertStringContainsString('x-on:clear-admin-ticket-badge.window', $renderedNavHtml);
        $this->assertStringNotContainsString('{{ $dotClearEvent }}', $renderedNavHtml);

        // Admin opens ticket -> marks read and dispatches clear-admin-ticket-badge
        Livewire::actingAs($admin, 'admin')
            ->test(SupportTicketComponent::class)
            ->call('selectTicket', (string) $ticket->id)
            ->assertDispatched('clear-admin-ticket-badge')
            ->assertDispatched('admin-ticket-badge-update');

        $this->assertTrue((bool) $customerMsg->fresh()->is_read_by_admin);

        // Unread message from Admin for Customer
        $adminMsg = \App\Models\Support\SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'message' => 'Dein Paket geht heute per DHL raus!',
            'is_read_by_admin' => true,
            'is_read_by_customer' => false,
        ]);

        auth()->guard('admin')->logout();

        // Customer opens ticket -> marks read and dispatches clear-ticket-badge
        Livewire::actingAs($customer, 'customer')
            ->test(CustomerTicketsComponent::class)
            ->call('setMode', 'chat', (string) $ticket->id)
            ->assertDispatched('clear-ticket-badge')
            ->assertDispatched('ticket-badge-update');

        $this->assertTrue((bool) $adminMsg->fresh()->is_read_by_customer);
    }

    public function test_dynamic_and_fuzzy_customer_search_in_admin_ticket_creation_handles_similar_names_and_large_lists(): void
    {
        $admin = $this->createAdmin();

        $clara = $this->createCustomer(['first_name' => 'Clara', 'last_name' => 'Stern', 'email' => 'clara.stern@example.test']);
        $meier = $this->createCustomer(['first_name' => 'Stefan', 'last_name' => 'Meier', 'email' => 'stefan.meier@example.test']);
        $steinhauer = $this->createCustomer(['first_name' => 'Alina', 'last_name' => 'Steinhauer', 'email' => 'alina.s@example.test']);

        // Create 20 additional customers with "Berger" to test scaling & Top-15 cap
        for ($i = 1; $i <= 20; $i++) {
            $this->createCustomer([
                'first_name' => 'Kunde' . $i,
                'last_name' => 'Berger',
                'email' => "kunde{$i}.berger@example.test",
            ]);
        }

        $component = Livewire::actingAs($admin, 'admin')
            ->test(SupportTicketComponent::class)
            ->call('openCreateTicketModal');

        // 1. Empty before typing
        $initialResults = $component->get('customerSearchResults');
        $this->assertEmpty($initialResults['items']);
        $this->assertSame(0, $initialResults['total']);

        // 2. Phonetic/similar search: "Klara" finds "Clara Stern" and marks is_similar = true
        $component->set('newTicketCustomerSearch', 'Klara');
        $klaraResults = $component->get('customerSearchResults');
        $this->assertNotEmpty($klaraResults['items']);
        $foundClara = collect($klaraResults['items'])->firstWhere('id', (string) $clara->id);
        $this->assertNotNull($foundClara);
        $this->assertTrue((bool) $foundClara['is_similar']);
        $component->assertSee('Clara Stern');

        // 3. Phonetic/similar search: "Stephan Mayer" finds "Stefan Meier"
        $component->set('newTicketCustomerSearch', 'Stephan Mayer');
        $mayerResults = $component->get('customerSearchResults');
        $foundMeier = collect($mayerResults['items'])->firstWhere('id', (string) $meier->id);
        $this->assertNotNull($foundMeier);
        $this->assertTrue((bool) $foundMeier['is_similar']);

        // 4. Typo search: "Stienhauer" finds "Alina Steinhauer"
        $component->set('newTicketCustomerSearch', 'Stienhauer');
        $typoResults = $component->get('customerSearchResults');
        $foundSteinhauer = collect($typoResults['items'])->firstWhere('id', (string) $steinhauer->id);
        $this->assertNotNull($foundSteinhauer);

        // 5. Large customer list scaling: "Berger" returns 15 items and reports total >= 20 + has_more = true
        $component->set('newTicketCustomerSearch', 'Berger');
        $largeResults = $component->get('customerSearchResults');
        $this->assertCount(15, $largeResults['items']);
        $this->assertGreaterThanOrEqual(20, $largeResults['total']);
        $this->assertTrue((bool) $largeResults['has_more']);
        $component->assertSee('Top 15 von');
    }
}
