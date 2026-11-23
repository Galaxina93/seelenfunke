<?php

namespace Tests\Feature\Management;

use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailAttachment;
use App\Models\Management\Mail\MailMessage;
use App\Services\Management\MailAttachmentFunnelService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailAttachmentFunnelTest extends TestCase
{
    use DatabaseTransactions;

    private function createTestAdmin(): Admin
    {
        $existing = Admin::first();
        if ($existing) {
            return $existing;
        }

        return Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-funnel-test@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    private function createTestMailAccount(string $name, string $email, bool $isCommercial): MailAccount
    {
        return MailAccount::create([
            'name' => $name,
            'email' => $email,
            'password' => 'test-secret',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_commercial' => $isCommercial,
        ]);
    }

    private function buildSampleCiiXml(string $sellerName, string $invoiceNo, string $issueDateYmd, string $grossAmount): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
                          xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
                          xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocument>
    <ram:ID>{$invoiceNo}</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime>
      <udt:DateTimeString format="102">{$issueDateYmd}</udt:DateTimeString>
    </ram:IssueDateTime>
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:IncludedSupplyChainTradeLineItem>
      <ram:AssociatedDocumentLineDocument><ram:LineID>1</ram:LineID></ram:AssociatedDocumentLineDocument>
      <ram:SpecifiedTradeProduct><ram:Name>Schmuckboxen Schwarz Matt</ram:Name></ram:SpecifiedTradeProduct>
      <ram:SpecifiedLineTradeAgreement>
        <ram:NetPriceProductTradePrice><ram:ChargeAmount>100.00</ram:ChargeAmount></ram:NetPriceProductTradePrice>
      </ram:SpecifiedLineTradeAgreement>
      <ram:SpecifiedLineTradeDelivery><ram:BilledQuantity unitCode="C62">1</ram:BilledQuantity></ram:SpecifiedLineTradeDelivery>
      <ram:SpecifiedLineTradeSettlement>
        <ram:ApplicableTradeTax><ram:TypeCode>VAT</ram:TypeCode><ram:CategoryCode>S</ram:CategoryCode><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
        <ram:SpecifiedTradeSettlementLineMonetarySummation><ram:LineTotalAmount>100.00</ram:LineTotalAmount></ram:SpecifiedTradeSettlementLineMonetarySummation>
      </ram:SpecifiedLineTradeSettlement>
    </ram:IncludedSupplyChainTradeLineItem>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>{$sellerName}</ram:Name>
        <ram:PostalTradeAddress><ram:CityName>Aarhus</ram:CityName><ram:CountryID>DK</ram:CountryID></ram:PostalTradeAddress>
      </ram:SellerTradeParty>
      <ram:BuyerTradeParty>
        <ram:Name>Alina Steinhauer</ram:Name>
      </ram:BuyerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery/>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
      <ram:ApplicableTradeTax>
        <ram:CalculatedAmount>19.00</ram:CalculatedAmount>
        <ram:TypeCode>VAT</ram:TypeCode>
        <ram:BasisAmount>100.00</ram:BasisAmount>
        <ram:CategoryCode>S</ram:CategoryCode>
        <ram:RateApplicablePercent>19.00</ram:RateApplicablePercent>
      </ram:ApplicableTradeTax>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:LineTotalAmount>100.00</ram:LineTotalAmount>
        <ram:TaxBasisTotalAmount>100.00</ram:TaxBasisTotalAmount>
        <ram:TaxTotalAmount currencyID="EUR">19.00</ram:TaxTotalAmount>
        <ram:GrandTotalAmount>{$grossAmount}</ram:GrandTotalAmount>
        <ram:DuePayableAmount>{$grossAmount}</ram:DuePayableAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;
    }

    public function test_ignores_inline_signatures_legal_boilerplate_and_sent_mails(): void
    {
        $this->createTestAdmin();
        $service = app(MailAttachmentFunnelService::class);

        $acc = $this->createTestMailAccount('Gewerblich Test', 'shop-test@mein-seelenfunke.de', true);

        $msg = MailMessage::create([
            'mail_account_id' => $acc->id,
            'message_id' => 'msg_test_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Ihre Bestellung und Rechnung',
            'from_name' => 'Lieferant GmbH',
            'from_email' => 'rechnung@lieferant.de',
            'to_email' => $acc->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $relSig = 'leitung/crm/mail-attachments/test_' . uniqid() . '_image001.png';
        Storage::disk('local')->put($relSig, str_repeat('x', 2048));
        $attSig = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'image001.png',
            'content_type' => 'image/png',
            'size' => 2048,
            'path' => $relSig,
            'content_id' => 'cid_logo_1',
        ]);

        $relAgb = 'leitung/crm/mail-attachments/test_' . uniqid() . '_AGB_und_Widerrufsbelehrung.pdf';
        Storage::disk('local')->put($relAgb, '%PDF-1.4 AGB');
        $attAgb = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'AGB_und_Widerrufsbelehrung.pdf',
            'content_type' => 'application/pdf',
            'size' => 18000,
            'path' => $relAgb,
        ]);

        $resSig = $service->handleIncomingAttachment($attSig, $msg, $acc);
        $resAgb = $service->handleIncomingAttachment($attAgb, $msg, $acc);

        $this->assertSame('inline_signature', $attSig->fresh()->funnel_type);
        $this->assertSame('ignored', $resSig['status']);
        $this->assertSame('legal_notice', $attAgb->fresh()->funnel_type);
        $this->assertSame('ignored', $resAgb['status']);

        Storage::disk('local')->delete([$relSig, $relAgb]);
    }

    public function test_auto_books_incoming_einvoice_deduplicates_and_separates_commercial_vs_private(): void
    {
        $admin = $this->createTestAdmin();
        $this->actingAs($admin, 'admin');
        $service = app(MailAttachmentFunnelService::class);

        $commAcc = $this->createTestMailAccount('Mein-Seelenfunke', 'buchhaltung-test@mein-seelenfunke.de', true);
        $privAcc = $this->createTestMailAccount('Alina Privat', 'alina-privat-test@t-online.de', false);

        $invNo1 = 'INV-TEST-' . strtoupper(Str::random(6));
        $xml1 = $this->buildSampleCiiXml('Westpack A/S', $invNo1, '20261122', '119.00');
        $relXml1 = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Rechnung_' . $invNo1 . '.xml';
        Storage::disk('local')->put($relXml1, $xml1);

        $msgComm = MailMessage::create([
            'mail_account_id' => $commAcc->id,
            'message_id' => 'msg_comm_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Ihre Rechnung ' . $invNo1,
            'from_name' => 'Westpack',
            'from_email' => 'billing@westpack.com',
            'to_email' => $commAcc->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $attComm = MailAttachment::create([
            'mail_message_id' => $msgComm->id,
            'filename' => 'Rechnung_' . $invNo1 . '.xml',
            'content_type' => 'application/xml',
            'size' => strlen($xml1),
            'path' => $relXml1,
        ]);

        $beforeCount = AccountingSpecialIssue::count();
        $res1 = $service->handleIncomingAttachment($attComm, $msgComm, $commAcc);

        $this->assertSame('booked', $res1['status']);
        $this->assertSame($beforeCount + 1, AccountingSpecialIssue::count());

        $bookedIssue = AccountingSpecialIssue::findOrFail($attComm->fresh()->accounting_issue_id);
        $this->assertTrue($bookedIssue->is_business);
        $this->assertEquals(-119.00, (float) $bookedIssue->amount);
        $this->assertSame($invNo1, $bookedIssue->invoice_number);
        $storedReceiptRel = $bookedIssue->file_paths[0] ?? null;
        $this->assertNotEmpty($storedReceiptRel);
        $this->assertTrue(Storage::disk('local')->exists($storedReceiptRel));

        // Duplicate send of identical invoice -> blocked by 3-stage duplicate guard
        $relXmlDup = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Kopie_' . $invNo1 . '.xml';
        Storage::disk('local')->put($relXmlDup, $xml1);
        $attDup = MailAttachment::create([
            'mail_message_id' => $msgComm->id,
            'filename' => 'Rechnung_Kopie_' . $invNo1 . '.xml',
            'content_type' => 'application/xml',
            'size' => strlen($xml1),
            'path' => $relXmlDup,
        ]);

        $resDup = $service->handleIncomingAttachment($attDup, $msgComm, $commAcc);
        $this->assertSame('already_booked', $resDup['status']);
        $this->assertSame($beforeCount + 1, AccountingSpecialIssue::count());

        // Private mailbox invoice without Seelenfunke supplier -> booked as Privat (is_business = false)
        $invNoPriv = 'PRIV-' . strtoupper(Str::random(6));
        $xmlPriv = $this->buildSampleCiiXml('Stadtwerke Energie GmbH', $invNoPriv, '20261121', '89.50');
        $relXmlPriv = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Rechnung_' . $invNoPriv . '.xml';
        Storage::disk('local')->put($relXmlPriv, $xmlPriv);

        $msgPriv = MailMessage::create([
            'mail_account_id' => $privAcc->id,
            'message_id' => 'msg_priv_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Ihre Jahresabrechnung ' . $invNoPriv,
            'from_name' => 'Stadtwerke',
            'from_email' => 'service@stadtwerke-beispiel.de',
            'to_email' => $privAcc->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $attPriv = MailAttachment::create([
            'mail_message_id' => $msgPriv->id,
            'filename' => 'Abrechnung_' . $invNoPriv . '.xml',
            'content_type' => 'application/xml',
            'size' => strlen($xmlPriv),
            'path' => $relXmlPriv,
        ]);

        $resPriv = $service->handleIncomingAttachment($attPriv, $msgPriv, $privAcc);
        $this->assertSame('booked', $resPriv['status']);
        $privIssue = AccountingSpecialIssue::findOrFail($attPriv->fresh()->accounting_issue_id);
        $this->assertFalse($privIssue->is_business);
        $this->assertNull($privIssue->tax_rate);

        // Cleanup stored test files
        $cleanup = array_filter(array_merge([$relXml1, $relXmlDup, $relXmlPriv, $storedReceiptRel], $privIssue->file_paths ?? []));
        Storage::disk('local')->delete($cleanup);
    }

    public function test_routes_document_into_dateitrichter_on_demand(): void
    {
        $admin = $this->createTestAdmin();
        $this->actingAs($admin, 'admin');
        $service = app(MailAttachmentFunnelService::class);

        $acc = $this->createTestMailAccount('Gewerblich Trichter', 'trichter-test@mein-seelenfunke.de', true);

        $msg = MailMessage::create([
            'mail_account_id' => $acc->id,
            'message_id' => 'msg_doc_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Versicherungsschein Betriebshaftpflicht',
            'from_name' => 'Versicherung AG',
            'from_email' => 'vertrag@versicherung.de',
            'to_email' => $acc->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $relTxt = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Versicherungsschein_2026.txt';
        Storage::disk('local')->put($relTxt, "Betriebshaftpflicht Police Nr. 998877 fuer Mein-Seelenfunke Gifhorn.");

        $att = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'Versicherungsschein_2026.txt',
            'content_type' => 'text/plain',
            'size' => 72,
            'path' => $relTxt,
        ]);

        $res = $service->sendToDateitrichter($att);
        $this->assertTrue($res['success']);
        $this->assertSame('workspace', $att->fresh()->funnel_status);

        $wsPath = $att->fresh()->funnel_meta['workspace_path'] ?? null;
        if ($wsPath) {
            Storage::disk('workspace')->delete($wsPath);
        }
        Storage::disk('local')->delete($relTxt);
    }
}
