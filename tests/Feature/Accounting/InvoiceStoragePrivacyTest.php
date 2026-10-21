<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class InvoiceStoragePrivacyTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function invoices_are_stored_on_private_local_disk_and_never_on_public_disk()
    {
        Storage::fake('local');
        Storage::fake('public');

        $customer = Customer::factory()->create();

        $invoice = AccountingInvoice::factory()->create([
            'customer_id' => $customer->id,
            'invoice_number' => 'RE-PRIVACY-TEST-001',
            'type' => 'invoice',
            'status' => 'open',
            'is_e_invoice' => false,
        ]);

        $serviceMock = Mockery::mock(InvoiceService::class)->makePartial();
        $pdfMock = Mockery::mock();
        $pdfMock->shouldReceive('output')->andReturn('%PDF-1.4 Mock PDF Content');
        $serviceMock->shouldReceive('generatePdf')->with($invoice)->andReturn($pdfMock);

        $path = $serviceMock->storePdf($invoice);

        $this->assertEquals("buchhaltung/invoices/{$invoice->invoice_number}.pdf", $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
