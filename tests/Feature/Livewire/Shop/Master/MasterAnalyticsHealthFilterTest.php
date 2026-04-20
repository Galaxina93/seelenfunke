<?php

namespace Tests\Feature\Livewire\Shop\Master;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Order\OrderOrder;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerProfile;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class MasterAnalyticsHealthFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * EXPERT COMMENT: Operational Alarms vs Analytical Filters
     * 
     * Dieser Test stellt sicher, dass analytische Filter (bspw. "Gewerbe" vs "Privat") 
     * KEINEN Einfluss auf die operativen Shop-Health Metriken haben. 
     * Ein Administrator darf keine wichtigem Alarme/To-Dos (Shop Health) verpassen, 
     * nur weil er aktuell eine Analytics-Gewerbe-Statistik auswertet. 
     * Die Metriken (Umsatz) werden gefiltert, das Alerting bleibt absolut (!).
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function analytical_filters_do_not_exclude_operational_health_checks()
    {
        // 1. Setup Admin
        $admin = Admin::factory()->create();

        // 2. Setup B2B und B2C Kunden
        $b2bCustomer = Customer::factory()->create();
        CustomerProfile::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'customer_id' => $b2bCustomer->id, 'is_business' => true]);

        $b2cCustomer = Customer::factory()->create();
        CustomerProfile::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'customer_id' => $b2cCustomer->id, 'is_business' => false]);

        // 3. Generiere operative Daten: Bestellungen (Pending)
        OrderOrder::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $b2bCustomer->id,
            'email' => 'test@example.com',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'shipping_price' => 500,
            'total_price' => 1690,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        OrderOrder::forceCreate([
            'id' => \Illuminate\Support\Str::uuid(),
            'order_number' => 'ORD-' . uniqid(),
            'customer_id' => $b2cCustomer->id,
            'email' => 'test@example.com',
            'billing_address' => [],
            'shipping_address' => [],
            'subtotal_price' => 1000,
            'tax_amount' => 190,
            'shipping_price' => 500,
            'total_price' => 1690,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // 4. Generiere operative Daten: Abandoned Carts (Rot / Über 24h)
        $product = Product::forceCreate(['id' => \Illuminate\Support\Str::uuid(), 'name' => 'Test', 'slug' => 'test-' . uniqid(), 'price' => 1000]);
        $b2bCart = Cart::create(['session_id' => '123', 'customer_id' => $b2bCustomer->id]);
        CartItem::create(['cart_id' => $b2bCart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $b2bCart->id)->update(['updated_at' => Carbon::now()->subHours(25)]);

        $b2cCart = Cart::create(['session_id' => '456', 'customer_id' => $b2cCustomer->id]);
        CartItem::create(['cart_id' => $b2cCart->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        \Illuminate\Support\Facades\DB::table('carts')->where('id', $b2cCart->id)->update(['updated_at' => Carbon::now()->subHours(25)]);

        // 5. Testlauf Component Loading
        $component = Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class);

        // a) Standardzustand (filterType = all)
        // Sollte 2 offene Bestellungen melden (B2B + B2C)
        // Sollte 2 verlassene Körbe melden (B2B + B2C)
        $initialHealthChecks = $component->get('healthChecks');
        $this->assertEquals(2, $initialHealthChecks['open_orders']['count'], "Initiale offenen Bestellungen (All) müssen 2 sein.");
        $this->assertEquals(2, $initialHealthChecks['open_abandoned_carts']['count'], "Initiale Abandoned Carts (All) müssen 2 sein.");

        // 6. Ändere Filter auf B2B ("business")
        $component->set('filterType', 'business');

        // b) Gefilterter Zustand
        // Analytics würde sich ändern, aber HealthChecks MÜSSEN IDENTISCH BLEIBEN (Isolation)
        $filteredHealthChecks = $component->get('healthChecks');
        
        $this->assertEquals(2, $filteredHealthChecks['open_orders']['count'], "Gefilterte offenen Bestellungen (Business) müssen weiterhin 2 sein (Sicherheitsmechanismus).");
        $this->assertEquals(2, $filteredHealthChecks['open_abandoned_carts']['count'], "Gefilterte Abandoned Carts (Business) müssen weiterhin 2 sein (Sicherheitsmechanismus).");

        // 7. Ändere Filter auf B2C ("private")
        $component->set('filterType', 'private');
        $filteredPrivateHealthChecks = $component->get('healthChecks');
        
        $this->assertEquals(2, $filteredPrivateHealthChecks['open_orders']['count'], "Gefilterte offenen Bestellungen (Private) müssen weiterhin 2 sein (Sicherheitsmechanismus).");
        $this->assertEquals(2, $filteredPrivateHealthChecks['open_abandoned_carts']['count'], "Gefilterte Abandoned Carts (Private) müssen weiterhin 2 sein (Sicherheitsmechanismus).");
    }
}
