<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AccountingInvoice;
use App\Services\AccountingXmlInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingXmlInvoiceServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_generation_sanitizes_filename_and_encodes_special_chars()
    {
        Storage::fake('local');

        $invoice = AccountingInvoice::create([
            'invoice_number' => 'RE/2026/001..traversal',
            'invoice_date' => now(),
            'delivery_date' => now(),
            'status' => 'issued',
            'subtotal' => 2000,
            'tax_amount' => 380,
            'shipping_cost' => 490,
            'total' => 2870,
            'billing_address' => [
                'company' => 'Müller & Söhne <GmbH>',
                'address' => 'Musterstr. 10',
                'postal_code' => '12345',
                'city' => 'Berlin',
                'country' => 'DE'
            ],
            'custom_items' => [
                [
                    'product_name' => 'Laser Gravur & Schieferstein',
                    'quantity' => 1,
                    'unit_price' => 2000,
                    'tax_rate' => 19.0,
                ]
            ],
            'is_small_business' => false,
        ]);

        $service = new AccountingXmlInvoiceService();
        $filename = $service->generate($invoice);

        // Filename slashes and dots replaced by underscore
        $this->assertEquals('buchhaltung/invoices/xml/RE_2026_001__traversal.xml', $filename);
        Storage::disk('local')->assertExists($filename);

        $xml = Storage::disk('local')->get($filename);
        // Valid XML without malformed tags
        $this->assertStringContainsString('Müller &amp; Söhne &lt;GmbH&gt;', $xml);
        $this->assertStringContainsString('Laser Gravur &amp; Schieferstein', $xml);
        $this->assertStringNotContainsString('&amp;amp;', $xml);
    }
}
