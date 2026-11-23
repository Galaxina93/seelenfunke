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

    public function test_generate_invoice_number_respects_soft_deleted_records_and_starts_at_1000_after_prelive_purge(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $year = date('Y');

        $this->assertSame("RE-{$year}-1000", $this->invoiceService->generateInvoiceNumber('RE'));

        $customer = Customer::factory()->create();
        $inv = AccountingInvoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => "RE-{$year}-1000",
            'type' => 'invoice',
            'status' => 'draft',
            'invoice_date' => now(),
            'due_date' => now()->addDays(7),
            'subtotal' => 4200,
            'tax_amount' => 798,
            'total' => 4998,
            'billing_address' => [],
            'shipping_address' => [],
        ]);
        $inv->delete(); // SoftDelete

        // Even when soft-deleted, UNIQUE constraint requires advancing to 1001
        $this->assertSame("RE-{$year}-1001", $this->invoiceService->generateInvoiceNumber('RE'));

        // Seed real expense & contract + fake archive files + xTool calibration to prove selective purge
        $admin = \App\Models\Admin\Admin::factory()->create();
        $group = \App\Models\Accounting\AccountingGroup::create([
            'admin_id' => $admin->id,
            'name' => 'Betriebsausgaben',
            'position' => 1,
        ]);
        \App\Models\Accounting\AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Serververtrag',
            'amount' => -2900,
            'interval_months' => 1,
            'first_payment_date' => now()->startOfMonth(),
            'is_business' => true,
        ]);
        \App\Models\Accounting\AccountingSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Echter Rohling-Einkauf',
            'amount' => -15000,
            'execution_date' => now(),
            'is_business' => true,
        ]);

        \Illuminate\Support\Facades\Storage::disk('local')->put("buchhaltung/invoices/RE-{$year}-1000.pdf", 'dummy');
        \Illuminate\Support\Facades\Storage::disk('local')->put('buchhaltung/receipts/echter-beleg.pdf', 'keep');
        \Illuminate\Support\Facades\Storage::disk('local')->put('buchhaltung/incoming_einvoices/RE2613298.xml', '<xml/>');
        \Illuminate\Support\Facades\Storage::disk('public')->put('bestellungen/xtool/fl_calib_uv.json', '{}');
        \Illuminate\Support\Facades\Storage::disk('public')->put('bestellungen/xtool/previews/test_preview.png', 'tmp');

        $order = OrderOrder::create([
            'order_number' => "ORD-{$year}-TEST01",
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
            'billing_address' => [],
            'shipping_address' => [],
        ]);
        $this->assertNotNull($order);

        $summary = app(\App\Services\Accounting\PreLiveOrderAndInvoiceCleanupService::class)->purge();

        $this->assertSame("RE-{$year}-1000", $summary['next_invoice_number']);
        $this->assertSame(0, OrderOrder::withTrashed()->count());
        $this->assertSame(0, AccountingInvoice::withTrashed()->count());
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('local')->exists("buchhaltung/invoices/RE-{$year}-1000.pdf"));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists('buchhaltung/receipts/echter-beleg.pdf'));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists('buchhaltung/incoming_einvoices/RE2613298.xml'));
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists('bestellungen/xtool/fl_calib_uv.json'));
        $this->assertFalse(\Illuminate\Support\Facades\Storage::disk('public')->exists('bestellungen/xtool/previews/test_preview.png'));
        $this->assertSame(1, \App\Models\Accounting\AccountingSpecialIssue::count());
        $this->assertSame(1, \App\Models\Accounting\AccountingCostItem::count());
    }

    public function test_prelive_cleanup_service_blocks_deletion_when_production_shop_is_live(): void
    {
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('GoBD-Schutz aktiv');
            app(\App\Services\Accounting\PreLiveOrderAndInvoiceCleanupService::class)->purge();
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
