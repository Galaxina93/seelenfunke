<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\Master\MasterShopCapacity;
use App\Livewire\Shop\System\SystemDeliveryTimes;
use App\Models\Admin\Admin;
use App\Models\Cart\Cart;
use App\Models\Delivery\DeliverySetting;
use App\Models\Delivery\DeliveryTime;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\CartService;
use App\Services\Order\ShopCapacityService;
use Carbon\Carbon;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class ShopCapacityAndDeliveryAutopilotIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_seeder_enables_autopilot_by_default_and_realtime_order_transitions_switch_delivery_times_and_google_schema(): void
    {
        config(['services.capacity.realtime_in_tests' => true]);

        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);
        $this->seed(SystemSettingSeeder::class);

        $this->assertTrue(ShopCapacityService::isAutopilotEnabled());

        $product = Product::create([
            'name' => 'Der Seelen-Talisman',
            'slug' => 'seelen-talisman-cap',
            'sku' => 'MSF-CAP-001',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3690,
            'track_quantity' => true,
            'quantity' => 50,
            'continue_selling_when_out_of_stock' => false,
            'laser_runtime_minutes' => 1,
        ]);

        // Initialer Sync bei 0 Bestellungen -> Standard (3-5 Werktage)
        app(ShopCapacityService::class)->syncAndApplyAutopilot();
        $this->assertSame('Standard', DeliveryTime::where('is_active', true)->value('name'));

        // 27 Bestellungen (bei Limit 30 = 90.0 %) -> Echtzeit-Wechsel auf Level 3 ("Hohe Auslastung" 10-14 Werktage + Express gesperrt)
        $orders = [];
        for ($i = 1; $i <= 27; $i++) {
            $orders[] = OrderOrder::create([
                'order_number' => 'ORD-RT-' . $i,
                'status' => 'pending',
                'payment_status' => 'paid',
                'email' => "kunde{$i}@example.com",
                'billing_address' => ['first_name' => 'Test', 'last_name' => 'Kunde'],
                'subtotal_price' => 3690,
                'tax_amount' => 589,
                'shipping_price' => 0,
                'total_price' => 3690,
            ]);
        }

        $this->assertSame(3, ShopCapacityService::getCurrentLevel());
        $this->assertFalse(ShopCapacityService::isExpressAvailable());
        $this->assertSame('Hohe Auslastung', DeliveryTime::where('is_active', true)->value('name'));

        // Google Structured Data Schema (@graph Product + FAQPage) spiegelt automatisch die 10 bis 14 Tage wider (statt starr 1-3 + 1-3)
        $schema = $product->fresh()->buildStructuredDataSchema('https://mein-seelenfunke.de/produkt/seelen-talisman-cap');
        $productNode = collect($schema['@graph'] ?? [])->firstWhere('@type', 'Product');
        $faqNode = collect($schema['@graph'] ?? [])->firstWhere('@type', 'FAQPage');
        $this->assertIsArray($productNode);
        $this->assertIsArray($faqNode);
        $shipDeliv = $productNode['offers']['shippingDetails']['deliveryTime'];
        $sumMin = $shipDeliv['handlingTime']['minValue'] + $shipDeliv['transitTime']['minValue'];
        $sumMax = $shipDeliv['handlingTime']['maxValue'] + $shipDeliv['transitTime']['maxValue'];
        $this->assertSame(10, $sumMin);
        $this->assertSame(14, $sumMax);
        $this->assertStringContainsString('10 bis 14 Werktagen', json_encode($faqNode, JSON_UNESCAPED_UNICODE));

        // Sobald 20 Bestellungen versendet sind (7 offen = 23.3 %), schaltet das System in Echtzeit zurück auf "Standard" + Express frei!
        foreach (array_slice($orders, 0, 20) as $shippedOrder) {
            $shippedOrder->update(['status' => 'shipped']);
        }

        $this->assertSame(0, ShopCapacityService::getCurrentLevel());
        $this->assertTrue(ShopCapacityService::isExpressAvailable());
        $this->assertSame('Standard', DeliveryTime::where('is_active', true)->value('name'));
    }

    public function test_express_stays_enabled_at_level_2_and_is_stripped_from_cart_at_level_3(): void
    {
        $product = Product::create([
            'name' => 'Express Herz',
            'slug' => 'express-herz',
            'sku' => 'MSF-EXP-01',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'weight' => 20,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $cart = Cart::create([
            'session_id' => 'sess_express_cap',
            'is_express' => true,
        ]);
        $cart->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 2990,
            'configuration' => ['is_express' => true],
        ]);

        // Level 2 (Drossel 2 = 85 %): Express MUSS noch aktiv und berechenbar bleiben!
        Cache::put('shop_capacity_level', 2);
        $this->assertTrue(ShopCapacityService::isExpressAvailable());
        $totalsL2 = app(CartService::class)->calculateTotals($cart->fresh('items.product'), 'DE');
        $this->assertGreaterThan(0, $totalsL2['express']);

        // Level 3 (Drossel 3 = 90 %): Express wird im Warenkorb/Checkout konsequent gesperrt!
        Cache::put('shop_capacity_level', 3);
        $this->assertFalse(ShopCapacityService::isExpressAvailable());
        $totalsL3 = app(CartService::class)->calculateTotals($cart->fresh('items.product'), 'DE');
        $this->assertSame(0, $totalsL3['express']);
        $this->assertFalse((bool) $cart->fresh()->is_express);
    }

    public function test_manual_selection_on_system_delivery_times_pauses_autopilot_against_silent_cron_overwrite(): void
    {
        $admin = Admin::factory()->create();
        ShopCapacityService::setAutopilotEnabled(true);

        $comp = Livewire::actingAs($admin, 'admin')
            ->test(SystemDeliveryTimes::class)
            ->assertSet('capacityAutopilotEnabled', true);

        $highLoadProfile = DeliveryTime::where('name', 'Hohe Auslastung')->firstOrFail();

        // Manuelles Umschalten bei 0 % Last -> Autopilot pausiert automatisch, Cronjob überschreibt Auswahl NICHT!
        $comp->call('setActiveDeliveryTime', $highLoadProfile->id)
            ->assertSet('capacityAutopilotEnabled', false);

        $this->assertFalse(ShopCapacityService::isAutopilotEnabled());
        $this->artisan('shop:capacity-engine')->assertSuccessful();
        $this->assertSame('Hohe Auslastung', DeliveryTime::where('is_active', true)->value('name'));

        // Reaktivieren des Autopiloten direkt auf der Lieferzeiten-Seite zieht sofort wieder das Last-Profil ("Standard") nach!
        $comp->call('toggleCapacityAutopilot')
            ->assertSet('capacityAutopilotEnabled', true);

        $this->assertSame('Standard', DeliveryTime::where('is_active', true)->value('name'));

        // Auch MasterShopCapacity spiegelt denselben Zustand 1:1
        Livewire::actingAs($admin, 'admin')
            ->test(MasterShopCapacity::class)
            ->assertSet('autoPilotEnabled', true)
            ->assertSee('Puffer-Pakete');
    }

    public function test_postal_working_days_skip_all_intermediate_sundays_and_german_holidays_without_gedankenstriche(): void
    {
        // Mittwoch, 23.12.2026 + 3 Post-Werktage:
        // Do 24.12. (Werktag 1), Fr 25.12. (1. Weihnachtstag -> übersprungen), Sa 26.12. (2. Weihnachtstag -> übersprungen),
        // So 27.12. (Sonntag -> übersprungen), Mo 28.12. (Werktag 2), Di 29.12. (Werktag 3)!
        $base = Carbon::create(2026, 12, 23, 10, 0, 0);
        $target = DeliverySetting::addPostalWorkingDays($base, 3);

        $this->assertSame('2026-12-29', $target->format('Y-m-d'));

        // Bei 14 Werktagen ab Montag müssen mindestens 2 dazwischenliegende Sonntage übersprungen werden (>= 16 Kalendertage)
        $mon = Carbon::create(2026, 11, 2, 10, 0, 0);
        $after14WorkingDays = DeliverySetting::addPostalWorkingDays($mon, 14);
        $this->assertGreaterThanOrEqual(16, (int) $mon->diffInDays($after14WorkingDays));
        $this->assertFalse($after14WorkingDays->isSunday());

        $details = DeliverySetting::getDeliveryDetails();
        $this->assertStringNotContainsString(' – ', $details['full_text']);
        $this->assertStringNotContainsString(' — ', $details['full_text']);
    }
}
