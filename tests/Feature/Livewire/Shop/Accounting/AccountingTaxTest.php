<?php

namespace Tests\Feature\Livewire\Shop\Accounting;

use App\Livewire\Shop\Accounting\AccountingTax;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use App\Services\Tax\TaxSettingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingTaxTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Admin
        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-' . uniqid() . '@seelenfunke.test',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->admin = Admin::find($adminId);
        $this->actingAs($this->admin, 'admin');

        // 2. Create Customer
        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => bcrypt('password123'),
        ]);

        // 3. Set shop settings
        SystemSetting::updateOrCreate(['key' => 'owner_tax_id'], ['value' => '9111081508152']);
        SystemSetting::updateOrCreate(['key' => 'owner_proprietor'], ['value' => 'Alina Steinhauer']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);

        // 4. Mock PDF generation to prevent DomPDF disk writing in tests
        $mockPdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $mockPdf->shouldReceive('loadView')->andReturnSelf();
        $mockPdf->shouldReceive('save')->andReturnSelf();
        $mockPdf->shouldReceive('output')->andReturn('pdf_content');
        Pdf::swap($mockPdf);

        // 5. Fake HTTP for ELSTER server check
        Http::fake([
            'https://www.elster.de/elsterweb/serverstatus_rss.xml' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?><rss><channel><item><title>Anmeldungssteuern (authentifiziert)</title><description>OK</description></item></channel></rss>',
                200
            ),
        ]);
    }

    #[Test]
    public function it_renders_the_accounting_tax_component_successfully()
    {
        Livewire::test(AccountingTax::class)
            ->assertStatus(200)
            ->assertViewIs('livewire.shop.accounting.accounting-tax')
            ->assertSee('Umsatzsteuer-Zentrale')
            ->assertSee('Schritt 1')
            ->assertSee('Ablauf-Guide')
            ->assertSee('Steuer-1x1');
    }

    #[Test]
    public function it_navigates_between_steps_in_wizard()
    {
        Livewire::test(AccountingTax::class)
            ->assertSet('activeStep', 1)
            ->call('setStep', 2)
            ->assertSet('activeStep', 2)
            ->assertSee('Meldungs-Art')
            ->assertSee('Authentifizierung')
            ->call('setStep', 3)
            ->assertSet('activeStep', 3)
            ->assertSee('DATEV EXTF Report (ZIP)')
            ->assertSee('Formular-Vorschau (XML)')
            ->assertSee('An ELSTER übermitteln');
    }

    #[Test]
    public function it_switches_selected_month_and_updates_data()
    {
        $component = Livewire::test(AccountingTax::class)
            ->call('selectMonth', 3)
            ->assertSet('selectedMonth', 3);

        $monthData = $component->instance()->getMonthData(3);
        $this->assertEquals('03', $monthData['month_number']);
    }

    #[Test]
    public function it_correctly_calculates_revenue_and_tax_from_paid_orders()
    {
        $testYear = 2026;
        $testMonth = 5;

        // Paid order: 119.00 € gross, 19.00 € tax
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-001',
            'customer_id' => $this->customer->id,
            'email' => 'max@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 10000,
            'total_price' => 11900,
            'tax_amount' => 1900,
            'currency' => 'EUR',
        ]);
        DB::table('order_orders')->where('id', $order->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 15, 12, 0, 0),
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth);

        $data = $component->instance()->getMonthData($testMonth);

        $this->assertEquals(119.00, $data['revenue_gross']);
        $this->assertEquals(100.00, $data['revenue_net']);
        $this->assertEquals(19.00, $data['vat_collected']);
        $this->assertEquals(19.00, $data['total_tax']);
        $this->assertEquals(1, $data['order_count']);
    }

    #[Test]
    public function it_deducts_credit_notes_from_revenue_and_tax()
    {
        $testYear = 2026;
        $testMonth = 6;

        // 1. Order: 238.00 € gross, 38.00 € tax
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-002',
            'customer_id' => $this->customer->id,
            'email' => 'max@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 20000,
            'total_price' => 23800,
            'tax_amount' => 3800,
            'currency' => 'EUR',
        ]);
        DB::table('order_orders')->where('id', $order->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 10, 10, 0, 0),
        ]);

        // 2. Cancellation/Credit note: -119.00 € gross, -19.00 € tax
        AccountingInvoice::create([
            'id' => (string) Str::uuid(),
            'invoice_number' => 'ST-TEST-001',
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'type' => 'credit_note',
            'status' => 'paid',
            'subtotal' => -10000,
            'total' => -11900,
            'tax_amount' => -1900,
            'invoice_date' => Carbon::create($testYear, $testMonth, 20, 10, 0, 0),
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth);

        $data = $component->instance()->getMonthData($testMonth);

        $this->assertEquals(119.00, $data['revenue_gross']);
        $this->assertEquals(100.00, $data['revenue_net']);
        $this->assertEquals(19.00, $data['vat_collected']);
    }

    #[Test]
    public function it_calculates_input_tax_vorsteuer_from_business_expenses()
    {
        $testYear = 2026;
        $testMonth = 7;

        // 1. Order: 119.00 € gross, 19.00 € tax
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-003',
            'customer_id' => $this->customer->id,
            'email' => 'max@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 10000,
            'total_price' => 11900,
            'tax_amount' => 1900,
            'currency' => 'EUR',
        ]);
        DB::table('order_orders')->where('id', $order->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 5, 12, 0, 0),
        ]);

        // 2. Variable Business Issue: 59.50 € gross, 19% tax rate -> Vorsteuer = 9.50 €
        AccountingSpecialIssue::create([
            'id' => (string) Str::uuid(),
            'admin_id' => $this->admin->id,
            'title' => 'Büromaterial Test',
            'amount' => -59.50,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => Carbon::create($testYear, $testMonth, 12, 10, 0, 0),
            'file_paths' => ['buchhaltung/receipts/test_receipt.pdf'],
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth);

        $data = $component->instance()->getMonthData($testMonth);

        $this->assertEquals(19.00, $data['total_tax']);
        $this->assertEquals(9.50, round($data['vat_paid'], 2));
        $this->assertEquals(9.50, round($data['zahllast'], 2)); // 19.00 - 9.50 = 9.50 €
    }

    #[Test]
    public function it_handles_small_business_status_properly()
    {
        $this->mock(TaxSettingService::class, function ($mock) {
            $mock->shouldReceive('isSmallBusinessForYear')->andReturn(true);
            $mock->shouldReceive('isMidyearSwitch')->andReturn(false);
            $mock->shouldReceive('getMidyearSwitchDate')->andReturn(null);
        });

        $testYear = 2026;
        $testMonth = 4;

        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-TEST-004',
            'customer_id' => $this->customer->id,
            'email' => 'max@example.com',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Mustermann'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 10000,
            'total_price' => 10000,
            'tax_amount' => 1900,
            'currency' => 'EUR',
        ]);
        DB::table('order_orders')->where('id', $order->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 5, 12, 0, 0),
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth);

        $data = $component->instance()->getMonthData($testMonth);

        $this->assertTrue($data['is_small_business_year']);
        $this->assertEquals(0, $data['vat_collected']);
        $this->assertEquals(0, $data['vat_paid']);
        $this->assertEquals(0, $data['zahllast']);
    }

    #[Test]
    public function it_validates_checklist_for_missing_receipts_and_tax_id()
    {
        $testYear = 2026;
        $testMonth = 8;

        // Variable issue with NO receipt file
        AccountingSpecialIssue::create([
            'id' => (string) Str::uuid(),
            'admin_id' => $this->admin->id,
            'title' => 'Fehlender Beleg Test',
            'amount' => -100.00,
            'is_business' => true,
            'execution_date' => Carbon::create($testYear, $testMonth, 10, 10, 0, 0),
            'file_paths' => [],
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth);

        $data = $component->instance()->getMonthData($testMonth);

        $this->assertFalse($data['all_checklist_passed']);
        $this->assertFalse($data['checklist']['buchhaltung/receipts']['passed']);
        $this->assertTrue($data['checklist']['tax_id']['passed']);
    }

    #[Test]
    public function it_allows_inline_upload_of_missing_receipts_for_variable_expenses()
    {
        Storage::fake('local');

        $issue = AccountingSpecialIssue::create([
            'id' => (string) Str::uuid(),
            'admin_id' => $this->admin->id,
            'title' => 'Test Variable Issue',
            'amount' => -80.00,
            'is_business' => true,
            'execution_date' => Carbon::create(2026, 9, 10, 10, 0, 0),
            'file_paths' => [],
        ]);

        $fakeFile = UploadedFile::fake()->create('rechnung.pdf', 100, 'application/pdf');

        Livewire::test(AccountingTax::class)
            ->set('receiptFiles.' . $issue->id, $fakeFile)
            ->call('uploadMissingReceipt', $issue->id, 'variable')
            ->assertSee('Beleg erfolgreich hinterlegt!');

        $issue->refresh();
        $this->assertNotEmpty($issue->file_paths);
        $this->assertStringContainsString('rechnung.pdf', $issue->file_paths[0]);
    }

    #[Test]
    public function it_allows_inline_upload_of_missing_receipts_for_fixed_cost_items()
    {
        Storage::fake('local');

        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Fixed Cost Group',
            'type' => 'expense',
            'position' => 1,
        ]);

        $costItem = AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Server Hosting Vertrag',
            'amount' => -50.00,
            'interval_months' => 1,
            'is_business' => true,
            'first_payment_date' => Carbon::create(2026, 1, 1),
            'contract_file_path' => null,
        ]);

        $fakeFile = UploadedFile::fake()->create('server_vertrag.pdf', 150, 'application/pdf');

        Livewire::test(AccountingTax::class)
            ->set('receiptFiles.' . $costItem->id, $fakeFile)
            ->call('uploadMissingReceipt', $costItem->id, 'fixed')
            ->assertSee('Beleg erfolgreich hinterlegt!');

        $costItem->refresh();
        $this->assertNotNull($costItem->contract_file_path);
        $this->assertStringContainsString('server_vertrag.pdf', $costItem->contract_file_path);
    }

    #[Test]
    public function it_deletes_archived_export_successfully()
    {
        Storage::disk('local')->put('buchhaltung/tax_exports/test_archive.zip', 'dummy content');

        Livewire::test(AccountingTax::class)
            ->call('deleteExport', 'test_archive.zip')
            ->assertSee('erfolgreich gelöscht');

        Storage::disk('local')->assertMissing('buchhaltung/tax_exports/test_archive.zip');
    }

    #[Test]
    public function it_checks_api_status_via_eric_service()
    {
        Livewire::test(AccountingTax::class)
            ->call('checkApiStatus')
            ->assertSet('apiStatus', 'online');
    }

    #[Test]
    public function it_generates_datev_export_zip_archive()
    {
        $testYear = 2026;
        $testMonth = 3;

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth)
            ->call('generateDatevExport')
            ->assertSee('Steuer-Export (Zip) für');

        $this->assertNotEmpty($component->get('archivedExports'));
    }

    #[Test]
    public function it_transmits_ustva_in_simulation_test_mode()
    {
        $tresorPath = env('ERIC_TRESOR_PATH', 'storage/app/buchhaltung/erictresor');
        $isAbs = str_starts_with($tresorPath, '/');
        $fullPath = $isAbs ? $tresorPath : base_path($tresorPath);
        File::ensureDirectoryExists($fullPath);
        $testCertPath = $fullPath . '/test-soft-pse.pfx';
        File::put($testCertPath, 'fake-pfx-data');

        try {
            // Use past month (e.g. 2025 month 1) so status is ready when no missing data
            $component = Livewire::test(AccountingTax::class)
                ->set('selectedYear', 2025)
                ->call('selectMonth', 1)
                ->set('selectedCertName', 'test-soft-pse.pfx')
                ->call('transmitToElster');

            $logs = $component->get('globalLogs');
            $lastLog = end($logs);
            $this->assertNotEmpty($logs);
            $allLogText = implode(' ', array_column($logs, 'message'));
            $this->assertStringContainsString('Transferticket', $allLogText);
        } finally {
            if (File::exists($testCertPath)) {
                File::delete($testCertPath);
            }
        }
    }
}
