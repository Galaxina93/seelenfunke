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
        @unlink(storage_path('app/buchhaltung/exports/Finanzbericht_2026_5.zip'));
        @unlink(storage_path('app/buchhaltung/exports/Rechnungen_und_Gutschriften_2026_05.zip'));
    }
}
