<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingCredit;
use App\Mail\CreditNoteMailToCustomer;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_send_credit_email_succeeds_for_registered_customer()
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Muster',
            'email' => 'alina@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $invoice = AccountingInvoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'GUT-2026-0001',
            'type' => 'credit_note',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now(),
            'delivery_date' => now(),
            'paid_at' => now(),
            'total' => -2500,
            'subtotal' => -2500,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Alina',
                'last_name' => 'Muster',
                'email' => 'alina@example.com',
            ],
        ]);

        Livewire::test(AccountingCredit::class)
            ->call('sendCreditEmail', $invoice->id)
            ->assertDispatched('toast', message: 'Die Gutschrift wurde an alina@example.com gesendet!', type: 'success');

        Mail::assertQueued(CreditNoteMailToCustomer::class, function ($mail) {
            return $mail->hasTo('alina@example.com');
        });
    }

    public function test_send_credit_email_succeeds_for_guest_order_customer()
    {
        // Gastbestellung ohne Customer-Account
        $order = OrderOrder::create([
            'order_number' => 'ORD-2026-GUEST',
            'customer_id' => null,
            'email' => 'gast@muster.de',
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_price' => 3000,
            'subtotal_price' => 2500,
            'tax_amount' => 500,
            'shipping_price' => 0,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Gastmann',
                'email' => 'gast@muster.de',
            ],
        ]);

        $invoice = AccountingInvoice::create([
            'order_id' => $order->id,
            'customer_id' => null,
            'invoice_number' => 'STO-2026-0001',
            'type' => 'cancellation',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now(),
            'delivery_date' => now(),
            'paid_at' => now(),
            'total' => -3000,
            'subtotal' => -2500,
            'tax_amount' => -500,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Gastmann',
                'email' => 'gast@muster.de',
            ],
        ]);

        Livewire::test(AccountingCredit::class)
            ->call('sendCreditEmail', $invoice->id)
            ->assertDispatched('toast', message: 'Die Gutschrift wurde an gast@muster.de gesendet!', type: 'success');

        Mail::assertQueued(CreditNoteMailToCustomer::class, function ($mail) {
            return $mail->hasTo('gast@muster.de');
        });

        // Test rendering the email view to ensure no "first_name on null" error
        $mailable = new CreditNoteMailToCustomer($invoice);
        $rendered = $mailable->render();
        $this->assertStringContainsString('Hallo Max,', $rendered);
        $this->assertStringContainsString('STO-2026-0001', $rendered);
    }

    public function test_send_credit_email_fails_gracefully_when_no_email_exists()
    {
        $invoice = AccountingInvoice::create([
            'customer_id' => null,
            'invoice_number' => 'GUT-2026-NOEMAIL',
            'type' => 'credit_note',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now(),
            'delivery_date' => now(),
            'paid_at' => now(),
            'total' => -1000,
            'subtotal' => -1000,
            'tax_amount' => 0,
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
            'billing_address' => [],
        ]);

        Livewire::test(AccountingCredit::class)
            ->call('sendCreditEmail', $invoice->id)
            ->assertDispatched('toast', message: 'Kunden- oder Beleg-Daten unvollständig (keine gültige E-Mail hinterlegt)!', type: 'error');

        Mail::assertNothingQueued();
    }
}
