<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Services\InvoiceService;

class InvoiceServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->invoiceService = new InvoiceService();
    }

    public function test_cancel_invoice_prevents_cancelling_already_cancelled_or_cancellation_documents(): void
    {
        $customer = Customer::factory()->create();

        $invoice = AccountingInvoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'RE-2026-9001',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'paid_at' => now(),
            'subtotal' => 5000,
            'tax_amount' => 950,
            'total' => 5950,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
        ]);

        // First cancellation succeeds
        $storno = $this->invoiceService->cancelInvoice($invoice);
        $this->assertEquals('cancellation', $storno->type);
        $this->assertEquals(-5950, $storno->total);

        // Attempting to cancel the invoice again must throw InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->invoiceService->cancelInvoice($invoice);
    }

    public function test_cancel_invoice_rejects_cancelling_a_cancellation_itself(): void
    {
        $customer = Customer::factory()->create();

        $cancellation = AccountingInvoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'STO-2026-9002',
            'type' => 'cancellation',
            'status' => 'paid',
            'invoice_date' => now(),
            'due_date' => now(),
            'paid_at' => now(),
            'subtotal' => -5000,
            'tax_amount' => -950,
            'total' => -5950,
            'billing_address' => [],
            'shipping_address' => [],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->invoiceService->cancelInvoice($cancellation);
    }

    public function test_generate_invoice_number_sanitizes_prefix_and_escapes_wildcards(): void
    {
        $num1 = $this->invoiceService->generateInvoiceNumber('re-test!');
        $this->assertStringStartsWith('RETEST-', $num1);

        $num2 = $this->invoiceService->generateInvoiceNumber('%_hack');
        $this->assertStringStartsWith('HACK-', $num2);
    }
}
