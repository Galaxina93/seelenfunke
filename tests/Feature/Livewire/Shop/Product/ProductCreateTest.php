<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductCreate;
use App\Livewire\Shop\Product\ProductIndex;
use App\Livewire\Shop\Product\ProductTax;
use App\Models\Product\ProductCategory;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductCreateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\Admin\Admin::factory()->create(), 'admin');
        
        // Populate standard tax rates for test
        DB::table('tax_rates')->insert([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Ermäßigt DE', 'rate' => 7.00, 'country_code' => 'DE', 'tax_class' => 'reduced', 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        // Optionally: mock global shop settings if we need to assure "is_small_business" is false
        // For simplicity, we assume helper handles this or default is appropriate.
    }

    #[Test]
    public function it_creates_a_draft_product()
    {
        $initialCount = Product::count();

        Livewire::test(ProductCreate::class)
            ->call('createDraft')
            ->assertSet('viewMode', 'edit')
            ->assertSet('currentStep', 1)
            ->assertStatus(200);

        $this->assertDatabaseCount('products', $initialCount + 1);
        $this->assertDatabaseHas('products', [
            'status' => 'draft',
            'type' => 'physical'
        ]);
    }

    #[Test]
    public function basic_product_validation_and_step_navigation()
    {
        $product = Product::create([
            'name' => 'Test Draft',
            'slug' => 'test-draft',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 0,
            'completion_step' => 1
        ]);

        $component = Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->set('name', '') // Invalid: name must be required
            ->set('price_input', 10.50)
            ->set('sku', 'TEST-SKU-1')
            ->set('slug_input', 'test-slug-1')
            ->set('type', 'physical')
            ->set('purchase_price_input', 5.00);

        // canProceed should be false because name is empty
        $this->assertFalse($component->instance()->canProceed());

        // Now set valid
        $component->set('name', 'Valid Product Name');
        $this->assertTrue($component->instance()->canProceed());

        // Move to step 2
        $component->call('nextStep')
            ->assertSet('currentStep', 2);
            
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Valid Product Name',
            'price' => 1050, // 10.50 * 100
            'sku' => 'TEST-SKU-1'
        ]);
    }

    #[Test]
    public function it_changes_type_and_adjusts_total_steps()
    {
        $product = Product::create([
            'name' => 'Test', 'slug' => 'p1', 'status' => 'draft', 'type' => 'physical', 'price' => 0
        ]);

        Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->assertSet('totalSteps', 4)
            ->set('type', 'digital')
            ->assertSet('totalSteps', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'type' => 'digital',
            'weight' => null // physical attributes cleaned
        ]);
    }

    #[Test]
    public function it_handles_file_uploads_for_digital_products()
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('ebook.pdf', 1000, 'application/pdf');

        $product = Product::create([
            'name' => 'Digital Book', 'slug' => 'digital-book', 'type' => 'digital', 'price' => 0
        ]);

        Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->set('new_digital_file', $file)
            ->assertHasNoErrors();

        $product->refresh();
        $this->assertNotNull($product->digital_download_path);
        
        Storage::disk('local')->assertExists($product->digital_download_path);
    }

    #[Test]
    public function test_physical_product_wizard_integration()
    {
        Storage::fake('public');

        $component = Livewire::test(ProductCreate::class)
            ->call('createDraft')
            ->set('name', 'Physisches Benchmark Produkt')
            ->set('sku', 'BENCH-PHYS-001')
            ->set('price_input', '19.99')
            ->set('purchase_price_input', '5.00')
            ->set('type', 'physical');

        // Verify Step 1 Validation Gate
        $this->assertTrue($component->instance()->canProceed(), 'Physical step 1 should proceed');
            
        $component->call('nextStep')
            ->assertSet('currentStep', 2);

        // Step 2 Requires Media
        $this->assertFalse($component->instance()->canProceed(), 'Step 2 should block without media');

        $image = UploadedFile::fake()->image('product.jpg');
        $component->set('new_media', [$image])
            ->call('nextStep')
            ->assertSet('currentStep', 3);

        // Step 3 (Lager & Attribute) & Shipping
        $component->set('weight', 1050)
            ->set('shipping_class', 'sperrgut')
            ->set('laser_runtime_minutes', 45)
            ->set('packaging_cost_input', '2.50')
            ->set('track_quantity', true)
            ->set('quantity', 10)
            ->call('nextStep')
            ->assertSet('currentStep', 4);

        $component->call('finish')
            ->assertSet('viewMode', 'list');

        $product = Product::firstWhere('sku', 'BENCH-PHYS-001');
        $this->assertEquals('active', $product->status);
        $this->assertEquals(4, $product->completion_step);
        $this->assertEquals(1999, $product->price);
        $this->assertEquals(500, $product->purchase_price);
        $this->assertEquals(1050, $product->weight);
        $this->assertEquals('sperrgut', $product->shipping_class);
        $this->assertEquals(45, $product->laser_runtime_minutes);
        $this->assertEquals(250, $product->packaging_cost);
        $this->assertEquals(10, $product->quantity);
    }

    #[Test]
    public function test_digital_product_wizard_integration()
    {
        Storage::fake('public');
        Storage::fake('local');

        // Create Draft & Test Setup
        $component = Livewire::test(ProductCreate::class)
            ->call('createDraft')
            ->set('type', 'digital')
            ->set('name', 'Digitales E-Book')
            ->set('sku', 'BENCH-DIGI-001')
            ->set('price_input', '9.99');

        // Digital shouldn't care about purchase price or weight
        $this->assertTrue($component->instance()->canProceed());
            
        $component->call('nextStep')
            ->assertSet('currentStep', 2);

        // Step 2 Media + Secure Digital File
        $image = UploadedFile::fake()->image('cover.jpg');
        $digitalFile = UploadedFile::fake()->create('ebook.pdf', 1000, 'application/pdf');

        $component->set('new_media', [$image])
            ->set('new_digital_file', $digitalFile)
            ->call('nextStep')
            ->assertSet('currentStep', 3); // Digital only has 3 steps

        $component->call('finish')
            ->assertSet('viewMode', 'list');

        $product = Product::firstWhere('sku', 'BENCH-DIGI-001');
        $this->assertEquals('active', $product->status);
        $this->assertEquals('digital', $product->type);
        $this->assertEquals(3, $product->completion_step);
        $this->assertEquals(999, $product->price);
        $this->assertEquals(0, $product->purchase_price);
        $this->assertNull($product->weight);
        $this->assertNotNull($product->digital_download_path);
        $this->assertFalse((bool) $product->track_quantity);
    }

    #[Test]
    public function test_service_product_wizard_integration()
    {
        Storage::fake('public');

        $component = Livewire::test(ProductCreate::class)
            ->call('createDraft')
            ->set('type', 'service')
            ->set('name', 'Workshop Ticket')
            ->set('sku', 'BENCH-SRV-001')
            ->set('price_input', '149.00');

        $this->assertTrue($component->instance()->canProceed());
            
        $component->call('nextStep')
            ->assertSet('currentStep', 2);

        // Upload media (required)
        $image = UploadedFile::fake()->image('workshop.jpg');
        $component->set('new_media', [$image])
            ->call('nextStep')
            ->assertSet('currentStep', 3); // Services only have 3 steps

        // Set quantity to limit bookable spots
        $component->set('track_quantity', true)
            ->set('quantity', 5)
            ->call('finish')
            ->assertSet('viewMode', 'list');

        $product = Product::firstWhere('sku', 'BENCH-SRV-001');
        $this->assertEquals('active', $product->status);
        $this->assertEquals('service', $product->type);
        $this->assertEquals(3, $product->completion_step);
        $this->assertEquals(14900, $product->price);
        $this->assertTrue((bool) $product->track_quantity);
        $this->assertEquals(5, $product->quantity);
    }

    #[Test]
    public function tax_component_updates_tax_class_and_emits_event()
    {
        $product = Product::create([
            'name' => 'Tax Product', 'slug' => 'tax-p', 'tax_class' => 'standard', 'price' => 0
        ]);

        Livewire::test(ProductTax::class, ['product' => $product])
            ->assertSet('tax_class', 'standard')
            ->assertSet('current_tax_rate', 19.00)
            ->set('tax_class', 'reduced')
            ->assertSet('current_tax_rate', 7.00)
            ->assertDispatched('product-updated')
            ->assertDispatched('tax-saved');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'tax_class' => 'reduced'
        ]);
    }

    #[Test]
    public function index_component_filters_products()
    {
        // Maintenance bypass
        config(['shop.maintenance_mode' => false]);
        
        $c1 = ProductCategory::create(['name' => 'Books', 'slug' => 'books']);
        $p1 = Product::create(['name' => 'Physical Book', 'slug' => 'pb', 'status' => 'active', 'type' => 'physical', 'price' => 1500]);
        $p2 = Product::create(['name' => 'Ebook PDF', 'slug' => 'ep', 'status' => 'active', 'type' => 'digital', 'price' => 900]);
        $p1->categories()->attach($c1);

        // Filter by Search
        Livewire::test(ProductCreate::class)
            ->set('search', 'Physical')
            ->assertSee('Physical Book')
            ->assertDontSee('Ebook PDF');
    }

    #[Test]
    public function it_switches_and_persists_layout_mode_for_product_overview()
    {
        Product::create([
            'name' => 'Ansichts-Testprodukt',
            'slug' => 'ansichts-testprodukt',
            'sku' => 'VIEW-001',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'completion_step' => 4,
        ]);

        // Default layout is 'large' and renders all 3 view toggle buttons
        Livewire::test(ProductCreate::class)
            ->assertSet('layoutMode', 'large')
            ->assertSee('Groß')
            ->assertSee('Mittel')
            ->assertSee('Liste')
            ->call('setLayoutMode', 'medium')
            ->assertSet('layoutMode', 'medium')
            ->call('setLayoutMode', 'list')
            ->assertSet('layoutMode', 'list')
            ->call('setLayoutMode', 'invalid_mode')
            ->assertSet('layoutMode', 'list');

        $this->assertDatabaseHas('shop_settings', [
            'key' => 'admin_products_layout_mode',
            'value' => 'list',
        ]);

        // Fresh component mount automatically restores the saved layout mode from SystemSetting
        Livewire::test(ProductCreate::class)
            ->assertSet('layoutMode', 'list')
            ->assertSee('Ansichts-Testprodukt');
    }

    #[Test]
    public function it_toggles_assortment_and_creates_quick_ai_product_per_knopfdruck()
    {
        $product = Product::create([
            'name' => 'Sortiment-Test',
            'slug' => 'sortiment-test',
            'sku' => 'SORT-001',
            'status' => 'active',
            'type' => 'physical',
            'price' => 1990,
            'completion_step' => 4,
        ]);

        Livewire::test(ProductCreate::class)
            ->call('toggleAssortment', $product->id);

        $this->assertEquals('draft', $product->fresh()->status);

        Livewire::test(ProductCreate::class)
            ->call('toggleAssortment', $product->id);

        $this->assertEquals('active', $product->fresh()->status);

        // Toggle Origin Badge (handcrafted_in_germany <-> none, § 5 UWG / Madrider Abkommen)
        $this->assertEquals('handcrafted_in_germany', $product->fresh()->getOriginBadgeMode());
        Livewire::test(ProductCreate::class)
            ->call('toggleOriginBadge', $product->id);
        $this->assertEquals('none', $product->fresh()->getOriginBadgeMode());
        Livewire::test(ProductCreate::class)
            ->call('toggleOriginBadge', $product->id);
        $this->assertEquals('handcrafted_in_germany', $product->fresh()->getOriginBadgeMode());
        $this->assertEquals('Handveredelt in Deutschland', $product->fresh()->getOriginBadgeLabel());

        // Create a turnkey AI product per 1-click
        Livewire::test(ProductCreate::class)
            ->call('openQuickAiModal')
            ->assertSet('showQuickAiModal', true)
            ->set('quickAiReorderUrl', 'https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html')
            ->set('quickAiName', 'Der Seelen-Talisman Test')
            ->set('quickAiSku', 'STEEL-TEST-99')
            ->set('quickAiPrice', '19.90')
            ->set('quickAiPurchasePrice', '1.16')
            ->call('createQuickAiProduct')
            ->assertSet('showQuickAiModal', false);

        $created = Product::where('sku', 'STEEL-TEST-99')->first();
        $this->assertNotNull($created);
        $this->assertEquals('active', $created->status);
        $this->assertEquals(4, $created->completion_step);
        $this->assertEquals(1990, $created->price);
        $this->assertEquals(116, $created->purchase_price);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_manages_custom_fields_and_configurator_fonts_in_product_create_and_quick_ai(): void
    {
        $product = Product::create([
            'name' => 'Schnell-Gravur Herz',
            'slug' => 'schnell-gravur-herz',
            'sku' => 'GOLD-HEART-099',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2490,
            'is_personalizable' => true,
            'completion_step' => 4,
        ]);

        $component = Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->call('setPersonalizationMode', 'custom_fields')
            ->call('applyCustomFieldsPreset', 'name_and_font');

        $fields = $component->get('configSettings.custom_fields');
        $this->assertCount(2, $fields);
        $this->assertSame('text', $fields[0]['type']);
        $this->assertTrue($fields[0]['required']);
        $this->assertNotEmpty($fields[0]['info_text']);
        $this->assertSame('font', $fields[1]['type']);
        $this->assertFalse($fields[1]['required']);
        $this->assertSame('', $fields[1]['info_text']);
        $this->assertSame('Arial', $fields[1]['options'][0]);
        $this->assertCount(22, $fields[1]['options']);

        // Filter fonts via preset and toggle single font
        $component->call('setCustomFieldFontPreset', 1, 'jewelry_top6');
        $this->assertCount(6, $component->get('configSettings.custom_fields.1.options'));

        $component->call('toggleCustomFieldFont', 1, 'Great Vibes');
        $this->assertCount(5, $component->get('configSettings.custom_fields.1.options'));

        // Add additional text and font fields
        $component->call('addCustomField')
            ->call('addCustomFontField');
        $this->assertCount(4, $component->get('configSettings.custom_fields'));
        $this->assertSame('font', $component->get('configSettings.custom_fields.3.type'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_switches_custom_field_types_and_enforces_pangv_surcharge_and_font_rules(): void
    {
        $product = Product::create([
            'name' => 'Logik-Test Anhänger',
            'slug' => 'logik-test-anhaenger',
            'sku' => 'LOGIC-TEST-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'is_personalizable' => true,
            'completion_step' => 4,
        ]);

        $component = Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->call('setPersonalizationMode', 'custom_fields')
            ->call('applyCustomFieldsPreset', 'single_name');

        // 1. Start: single_name ('text', required = true, has info_text)
        $this->assertSame('text', $component->get('configSettings.custom_fields.0.type'));
        $this->assertTrue($component->get('configSettings.custom_fields.0.required'));
        $this->assertNotEmpty($component->get('configSettings.custom_fields.0.info_text'));

        // 2. Switch type from 'text' to 'font' -> clears info_text, required = false, surcharge = 0, options = 22 fonts starting with Arial
        $component->set('configSettings.custom_fields.0.type', 'font');
        $this->assertSame('Schriftart wählen', $component->get('configSettings.custom_fields.0.label'));
        $this->assertSame('', $component->get('configSettings.custom_fields.0.info_text'));
        $this->assertFalse($component->get('configSettings.custom_fields.0.required'));
        $this->assertSame('Arial', $component->get('configSettings.custom_fields.0.options.0'));
        $this->assertCount(22, $component->get('configSettings.custom_fields.0.options'));

        // 3. Switch type from 'font' to 'select' -> replaces the 22 font names with symbol dropdown options & restores info_text + required
        $component->set('configSettings.custom_fields.0.type', 'select');
        $this->assertSame('Auswahl / Symbol', $component->get('configSettings.custom_fields.0.label'));
        $this->assertTrue($component->get('configSettings.custom_fields.0.required'));
        $this->assertNotEmpty($component->get('configSettings.custom_fields.0.info_text'));
        $this->assertContains('Kein Symbol', $component->get('configSettings.custom_fields.0.options'));
        $this->assertNotContains('Times New Roman', $component->get('configSettings.custom_fields.0.options'));

        // 4. Switch type to 'date' -> clears placeholder, sets date info_text
        $component->set('configSettings.custom_fields.0.type', 'date');
        $this->assertSame('Wunschdatum', $component->get('configSettings.custom_fields.0.label'));
        $this->assertSame('', $component->get('configSettings.custom_fields.0.placeholder'));
        $this->assertStringContainsString('Datum', $component->get('configSettings.custom_fields.0.info_text'));

        // 5. Switch type to 'textarea' -> bumps max_length to 150
        $component->set('configSettings.custom_fields.0.type', 'textarea');
        $this->assertSame(150, $component->get('configSettings.custom_fields.0.max_length'));
        $this->assertTrue($component->get('configSettings.custom_fields.0.required'));

        // 6. PAngV-Schutz: Setting surcharge_price > 0 on a text/textarea/date field automatically sets required = false
        $component->set('configSettings.custom_fields.0.surcharge_price', '5.00');
        $this->assertFalse($component->get('configSettings.custom_fields.0.required'));

        $persistedDefs = $product->fresh()->getCustomFieldsDefinition();
        $this->assertFalse($persistedDefs[0]['required']);
        $this->assertSame(500, $persistedDefs[0]['surcharge_cents']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_font_default_arial_date_formatting_and_long_select_options_in_configurator_and_cart(): void
    {
        $product = Product::create([
            'name' => 'Kombi-Unikat',
            'slug' => 'kombi-unikat',
            'sku' => 'KOMBI-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'is_personalizable' => true,
            'completion_step' => 4,
            'configurator_settings' => [
                'personalization_mode' => 'custom_fields',
                'custom_fields' => [
                    [
                        'id' => 'cf_font',
                        'label' => 'Schriftart wählen',
                        'type' => 'font',
                        'required' => true, // Should be normalized to false
                        'info_text' => 'Alter Text der verschwinden muss', // Should be normalized to ''
                        'surcharge_price' => '4.00', // Should be normalized to 0
                    ],
                    [
                        'id' => 'cf_name',
                        'label' => 'Wunschtext',
                        'type' => 'text',
                        'required' => true,
                        'max_length' => 25,
                    ],
                    [
                        'id' => 'cf_date',
                        'label' => 'Wunschdatum',
                        'type' => 'date',
                        'required' => false,
                    ],
                    [
                        'id' => 'cf_symbol',
                        'label' => 'Gravur-Symbol',
                        'type' => 'select',
                        'required' => true,
                        'max_length' => 15, // Intentionally small to test that select options > 15 chars are NOT truncated
                        'options_raw' => 'Kein Symbol, Unendlichkeitssymbol mit Doppelherz (Groß)',
                    ],
                ],
            ],
        ]);

        // 1. Verify normalization on Product model
        $defs = $product->getCustomFieldsDefinition();
        $this->assertFalse($defs[0]['required']);
        $this->assertSame('', $defs[0]['info_text']);
        $this->assertSame(0, $defs[0]['surcharge_cents']);
        $this->assertSame('Arial', $defs[0]['options'][0]);

        // 2. Mount ProductConfigurator when 'font' is at index 0 and legacy text is passed:
        //    'cf_font' must stay 'Arial', and 'cf_name' (index 1, first text field) receives the legacy text!
        $configurator = Livewire::test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
            'product' => $product,
            'initialData' => ['text' => 'Emilia'],
            'context' => 'add',
        ]);

        $this->assertSame('Arial', $configurator->get('customFieldValues.cf_font'));
        $this->assertSame('Emilia', $configurator->get('customFieldValues.cf_name'));

        // 3. Set HTML5 ISO date (2026-08-14) and long select option (> 15 chars) and save to cart
        $longOption = 'Unendlichkeitssymbol mit Doppelherz (Groß)';
        $configurator
            ->set('customFieldValues.cf_date', '2026-08-14')
            ->set('customFieldValues.cf_symbol', $longOption)
            ->set('config_confirmed', true)
            ->call('save');

        $cart = app(\App\Services\CartService::class)->getCart();
        $cartItem = $cart->items()->first();
        $this->assertNotNull($cartItem);

        $cfData = collect($cartItem->configuration['custom_fields_data'])->keyBy('id');
        $this->assertSame('Arial', $cfData['cf_font']['value']);
        $this->assertSame('Emilia', $cfData['cf_name']['value']);
        // Date must be converted from ISO 2026-08-14 to German 14.08.2026
        $this->assertSame('14.08.2026', $cfData['cf_date']['value']);
        // Long select option must NOT be truncated by max_length=15
        $this->assertSame($longOption, $cfData['cf_symbol']['value']);

        // 4. Re-mounting ProductConfigurator in edit mode converts German 14.08.2026 back to ISO 2026-08-14 for <input type="date">
        $editConfigurator = Livewire::test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
            'product' => $product,
            'initialData' => $cartItem->configuration,
            'context' => 'edit',
        ]);
        $this->assertSame('2026-08-14', $editConfigurator->get('customFieldValues.cf_date'));
    }
}
