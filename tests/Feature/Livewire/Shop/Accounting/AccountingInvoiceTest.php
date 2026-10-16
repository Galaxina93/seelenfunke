<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Jobs\ProcessOrderDocumentsAndMails;
use App\Livewire\Shop\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvoice as InvoiceModel;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemLog;
use App\Services\AccountingXmlInvoiceService;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
        
        // Partial mock InvoiceService so real methods (getNextInvoiceNumber, createFromOrder) work
        $this->partialMock(InvoiceService::class, function ($mock) {
            $mock->shouldReceive('storePdf')->andReturn('buchhaltung/invoices/test.pdf');
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

    #[Test]
    public function it_generates_invoices_and_dispatches_mail_jobs_for_paid_orders_in_bulk_action()
    {
        Queue::fake([ProcessOrderDocumentsAndMails::class]);

        $order = OrderOrder::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'order_number' => 'ORD-1001',
            'customer_id' => $this->customer->id,
            'customer_name' => 'John Finance',
            'email' => 'john.finance@example.com',
            'payment_status' => 'paid',
            'status' => 'processing',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => [
                'first_name' => 'John',
                'last_name' => 'Finance',
                'address' => 'Teststr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'john.finance@example.com',
            ],
            'shipping_address' => [
                'first_name' => 'John',
                'last_name' => 'Finance',
                'address' => 'Teststr. 1',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
        ]);

        Livewire::test(AccountingInvoice::class)
            ->call('generateForPaidOrders')
            ->assertDispatched('notify')
            ->assertSee('1 Rechnungen wurden erfolgreich generiert und E-Mails vorbereitet.');

        $this->assertDatabaseHas('accounting_invoices', [
            'order_id' => $order->id,
            'type' => 'invoice',
            'status' => 'paid',
        ]);

        Queue::assertPushed(ProcessOrderDocumentsAndMails::class, function ($job) use ($order) {
            return $job->order->id === $order->id;
        });
    }

    #[Test]
    public function it_handles_bulk_action_when_no_paid_orders_need_invoices()
    {
        Queue::fake([ProcessOrderDocumentsAndMails::class]);

        Livewire::test(AccountingInvoice::class)
            ->call('generateForPaidOrders')
            ->assertDispatched('notify')
            ->assertSee('Keine offenen bezahlten Bestellungen ohne Rechnung gefunden.');

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_handles_exceptions_gracefully_during_bulk_action_and_logs_error()
    {
        Queue::fake([ProcessOrderDocumentsAndMails::class]);

        OrderOrder::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'order_number' => 'ORD-ERR-1',
            'customer_id' => $this->customer->id,
            'customer_name' => 'Corrupt Order',
            'email' => 'err@example.com',
            'payment_status' => 'paid',
            'status' => 'processing',
            'subtotal_price' => 5000,
            'tax_amount' => 950,
            'shipping_price' => 490,
            'total_price' => 5490,
            'billing_address' => [],
            'shipping_address' => [],
        ]);

        // Mock invoice service to throw an exception for this order
        $mockService = Mockery::mock(InvoiceService::class);
        $mockService->shouldReceive('createFromOrder')
            ->andThrow(new \RuntimeException('PDF Engine crashed'));

        Livewire::test(AccountingInvoice::class)
            ->call('generateForPaidOrders', $mockService)
            ->assertDispatched('notify')
            ->assertSee('0 Rechnungen generiert (1 fehlgeschlagen)');

        $this->assertDatabaseHas('system_logs', [
            'action_id' => 'admin_invoice_generation',
            'type' => 'error',
        ]);
    }

    #[Test]
    public function it_saves_a_manual_e_invoice_without_type_error()
    {
        $this->mock(AccountingXmlInvoiceService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('buchhaltung/invoices/xml/TEST-EINVOICE-001.xml');
        });

        Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.invoice_number', 'TEST-EINVOICE-001')
            ->set('manualInvoice.customer_email', 'einvoice@example.com')
            ->set('manualInvoice.first_name', 'Tech')
            ->set('manualInvoice.last_name', 'Corp')
            ->set('manualInvoice.company', 'Tech GmbH')
            ->set('manualInvoice.address', 'Silicon St. 42')
            ->set('manualInvoice.postal_code', '80331')
            ->set('manualInvoice.city', 'München')
            ->set('manualInvoice.invoice_date', '2026-04-01')
            ->set('manualInvoice.delivery_date', '2026-04-01')
            ->set('manualInvoice.is_e_invoice', true)
            ->set('manualInvoice.items', [
                [
                    'product_name' => 'Software Licence',
                    'quantity' => 1,
                    'unit_price' => 200,
                    'tax_rate' => 19,
                ]
            ])
            ->call('saveManualInvoice', 'paid')
            ->assertDispatched('notify');

        $this->assertDatabaseHas('accounting_invoices', [
            'invoice_number' => 'TEST-EINVOICE-001',
            'is_e_invoice' => 1,
            'status' => 'paid',
        ]);
    }

    #[Test]
    public function it_calculates_correct_totals_preview_on_netto_basis()
    {
        $component = Livewire::test(AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.items', [
                [
                    'product_name' => 'Service Netto',
                    'quantity' => 2,
                    'unit_price' => 100, // 200 Netto
                    'tax_rate' => 19,    // 38 Tax
                ]
            ])
            ->set('manualInvoice.shipping_cost', 10); // 10 Netto + 1.90 Tax = 11.90

        $totals = $component->viewData('totalsPreview');

        // Netto = 200 + 10 = 210
        $this->assertEquals(210, $totals['net']);
        // Tax = 38 + 1.90 = 39.90
        $this->assertEquals(39.9, $totals['tax']);
        // Gross = 210 + 39.9 = 249.9
        $this->assertEquals(249.9, $totals['gross']);
    }

    #[Test]
    public function it_sanitizes_filename_when_downloading_archived_pdf()
    {
        Storage::fake('local');
        Storage::disk('local')->put('buchhaltung/invoices/test_file.pdf', 'pdf content');

        Livewire::test(AccountingInvoice::class)
            ->call('downloadPdfByFilename', '../../test_file.pdf')
            ->assertFileDownloaded('test_file.pdf');
    }
}
