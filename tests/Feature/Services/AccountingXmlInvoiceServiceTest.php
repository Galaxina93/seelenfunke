<?php

namespace Tests\Feature\Services;

use App\Models\Accounting\AccountingInvoice;
use App\Services\AccountingXmlInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use DOMDocument;

class AccountingXmlInvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_invoice_generation_complies_with_zugferd_2_4_d22b()
    {
        Storage::fake('local');

        $invoice = AccountingInvoice::factory()->create([
            'invoice_number' => 'RE-2026-001',
            'custom_items' => [
                [
                    'product_name' => 'Test Produkt',
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'tax_rate' => 19.0,
                ]
            ],
            'shipping_cost' => 500,
            'tax_amount' => 1995,
            'total' => 12495,
        ]);

        $service = new AccountingXmlInvoiceService();
        $filename = $service->generate($invoice);

        $this->assertStringContainsString('RE-2026-001.xml', $filename);
        $this->assertTrue(Storage::disk('local')->exists($filename));

        // XML validieren
        $xmlContent = Storage::disk('local')->get($filename);
        $dom = new DOMDocument();
        $dom->loadXML($xmlContent);

        // ZUGFeRD 2.4 (D22B) Root Name & Namespace Check
        $this->assertEquals('rsm:CrossIndustryInvoice', $dom->documentElement->nodeName);
        $this->assertEquals('urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100', $dom->documentElement->getAttribute('xmlns:rsm'));

        // EN16931 Profil Guideline Check (FACTUR-X/ZUGFeRD 2.4 compliant)
        $guidelineNodes = $dom->getElementsByTagName('ID');
        $foundGuideline = false;
        foreach ($guidelineNodes as $node) {
            if ($node->nodeValue === 'urn:cen.eu:en16931:2017') {
                $foundGuideline = true;
                break;
            }
        }
        $this->assertTrue($foundGuideline, 'EN16931 Guideline ID is missing. The generated XML might not be a valid FACTUR-X/ZUGFeRD 2.4 file.');
    }
}
