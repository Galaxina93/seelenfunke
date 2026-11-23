<?php

namespace Tests\Feature\Management;

use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailAttachment;
use App\Models\Management\Mail\MailMessage;
use App\Services\Management\MailAiSortService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MailKiTrichterInvoiceReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private array $cleanupPaths = [];

    protected function tearDown(): void
    {
        if (!empty($this->cleanupPaths)) {
            Storage::disk('local')->delete(array_values(array_unique(array_filter($this->cleanupPaths))));
        }
        parent::tearDown();
    }

    private function resolveAdmin(): Admin
    {
        $existing = Admin::first();
        if ($existing) {
            return $existing;
        }

        return Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-kitrichter-test@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    private function createMailbox(bool $isCommercial = true): MailAccount
    {
        return MailAccount::create([
            'name' => 'KI-Trichter Testpostfach',
            'email' => 'kitrichter-' . uniqid() . '@mein-seelenfunke.de',
            'password' => 'secret',
            'imap_host' => 'imap.example.test',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_commercial' => $isCommercial,
        ]);
    }

    private function buildCiiXml(string $sellerName, string $invoiceNo, string $issueDateYmd, string $grossAmount, string $note = ''): string
    {
        $escSeller = htmlspecialchars($sellerName, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $escInv = htmlspecialchars($invoiceNo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $escNote = htmlspecialchars($note, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $noteXml = $escNote !== '' ? "<ram:IncludedNote><ram:Content>{$escNote}</ram:Content></ram:IncludedNote>" : '';
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
                          xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
                          xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100">
  <rsm:ExchangedDocument>
    <ram:ID>{$escInv}</ram:ID>
    <ram:TypeCode>380</ram:TypeCode>
    <ram:IssueDateTime><udt:DateTimeString format="102">{$issueDateYmd}</udt:DateTimeString></ram:IssueDateTime>
    {$noteXml}
  </rsm:ExchangedDocument>
  <rsm:SupplyChainTradeTransaction>
    <ram:ApplicableHeaderTradeAgreement>
      <ram:SellerTradeParty>
        <ram:Name>{$escSeller}</ram:Name>
        <ram:PostalTradeAddress><ram:CityName>Lemgo</ram:CityName><ram:CountryID>DE</ram:CountryID></ram:PostalTradeAddress>
      </ram:SellerTradeParty>
      <ram:BuyerTradeParty><ram:Name>Alina Steinhauer</ram:Name></ram:BuyerTradeParty>
    </ram:ApplicableHeaderTradeAgreement>
    <ram:ApplicableHeaderTradeDelivery/>
    <ram:ApplicableHeaderTradeSettlement>
      <ram:InvoiceCurrencyCode>EUR</ram:InvoiceCurrencyCode>
      <ram:ApplicableTradeTax>
        <ram:CalculatedAmount>1.59</ram:CalculatedAmount>
        <ram:TypeCode>VAT</ram:TypeCode>
        <ram:BasisAmount>8.40</ram:BasisAmount>
        <ram:CategoryCode>S</ram:CategoryCode>
        <ram:RateApplicablePercent>19.00</ram:RateApplicablePercent>
      </ram:ApplicableTradeTax>
      <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        <ram:GrandTotalAmount>{$grossAmount}</ram:GrandTotalAmount>
        <ram:DuePayableAmount>{$grossAmount}</ram:DuePayableAmount>
      </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
    </ram:ApplicableHeaderTradeSettlement>
  </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;
    }

    public function test_ki_trichter_backfills_missing_invoice_onto_existing_special_issue_and_archives_mail(): void
    {
        $admin = $this->resolveAdmin();
        $this->actingAs($admin, 'admin');
        $mailbox = $this->createMailbox(false);

        $orderPreviewRel = 'buchhaltung/receipts/2026/11/test_order_preview_' . uniqid() . '.pdf';
        Storage::disk('local')->put($orderPreviewRel, '%PDF-1.4 Order Preview 306-9991111-2223333');
        $this->cleanupPaths[] = $orderPreviewRel;

        $existingIssue = AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => '100 Stück Frau WUNDERVoll Papierflachbeutel Schwarz',
            'location' => 'Amazon (frauwundervoll)',
            'category' => 'Verpackungen',
            'amount' => -9.99,
            'execution_date' => '2026-11-20',
            'is_business' => true,
            'tax_rate' => 19,
            'invoice_number' => '306-9991111-2223333',
            'file_paths' => [$orderPreviewRel],
            'note' => 'Bestellnummer: 306-9991111-2223333',
        ]);

        $invNo = 'R99' . random_int(1000, 9999);
        $xml = $this->buildCiiXml('Frau Wundervoll GmbH', $invNo, '20261123', '9.99', 'Amazon Order 306-9991111-2223333');
        $attRel = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Rechnung_' . $invNo . '.xml';
        Storage::disk('local')->put($attRel, $xml);
        $this->cleanupPaths[] = $attRel;

        $msg = MailMessage::create([
            'mail_account_id' => $mailbox->id,
            'message_id' => 'msg_reconcile_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => '[Wichtig] Rechnung von Frau Wundervoll GmbH',
            'from_name' => 'no-reply',
            'from_email' => 'no-reply@amazon.de',
            'to_email' => $mailbox->email,
            'body_plain' => 'Ihre Rechnung zur Bestellung 306-9991111-2223333.',
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $att = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'Rechnung_' . $invNo . '.xml',
            'content_type' => 'application/xml',
            'size' => strlen($xml),
            'path' => $attRel,
        ]);

        $countBefore = AccountingSpecialIssue::count();

        $result = app(MailAiSortService::class)->sortAllMailsAndSubfolders();

        $this->assertTrue($result['success']);
        $this->assertSame($countBefore, AccountingSpecialIssue::count(), 'Must reconcile onto existing Sonderausgabe without creating a duplicate.');

        $refreshedIssue = $existingIssue->fresh();
        $this->assertCount(2, $refreshedIssue->file_paths);
        $this->assertStringContainsString($invNo, (string) $refreshedIssue->invoice_number);
        $this->assertStringContainsString('306-9991111-2223333', (string) $refreshedIssue->invoice_number);

        foreach ($refreshedIssue->file_paths as $fp) {
            $this->cleanupPaths[] = $fp;
            $this->assertTrue(Storage::disk('local')->exists($fp));
        }

        $this->assertSame('booked', $att->fresh()->funnel_status);
        $this->assertSame((string) $existingIssue->id, (string) $att->fresh()->accounting_issue_id);

        $refreshedMsg = $msg->fresh();
        $this->assertSame('Archive', $refreshedMsg->folder);
        $this->assertSame((string) $existingIssue->id, (string) ($refreshedMsg->tags['ai_receipt_booked_id'] ?? ''));
    }

    public function test_ki_trichter_reconciles_recurring_fix_cost_invoice_without_duplicate_special_issue(): void
    {
        $admin = $this->resolveAdmin();
        $this->actingAs($admin, 'admin');
        $mailbox = $this->createMailbox(true);

        $group = AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Vertrag & Lizenz Test',
            'type' => 'expense',
            'position' => 99,
        ]);

        $fixItem = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Hosting Test Dedicated Cluster',
            'amount' => -63.49,
            'interval_months' => 1,
            'first_payment_date' => '2026-04-15',
            'is_business' => true,
            'tax_rate' => 19,
            'provider_company' => 'ClusterHost Cloud GmbH',
        ]);

        $invNo = 'RG99' . random_int(10000, 99999);
        $xml = $this->buildCiiXml('ClusterHost Cloud GmbH', $invNo, '20261103', '63.49');
        $attRel = 'leitung/crm/mail-attachments/test_' . uniqid() . '_' . $invNo . '.xml';
        Storage::disk('local')->put($attRel, $xml);
        $this->cleanupPaths[] = $attRel;

        $msg = MailMessage::create([
            'mail_account_id' => $mailbox->id,
            'message_id' => 'msg_fixcost_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Deine Rechnung Nr. ' . $invNo,
            'from_name' => 'ClusterHost',
            'from_email' => 'noreply@clusterhost-cloud.test',
            'to_email' => $mailbox->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $att = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => $invNo . '.xml',
            'content_type' => 'application/xml',
            'size' => strlen($xml),
            'path' => $attRel,
        ]);

        $specialCountBefore = AccountingSpecialIssue::count();

        app(MailAiSortService::class)->sortAllMailsAndSubfolders();

        $this->assertSame($specialCountBefore, AccountingSpecialIssue::count(), 'Recurring Fixkosten invoice must not create a duplicate Sonderausgabe.');
        $this->assertSame('already_booked', $att->fresh()->funnel_status);
        $this->assertSame((string) $fixItem->id, (string) ($att->fresh()->funnel_meta['fix_cost_id'] ?? ''));

        $storedFixReceipt = $att->fresh()->funnel_meta['receipt_path'] ?? null;
        if ($storedFixReceipt) {
            $this->cleanupPaths[] = $storedFixReceipt;
            $this->assertTrue(Storage::disk('local')->exists($storedFixReceipt));
        }

        $this->assertSame('Archive', $msg->fresh()->folder);
        $this->assertSame('fixcost:' . $fixItem->id, (string) ($msg->fresh()->tags['ai_receipt_booked_id'] ?? ''));
    }

    public function test_ki_trichter_keeps_unreadable_supplier_invoice_in_processed_verarbeitet(): void
    {
        $admin = $this->resolveAdmin();
        $this->actingAs($admin, 'admin');
        $mailbox = $this->createMailbox(true);

        $attRel = 'leitung/crm/mail-attachments/test_' . uniqid() . '_Rechnung_Unlesbar.pdf';
        Storage::disk('local')->put($attRel, '%PDF-1.4 empty broken binary');
        $this->cleanupPaths[] = $attRel;

        $msg = MailMessage::create([
            'mail_account_id' => $mailbox->id,
            'message_id' => 'msg_unreadable_' . uniqid(),
            'folder' => 'INBOX',
            'subject' => 'Ihre Rechnung 999888777',
            'from_name' => 'Lieferant Unbekannt',
            'from_email' => 'rechnung@unbekannter-lieferant.test',
            'to_email' => $mailbox->email,
            'has_attachments' => true,
            'received_at' => now(),
        ]);

        $att = MailAttachment::create([
            'mail_message_id' => $msg->id,
            'filename' => 'Rechnung_Unlesbar.pdf',
            'content_type' => 'application/pdf',
            'size' => 28,
            'path' => $attRel,
        ]);

        app(MailAiSortService::class)->sortAllMailsAndSubfolders();

        $this->assertSame('needs_review', $att->fresh()->funnel_status);
        $this->assertSame('Processed', $msg->fresh()->folder);
        $this->assertSame('high', $msg->fresh()->priority);
        $this->assertSame('Rechnung & Buchhaltung', $msg->fresh()->category);
    }
}
