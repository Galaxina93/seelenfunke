<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Auth\AuthLogin;
use App\Livewire\Employee\Influencer\InfluencerDashboard;
use App\Livewire\Employee\TaxAdvisor\TaxAdvisorDashboard;
use App\Livewire\Shop\Accounting\AccountingTax;
use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingInfluencer;
use App\Models\Marketing\MarketingInfluencerPayout;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TaxAdvisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Employee $taxAdvisor;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);

        $this->admin = Admin::where('email', 'kontakt@mein-seelenfunke.de')->firstOrFail();
        $this->taxAdvisor = Employee::where('email', 'steuerberater@mein-seelenfunke.de')->firstOrFail();
        $this->customer = Customer::where('email', 'alina.stone@t-online.de')->firstOrFail();

        SystemSetting::updateOrCreate(['key' => 'owner_tax_id'], ['value' => '19/123/45678']);
        SystemSetting::updateOrCreate(['key' => 'owner_proprietor'], ['value' => 'Alina Steinhauer']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
    }

    #[Test]
    public function user_seeder_creates_test_tax_advisor_user_with_profile(): void
    {
        $this->assertNotNull($this->taxAdvisor);
        $this->assertTrue($this->taxAdvisor->isTaxAdvisor());
        $this->assertFalse($this->taxAdvisor->isInternalEmployee());
        $this->assertFalse($this->taxAdvisor->isInfluencer());
        $this->assertSame('Dr. Thomas', $this->taxAdvisor->first_name);
        $this->assertSame('Steuerberater', $this->taxAdvisor->last_name);
        $this->assertSame('Kanzlei Dr. Steuerberater & Partner StBG', $this->taxAdvisor->profile?->company_name);
        $this->assertSame('DE123456789', $this->taxAdvisor->profile?->vat_id);
    }

    #[Test]
    public function tax_advisor_login_redirects_to_tax_advisor_dashboard_and_enforces_strict_role_isolation(): void
    {
        Livewire::test(AuthLogin::class)
            ->set('email', 'steuerberater@mein-seelenfunke.de')
            ->set('password', 'SeeleSeele1993+++')
            ->call('login')
            ->assertRedirect(route('employee.tax-advisor.dashboard'));

        $this->actingAs($this->taxAdvisor, 'employee')
            ->get(route('employee.tax-advisor.dashboard'))
            ->assertOk()
            ->assertSee('Seelenfunke Steuerberater Cockpit');

        // Internal employee routes redirect tax_advisor back to tax-advisor dashboard
        $this->actingAs($this->taxAdvisor, 'employee')
            ->get('/employee/dashboard')
            ->assertRedirect(route('employee.tax-advisor.dashboard'));

        // Influencer routes redirect tax_advisor back to tax-advisor dashboard
        $this->actingAs($this->taxAdvisor, 'employee')
            ->get(route('employee.influencer.dashboard'))
            ->assertRedirect(route('employee.tax-advisor.dashboard'));

        // Direct Livewire component mounting of internal/admin/influencer components is blocked (403)
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(OrderOverview::class)
            ->assertForbidden();

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(InfluencerDashboard::class)
            ->assertForbidden();

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(AccountingTax::class)
            ->assertForbidden();

        // Internal employee and Influencer cannot access TaxAdvisorDashboard
        $internalEmployee = Employee::where('email', 'mitarbeiter@mein-seelenfunke.de')->firstOrFail();
        $this->actingAs($internalEmployee, 'employee')
            ->get(route('employee.tax-advisor.dashboard'))
            ->assertRedirect(route('employee.dashboard'));

        Livewire::actingAs($internalEmployee, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->assertForbidden();
    }

    #[Test]
    public function tax_advisor_dashboard_displays_all_tax_relevant_data_and_provides_all_downloads(): void
    {
        Storage::fake('local');

        $testYear = 2026;
        $testMonth = 5;

        // 1. Create sample contract & receipt files in storage
        Storage::disk('local')->put('buchhaltung/contracts/hosting_vertrag.pdf', '%PDF-1.4 Fake Contract PDF');
        Storage::disk('local')->put('buchhaltung/receipts/rohlinge_rechnung.pdf', '%PDF-1.4 Fake Receipt PDF');

        // 2. Fixed Costs (Gewerblich & Privat)
        $businessGroup = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'IT & Werkstatt (Gewerbe)',
            'type' => 'expense',
            'position' => 1,
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $businessGroup->id,
            'name' => 'Cloud Server Hosting',
            'amount' => -119.00,
            'interval_months' => 1,
            'is_business' => true,
            'tax_rate' => 19,
            'first_payment_date' => Carbon::create($testYear, 1, 1),
            'contract_file_path' => 'buchhaltung/contracts/hosting_vertrag.pdf',
        ]);

        $privateGroup = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Wohnen (Privat)',
            'type' => 'expense',
            'position' => 2,
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $privateGroup->id,
            'name' => 'Private Wohnungsmiete',
            'amount' => -850.00,
            'interval_months' => 1,
            'is_business' => false,
            'tax_rate' => 0,
            'first_payment_date' => Carbon::create($testYear, 1, 1),
        ]);

        // 3. Variable / Special Costs (Gewerblich & Privat)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Kristallglas-Rohlinge Großhandel',
            'category' => 'Wareneinkauf',
            'invoice_number' => 'RE-GH-900',
            'amount' => -238.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => Carbon::create($testYear, $testMonth, 10),
            'file_paths' => ['buchhaltung/receipts/rohlinge_rechnung.pdf'],
        ]);

        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Privater Wocheneinkauf',
            'category' => 'Lebenshaltung',
            'amount' => -120.00,
            'tax_rate' => 0,
            'is_business' => false,
            'execution_date' => Carbon::create($testYear, $testMonth, 12),
            'file_paths' => [],
        ]);

        // 4. Paid Shop Order & Invoice
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-2026-5001',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'billing_address' => [
                'first_name' => 'Sarah',
                'last_name' => 'Sonnenschein',
                'street' => 'Lindenstr. 12a',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 50000,
            'total_price' => 59500, // 595.00 € gross
            'tax_amount' => 9500,   // 95.00 € VAT (19%)
            'discount_amount' => 1000,
            'shipping_price' => 490,
            'currency' => 'EUR',
        ]);
        DB::table('order_orders')->where('id', $order->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 15, 12, 0, 0),
        ]);

        $invoice = AccountingInvoice::create([
            'id' => (string) Str::uuid(),
            'order_id' => $order->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'RE-2026-5001',
            'type' => 'invoice',
            'status' => 'paid',
            'invoice_date' => Carbon::create($testYear, $testMonth, 15),
            'paid_at' => Carbon::create($testYear, $testMonth, 15),
            'billing_address' => $order->billing_address,
            'subtotal' => 50000,
            'tax_amount' => 9500,
            'total' => 59500,
        ]);

        // 5. Influencer Payout Credit Note (§ 14 Abs. 2 S. 2 UStG)
        $influencer = MarketingInfluencer::firstOrFail();
        $payout = MarketingInfluencerPayout::create([
            'influencer_id' => $influencer->id,
            'credit_note_number' => 'GS-2026-0001',
            'net_amount_cents' => 4500,
            'vat_rate_percent' => 0,
            'vat_amount_cents' => 0,
            'gross_amount_cents' => 4500,
            'tax_status' => 'small_business',
            'tax_number' => '41/123/45678',
            'payout_method' => 'bank_transfer',
            'status' => 'paid',
            'paid_at' => Carbon::create($testYear, $testMonth, 20),
        ]);
        DB::table('marketing_influencer_payouts')->where('id', $payout->id)->update([
            'created_at' => Carbon::create($testYear, $testMonth, 20, 10, 0, 0),
        ]);

        // 6. Test Livewire Dashboard rendering & KPIs
        $component = Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->set('selectedYear', $testYear)
            ->call('selectMonth', $testMonth)
            ->assertOk()
            ->assertSee('Cloud Server Hosting')
            ->assertSee('Private Wohnungsmiete')
            ->assertSee('Kristallglas-Rohlinge Großhandel')
            ->assertSee('Privater Wocheneinkauf')
            ->assertSee('RE-2026-5001')
            ->assertSee('GS-2026-0001')
            ->assertSee('595,00 €') // Shop-Umsatz Brutto
            ->assertSee('357,00 €') // Gewerbe-Ausgaben (119 Fix + 238 Variabel)
            ->assertSee('57,00 €')  // Vorsteuer Kz 66 (19 Fix + 38 Variabel)
            ->assertSee('38,00 €'); // Zahllast Kz 83 (95 USt - 57 Vorsteuer)

        // 7. Test Scope Filter (business vs private)
        $component->call('setScopeFilter', 'business')
            ->assertSee('Cloud Server Hosting')
            ->assertSee('Kristallglas-Rohlinge Großhandel')
            ->assertDontSee('Private Wohnungsmiete')
            ->assertDontSee('Privater Wocheneinkauf');

        $component->call('setScopeFilter', 'private')
            ->assertSee('Private Wohnungsmiete')
            ->assertSee('Privater Wocheneinkauf')
            ->assertDontSee('Cloud Server Hosting')
            ->assertDontSee('Kristallglas-Rohlinge Großhandel');

        $component->call('setScopeFilter', 'all');

        // 8. Test All Downloads
        $component->call('downloadDatevCsv')
            ->assertFileDownloaded("EXTF_Buchungsstapel_05_2026.csv");

        $component->call('downloadUstvaPdf')
            ->assertFileDownloaded("UStVA_Auswertung_05_2026.pdf");

        $component->call('downloadDatevPackageZip')
            ->assertFileDownloaded("TaxExport_2026_05.zip");

        $component->call('downloadFinancialReportZip')
            ->assertFileDownloaded("Finanzbericht_2026_05.zip");

        $component->call('downloadMonthlyInvoicesZip')
            ->assertFileDownloaded("Rechnungen_und_Gutschriften_2026_05.zip");

        $component->call('downloadInvoicePdf', $invoice->id)
            ->assertFileDownloaded("Rechnung_RE-2026-5001.pdf");

        $component->call('downloadInvoiceXml', $invoice->id)
            ->assertFileDownloaded("ERechnung_RE-2026-5001.xml");

        $component->call('downloadReceiptOrContract', 'buchhaltung/contracts/hosting_vertrag.pdf')
            ->assertFileDownloaded('hosting_vertrag.pdf');

        $component->call('downloadReceiptOrContract', 'buchhaltung/receipts/rohlinge_rechnung.pdf')
            ->assertFileDownloaded('rohlinge_rechnung.pdf');

        // Path traversal attempt on receipt download is blocked (403)
        $component->call('downloadReceiptOrContract', 'buchhaltung/receipts/../../../.env')
            ->assertForbidden();

        // Influencer Payout PDF route works for Tax Advisor
        $this->actingAs($this->taxAdvisor, 'employee')
            ->get(route('employee.tax-advisor.payout.pdf', $payout->id))
            ->assertOk();

        // Cleanup generated export files on disk
        @unlink(storage_path('app/buchhaltung/tax_exports/TaxExport_2026_05.zip'));
        @unlink(storage_path('app/buchhaltung/exports/Finanzbericht_2026_05.zip'));
        @unlink(storage_path('app/buchhaltung/exports/Finanzbericht_2026_5.zip'));
        @unlink(storage_path('app/buchhaltung/exports/Rechnungen_und_Gutschriften_2026_05.zip'));
    }

    #[Test]
    public function tax_advisor_exports_respect_quarter_and_year_modes_skr03_accounts_asset_afa_and_strict_business_privacy(): void
    {
        Storage::fake('local');

        $year = 2026;
        Storage::disk('local')->put('buchhaltung/receipts/laser_rechnung.pdf', '%PDF-1.4 Laser PDF');
        Storage::disk('local')->put('buchhaltung/receipts/privat_beleg.pdf', '%PDF-1.4 Private PDF');

        // 1. Capital Asset (> 800 € net -> SKR03 0440 + BU 9, linear 5y AfA)
        $laserIssue = AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'xTool F2 Ultra UV Laser',
            'category' => 'Maschinen',
            'invoice_number' => 'RE-LASER-01',
            'amount' => -4907.51, // 4123.96 net + 783.55 VAT
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => Carbon::create($year, 10, 15),
            'file_paths' => ['buchhaltung/receipts/laser_rechnung.pdf'],
        ]);

        // 2. GWG (150-800 € net -> SKR03 0480 + BU 9)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Bambu Lab A1 3D-Drucker',
            'category' => 'Maschinen',
            'invoice_number' => 'RE-BAMBU-01',
            'amount' => -281.25, // 236.34 net
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => Carbon::create($year, 11, 17),
            'file_paths' => ['buchhaltung/receipts/laser_rechnung.pdf'],
        ]);

        // 3. Raw Material (SKR03 3400 Automatik -> empty BU key)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Schiefer-Rohlinge Großhandel',
            'category' => 'Rohmaterial',
            'invoice_number' => 'RE-ROH-99',
            'amount' => -119.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => Carbon::create($year, 11, 20),
            'file_paths' => ['buchhaltung/receipts/laser_rechnung.pdf'],
        ]);

        // 4. Private expense (MUST NEVER leak into Finanzbericht ZIP or DATEV CSV)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Private Hundeversicherung & Einkauf',
            'category' => 'Privat',
            'amount' => -250.00,
            'tax_rate' => 0,
            'is_business' => false,
            'execution_date' => Carbon::create($year, 11, 18),
            'file_paths' => ['buchhaltung/receipts/privat_beleg.pdf'],
        ]);

        // 5. Planned Reinvestment (§ 7g EStG IAB preview)
        \App\Models\Accounting\AccountingInvestmentPlan::create([
            'admin_id' => $this->admin->id,
            'title' => 'Zweit-Laser Reserve',
            'target_amount_gross' => 4950.00,
            'tax_rate' => 19,
            'monthly_rate' => 125.00,
            'saved_initial' => 0.00,
            'start_date' => '2026-11-01',
            'afa_years' => 5,
            'use_iab' => true,
            'is_active' => true,
            'priority' => 1,
        ]);

        $service = app(\App\Services\FinancialService::class);

        // Verify Q4 UStVA & EÜR separation (Oct + Nov + Dec)
        $q4Data = $service->getUstvaPeriodData($year, 4, 'quarter', $this->admin->id);
        $this->assertSame('Q4', $q4Data['export_period_slug']);
        $this->assertEqualsWithDelta(4123.96, $q4Data['capital_additions_net'], 0.05);
        $this->assertEqualsWithDelta(336.34, $q4Data['operating_expenses_net'], 0.05); // 236.34 GWG + 100.00 Rohlinge
        $this->assertGreaterThan(200.00, $q4Data['depreciation_net']); // 3 months * ~68.73 € AfA

        // Verify SKR03 & BU-Schlüssel in DATEV CSV
        $csv = $service->buildDatevCsv($q4Data);
        $this->assertStringContainsString('"BU-Schlüssel"', $csv);
        $this->assertStringContainsString(';"0440";"1200";"9";', $csv); // Laser -> 0440 + BU 9
        $this->assertStringContainsString(';"0480";"1200";"9";', $csv); // Bambu GWG -> 0480 + BU 9
        $this->assertStringContainsString(';"3400";"1200";"";', $csv);  // Rohmaterial -> 3400 Automatik (no BU)
        $this->assertStringNotContainsString('Private Hundeversicherung', $csv);

        // Verify Livewire Quarter & Year exports + Asset Schedule CSV
        $component = Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->set('selectedYear', $year)
            ->call('setPeriodMode', 'quarter')
            ->call('selectQuarter', 4)
            ->assertOk()
            ->assertSee('Kto. 0440')
            ->assertSee('Kto. 0480')
            ->assertSee('Kto. 3400')
            ->assertSee('Zweit-Laser Reserve');

        $component->call('downloadDatevCsv')
            ->assertFileDownloaded('EXTF_Buchungsstapel_Q4_2026.csv');

        $component->call('downloadDatevPackageZip')
            ->assertFileDownloaded('TaxExport_2026_Q4.zip');

        $component->call('downloadAssetScheduleCsv')
            ->assertFileDownloaded('Anlagenspiegel_und_AfA_2026.csv');

        $component->call('downloadFinancialReportZip')
            ->assertFileDownloaded('Finanzbericht_2026_Q4.zip');

        // Verify TaxExport_2026_Q4.zip contains Anlagenspiegel_und_AfA_2026.csv
        $q4TaxZipPath = storage_path('app/buchhaltung/tax_exports/TaxExport_2026_Q4.zip');
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($q4TaxZipPath) === true);
        $assetCsvInsideZip = $zip->getFromName('Anlagenspiegel_und_AfA_2026.csv');
        $zip->close();
        $this->assertNotFalse($assetCsvInsideZip);
        $this->assertStringContainsString('xTool F2 Ultra UV Laser', $assetCsvInsideZip);
        $this->assertStringContainsString('Bambu Lab A1 3D-Drucker', $assetCsvInsideZip);
        $this->assertStringContainsString('Zweit-Laser Reserve', $assetCsvInsideZip);

        // Verify Finanzbericht_2026_Q4.zip contains ZERO private expenses or private files
        $q4FinZipPath = storage_path('app/buchhaltung/exports/Finanzbericht_2026_Q4.zip');
        $fZip = new \ZipArchive();
        $this->assertTrue($fZip->open($q4FinZipPath) === true);
        $txCsvInsideFinZip = $fZip->getFromName('Transaktionen_2026_Q4.csv');
        $fZip->close();
        $this->assertNotFalse($txCsvInsideFinZip);
        $this->assertStringContainsString('xTool F2 Ultra UV Laser', $txCsvInsideFinZip);
        $this->assertStringNotContainsString('Private Hundeversicherung', $txCsvInsideFinZip);

        // Verify Year mode exports full year
        $component->call('setPeriodMode', 'year')
            ->call('downloadDatevCsv')
            ->assertFileDownloaded('EXTF_Buchungsstapel_GESAMT_2026.csv');

        // Verify CLI command funki:generate-tax-export delegates cleanly to FinancialService
        $this->artisan('funki:generate-tax-export', ['--month' => 11, '--year' => 2026, '--force' => true])
            ->assertSuccessful();
        $this->assertFileExists(storage_path('app/buchhaltung/tax_exports/TaxExport_2026_11.zip'));

        // Cleanup
        @unlink($q4TaxZipPath);
        @unlink($q4FinZipPath);
        @unlink(storage_path('app/buchhaltung/tax_exports/TaxExport_2026_11.zip'));
    }
}
