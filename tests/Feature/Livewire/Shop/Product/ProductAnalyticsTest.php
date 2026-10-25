<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductAnalytics;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\Product\ProductLoss;
use App\Models\Product\ProductReview;
use App\Models\Product\ProductSupplier;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAnalyticsTest extends TestCase
{
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::firstOrCreate(
            ['email' => 'admin-analytics-test@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Analytics',
                'password' => bcrypt('secret123'),
            ]
        );
    }

    public function test_guest_cannot_access_product_analytics(): void
    {
        Livewire::test(ProductAnalytics::class)
            ->assertForbidden();
    }

    public function test_it_correctly_groups_products_by_supplier_in_supplier_risk_chart(): void
    {
        $this->actingAs($this->admin, 'admin');

        // 1. Zwei Lieferanten erstellen
        $supplierA = ProductSupplier::create([
            'name' => 'Holzmanufaktur Nord',
            'shipping_method' => 'land',
            'lead_time_land_days' => 5,
        ]);

        $supplierB = ProductSupplier::create([
            'name' => 'Glasbläserei Süd',
            'shipping_method' => 'air',
            'lead_time_air_days' => 8,
        ]);

        // 2. Produkte erstellen mit und ohne Lieferant
        $prodA1 = Product::create([
            'name' => 'Holzbox Alpha',
            'slug' => 'holzbox-alpha-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1990,
            'product_supplier_id' => $supplierA->id,
        ]);

        $prodA2 = Product::create([
            'name' => 'Holzbox Beta',
            'slug' => 'holzbox-beta-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2490,
            'product_supplier_id' => $supplierA->id,
        ]);

        $prodB1 = Product::create([
            'name' => 'Glasuntersetzer',
            'slug' => 'glasuntersetzer-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 1490,
            'product_supplier_id' => $supplierB->id,
        ]);

        $prodNoSupp = Product::create([
            'name' => 'Eigenbau Unikat',
            'slug' => 'eigenbau-unikat-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3990,
            'product_supplier_id' => null,
        ]);

        // 3. Product-Relation direkt testen
        $this->assertNotNull($prodA1->supplier);
        $this->assertEquals('Holzmanufaktur Nord', $prodA1->supplier->name);
        $this->assertEquals('Glasbläserei Süd', $prodB1->supplier->name);
        $this->assertNull($prodNoSupp->supplier);

        // 4. Livewire ProductAnalytics testen
        $component = Livewire::test(ProductAnalytics::class);

        $labels = $component->get('supplierData')['labels'] ?? [];
        $data = $component->get('supplierData')['data'] ?? [];

        // Sicherstellen, dass Lieferantennamen statt 'Ohne Lieferant' erscheinen
        $this->assertContains('Holzmanufaktur Nord', $labels);
        $this->assertContains('Glasbläserei Süd', $labels);
        $this->assertContains('Ohne Lieferant', $labels);

        $indexA = array_search('Holzmanufaktur Nord', $labels);
        $this->assertGreaterThanOrEqual(2, $data[$indexA]);

        $indexB = array_search('Glasbläserei Süd', $labels);
        $this->assertGreaterThanOrEqual(1, $data[$indexB]);

        // 5. Combined Data prüfen (Lieferantenname, Lieferzeit & Stripe-Gebühr in Tabelle)
        $combinedData = ProductAnalytics::getCombinedAnalyticsData();
        $foundA1 = $combinedData->firstWhere('id', $prodA1->id);
        $this->assertNotNull($foundA1);
        $this->assertEquals('Holzmanufaktur Nord', $foundA1['supplier_name']);
        $this->assertEquals(5, $foundA1['delivery_days']);
        $this->assertEquals(0.80, $foundA1['payment_cost']);

        $foundB1 = $combinedData->firstWhere('id', $prodB1->id);
        $this->assertNotNull($foundB1);
        $this->assertEquals('Glasbläserei Süd', $foundB1['supplier_name']);
        $this->assertEquals(8, $foundB1['delivery_days']);
    }

    public function test_it_computes_losses_and_reviews(): void
    {
        $this->actingAs($this->admin, 'admin');

        $product = Product::create([
            'name' => 'Schadensartikel Test',
            'slug' => 'schadensartikel-test-' . Str::random(6),
            'status' => 'active',
            'type' => 'physical',
            'price' => 5000,
        ]);

        // Verlust anlegen
        ProductLoss::create([
            'product_id' => $product->id,
            'quantity' => 3,
            'cost_value' => 7500,
            'reason' => 'Bruch beim Lasern',
        ]);

        // Kunde anlegen
        $customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max-review-' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
        ]);

        // Bewertung anlegen
        ProductReview::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'title' => 'Top!',
            'content' => 'Hervorragende Qualität!',
            'status' => 'approved',
        ]);

        $component = Livewire::test(ProductAnalytics::class);

        $reviewLabels = $component->get('reviewData')['labels'] ?? [];
        $this->assertContains('5 Sterne', $reviewLabels);

        $topLossLabels = $component->get('topLossData')['labels'] ?? [];
        $this->assertContains('Schadensartikel Test', $topLossLabels);
    }

    public function test_it_switches_tabs_and_updates_date_ranges(): void
    {
        $this->actingAs($this->admin, 'admin');

        $component = Livewire::test(ProductAnalytics::class);

        // Standard: configurator
        $component->assertSet('activeTab', 'configurator');

        // Umschalten auf calculator
        $component->call('setTab', 'calculator')
            ->assertSet('activeTab', 'calculator');

        // Umschalten auf economics
        $component->call('setTab', 'economics')
            ->assertSet('activeTab', 'economics');

        // DateRange ändern
        $component->set('dateRange', '7');
        $this->assertEquals('7', $component->get('dateRange'));
    }
}
