<?php

namespace Tests\Feature;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\AccountingXmlInvoiceService;
use App\Services\CartService;
use App\Traits\FormatsECommerceData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmallBusinessAndDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function setSmallBusiness(bool $value): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'is_small_business'],
            ['value' => $value ? 'true' : 'false']
        );
        Cache::forget('global_shop_settings');
    }

    /**
     * Test Punkt 2: Standard delivery time details and formatting.
     */
    public function test_delivery_setting_details_standard(): void
    {
        DeliverySetting::query()->delete();
        DeliveryTime::query()->delete();

        DeliverySetting::create([
            'is_vacation_mode' => false,
            'is_sick_mode' => false,
        ]);

        DeliveryTime::create([
            'name' => 'Standard',
            'min_days' => 3,
            'max_days' => 5,
            'is_active' => true,
        ]);

        $details = DeliverySetting::getDeliveryDetails();

        $this->assertEquals(3, $details['min_days']);
        $this->assertEquals(5, $details['max_days']);
        $this->assertFalse($details['is_vacation']);
        $this->assertFalse($details['is_sick']);
        $this->assertStringStartsWith('Lieferzeit: 3-5 Werktage (ca. ', $details['full_text']);
        $this->assertStringContainsString(' – ', $details['date_range_formatted']);

        // Sonntag-Ausschluss prüfen
        $this->assertFalse($details['start_date']->isSunday(), 'Lieferdatum Start darf kein Sonntag sein');
        $this->assertFalse($details['end_date']->isSunday(), 'Lieferdatum Ende darf kein Sonntag sein');

        // Shortcut-Funktion
        $this->assertEquals($details['full_text'], DeliverySetting::getCurrentDeliveryText());
    }

    /**
     * Test Punkt 2: Delivery time during vacation and sick modes.
     */
    public function test_delivery_setting_details_vacation_and_sick_modes(): void
    {
        DeliverySetting::query()->delete();
        DeliveryTime::query()->delete();

        DeliveryTime::create([
            'name' => 'Standard',
            'min_days' => 2,
            'max_days' => 4,
            'is_active' => true,
        ]);

        // Fall 1: Krankheitsmodus (+6 Tage Puffer)
        $setting = DeliverySetting::create([
            'is_vacation_mode' => false,
            'is_sick_mode' => true,
        ]);

        $detailsSick = DeliverySetting::getDeliveryDetails();
        $this->assertTrue($detailsSick['is_sick']);
        $this->assertEquals(8, $detailsSick['min_days']); // 2 + 6
        $this->assertEquals(10, $detailsSick['max_days']); // 4 + 6
        $this->assertStringStartsWith('Voraussichtliche Lieferzeit: 8-10 Werktage (ca. ', $detailsSick['full_text']);

        // Fall 2: Urlaubsmodus (Lieferzeit startet erst nach Urlaubsende)
        $futureVacationEnd = now()->addDays(7)->startOfDay();
        $setting->update([
            'is_sick_mode' => false,
            'is_vacation_mode' => true,
            'vacation_end_date' => $futureVacationEnd,
        ]);

        $detailsVacation = DeliverySetting::getDeliveryDetails();
        $this->assertTrue($detailsVacation['is_vacation']);
        $this->assertStringStartsWith('Voraussichtliche Lieferung: ca. ', $detailsVacation['full_text']);
        $this->assertTrue($detailsVacation['start_date']->gte($futureVacationEnd->copy()->addDays(2)));
    }

    /**
     * Test Punkt 1: Product tax_rate attribute respects is_small_business.
     */
    public function test_product_tax_rate_respects_small_business(): void
    {
        $product = new Product([
            'id' => (string) Str::uuid(),
            'name' => 'Personalisierte Holzbox',
            'tax_rate' => 19.0,
            'tax_class' => 'standard',
            'price' => 3990,
        ]);

        // Standardmodus: USt. fällt an
        $this->setSmallBusiness(false);
        $this->assertEquals(19.0, $product->tax_rate);

        // Kleinunternehmerregelung aktiv: 0% USt.
        $this->setSmallBusiness(true);
        $this->assertEquals(0.00, $product->tax_rate);
    }

    /**
     * Test Punkt 1: CartService calculation in standard vs small business mode.
     */
    public function test_cart_service_totals_calculation_with_small_business(): void
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Holz-Zwerg Dekofigur',
            'slug' => 'holz-zwerg-' . Str::random(6),
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990, // 29,90 € Brutto
            'tax_class' => 'standard',
            'weight' => 250,
        ]);

        $cart = Cart::create([
            'session_id' => Str::random(32),
            'is_express' => false,
        ]);

        CartItem::create([
            'id' => (string) Str::uuid(),
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 2990,
        ]);

        $cartService = app(CartService::class);

        // Standardmodus
        $this->setSmallBusiness(false);
        $totalsStandard = $cartService->calculateTotals($cart);
        $this->assertGreaterThan(0, $totalsStandard['tax'], 'Im Regelbesteuerungs-Modus muss Steuer anfallen');
        $this->assertEquals(2990, $totalsStandard['subtotal_gross']);

        // Kleinunternehmer-Modus
        $this->setSmallBusiness(true);
        $totalsSmallBiz = $cartService->calculateTotals($cart);
        $this->assertEquals(0, $totalsSmallBiz['tax'], 'Im Kleinunternehmer-Modus darf keine Steuer anfallen');
        $this->assertEquals(0, $totalsSmallBiz['shipping_tax']);
        $this->assertEquals(0, $totalsSmallBiz['express_tax']);
        // WICHTIG: Endkundenpreis darf NICHT um 19% gekürzt werden!
        $this->assertEquals(2990, $totalsSmallBiz['subtotal_gross']);
    }

    /**
     * Test Punkt 1: FormatsECommerceData trait outputs legal notices correctly.
     */
    public function test_formats_ecommerce_data_trait_with_small_business(): void
    {
        $mockModel = new class {
            use FormatsECommerceData;

            public $invoice_number = 'RE-2026-001';
            public $total = 5000;
            public $shipping_cost = 0;
            public $items = [];
            public $is_express = false;
            public $expires_at = null;
            public $due_date = null;
            public $token = null;
            public $volume_discount = 0;
            public $discount_amount = 0;
            public $coupon_code = null;
        };

        // Modus 1: Kleinunternehmer aktiv
        $this->setSmallBusiness(true);
        $formatted = $mockModel->toFormattedArray();

        $this->assertTrue($formatted['is_small_business']);
        $this->assertEquals('0,00', $formatted['total_vat']);
        $this->assertEmpty($formatted['tax_breakdown']);
        $this->assertStringContainsString('§ 19 UStG', $formatted['tax_note']);

        // Modus 2: Kleinunternehmer inaktiv
        $this->setSmallBusiness(false);
        $formattedStd = $mockModel->toFormattedArray();

        $this->assertFalse($formattedStd['is_small_business']);
        $this->assertStringContainsString('Enthaltene MwSt.', $formattedStd['tax_note']);
    }

    /**
     * Test Punkt 1: ProductCalculator calculateTotal in small business mode.
     */
    public function test_product_calculator_calculate_total_with_small_business(): void
    {
        $calc = new ProductCalculator();
        $calc->cartItems = [
            [
                'product_id' => 'p1',
                'qty' => 2,
                'configuration' => [],
            ]
        ];
        $calc->dbProducts = [
            'p1' => [
                'id' => 'p1',
                'name' => 'Holzanhänger',
                'price_cents' => 1500, // 15,00 € pro Stück
                'tax_rate' => 19.0,
                'tax_included' => true,
                'weight' => 50,
                'tier_pricing' => [],
            ]
        ];
        $calc->form = ['country' => 'DE'];

        $this->setSmallBusiness(true);
        $calc->calculateTotal();

        $this->assertEquals(0.0, (float)$calc->totalMwst, 'MwSt muss 0,00 sein');
        $this->assertEquals((float)$calc->totalNetto, (float)$calc->totalBrutto, 'Netto muss gleich Brutto sein');
        $this->assertEquals(4.90, (float)$calc->shippingCost, 'Versandkosten müssen 4,90 € betragen');
        $this->assertEquals(34.90, (float)$calc->totalBrutto, 'Gesamtwert muss 34,90 € (30 € Ware + 4,90 € Versand) betragen');
    }

    /**
     * Test Punkt 1: Factur-X / ZUGFeRD E-Invoice XML contains § 19 UStG Category E and exemption reason.
     */
    public function test_zugferd_xml_invoice_contains_exemption_for_small_business(): void
    {
        $this->setSmallBusiness(true);

        $invoice = AccountingInvoice::factory()->create([
            'invoice_number' => 'RE-TEST-ZUGFERD-01',
            'type' => 'invoice',
            'status' => 'draft',
            'total' => 5000,
            'tax_amount' => 0,
            'custom_items' => [
                [
                    'product_name' => 'Persönliches Seelenbuch',
                    'quantity' => 1,
                    'unit_price' => 5000,
                    'tax_rate' => 0.0,
                ]
            ],
            'billing_address' => [
                'first_name' => 'Erika',
                'last_name' => 'Mustermann',
                'address' => 'Musterstr. 1',
                'postal_code' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE',
            ],
        ]);

        $xmlService = app(AccountingXmlInvoiceService::class);
        $filePath = $xmlService->generate($invoice);

        $this->assertTrue(Storage::disk('local')->exists($filePath));
        $xmlContent = Storage::disk('local')->get($filePath);

        $this->assertStringContainsString('<ram:CategoryCode>E</ram:CategoryCode>', $xmlContent);
        $this->assertStringContainsString('<ram:ExemptionReason>Umsatzsteuerfrei aufgrund der Kleinunternehmerregelung gemäß § 19 UStG</ram:ExemptionReason>', $xmlContent);
        $this->assertStringContainsString('<ram:ExemptionReasonCode>VATEX-EU-19</ram:ExemptionReasonCode>', $xmlContent);
        $this->assertStringContainsString('<ram:RateApplicablePercent>0.00</ram:RateApplicablePercent>', $xmlContent);
    }

    /**
     * Test: Steuerjahr-Logik (§ 19 UStG Ganzjahres-Prinzip) über TaxSettingService.
     */
    public function test_tax_setting_service_annual_and_midyear_logic(): void
    {
        $taxService = app(\App\Services\Tax\TaxSettingService::class);

        // Fall 1: Normal aktiv
        $this->setSmallBusiness(true);
        $this->assertTrue($taxService->isSmallBusinessForYear(2026));

        // Fall 2: Unterjähriger Wechsel im Jahr 2026
        $taxService->applyTransition(false, 'immediate_retroactive');

        // Für 2026 MUSS jetzt Regelbesteuerung gelten (da ganzes Jahr 2026 MwSt fällig)
        $this->assertFalse($taxService->isSmallBusinessForYear(2026));
        $this->assertTrue($taxService->isMidyearSwitch(2026));
        $this->assertFalse($taxService->isCurrentSmallBusiness());

        // Vergangenes Jahr 2025 bleibt aber Kleinunternehmer
        $this->assertTrue($taxService->isSmallBusinessForYear(2025));

        // Rückrechnung testen: 119,00 € Brutto -> 100,00 € Netto + 19,00 € MwSt
        $calc = $taxService->calculateRetroactiveVat(11900, 19.0);
        $this->assertEquals(10000, $calc['net_cents']);
        $this->assertEquals(1900, $calc['vat_cents']);
    }

    /**
     * Test: Geplanter Wechsel zum 01.01. des Folgejahres.
     */
    public function test_scheduled_tax_transition_for_next_year(): void
    {
        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $currentYear = (int)now()->year;

        // Geplanter Wechsel zum nächsten Jahr
        $taxService->applyTransition(false, 'next_year');

        // Für das laufende Jahr bleibt Kleinunternehmer aktiv
        $this->assertTrue($taxService->isSmallBusinessForYear($currentYear));
        $this->assertTrue($taxService->isCurrentSmallBusiness());

        // Für das nächste Jahr gilt bereits Regelbesteuerung
        $this->assertFalse($taxService->isSmallBusinessForYear($currentYear + 1));
    }

    /**
     * Test: Belege behalten ihren festen Snapshot-Status (GoBD-Sicherheit).
     */
    public function test_invoice_retains_small_business_snapshot_regardless_of_global_setting(): void
    {
        // 1. Rechnung mit Snapshot Kleinunternehmer (z.B. aus Mai 2026)
        $invoiceSB = new AccountingInvoice([
            'invoice_number' => 'RE-2026-MAY',
            'total' => 5950,
            'tax_amount' => 0,
            'is_small_business' => true,
        ]);

        // Aktuell ist im Shop aber Regelbesteuerung aktiv
        $this->setSmallBusiness(false);
        $formatted = $invoiceSB->toFormattedArray();

        $this->assertTrue($formatted['is_small_business'], 'Beleg muss seinen Kleinunternehmer-Snapshot behalten');
        $this->assertStringContainsString('§ 19 UStG', $formatted['tax_note']);

        // 2. Umgekehrt: Rechnung mit Snapshot Regelbesteuerung
        $invoiceStandard = new AccountingInvoice([
            'invoice_number' => 'RE-2026-NOV',
            'total' => 5950,
            'tax_amount' => 950,
            'is_small_business' => false,
        ]);

        // Aktuell ist im Shop Kleinunternehmer aktiv
        $this->setSmallBusiness(true);
        $formattedStd = $invoiceStandard->toFormattedArray();

        $this->assertFalse($formattedStd['is_small_business'], 'Beleg muss seinen Regelbesteuerungs-Snapshot behalten');
        $this->assertStringContainsString('Enthaltene MwSt', $formattedStd['tax_note']);
    }

    /**
     * Test: CartService::calculateTierPrice berücksichtigt prices_entered_gross = false (Netto-Preispflege).
     */
    public function test_cart_service_calculate_tier_price_adds_vat_when_prices_entered_net(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
        Cache::forget('global_shop_settings');

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Netto-Testprodukt',
            'slug' => 'netto-testprodukt-' . Str::random(6),
            'price' => 1000, // 10,00 € Netto
            'tax_class' => 'standard',
            'type' => 'physical',
            'status' => 'active',
        ]);

        $this->assertFalse($product->tax_included);

        $cartService = app(CartService::class);
        $grossUnitPrice = $cartService->calculateTierPrice($product, 1);

        $this->assertEquals(1190, $grossUnitPrice, 'Bei Netto-Preispflege muss im Warenkorb 19% MwSt aufgeschlagen werden (10,00 € Netto -> 11,90 € Brutto)');

        // Zurücksetzen auf Standard (Brutto)
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => 'true']);
        Cache::forget('global_shop_settings');
    }

    /**
     * Test: ProductCalculator berechnet bei Netto-Preispflege (tax_included = false) konsistente Brutto-Einzel- und Gesamtpreise.
     */
    public function test_product_calculator_unit_price_matches_gross_total_when_prices_entered_net(): void
    {
        $this->setSmallBusiness(false);

        $calc = new ProductCalculator();
        $calc->cartItems = [
            [
                'product_id' => 'p_net',
                'qty' => 3,
                'configuration' => [],
            ]
        ];
        $calc->dbProducts = [
            'p_net' => [
                'id' => 'p_net',
                'name' => 'Netto-Trophäe',
                'price_cents' => 1000, // 10,00 € Netto
                'tax_rate' => 19.0,
                'tax_included' => false,
                'weight' => 100,
                'tier_pricing' => [],
            ]
        ];
        $calc->form = ['country' => 'DE'];

        $calc->calculateTotal();

        $this->assertEquals(11.90, (float) $calc->cartItems[0]['calculated_single_price']);
        $this->assertEquals(35.70, (float) $calc->cartItems[0]['calculated_total']);
    }

    /**
     * Test: AccountingLiquidity berücksichtigt § 19 UStG (Kleinunternehmerregelung) in Rentabilitäts- und Steuervorschau.
     */
    public function test_accounting_liquidity_respects_small_business_regulation_and_tax_settings(): void
    {
        $admin = \App\Models\Admin\Admin::factory()->create();

        // 1. Mit aktiver Kleinunternehmerregelung: USt = 0,00 €
        $this->setSmallBusiness(true);
        $compSmall = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingLiquidity::class);

        $rentSmall = $compSmall->get('rentabilitaet');
        $taxSmall = $compSmall->get('taxCalculations');
        $firstYear = array_key_first($rentSmall);

        $this->assertEquals(0.0, (float) $rentSmall[$firstYear]['ust']);
        $this->assertEquals((float) $rentSmall[$firstYear]['brutto_umsatz'], (float) $rentSmall[$firstYear]['umsatz']);
        $this->assertEquals(0.0, (float) $taxSmall['vat'][$firstYear]['total']);

        // 2. Im Regelbesteuerungs-Modus: USt > 0,00 € (sofern Umsatz > 0)
        $this->setSmallBusiness(false);
        $compStd = \Livewire\Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Accounting\AccountingLiquidity::class);

        $rentStd = $compStd->get('rentabilitaet');
        if (($rentStd[$firstYear]['brutto_umsatz'] ?? 0) > 0) {
            $this->assertGreaterThan(0.0, (float) $rentStd[$firstYear]['ust']);
        }
    }

    /**
     * Test: OrderOrder::shipping_tax_amount respektiert den historischen is_small_business-Snapshot der Bestellung.
     */
    public function test_order_shipping_tax_amount_respects_historical_small_business_snapshot(): void
    {
        $orderSmall = new \App\Models\Order\OrderOrder([
            'shipping_price' => 499,
            'is_small_business' => true,
        ]);

        $orderRegular = new \App\Models\Order\OrderOrder([
            'shipping_price' => 1190,
            'is_small_business' => false,
        ]);

        // Selbst wenn global Regelbesteuerung aktiv ist, bleibt die historische Kleinunternehmer-Bestellung bei 0 Cent Versand-MwSt
        $this->setSmallBusiness(false);
        $this->assertSame(0, $orderSmall->shipping_tax_amount);
        $this->assertSame(190, $orderRegular->shipping_tax_amount);

        // Und umgekehrt: Selbst wenn global Kleinunternehmer aktiv ist, behält die historische Regelbesteuerungs-Bestellung ihre Versand-MwSt
        $this->setSmallBusiness(true);
        $this->assertSame(0, $orderSmall->shipping_tax_amount);
        $this->assertSame(190, $orderRegular->shipping_tax_amount);
    }

    /**
     * Test: OrderQuoteAcceptance berechnet bei Netto-Preispflege (prices_entered_gross = false) keine doppelte MwSt auf bereits brutto gespeicherte Quote-Items.
     */
    public function test_quote_acceptance_does_not_double_tax_gross_stored_items_when_prices_entered_net(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $product = Product::create([
            'name' => 'Netto-Quote-Produkt',
            'slug' => 'netto-quote-produkt-' . Str::random(6),
            'price' => 1000, // 10,00 € Netto -> 11,90 € Brutto (1190 Cent)
            'tax_class' => 'standard',
            'type' => 'physical',
            'status' => 'active',
        ]);

        $quote = \App\Models\Order\OrderQuoteRequest::create([
            'quote_number' => 'AN-' . strtoupper(Str::random(6)),
            'token' => Str::random(32),
            'status' => 'open',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'net_total' => 1000,
            'tax_total' => 190,
            'gross_total' => 1190,
            'shipping_price' => 0,
            'express_price' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $item = \App\Models\Order\OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 1190, // Brutto in Cent
            'total_price' => 1190, // Brutto in Cent
            'tax_rate' => 19.0,
            'configuration' => [],
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('editItem', $item->id)
            ->call('saveItem', ['qty' => 1]);

        $quote->refresh();
        $item->refresh();
        $this->assertSame(1190, (int) $item->total_price, 'Positionssumme muss 1190 Cent (Brutto) sein');
        $this->assertSame(1190, (int) $quote->gross_total - (int) $quote->shipping_price, 'Produkt-Brutto-Gesamtsumme darf bei Netto-Preispflege nicht doppelt besteuert werden (muss 1190 Cent bleiben)');

        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => 'true']);
        Cache::forget('global_shop_settings');
    }

    /**
     * Test: FinancialService::getCompanyTaxMeta() verdoppelt weder Hausnummer ("26 26") noch PLZ ("38518 38518"),
     * wenn company_street / company_city leer sind und auf owner_street / owner_city zurückgefallen wird.
     */
    public function test_financial_service_company_tax_meta_does_not_duplicate_house_number_or_zip_on_owner_fallback(): void
    {
        SystemSetting::updateOrCreate(['key' => 'company_street'], ['value' => '']);
        SystemSetting::updateOrCreate(['key' => 'company_street_number'], ['value' => '26']);
        SystemSetting::updateOrCreate(['key' => 'owner_street'], ['value' => 'Carl-Goerdeler-Ring 26']);
        SystemSetting::updateOrCreate(['key' => 'company_city'], ['value' => '']);
        SystemSetting::updateOrCreate(['key' => 'company_zip'], ['value' => '38518']);
        SystemSetting::updateOrCreate(['key' => 'owner_city'], ['value' => '38518 Gifhorn']);
        Cache::forget('global_shop_settings');

        $meta = app(\App\Services\FinancialService::class)->getCompanyTaxMeta();

        $this->assertSame('Carl-Goerdeler-Ring 26', $meta['street']);
        $this->assertSame('38518 Gifhorn', $meta['city']);
        $this->assertStringNotContainsString('26 26', $meta['street']);
        $this->assertStringNotContainsString('38518 38518', $meta['city']);
    }

    /**
     * Test: OrderQuoteAcceptance berechnet 0 Cent Versandkosten, wenn skip_shipping_for_digital aktiv ist und das Angebot nur digitale Produkte enthält.
     */
    public function test_quote_acceptance_respects_skip_shipping_for_digital_items(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'skip_shipping_for_digital'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shipping_cost'], ['value' => '4.90']);
        SystemSetting::updateOrCreate(['key' => 'shipping_free_threshold'], ['value' => '50.00']);
        Cache::forget('global_shop_settings');

        $digitalProduct = Product::create([
            'name' => 'Digitales Gutschein-PDF',
            'slug' => 'digitales-gutschein-pdf-' . Str::random(6),
            'price' => 1500, // 15,00 € (unter 50,00 € Versandfreigrenze)
            'tax_class' => 'standard',
            'type' => 'digital',
            'status' => 'active',
        ]);

        $quote = \App\Models\Order\OrderQuoteRequest::create([
            'quote_number' => 'AN-' . strtoupper(Str::random(6)),
            'token' => Str::random(32),
            'status' => 'open',
            'first_name' => 'Anna',
            'last_name' => 'Digital',
            'email' => 'anna@example.com',
            'net_total' => 1261,
            'tax_total' => 239,
            'gross_total' => 1500,
            'shipping_price' => 490,
            'express_price' => 0,
            'expires_at' => now()->addDays(14),
        ]);

        $item = \App\Models\Order\OrderQuoteRequestItem::create([
            'quote_request_id' => $quote->id,
            'product_id' => $digitalProduct->id,
            'product_name' => $digitalProduct->name,
            'quantity' => 1,
            'unit_price' => 1500,
            'total_price' => 1500,
            'tax_rate' => 19.0,
            'configuration' => [],
        ]);

        \Livewire\Livewire::test(\App\Livewire\Shop\Order\OrderQuoteAcceptance::class, ['token' => $quote->token])
            ->call('editItem', $item->id)
            ->call('saveItem', ['qty' => 1]);

        $quote->refresh();
        $this->assertSame(0, (int) $quote->shipping_price, 'Reine Digital-Angebote müssen bei skip_shipping_for_digital = true 0 Cent Versandkosten haben');
        $this->assertSame(1500, (int) $quote->gross_total);
    }

    /**
     * Test: shop_setting() bereinigt beim Fallback von company_city auf owner_city ("38518 Gifhorn")
     * bzw. company_street auf owner_street ("Carl-Goerdeler-Ring 26") die PLZ bzw. Hausnummer.
     */
    public function test_shop_setting_company_city_and_street_normalize_combined_owner_fallbacks(): void
    {
        SystemSetting::updateOrCreate(['key' => 'company_city'], ['value' => '']);
        SystemSetting::updateOrCreate(['key' => 'owner_city'], ['value' => '38518 Gifhorn']);
        SystemSetting::updateOrCreate(['key' => 'company_street'], ['value' => '']);
        SystemSetting::updateOrCreate(['key' => 'owner_street'], ['value' => 'Carl-Goerdeler-Ring 26']);
        Cache::forget('global_shop_settings');

        $this->assertSame('Gifhorn', shop_setting('company_city', shop_setting('owner_city', 'Gifhorn')));
        $this->assertSame('Carl-Goerdeler-Ring', shop_setting('company_street', shop_setting('owner_street', 'Carl-Goerdeler-Ring')));
    }

    /**
     * Test: AccountingInvoice::editDraft() + saveManualInvoice() besteuert Positionen und Versand beim erneuten Speichern eines Entwurfs nicht doppelt.
     */
    public function test_manual_invoice_draft_editing_does_not_double_tax_items_or_shipping(): void
    {
        $this->setSmallBusiness(false);

        $admin = \App\Models\Admin\Admin::first() ?? \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin-draft-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin, 'admin');

        $component = \Livewire\Livewire::test(\App\Livewire\Shop\Accounting\AccountingInvoice::class)
            ->call('toggleManualCreate')
            ->set('manualInvoice.invoice_number', 'RE-DRAFT-001')
            ->set('manualInvoice.customer_email', 'kunde@example.com')
            ->set('manualInvoice.first_name', 'Max')
            ->set('manualInvoice.last_name', 'Mustermann')
            ->set('manualInvoice.address', 'Musterstr. 1')
            ->set('manualInvoice.postal_code', '12345')
            ->set('manualInvoice.city', 'Musterstadt')
            ->set('manualInvoice.shipping_cost', 10.00) // 10,00 € Netto -> 11,90 € Brutto (1190 Cent)
            ->set('manualInvoice.items.0.product_name', 'Lasergravur Unikat')
            ->set('manualInvoice.items.0.quantity', 1)
            ->set('manualInvoice.items.0.unit_price', 100.00) // 100,00 € Netto -> 119,00 € Brutto (11900 Cent)
            ->set('manualInvoice.items.0.tax_rate', 19)
            ->call('saveManualInvoice', 'draft');

        $invoice = \App\Models\Accounting\AccountingInvoice::where('invoice_number', 'RE-DRAFT-001')->firstOrFail();
        $this->assertSame(11900, (int) $invoice->subtotal);
        $this->assertSame(1190, (int) $invoice->shipping_cost);
        $this->assertSame(13090, (int) $invoice->total);

        // Entwurf im Editor erneut öffnen und wieder speichern -> Preise dürfen sich NICHT durch erneute 19%-Aufschläge erhöhen!
        $component->call('editDraft', $invoice->id)
            ->assertSet('manualInvoice.items.0.unit_price', 100)
            ->assertSet('manualInvoice.shipping_cost', 10)
            ->call('saveManualInvoice', 'draft');

        $invoice->refresh();
        $this->assertSame(11900, (int) $invoice->subtotal, 'Brutto-Positionssumme muss nach erneutem Entwurf-Speichern 11900 Cent bleiben');
        $this->assertSame(1190, (int) $invoice->shipping_cost, 'Brutto-Versand muss nach erneutem Entwurf-Speichern 1190 Cent bleiben');
        $this->assertSame(13090, (int) $invoice->total, 'Gesamtbetrag muss nach erneutem Entwurf-Speichern 13090 Cent bleiben');
    }

    /**
     * Test: AccountingCredit::generateCreditNote() speichert unit_price als Brutto-Einzelpreis in Cent,
     * sodass FormatsECommerceData::toFormattedArray() und AccountingXmlInvoiceService konsistente Werte liefern.
     */
    public function test_credit_note_generation_stores_gross_unit_price_for_formatting_and_xml_export(): void
    {
        $this->setSmallBusiness(false);

        $admin = \App\Models\Admin\Admin::first() ?? \App\Models\Admin\Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin-credit-test@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($admin, 'admin');

        \Livewire\Livewire::test(\App\Livewire\Shop\Accounting\AccountingCredit::class)
            ->call('openCreateModal')
            ->set('newCredit.subject', 'Gutschrift Test')
            ->set('creditItems.0.name', 'Kulanz-Erstattung')
            ->set('creditItems.0.quantity', 1)
            ->set('creditItems.0.unit_price', 119.00) // 119,00 € Brutto
            ->set('creditItems.0.tax_rate', 19)
            ->call('generateCreditNote');

        $creditNote = \App\Models\Accounting\AccountingInvoice::where('type', 'credit_note')
            ->where('subject', 'Gutschrift Test')
            ->latest()
            ->firstOrFail();

        $this->assertSame(11900, (int) $creditNote->custom_items[0]['unit_price']);
        $this->assertSame(10000, (int) $creditNote->custom_items[0]['net_unit_price']);
        $this->assertSame(11900, (int) $creditNote->custom_items[0]['total_price']);

        $formatted = $creditNote->toFormattedArray();
        $this->assertSame('119,00', $formatted['items'][0]['single_price']);
        $this->assertSame('119,00', $formatted['items'][0]['total_price']);

        $xmlPath = app(\App\Services\AccountingXmlInvoiceService::class)->generate($creditNote);
        $xml = \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath);
        $this->assertStringContainsString('<ram:ChargeAmount>100.0000</ram:ChargeAmount>', $xml);
    }

    /**
     * Test: Stornorechnung (cancellation) behält Versand-, Express- und Rabattwerte in FormatsECommerceData,
     * mail_price_list.blade.php (kein "Kostenlos" bei negativem Versand) sowie im XRechnung-XML-Export.
     */
    public function test_cancellation_invoice_formatting_and_xml_export_preserve_shipping_express_and_discounts(): void
    {
        $this->setSmallBusiness(false);

        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-STORNO-R8',
            'email' => 'storno-r8@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Testweg 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Muster', 'address' => 'Testweg 1', 'postal_code' => '38518', 'city' => 'Gifhorn', 'country' => 'DE'],
            'subtotal_price' => 9000, // 90,00 € nach 10,00 € Mengenrabatt
            'volume_discount' => 1000,
            'discount_amount' => 500,
            'coupon_code' => 'SAVE5',
            'shipping_price' => 490,
            'is_express' => true,
            'express_price' => 500,
            'tax_amount' => 1515,
            'total_price' => 9490,
            'is_small_business' => false,
        ]);

        $invoice = \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => $order->id,
            'invoice_number' => 'RE-STORNO-R8-1',
            'type' => 'invoice',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now(),
            'subtotal' => 9000,
            'volume_discount' => 1000,
            'discount_amount' => 500,
            'shipping_cost' => 490,
            'express_price' => 500,
            'tax_amount' => 1515,
            'total' => 9490,
            'is_small_business' => false,
            'billing_address' => $order->billing_address,
            'shipping_address' => $order->shipping_address,
            'custom_items' => [
                [
                    'product_name' => 'Gravur-Kristall',
                    'quantity' => 10,
                    'unit_price' => 900,
                    'total_price' => 9000,
                    'tax_rate' => 19,
                ],
            ],
        ]);

        $storno = app(\App\Services\InvoiceService::class)->cancelInvoice($invoice);
        $data = $storno->toFormattedArray();

        $this->assertSame('15,15', $data['tax_breakdown']['19'] ?? null, 'Steueraufschlüsselung bei Stornorechnung muss Rabatt, Versand und Express exakt berücksichtigen');
        $this->assertNotSame('0,00 €', $data['display_netto_goods'], 'Netto-Warenwert darf bei Stornorechnungen nicht auf 0,00 € gekappt werden');

        $html = view('global.mails.partials.mail_price_list', [
            'data' => $data,
            'order' => $order,
            'invoice' => $storno,
            'isPdf' => true,
        ])->render();

        $this->assertStringNotContainsString('Kostenlos', $html, 'Negative Versandkosten auf Stornorechnungen dürfen nicht als Kostenlos angezeigt werden');
        $this->assertStringContainsString('-4,90 €', $html);
        $this->assertStringContainsString('-100,00 €', $html, 'Brutto-Warenwert vor Rabatten muss auf der Stornorechnung -100,00 € betragen');

        $xmlPath = app(\App\Services\AccountingXmlInvoiceService::class)->generate($storno);
        $xml = \Illuminate\Support\Facades\Storage::disk('local')->get($xmlPath);
        $this->assertStringContainsString('<ram:TypeCode>381</ram:TypeCode>', $xml);
        $this->assertStringContainsString('<ram:Name>Versandkosten</ram:Name>', $xml);
        $this->assertStringContainsString('<ram:Name>Priorisierte Fertigung (Express-Service)</ram:Name>', $xml);
        $this->assertStringContainsString('<ram:GrandTotalAmount>94.90</ram:GrandTotalAmount>', $xml);
    }

    /**
     * Test: <x-shop.cost-summary> respektiert den historischen is_small_business-Snapshot am Beleg
     * und berechnet den Brutto-Warenwert für Angebote (OrderQuoteRequest) und Mengenrabatt-Bestellungen korrekt.
     */
    public function test_cost_summary_component_respects_historical_small_business_snapshot_and_quote_gross_subtotal(): void
    {
        $this->setSmallBusiness(false);

        $historicalOrder = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-HIST-SB-R8',
            'email' => 'hist-sb@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Anna', 'last_name' => 'Klein'],
            'shipping_address' => ['first_name' => 'Anna', 'last_name' => 'Klein'],
            'subtotal_price' => 9000, // 90,00 € nach 10,00 € Mengenrabatt
            'volume_discount' => 1000,
            'discount_amount' => 0,
            'shipping_price' => 0,
            'tax_amount' => 0,
            'total_price' => 9000,
            'is_small_business' => true,
        ]);

        $orderHtml = \Illuminate\Support\Facades\Blade::render('<x-shop.cost-summary :model="$order" />', ['order' => $historicalOrder]);
        $this->assertStringContainsString('§ 19 UStG', $orderHtml);
        $this->assertStringContainsString('100,00&nbsp;€', $orderHtml, 'Warenwert vor Mengenrabatt muss 100,00 € anzeigen');
        $this->assertStringContainsString('90,00&nbsp;€', $orderHtml, 'Zwischensumme und Gesamtsumme müssen 90,00 € anzeigen');

        $quote = \App\Models\Order\OrderQuoteRequest::create([
            'quote_number' => 'AN-COST-SUM-R8',
            'email' => 'quote-r8@example.com',
            'first_name' => 'Tom',
            'last_name' => 'Tester',
            'status' => 'open',
            'net_total' => 8815,
            'tax_total' => 1675,
            'gross_total' => 10490,
            'shipping_price' => 490,
            'volume_discount' => 0,
        ]);

        $quoteHtml = \Illuminate\Support\Facades\Blade::render('<x-shop.cost-summary :model="$quote" />', ['quote' => $quote]);
        $this->assertStringContainsString('100,00&nbsp;€', $quoteHtml, 'Angebot-Warenwert in cost-summary muss Brutto (100,00 €) statt Netto-Gesamt (88,15 €) anzeigen');
    }

    /**
     * Test: FinancialService::getTaxEvaluation() berücksichtigt bezahlte manuelle Rechnungen (order_id = null)
     * und zieht Stornorechnungen von unbezahlten Rechnungen (status = cancelled, paid_at = null) nicht vom Umsatz ab.
     */
    public function test_tax_evaluation_includes_paid_manual_invoices_and_ignores_unpaid_cancelled_invoices(): void
    {
        $this->setSmallBusiness(false);

        // 1. Bezahlte manuelle Rechnung (ohne Bestellung) im Oktober 2026: 119,00 € Brutto (19,00 € USt.)
        \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => null,
            'invoice_number' => 'RE-MAN-R8-1',
            'type' => 'invoice',
            'status' => 'paid',
            'paid_at' => \Carbon\Carbon::create(2026, 10, 12, 10, 0, 0),
            'invoice_date' => \Carbon\Carbon::create(2026, 10, 12),
            'subtotal' => 11900,
            'tax_amount' => 1900,
            'total' => 11900,
            'is_small_business' => false,
            'billing_address' => ['first_name' => 'Manuell', 'last_name' => 'Zahler'],
        ]);

        // 2. Unbezahlte Rechnung im Oktober 2026, die storniert wird (paid_at = null)
        $unpaidInv = \App\Models\Accounting\AccountingInvoice::create([
            'order_id' => null,
            'invoice_number' => 'RE-UNPAID-R8-2',
            'type' => 'invoice',
            'status' => 'open',
            'paid_at' => null,
            'invoice_date' => \Carbon\Carbon::create(2026, 10, 14),
            'subtotal' => 23800,
            'tax_amount' => 3800,
            'total' => 23800,
            'is_small_business' => false,
            'billing_address' => ['first_name' => 'Nicht', 'last_name' => 'Zahler'],
        ]);

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::create(2026, 10, 15, 12, 0, 0));
        try {
            app(\App\Services\InvoiceService::class)->cancelInvoice($unpaidInv);
        } finally {
            \Carbon\Carbon::setTestNow();
        }

        $eval = app(\App\Services\FinancialService::class)->getUstvaMonthData(null, 10, 2026);
        $this->assertEqualsWithDelta(119.00, $eval['revenue_gross'], 0.01);
        $this->assertEqualsWithDelta(19.00, $eval['vat_collected'], 0.01);
        $this->assertEqualsWithDelta(100.00, $eval['revenue_net'], 0.01);
    }

    /**
     * Test: Schema.org JSON-LD (Product::buildStructuredDataSchema) und /llms-full.txt
     * addieren Varianten-Aufpreise (variants_data[*]['price']) korrekt zum Basispreis
     * und berücksichtigen die Versandkostenfrei-Grenze (shipping_free_threshold).
     */
    public function test_product_structured_data_schema_and_llms_full_txt_add_variant_surcharges_and_free_shipping_threshold(): void
    {
        $this->setSmallBusiness(true);
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => '1']);
        SystemSetting::updateOrCreate(['key' => 'shipping_cost'], ['value' => '490']);
        SystemSetting::updateOrCreate(['key' => 'shipping_free_threshold'], ['value' => '5000']);
        Cache::forget('global_shop_settings');

        $product = Product::create([
            'name' => 'Kristall Pokal',
            'slug' => 'kristall-pokal-r9',
            'price' => 2990, // 29,90 € Basispreis
            'tax_class' => 'standard',
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => false,
            'attributes' => [
                'Größe' => 'Standard, XL',
            ],
            'variants_data' => [
                [
                    'id' => 'var-std',
                    'name' => 'Standard',
                    'price' => 0, // +0,00 € Aufpreis -> 29,90 €
                    'stock' => 10,
                    'is_active' => true,
                    'attributes' => ['Größe' => 'Standard'],
                ],
                [
                    'id' => 'var-xl',
                    'name' => 'XL',
                    'price' => 25.00, // +25,00 € Aufpreis -> 54,90 € (>= 50,00 € versandkostenfrei!)
                    'stock' => 5,
                    'is_active' => true,
                    'attributes' => ['Größe' => 'XL'],
                ],
            ],
        ]);

        $schema = $product->buildStructuredDataSchema('https://mein-seelenfunke.de/produkt/kristall-pokal-r9');
        $productNode = collect($schema['@graph'] ?? [])->firstWhere('@type', 'Product');
        $this->assertNotNull($productNode);

        $offers = $productNode['offers'];
        $this->assertSame('AggregateOffer', $offers['@type']);
        $this->assertSame('29.90', $offers['lowPrice']);
        $this->assertSame('54.90', $offers['highPrice']);
        $this->assertSame('29.90', $offers['offers'][0]['price']);
        $this->assertSame('4.90', $offers['offers'][0]['shippingDetails']['shippingRate']['value']);
        $this->assertSame('54.90', $offers['offers'][1]['price']);
        $this->assertSame('0.00', $offers['offers'][1]['shippingDetails']['shippingRate']['value']);

        $llmsResponse = app(\App\Http\Controllers\SeoController::class)->llmsFullTxt();
        $llmsContent = $llmsResponse->getContent();
        $this->assertStringContainsString('Standard (29,90 €), XL (54,90 €)', $llmsContent);
        $this->assertStringContainsString('Endpreis gem. § 19 UStG', $llmsContent);
    }

    /**
     * Test: Konfigurator rundet Staffelpreis-Einzelpreise exakt wie der Warenkorb (CartService) auf ganze Cent,
     * und HerzensprojektService sowie Product::computeStripeFeeCents() verarbeiten dezimale Euro-Strings sicher.
     */
    public function test_configurator_rounds_tier_unit_price_and_services_normalize_decimal_euro_strings(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => '1']);
        SystemSetting::updateOrCreate(['key' => 'herzensprojekt_cents_per_order'], ['value' => '2.50']);
        SystemSetting::updateOrCreate(['key' => 'stripe_fee_fixed_cents'], ['value' => '0.30']);
        SystemSetting::updateOrCreate(['key' => 'stripe_fee_percent'], ['value' => '2,50']);
        Cache::forget('global_shop_settings');

        $this->assertSame(250, \App\Services\HerzensprojektService::resolveCentsPerOrder());
        // 100,00 € (10000 Cents) * 2.5% = 250 + 30 Cents = 280 Cents
        $this->assertSame(280, Product::computeStripeFeeCents(10000));

        $product = Product::create([
            'name' => 'Staffel Test',
            'slug' => 'staffel-test-r9',
            'price' => 2990, // 29,90 € -> 5% Rabatt = 2840.5 -> gerundet 2841 Cents
            'tax_class' => 'standard',
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => false,
            'tier_pricing' => [
                ['qty' => 3, 'percent' => 5],
            ],
        ]);

        $dummyConfigurator = new class($product) {
            use \App\Livewire\Shop\Product\ProductConfigurator\Traits\HandlesConfiguratorLogic;
            public $product;
            public $variantId = null;
            public $qty = 3;
            public $currentPrice = 0;
            public $totalPrice = 0;
            public $is_express = false;
            public function __construct($p) { $this->product = $p; }
        };

        $dummyConfigurator->calculatePrice();
        $this->assertSame(2841, $dummyConfigurator->currentPrice);
        $this->assertSame(8523, $dummyConfigurator->totalPrice);
    }

    /**
     * Test: ShopCapacityEngine respektiert shop_capacity_buffer = 0 (kein Rückfall auf 12 durch ?: Operator)
     * und shop_setting() verhindert doppelte Hausnummern oder PLZ auch bei direkt in der DB gesetzten Werten.
     */
    public function test_shop_capacity_engine_respects_zero_buffer_and_shop_setting_prevents_duplicate_street_number(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_daily_working_hours'], ['value' => '7']);
        SystemSetting::updateOrCreate(['key' => 'shop_minutes_per_order'], ['value' => '10']);
        SystemSetting::updateOrCreate(['key' => 'shop_capacity_buffer'], ['value' => '0']);
        Cache::forget('global_shop_settings');

        \Illuminate\Support\Facades\Artisan::call('shop:capacity-engine');
        $output = \Illuminate\Support\Facades\Artisan::output();
        // (7 * 60) / 10 - 0 = 42 (statt 30 bei fehlerhaftem Fallback auf 12)
        $this->assertStringContainsString('Limit: 42', $output);

        // Fall A: company_street enthält bereits "26" UND company_street_number ist "26"
        SystemSetting::updateOrCreate(['key' => 'company_street'], ['value' => 'Carl-Goerdeler-Ring 26']);
        SystemSetting::updateOrCreate(['key' => 'company_street_number'], ['value' => '26']);
        SystemSetting::updateOrCreate(['key' => 'company_city'], ['value' => '38518 Gifhorn']);
        Cache::forget('global_shop_settings');

        $this->assertSame(
            'Carl-Goerdeler-Ring 26',
            trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26'))
        );
        $this->assertSame('Gifhorn', shop_setting('company_city'));

        // Fall B: company_street enthält "Neue Straße 10" und company_street_number ist leer (Default '26' im Template)
        SystemSetting::updateOrCreate(['key' => 'company_street'], ['value' => 'Neue Straße 10']);
        SystemSetting::updateOrCreate(['key' => 'company_street_number'], ['value' => '']);
        Cache::forget('global_shop_settings');

        $this->assertSame(
            'Neue Straße 10',
            trim(shop_setting('company_street') . ' ' . shop_setting('company_street_number', '26'))
        );
    }

    /**
     * Test: ShippingCalculatorService liefert bei shipping_cost = 0 in DE korrekt is_free = true und missing = 0,
     * und HolidayOfferAutopilotService zieht bei Netto-Produktpreisen (tax_included = false) nicht doppelt MwSt. ab.
     */
    public function test_shipping_calculator_zero_cost_in_de_and_holiday_offer_autopilot_net_prices(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'shipping_cost'], ['value' => '0']);
        SystemSetting::updateOrCreate(['key' => 'shipping_free_threshold'], ['value' => '5000']);
        SystemSetting::updateOrCreate(['key' => 'prices_entered_gross'], ['value' => '0']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
        Cache::forget('global_shop_settings');

        $shippingRes = app(\App\Services\ShippingCalculatorService::class)->calculateShippingCost([], 2000, 500, 'DE');
        $this->assertSame(0, $shippingRes['cost']);
        $this->assertTrue($shippingRes['is_free']);
        $this->assertSame(0, $shippingRes['missing']);

        // Produkt mit Netto-Preis 50,00 € (5000 Cents), prices_entered_gross = 0 -> tax_included = false, geringe direkte Kosten (5,00 €)
        $product = Product::create([
            'name' => 'Autopilot Netto Produkt',
            'slug' => 'autopilot-netto-r10',
            'price' => 5000,
            'tax_class' => 'standard',
            'purchase_price' => 300,
            'packaging_cost' => 100,
            'shipping_cost' => 100,
            'laser_runtime_minutes' => 0,
            'electricity_wear_factor' => 0,
            'marketing_cost_percent' => 10.0,
            'status' => 'active',
            'type' => 'physical',
            'track_quantity' => false,
        ]);

        $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
        $offer = $autopilot->calculateProductOffer($product, 10);

        // Bei 10% Rabatt auf 50,00 € (45,00 €) -> psychologisch gerundet 45,90 € Netto.
        // Vor dem Fix wurde 45,90 € nochmals durch 1,19 geteilt (38,57 €), jetzt bleibt net_profit_cents exakt auf Basis 45,90 € Netto!
        $this->assertSame(4590, $offer['sale_price_cents']);
        // 4590 Netto - 400 Direktkosten (Einkauf 300 + Verpackung 100) - 459 Marketing (10%) - 167 Stripe (auf 54,62 € Brutto) = 3564 Cents Gewinn (77,6% Netto-Marge)
        $this->assertSame(3564, $offer['net_profit_cents']);
        $this->assertGreaterThan(74.0, $offer['net_margin_percent']);
    }

    /**
     * Test: ReceiptAiScannerService akzeptiert benutzerdefinierte Steuersätze aus active_tax_rates,
     * AccountingQuickEntry übernimmt den Steuersatz aus ZUGFeRD/CII-XML, und AccountingAnalytics zieht
     * Gutschriften (credit_note) auch bei positiv gespeicherten Beträgen korrekt vom Umsatz ab.
     */
    public function test_receipt_ai_scanner_custom_tax_rate_and_quick_entry_xml_tax_rate_and_analytics_credit_note_sign(): void
    {
        $this->setSmallBusiness(false);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
        SystemSetting::updateOrCreate(['key' => 'reduced_tax_rate'], ['value' => '7']);
        SystemSetting::updateOrCreate(['key' => 'active_tax_rates'], ['value' => json_encode([19, 7, 16, 0])]);
        Cache::forget('global_shop_settings');

        // 1. ReceiptAiScannerService::normalizeResult mit 16% Steuersatz aus active_tax_rates
        $normMethod = new \ReflectionMethod(\App\Services\AI\ReceiptAiScannerService::class, 'normalizeResult');
        $normMethod->setAccessible(true);
        $normalized = $normMethod->invoke(null, [
            'merchant' => 'Spezial Lieferant',
            'amount' => 116.00,
            'date' => '2026-10-15',
            'tax_rate' => 16.0,
            'is_business' => true,
        ]);
        $this->assertSame(16.0, $normalized['tax_rate']);

        // 2. AccountingQuickEntry::parseInvoiceXml liest 7% Steuersatz aus CII-XML statt auf 19% zu überschreiben
        $quickEntry = new \App\Livewire\Shop\Accounting\AccountingQuickEntry();
        $parseMethod = new \ReflectionMethod(\App\Livewire\Shop\Accounting\AccountingQuickEntry::class, 'parseInvoiceXml');
        $parseMethod->setAccessible(true);

        $ciiXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
    <rsm:ExchangedDocument>
        <ram:ID>RE-XML-7PCT</ram:ID>
    </rsm:ExchangedDocument>
    <rsm:SupplyChainTradeTransaction>
        <ram:ApplicableHeaderTradeAgreement>
            <ram:SellerTradeParty>
                <ram:Name>Buchverlag GmbH</ram:Name>
            </ram:SellerTradeParty>
        </ram:ApplicableHeaderTradeAgreement>
        <ram:ApplicableHeaderTradeSettlement>
            <ram:ApplicableTradeTax>
                <ram:RateApplicablePercent>7.00</ram:RateApplicablePercent>
            </ram:ApplicableTradeTax>
            <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
                <ram:GrandTotalAmount>53.50</ram:GrandTotalAmount>
            </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        </ram:ApplicableHeaderTradeSettlement>
    </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;

        $parsedOk = $parseMethod->invoke($quickEntry, $ciiXml);
        $this->assertTrue($parsedOk);
        $this->assertSame(7.0, $quickEntry->specialTaxRate);

        // 3. AccountingAnalytics::computeAnalytics zieht credit_note auch mit positivem total in der DB ab
        \App\Models\Accounting\AccountingInvoice::query()->delete();

        \App\Models\Accounting\AccountingInvoice::create([
            'invoice_number' => 'RE-ANA-100',
            'type' => 'invoice',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now(),
            'subtotal' => 10000,
            'tax_amount' => 1900,
            'total' => 11900,
            'billing_address' => ['first_name' => 'A', 'last_name' => 'B'],
        ]);

        \App\Models\Accounting\AccountingInvoice::create([
            'invoice_number' => 'GUT-ANA-101',
            'type' => 'credit_note',
            'status' => 'paid',
            'paid_at' => now(),
            'invoice_date' => now(),
            'subtotal' => 2000,
            'tax_amount' => 380,
            'total' => 2380, // Positiv gespeichert wie in AccountingCredit
            'billing_address' => ['first_name' => 'A', 'last_name' => 'B'],
        ]);

        $analyticsComp = new \App\Livewire\Shop\Accounting\AccountingAnalytics();
        $analyticsComp->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $analyticsComp->dateTo = now()->endOfMonth()->format('Y-m-d');
        $analyticsComp->isNet = true;

        $compMethod = new \ReflectionMethod(\App\Livewire\Shop\Accounting\AccountingAnalytics::class, 'computeAnalytics');
        $compMethod->setAccessible(true);
        $compMethod->invoke($analyticsComp);

        // 100,00 € Netto - 20,00 € Netto Gutschrift = 80,00 € Netto
        $this->assertEquals([80.0], $analyticsComp->invoiceData['data']);
    }

    public function test_round_11_deep_audit_fixes_regression(): void
    {
        // 1. Marketing Livewire components load without duplicate trait Fatal Error
        $this->assertTrue(class_exists(\App\Livewire\Shop\Marketing\MarketingBlog::class));
        $this->assertTrue(class_exists(\App\Livewire\Shop\Marketing\MarketingNewsletter::class));
        $this->assertTrue(class_exists(\App\Livewire\Shop\Marketing\MarketingVoucher::class));

        // 2. AccountingQuickEntry::parseInvoiceXml negates incoming invoice total so expenses are negative
        $quickEntry = new \App\Livewire\Shop\Accounting\AccountingQuickEntry();
        $parseMethod = new \ReflectionMethod(\App\Livewire\Shop\Accounting\AccountingQuickEntry::class, 'parseInvoiceXml');
        $parseMethod->setAccessible(true);

        $ciiXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rsm:CrossIndustryInvoice xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100" xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100">
    <rsm:ExchangedDocument><ram:ID>RE-EXP-1</ram:ID></rsm:ExchangedDocument>
    <rsm:SupplyChainTradeTransaction>
        <ram:ApplicableHeaderTradeAgreement>
            <ram:SellerTradeParty><ram:Name>Lieferant GmbH</ram:Name></ram:SellerTradeParty>
        </ram:ApplicableHeaderTradeAgreement>
        <ram:ApplicableHeaderTradeSettlement>
            <ram:ApplicableTradeTax><ram:RateApplicablePercent>19.00</ram:RateApplicablePercent></ram:ApplicableTradeTax>
            <ram:SpecifiedTradeSettlementHeaderMonetarySummation>
                <ram:GrandTotalAmount>119.00</ram:GrandTotalAmount>
            </ram:SpecifiedTradeSettlementHeaderMonetarySummation>
        </ram:ApplicableHeaderTradeSettlement>
    </rsm:SupplyChainTradeTransaction>
</rsm:CrossIndustryInvoice>
XML;
        $this->assertTrue($parseMethod->invoke($quickEntry, $ciiXml));
        $this->assertSame('-119.00', (string) $quickEntry->specialAmount);

        // 3. HolidayOfferAutopilotService: disabled autopilot returns null in resolveActiveOfferWindow
        // and resolveTrueBasePriceCents does not overwrite price with UVP compare_at_price when not active
        \App\Models\System\SystemSetting::updateOrCreate(['key' => 'holiday_offer_autopilot_enabled'], ['value' => '0']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $autopilot = app(\App\Services\Marketing\HolidayOfferAutopilotService::class);
        $this->assertNull($autopilot->resolveActiveOfferWindow());

        $prodWithUvp = \App\Models\Product\Product::create([
            'name' => 'UVP Testprodukt',
            'slug' => 'uvp-testprodukt-' . uniqid(),
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'compare_at_price' => 3990,
        ]);

        $resolveBaseMethod = new \ReflectionMethod(\App\Services\Marketing\HolidayOfferAutopilotService::class, 'resolveTrueBasePriceCents');
        $resolveBaseMethod->setAccessible(true);
        $this->assertSame(2990, $resolveBaseMethod->invoke($autopilot, $prodWithUvp));

        // 4. DhlService: mapCountryCode maps European ISO-2 codes instead of defaulting to DEU
        $dhlService = new \App\Services\DhlService();
        $mapMethod = new \ReflectionMethod(\App\Services\DhlService::class, 'mapCountryCode');
        $mapMethod->setAccessible(true);
        $this->assertSame('POL', $mapMethod->invoke($dhlService, 'PL'));
        $this->assertSame('BEL', $mapMethod->invoke($dhlService, 'BE'));
        $this->assertSame('DNK', $mapMethod->invoke($dhlService, 'DK'));
        $this->assertSame('CHE', $mapMethod->invoke($dhlService, 'CH'));

        // 5. NewOrderMailToCustomer & NewOrderMailToAdmin pass 'order' to view data
        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-MAIL-TEST-1',
            'status' => 'pending',
            'payment_status' => 'paid',
            'email' => 'kunde@example.com',
            'billing_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'street' => 'Straße des 17. Juni 12',
                'postal_code' => '10623',
                'city' => 'Berlin',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Max',
                'last_name' => 'Mustermann',
                'street' => 'Bahnhofstr. 5',
                'postal_code' => '8001',
                'city' => 'Zürich',
                'country' => 'CH',
            ],
            'subtotal_price' => 5000,
            'shipping_price' => 1590,
            'tax_amount' => 0,
            'total_price' => 6590,
        ]);

        $formatted = $order->toFormattedArray();
        // CH shipping is tax-free (0% VAT on shipping)
        $this->assertSame(0, $order->shipping_tax_amount);

        $customerMail = new \App\Mail\NewOrderMailToCustomer($formatted, null, null, [], $order);
        $this->assertSame($order, $customerMail->content()->with['order'] ?? null);
        $this->assertCount(1, $customerMail->attachments());
        $this->assertStringContainsString('AGB-und-Widerrufsbelehrung.pdf', $customerMail->render());

        $adminMail = new \App\Mail\NewOrderMailToAdmin($formatted, null, null, [], $order);
        $this->assertSame($order, $adminMail->content()->with['order'] ?? null);
    }
}

