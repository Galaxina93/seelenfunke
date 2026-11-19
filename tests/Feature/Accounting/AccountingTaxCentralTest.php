<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingTax;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\ElsterEricService;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingTaxCentralTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-tax-' . uniqid() . '@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->admin = Admin::findOrFail($adminId);
        $this->actingAs($this->admin, 'admin');

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Kundin',
            'email' => 'klara@example.com',
            'password' => bcrypt('password123'),
        ]);

        SystemSetting::updateOrCreate(['key' => 'owner_tax_id'], ['value' => '19/143/11624']);
        SystemSetting::updateOrCreate(['key' => 'owner_finanzamt_nr'], ['value' => '2319']);
        SystemSetting::updateOrCreate(['key' => 'owner_proprietor'], ['value' => 'Alina Steinhauer']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
        SystemSetting::updateOrCreate(['key' => 'is_small_business'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        Http::fake([
            'https://www.elster.de/elsterweb/serverstatus_rss.xml' => Http::response(
                '<?xml version="1.0" encoding="UTF-8"?><rss><channel><item><title>Anmeldungssteuern (authentifiziert)</title><description>OK</description></item></channel></rss>',
                200
            ),
        ]);
    }

    #[Test]
    public function it_converts_lower_saxony_tax_number_to_13_digit_elster_format_and_builds_live_v2026_xml(): void
    {
        $converted = ElsterEricService::toElsterSteuernummer('19/143/11624', '2319');
        $this->assertSame('2319014311624', $converted);

        // Fallback without explicit BUFA also resolves Lower Saxony 2319
        $this->assertSame('2319014311624', ElsterEricService::toElsterSteuernummer('19/143/11624'));
        // Already 13-digit numbers remain untouched
        $this->assertSame('2319014311624', ElsterEricService::toElsterSteuernummer('2319014311624'));

        $eric = app(ElsterEricService::class);
        $xml = $eric->buildXmlPayload([
            'year' => 2026,
            'elster_zeitraum' => '44',
            'month_number' => '44',
            'kz81_base' => 200,
            'kz86_base' => 100,
            'vat_paid' => 55.40,
            'zahllast' => -10.40,
        ], 'Erstübermittlung', false);

        $this->assertStringContainsString('xmlns="http://finkonsens.de/elster/elsteranmeldung/ustva/v2026" version="2026"', $xml);
        $this->assertStringContainsString('<Empfaenger id="F">2319</Empfaenger>', $xml);
        $this->assertStringContainsString('<Jahr>2026</Jahr>', $xml);
        $this->assertStringContainsString('<Zeitraum>44</Zeitraum>', $xml);
        $this->assertStringContainsString('<Steuernummer>2319014311624</Steuernummer>', $xml);
        $this->assertStringContainsString('<Kz81>200</Kz81>', $xml);
        $this->assertStringContainsString('<Kz86>100</Kz86>', $xml);
        $this->assertStringContainsString('<Kz66>55.40</Kz66>', $xml);
        $this->assertStringContainsString('<Kz83>-10.40</Kz83>', $xml);
        $this->assertStringNotContainsString('<Testmerker>', $xml);
    }

    #[Test]
    public function it_excludes_foreign_25_percent_vat_and_0_percent_insurance_tax_and_null_fixed_tax_from_kz66(): void
    {
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Gewerbe Verträge & Versicherungen',
            'type' => 'expense',
            'position' => 1,
        ]);

        // 1. Hiscox Betriebshaftpflicht: 0 % Vorsteuer (19 % Versicherungssteuer nicht abziehbar)
        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Betriebshaftpflicht Hiscox',
            'amount' => -100.14,
            'interval_months' => 12,
            'is_business' => true,
            'tax_rate' => 0,
            'first_payment_date' => '2026-11-01',
            'contract_file_path' => 'buchhaltung/contracts/hiscox.pdf',
        ]);

        // 2. Fixkosten mit NULL tax_rate darf NICHT blind mit 19 % gerechnet werden
        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Gebühr ohne USt',
            'amount' => -50.00,
            'interval_months' => 1,
            'is_business' => true,
            'tax_rate' => null,
            'first_payment_date' => '2026-11-01',
            'contract_file_path' => 'buchhaltung/contracts/gebuehr.pdf',
        ]);

        // 3. Mittwald Hosting: echte 19 % deutsche Vorsteuer (48,79 € brutto -> 7,79 € VSt)
        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Mittwald Hosting',
            'amount' => -48.79,
            'interval_months' => 1,
            'is_business' => true,
            'tax_rate' => 19,
            'first_payment_date' => '2026-11-01',
            'contract_file_path' => 'buchhaltung/contracts/mittwald.pdf',
        ]);

        // 4. Westpack Dänemark: 25 % ausländische MOMS -> 0,00 € in Kz 66!
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Westpack Schmuckschachteln DK',
            'amount' => -145.95,
            'tax_rate' => 25,
            'is_business' => true,
            'execution_date' => '2026-11-02',
            'file_paths' => ['buchhaltung/receipts/westpack_rechnung.pdf'],
        ]);

        // 5. AfB Docking Station: 7 % deutsche Vorsteuer (84,90 € brutto -> 5,55 € VSt)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Lenovo Docking Station AfB',
            'amount' => -84.90,
            'tax_rate' => 7,
            'is_business' => true,
            'execution_date' => '2026-11-05',
            'file_paths' => ['buchhaltung/receipts/afb_rechnung.pdf'],
        ]);

        // 6. Deutsche 19 %-Rechnung (119,00 € brutto -> 19,00 € VSt)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Laser-Rohlinge Deutschland',
            'amount' => -119.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2026-11-10',
            'file_paths' => ['buchhaltung/receipts/rohlinge_rechnung.pdf'],
        ]);

        $service = app(FinancialService::class);
        $novData = $service->getUstvaMonthData($this->admin->id, 11, 2026);

        // Erwartete Vorsteuer: 7,79 € (Mittwald) + 5,5542 € (AfB 7 %) + 19,00 € (Rohlinge 19 %) = 32,34 €
        $this->assertEquals(7.79, round($novData['vorsteuer_fix'], 2));
        $this->assertEquals(24.55, round($novData['vorsteuer_special'], 2));
        $this->assertEquals(32.34, round($novData['vat_paid'], 2));
        $this->assertEquals(-32.34, round($novData['zahllast'], 2));
    }

    #[Test]
    public function it_calculates_kz86_7_percent_and_kz81_19_percent_sales_and_aggregates_quarters(): void
    {
        $prod19 = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Seelenanhänger 19%',
            'slug' => 'seelenanhaenger-19-' . uniqid(),
            'price' => 11900,
            'status' => 'active',
            'type' => 'physical',
            'tax_class' => 'standard',
            'weight' => 50,
        ]);

        $prod7 = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Gedichtband 7%',
            'slug' => 'gedichtband-7-' . uniqid(),
            'price' => 10700,
            'status' => 'active',
            'type' => 'physical',
            'tax_class' => 'reduced',
            'weight' => 200,
        ]);

        // Oktober 2026 (Q4): Bestellung mit 19 % (119,00 € brutto -> 100 € netto, 19 € USt)
        $orderOct = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-Q4-OCT',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'billing_address' => ['first_name' => 'Klara', 'last_name' => 'Kundin'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 11900,
            'total_price' => 11900,
            'tax_amount' => 1900,
        ]);
        DB::table('order_orders')->where('id', $orderOct->id)->update(['created_at' => Carbon::create(2026, 10, 15, 10, 0)]);
        OrderOrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $orderOct->id,
            'product_id' => $prod19->id,
            'product_name' => $prod19->name,
            'quantity' => 1,
            'unit_price' => 11900,
            'total_price' => 11900,
            'tax_rate' => 19.00,
        ]);

        // November 2026 (Q4): Bestellung mit 7 % (214,00 € brutto -> 200 € netto, 14 € USt)
        $orderNov = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-Q4-NOV',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'billing_address' => ['first_name' => 'Klara', 'last_name' => 'Kundin'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 21400,
            'total_price' => 21400,
            'tax_amount' => 1400,
        ]);
        DB::table('order_orders')->where('id', $orderNov->id)->update(['created_at' => Carbon::create(2026, 11, 10, 14, 0)]);
        OrderOrderItem::create([
            'id' => (string) Str::uuid(),
            'order_id' => $orderNov->id,
            'product_id' => $prod7->id,
            'product_name' => $prod7->name,
            'quantity' => 2,
            'unit_price' => 10700,
            'total_price' => 21400,
            'tax_rate' => 7.00,
        ]);

        // Vorsteuer im November: 59,50 € brutto @ 19 % -> 9,50 € VSt
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Versandkartons Q4',
            'amount' => -59.50,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2026-11-12',
            'file_paths' => ['buchhaltung/receipts/kartons_rechnung.pdf'],
        ]);

        $service = app(FinancialService::class);
        $q4 = $service->getUstvaPeriodData(2026, 4, 'quarter', $this->admin->id);

        $this->assertSame('quarter', $q4['period_mode']);
        $this->assertSame('44', $q4['elster_zeitraum']);
        $this->assertSame(100, $q4['kz81_base']);
        $this->assertSame(200, $q4['kz86_base']);
        $this->assertEquals(19.00, round($q4['vat_collected_19'], 2));
        $this->assertEquals(14.00, round($q4['vat_collected_7'], 2));
        $this->assertEquals(33.00, round($q4['total_tax'], 2));
        $this->assertEquals(9.50, round($q4['vat_paid'], 2));
        $this->assertEquals(23.50, round($q4['zahllast'], 2));
    }

    #[Test]
    public function it_computes_potential_tax_refund_summary_across_all_three_scopes_and_tracks_provisional_receipts(): void
    {
        $asOf = Carbon::create(2026, 11, 17);

        // Vorgründung 12/2025: 119,00 € brutto -> 19,00 € VSt (belegsicher)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Schwerlastregal Vorgründung',
            'amount' => -119.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2025-12-28',
            'file_paths' => ['buchhaltung/receipts/regal_rechnung.pdf'],
        ]);

        // Ab Gründung 11/2026 (belegsicher): 238,00 € brutto -> 38,00 € VSt
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Etikettendrucker',
            'amount' => -238.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2026-11-05',
            'file_paths' => ['buchhaltung/receipts/drucker_rechnung.pdf'],
        ]);

        // Ab Gründung 11/2026 (vorläufiger Beleg: Bestellübersicht): 59,50 € brutto -> 9,50 € VSt
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Monitorhalterung Amazon',
            'amount' => -59.50,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2026-11-09',
            'note' => 'Hinweis: Aktuell Amazon-Bestellübersicht; offizielle Amazon-Umsatzsteuerrechnung nach Versand ergänzen.',
            'file_paths' => ['buchhaltung/receipts/amazon_order_summary.pdf'],
        ]);

        // Zukünftiger Monat 12/2026 Fixkosten -> darf am Stichtag 17.11.2026 NICHT mitgezählt werden!
        $group = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'IT Verträge',
            'type' => 'expense',
            'position' => 1,
        ]);
        AccountingCostItem::create([
            'accounting_group_id' => $group->id,
            'name' => 'Hosting Abo',
            'amount' => -119.00, // 19,00 € VSt pro fälligem Monat (Okt + Nov = 38,00 €, Dez ignoriert!)
            'interval_months' => 1,
            'is_business' => true,
            'tax_rate' => 19,
            'first_payment_date' => '2026-10-15',
            'contract_file_path' => 'buchhaltung/contracts/hosting_rechnung.pdf',
        ]);

        $service = app(FinancialService::class);
        $summary = $service->getPotentialTaxRefundSummary(2026, 11, 'month', $this->admin->id, $asOf);

        // 1. Gesamt Bisher: Special (19 + 38 + 9.50 = 66.50) + Fix Okt & Nov (38.00) = 104.50 €
        $this->assertEquals(66.50, $summary['total_all']['vorsteuer_special']);
        $this->assertEquals(38.00, $summary['total_all']['vorsteuer_fix']);
        $this->assertEquals(104.50, $summary['total_all']['vorsteuer_total']);
        $this->assertEquals(104.50, $summary['total_all']['net_refund']);
        $this->assertEquals(95.00, $summary['total_all']['verified_vorsteuer']);
        $this->assertEquals(9.50, $summary['total_all']['pending_doc_vorsteuer']);
        $this->assertSame(1, $summary['total_all']['pending_doc_count']);

        // 2. Ab Gründung 11/26: Special Nov (38 + 9.50 = 47.50) + Fix Nov (19.00) = 66.50 €
        $this->assertEquals(47.50, $summary['since_launch']['vorsteuer_special']);
        $this->assertEquals(19.00, $summary['since_launch']['vorsteuer_fix']);
        $this->assertEquals(66.50, $summary['since_launch']['net_refund']);
    }

    #[Test]
    public function it_renders_accounting_tax_livewire_with_refund_card_quarter_toggle_and_clean_design(): void
    {
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'UV-Laser Manufaktur Investition',
            'amount' => -1190.00,
            'tax_rate' => 19,
            'is_business' => true,
            'execution_date' => '2026-11-01',
            'file_paths' => ['buchhaltung/receipts/laser_rechnung.pdf'],
        ]);

        $component = Livewire::test(AccountingTax::class)
            ->set('selectedYear', 2026)
            ->call('selectMonth', 11)
            ->assertStatus(200)
            ->assertSee('Potenzielle Steuerrückgabe')
            ->assertSee('Gesamt Bisher')
            ->assertSee('Ab Gründung 11/26')
            ->assertSee('Gewählter Zeitraum')
            ->assertSee('Quartal (Q1-Q4)')
            ->assertSee('190,00 €')
            ->call('setRefundScope', 'since_launch')
            ->assertSet('refundScope', 'since_launch')
            ->call('setPeriodMode', 'quarter')
            ->assertSet('periodMode', 'quarter')
            ->call('selectQuarter', 4)
            ->assertSet('selectedQuarter', 4)
            ->assertSee('ELSTER Zeitraum: 44 / 2026');

        // Clean-Design Check: 0 KI-Gedankenstriche im gerenderten HTML
        $this->assertStringNotContainsString(' – ', $component->html());

        // Wenn Umsatzsteuer > Vorsteuer -> automatischer Titelwechsel auf "Voraussichtliche USt-Zahllast"
        $order = OrderOrder::create([
            'id' => (string) Str::uuid(),
            'order_number' => 'ORD-BIG-SALE',
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'billing_address' => ['first_name' => 'Klara', 'last_name' => 'Kundin'],
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 200000,
            'total_price' => 238000, // 2.380,00 € brutto -> 380,00 € USt (> 190,00 € VSt)
            'tax_amount' => 38000,
        ]);
        DB::table('order_orders')->where('id', $order->id)->update(['created_at' => now()->subMinute()]);

        Livewire::test(AccountingTax::class)
            ->assertSee('Voraussichtliche USt-Zahllast');
    }
}
