<?php

namespace Tests\Feature\Customer;

use App\Livewire\Auth\AuthLogin;
use App\Livewire\Auth\AuthProfileDropdown;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Cart\Cart;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Models\Order\OrderOrder;
use App\Models\Support\SupportTicket;
use App\Models\System\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAccountDeletionAndProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => Hash::make('Secret123!'),
        ]);

        $this->customer->profile()->update([
            'email_verified_at' => now(),
            'street' => 'Musterstraße',
            'house_number' => '42',
            'postal' => '12345',
            'city' => 'Berlin',
            'country' => 'DE',
            'about' => 'Das ist mein Profiltext.',
        ]);
    }

    public function test_customer_profile_renders_with_enhanced_ui(): void
    {
        Auth::guard('customer')->login($this->customer);

        Livewire::test(AuthProfileDropdown::class)
            ->assertStatus(200)
            ->assertSet('firstName', 'Max')
            ->assertSet('lastName', 'Mustermann')
            ->assertSet('email', 'max@example.com')
            ->assertSet('postal', '12345')
            ->assertSet('about', 'Das ist mein Profiltext.')
            ->assertSeeHtml('wire:model="about"')
            ->assertSeeHtml('textarea');
    }

    public function test_customer_delete_account_soft_deletes_customer_and_profile_and_preserves_orders(): void
    {
        Auth::guard('customer')->login($this->customer);

        // 1. Create related entities
        // Newsletter
        MarketingNewsletterSubscriber::create([
            'email' => $this->customer->email,
            'status' => 'subscribed',
        ]);

        // Cart
        $cart = Cart::create([
            'session_id' => 'test_session_delete',
            'customer_id' => $this->customer->id,
        ]);

        // Order
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-DEL-001',
            'customer_id' => $this->customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'email' => $this->customer->email,
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'total_price' => 5950,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        // Invoice
        $invoice = AccountingInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'INV-DEL-001',
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'invoice_date' => now()->toDateString(),
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'type' => 'invoice',
            'status' => 'paid',
            'subtotal' => 5000,
            'tax_amount' => 950,
            'total' => 5950,
        ]);

        // Support Ticket
        $ticket = SupportTicket::create([
            'id' => (string) Str::uuid(),
            'ticket_number' => 'TIC-DEL-001',
            'customer_id' => $this->customer->id,
            'subject' => 'Frage zur Bestellung',
            'category' => 'general',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        // 2. Perform Account Deletion via AuthProfileDropdown
        Livewire::test(AuthProfileDropdown::class)
            ->call('deleteAccount')
            ->assertRedirect('/');

        // 3. Verify Soft Deletion
        $this->assertSoftDeleted('customers', ['id' => $this->customer->id]);
        $this->assertSoftDeleted('customer_profiles', ['customer_id' => $this->customer->id]);

        // 4. Verify Newsletter removed
        $this->assertDatabaseMissing('marketing_newsletter_subscribers', ['email' => 'max@example.com']);

        // 5. Verify Cart customer_id is detached
        $this->assertNull($cart->fresh()->customer_id);

        // 6. Verify Auth guard is logged out
        $this->assertFalse(Auth::guard('customer')->check());

        // 7. Verify relations still resolve soft-deleted customer (GoBD / Legal retention compliance)
        $this->assertNotNull($order->fresh()->customer, 'Order must still resolve soft-deleted customer');
        $this->assertEquals($this->customer->id, $order->fresh()->customer->id);

        $this->assertNotNull($invoice->fresh()->customer, 'Invoice must still resolve soft-deleted customer');
        $this->assertEquals($this->customer->id, $invoice->fresh()->customer->id);

        $this->assertNotNull($ticket->fresh()->customer, 'Ticket must still resolve soft-deleted customer');
        $this->assertEquals($this->customer->id, $ticket->fresh()->customer->id);

        // 8. Verify Audit log in SystemLog
        $this->assertDatabaseHas('system_logs', [
            'action_id' => 'user:account_deleted',
        ]);
    }

    public function test_deleted_customer_cannot_login_and_can_be_restored(): void
    {
        // Soft delete the customer
        $this->customer->delete();
        $this->assertTrue($this->customer->fresh()->trashed());

        // Attempt login -> should fail with deactivated message
        Livewire::test(AuthLogin::class)
            ->set('email', 'max@example.com')
            ->set('password', 'Secret123!')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertFalse(Auth::guard('customer')->check());

        // Restore customer (legally compliant restoration)
        $this->customer->restore();
        $this->assertFalse($this->customer->fresh()->trashed());

        // Login again -> should succeed
        Livewire::test(AuthLogin::class)
            ->set('email', 'max@example.com')
            ->set('password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::guard('customer')->check());
    }

    public function test_admin_can_archive_and_restore_customer_via_user_management(): void
    {
        $admin = \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'Boss',
            'email' => 'admin@example.com',
            'password' => Hash::make('AdminSecret123!'),
        ]);

        Auth::guard('admin')->login($admin);

        // 1. Admin archives customer
        Livewire::test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->call('archiveUser', $this->customer->id, 'customer');

        $this->assertSoftDeleted('customers', ['id' => $this->customer->id]);
        $this->assertSoftDeleted('customer_profiles', ['customer_id' => $this->customer->id]);

        // Customer cannot login while archived
        Livewire::test(AuthLogin::class)
            ->set('email', 'max@example.com')
            ->set('password', 'Secret123!')
            ->call('login')
            ->assertHasErrors(['email']);

        // 2. Admin restores customer
        Livewire::test(\App\Livewire\Shop\System\SystemUserManagement::class)
            ->call('restoreUser', $this->customer->id, 'customer');

        $this->assertFalse($this->customer->fresh()->trashed());
        $this->assertFalse($this->customer->profile()->withTrashed()->first()->trashed());

        // Customer can login again cleanly
        Livewire::test(AuthLogin::class)
            ->set('email', 'max@example.com')
            ->set('password', 'Secret123!')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::guard('customer')->check());
    }
}
