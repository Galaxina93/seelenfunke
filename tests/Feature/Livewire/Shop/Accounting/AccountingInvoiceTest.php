<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoice as InvoiceModel;
use App\Models\Customer\Customer;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->customer = Customer::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Finance',
            'email' => 'john.finance@example.com',
            'company' => 'Finance Corp',
            'password' => bcrypt('secret123')
        ]);
        
        // Mock PDF generation to prevent actual PDF rendering overhead during tests
        $mockPdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('loadView')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf_content');
        Pdf::swap($mockPdf);
        
        // Mock InvoiceService so we don't accidentally write raw files
        $this->mock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('storePdf')->andReturn(true);
        });
    }

    #[Test]
    public function it_renders_the_accounting_invoice_component()
    {
        Livewire::test(AccountingInvoice::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.shop.accounting.accounting-invoice');
    }

    #[Test]
    public function it_calculates_due_dates_dynamically()
    {
        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.invoice_date', '2026-04-01')
            ->set('manualInvoice.due_days', 30)
            ->assertSet('manualInvoice.due_date', '2026-05-01');
    }

    #[Test]
    public function it_adds_and_removes_invoice_items()
    {
        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate') // Initializes with 1 item
            ->assertCount('manualInvoice.items', 1)
            ->call('addItem')
            ->assertCount('manualInvoice.items', 2)
            ->call('removeItem', 0)
            ->assertCount('manualInvoice.items', 1);
    }
    
    #[Test]
    public function it_saves_a_manual_invoice_as_draft()
    {
        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.invoice_number', 'TEST-DRAFT-001')
            ->call('saveManualInvoice', 'draft')
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('accounting_invoices', [
            'invoice_number' => 'TEST-DRAFT-001',
            'status' => 'draft'
        ]);
    }
    
    #[Test]
    public function it_validates_required_fields_before_saving_paid_invoice()
    {
        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->call('saveManualInvoice', 'paid')
            ->assertHasErrors(['manualInvoice.customer_email', 'manualInvoice.last_name', 'manualInvoice.address']);
    }
    
    #[Test]
    public function it_saves_and_calculates_a_full_paid_invoice()
    {
        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.invoice_number', 'TEST-PAID-001')
            ->set('manualInvoice.customer_email', 'john@example.com')
            ->set('manualInvoice.first_name', 'John')
            ->set('manualInvoice.last_name', 'Doe')
            ->set('manualInvoice.address', 'Wallstreet 1')
            ->set('manualInvoice.postal_code', '10000')
            ->set('manualInvoice.city', 'New York')
            ->set('manualInvoice.invoice_date', '2026-04-01')
            ->set('manualInvoice.delivery_date', '2026-04-02')
            ->set('manualInvoice.items', [
                [
                    'product_name' => 'Consulting',
                    'quantity' => 2,
                    'unit_price' => 100, // This is NET price input. Tax 19%
                    'tax_rate' => 19
                ]
            ])
            ->call('saveManualInvoice', 'paid')
            ->assertDispatched('notify');
            
        $invoice = InvoiceModel::where('invoice_number', 'TEST-PAID-001')->first();
        $this->assertNotNull($invoice);
        
        // 2 qty * 100 Net = 200 Net
        // Tax = 200 * 0.19 = 38
        // Total Gross = 238
        // In DB, amounts are in cents: 23800
        $this->assertEquals(23800, $invoice->total);
        $this->assertEquals(3800, $invoice->tax_amount);
        $this->assertEquals('paid', $invoice->status);
    }
    
    #[Test]
    public function it_cancels_an_invoice_and_creates_cancellation_document()
    {
        $invoice = InvoiceModel::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'invoice_number' => 'INV-TO-CANCEL',
            'type' => 'invoice',
            'status' => 'paid',
            'total' => 10000,
            'subtotal' => 10000,
            'tax_amount' => 0,
            'invoice_date' => now(),
            'delivery_date' => now(),
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'company' => '',
                'address' => 'Fake St 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'john@test.com'
            ],
            'custom_items' => []
        ]);
        
        Livewire::test(AccountingInvoice::class)
            ->call('cancelInvoice', $invoice->id)
            ->assertHasNoErrors();
            
        $this->assertDatabaseHas('accounting_invoices', [
            'invoice_number' => 'INV-TO-CANCEL',
            'status' => 'cancelled'
        ]);
        
        $this->assertDatabaseHas('accounting_invoices', [
            'invoice_number' => 'ST-INV-TO-CANCEL',
            'type' => 'cancellation',
            'total' => -10000
        ]);
    }
}
