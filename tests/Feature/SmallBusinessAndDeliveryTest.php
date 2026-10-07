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
}
