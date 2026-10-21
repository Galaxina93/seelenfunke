<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductAnalytics;
use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Livewire\Shop\Product\ProductShow;
use App\Models\Admin\Admin;
use App\Models\Product\Product;
use App\Models\Product\ProductAnalyticsEvent;
use App\Models\Product\ProductTemplate;
use App\Services\ProductTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ProductAnalyticsTrackingTest extends TestCase
{
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'name' => 'Test Holzschild',
            'slug' => 'test-holzschild-tracking-' . Str::random(8),
            'sku' => 'TEST-TRACK-' . Str::random(6),
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'is_personalizable' => true,
        ]);
    }

    public function test_product_tracking_service_writes_anonymous_event(): void
    {
        ProductTrackingService::track('configurator', 'test_event', [
            'product_id' => $this->product->id,
            'item_identifier' => 'test_item',
            'value_cents' => 1500,
        ]);

        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'test_event',
            'product_id' => $this->product->id,
            'item_identifier' => 'test_item',
            'value_cents' => 1500,
        ]);

        $event = ProductAnalyticsEvent::where('event_name', 'test_event')->first();
        $this->assertNotNull($event);
        $this->assertEquals(64, strlen($event->session_hash)); // SHA-256 hash
    }

    public function test_product_show_tracks_view_mode_and_template_selection(): void
    {
        $template = ProductTemplate::create([
            'product_id' => $this->product->id,
            'name' => 'Liebes-Vorlage',
            'is_active' => true,
            'configuration' => ['text' => 'Liebe'],
        ]);

        // 1. Aufruf der Produktseite (View)
        $component = Livewire::test(ProductShow::class, ['product' => $this->product]);

        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'configurator_view',
            'product_id' => $this->product->id,
        ]);

        // 2. Modus: Vorlage nutzen
        $component->call('openTemplatesList');
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'mode_chosen',
            'item_identifier' => 'template',
        ]);

        // 3. Vorlage auswählen
        $component->call('selectTemplate', $template->id);
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'template_selected',
            'template_id' => $template->id,
        ]);

        // 4. Modus: Selbst konfigurieren
        $component->call('startCustomConfig');
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'mode_chosen',
            'item_identifier' => 'custom',
        ]);
    }

    public function test_product_configurator_tracks_symbol_and_express(): void
    {
        $component = Livewire::test(ProductConfigurator::class, [
            'product' => $this->product,
            'context' => 'add',
        ]);

        // Symbol hinzufügen
        $component->call('addStandardVector', 'herz_01.svg');
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'symbol_added',
            'item_identifier' => 'herz_01.svg',
        ]);

        // Express toggeln
        $component->set('is_express', true);
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'configurator',
            'event_name' => 'express_toggled',
            'item_identifier' => 'enabled',
        ]);
    }

    public function test_product_calculator_tracks_funnel_progression(): void
    {
        // 1. Initialer Aufruf
        $component = Livewire::test(ProductCalculator::class);
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'calculator',
            'event_name' => 'calc_view',
            'step' => 0,
        ]);

        // 2. AGB nicht akzeptiert -> Validierungsfehler
        $component->set('agb_accepted', false);
        $component->call('startCalculator');
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'calculator',
            'event_name' => 'calc_validation_failed',
            'step' => 0,
        ]);

        // 3. AGB akzeptiert -> Rechner aktiv
        $component->set('agb_accepted', true);
        $component->call('startCalculator');
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'calculator',
            'event_name' => 'calc_started',
            'step' => 1,
        ]);

        // 4. Produkt zur Konfiguration geöffnet
        $component->call('openConfig', $this->product->id);
        $this->assertDatabaseHas('product_analytics_events', [
            'source' => 'calculator',
            'event_name' => 'calc_product_opened',
            'product_id' => $this->product->id,
            'step' => 2,
        ]);
    }

    public function test_product_analytics_admin_component_renders_and_computes_stats(): void
    {
        $admin = Admin::first();
        if (!$admin) {
            $admin = Admin::create([
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin-analytics@example.com',
                'password' => bcrypt('password123'),
            ]);
        }

        $this->actingAs($admin, 'admin');

        // Vorbereitete Telemetriedaten erzeugen
        ProductAnalyticsEvent::create([
            'session_hash' => hash('sha256', 'session_test'),
            'source' => 'configurator',
            'event_name' => 'mode_chosen',
            'item_identifier' => 'template',
            'created_at' => now(),
        ]);

        ProductAnalyticsEvent::create([
            'session_hash' => hash('sha256', 'session_test'),
            'source' => 'configurator',
            'event_name' => 'symbol_added',
            'item_identifier' => 'herz_01.svg',
            'created_at' => now(),
        ]);

        ProductAnalyticsEvent::create([
            'session_hash' => hash('sha256', 'session_test'),
            'source' => 'calculator',
            'event_name' => 'calc_view',
            'step' => 0,
            'created_at' => now(),
        ]);

        $component = Livewire::test(ProductAnalytics::class)
            ->assertOk()
            ->assertSee('Produktkonfigurator')
            ->assertSee('Mengenrechner (/calculator)')
            ->assertSee('Wirtschaftlichkeit & LUCID', false);

        // Tab umschalten auf Calculator
        $component->call('setTab', 'calculator')
            ->assertSet('activeTab', 'calculator')
            ->assertSee('5-Stufen-Trichter');

        // Tab umschalten auf Economics
        $component->call('setTab', 'economics')
            ->assertSet('activeTab', 'economics')
            ->assertSee('Wahre Zahlen');
    }
}
