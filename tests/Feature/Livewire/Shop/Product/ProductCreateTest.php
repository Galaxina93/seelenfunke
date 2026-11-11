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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_supports_three_step_start_selection_neuling_anfaenger_profi_and_beginner_gallery_jump(): void
    {
        $product = Product::create([
            'name' => 'Der Seelen-Starttest',
            'slug' => 'seelen-starttest',
            'sku' => 'START-3-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'is_personalizable' => true,
            'three_d_active' => true,
            'completion_step' => 4,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/test/main-normal.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => false, 'is_ai_generated' => true],
                ['type' => 'image', 'path' => 'produkte/test/neuling-preview.jpg', 'is_main' => false, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true],
            ],
            'configurator_settings' => [
                'personalization_mode' => 'studio',
            ],
        ]);

        \App\Models\Product\ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Weihnachts-Vorlage',
            'holiday' => 'Weihnachten',
            'is_active' => true,
            'configuration' => ['texts' => [['id' => 't1', 'text' => 'Frohes Fest', 'font' => 'Cinzel', 'x' => 50, 'y' => 50, 'size' => 1.0]]],
        ]);

        // 1. Mount ProductShow: must show 3 start options (Neuling -> Anfänger -> Profi)
        $showComp = Livewire::test(\App\Livewire\Shop\Product\ProductShow::class, ['product' => $product])
            ->assertSet('showTemplateSelection', true)
            ->assertSee('Wie möchtest du starten?')
            ->assertSee('Neuling')
            ->assertSee('Einfache Variante')
            ->assertSee('Anfänger')
            ->assertSee('Vorlage nutzen')
            ->assertSee('Profi')
            ->assertSee('Selber Konfigurieren');

        // 2. Click "Neuling" -> switches to custom_fields mode (with live 2D/3D read-only preview)
        $showComp->call('startBeginnerConfig')
            ->assertSet('showTemplateSelection', false)
            ->assertSet('currentConfig.personalization_mode', 'custom_fields');

        // 3. Cancel returns to 3-tile selection
        $showComp->call('cancelConfig')
            ->assertSet('showTemplateSelection', true);

        // 4. Click "Profi" -> opens Studio configurator
        $showComp->call('startCustomConfig')
            ->assertSet('showTemplateSelection', false)
            ->assertSet('currentConfig.personalization_mode', 'studio');

        // 5. Mount ProductConfigurator in "Neuling" mode on this Studio product -> live 2D/3D Neulings-Position
        $beginnerConf = Livewire::test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
            'product' => $product,
            'initialData' => ['personalization_mode' => 'custom_fields'],
            'context' => 'add',
        ]);

        $this->assertSame('custom_fields', $beginnerConf->get('effectivePersonalizationMode'));
        $this->assertTrue($beginnerConf->instance()->isUsingCustomFieldsMode());
        $coords = $beginnerConf->instance()->getBeginnerPositionCoordinates();
        $this->assertLessThan($coords['text_y'], $coords['symbol_y']);

        $beginnerConf
            ->set('customFieldValues.cf_wunschsymbol', 'Herz ♥')
            ->set('customFieldValues.cf_wunschtext', 'Für Mama')
            ->set('customFieldValues.cf_schriftart', 'Great Vibes')
            ->set('config_confirmed', true)
            ->call('save');

        $cart = app(\App\Services\CartService::class)->getCart();
        $cartItem = $cart->items()->where('product_id', $product->id)->first();
        $this->assertNotNull($cartItem);
        $this->assertSame('custom_fields', $cartItem->configuration['personalization_mode'] ?? null);

        $cfById = collect($cartItem->configuration['custom_fields_data'] ?? [])->keyBy('id');
        $this->assertSame('Herz ♥', $cfById['cf_wunschsymbol']['value'] ?? null);
        $this->assertSame('Für Mama', $cfById['cf_wunschtext']['value'] ?? null);
        $this->assertSame('Great Vibes', $cfById['cf_schriftart']['value'] ?? null);

        // Live 2D/3D canvas elements (symbol top-center + text bottom-center) must be persisted for 3D preview & xTool SVG export
        $this->assertSame('vectors/Liebe/herz.svg', $cartItem->configuration['logos'][0]['value'] ?? null);
        $this->assertEquals($coords['symbol_x'], $cartItem->configuration['logos'][0]['x'] ?? null);
        $this->assertEquals($coords['symbol_y'], $cartItem->configuration['logos'][0]['y'] ?? null);
        $this->assertSame('Für Mama', $cartItem->configuration['texts'][0]['text'] ?? null);
        $this->assertSame('Great Vibes', $cartItem->configuration['texts'][0]['font'] ?? null);
        $this->assertEquals($coords['text_x'], $cartItem->configuration['texts'][0]['x'] ?? null);
        $this->assertEquals($coords['text_y'], $cartItem->configuration['texts'][0]['y'] ?? null);

        // 6. Verify xTool Laser-SVG export for Neuling (custom_fields) orders (even if logos was empty on an older order)
        $order = \App\Models\Order\OrderOrder::create([
            'order_number' => 'ORD-2026-TESTSVG',
            'status' => 'pending',
            'payment_status' => 'paid',
            'email' => 'alina@example.com',
            'billing_address' => ['first_name' => 'Alina', 'last_name' => 'Steinhauer'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Steinhauer'],
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
        ]);

        $orderItem = \App\Models\Order\OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
            'tax_rate' => 19.0,
            'configuration' => [
                'personalization_mode' => 'custom_fields',
                'custom_fields_data' => [
                    ['id' => 'cf_wunschsymbol', 'label' => '1. Wunsch-Symbol (oben mittig)', 'type' => 'select', 'value' => 'Unendlichkeit ∞'],
                    ['id' => 'cf_wunschtext', 'label' => '2. Dein Wunschtext / Name', 'type' => 'text', 'value' => 'Alina ist so toll'],
                    ['id' => 'cf_schriftart', 'label' => '3. Schriftart wählen', 'type' => 'font', 'value' => 'Dancing Script'],
                ],
                'texts' => [['id' => 'cf_summary', 'text' => "Unendlichkeit ∞\nAlina ist so toll", 'font' => 'Dancing Script']],
                'logos' => [],
            ],
        ]);

        $svgResponse = app(\App\Services\Export\FileDownloadService::class)->downloadLaserSvg($orderItem->id, 'order', 'front');
        $this->assertNotNull($svgResponse);
        ob_start();
        $svgResponse->sendContent();
        $svgContent = (string) ob_get_clean();
        $this->assertStringContainsString('<svg', $svgContent);
        $this->assertStringContainsString('Alina ist so toll', $svgContent);
        $this->assertStringContainsString('Dancing Script', $svgContent);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_manages_beginner_image_and_seasonal_main_images_in_product_editor_step_2(): void
    {
        Storage::fake('public');

        $product = Product::create([
            'name' => 'Medien- & Saison-Test',
            'slug' => 'medien-saison-test',
            'sku' => 'MEDIA-SEAS-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 3490,
            'is_personalizable' => true,
            'completion_step' => 4,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/media-test/img-1.jpg', 'is_main' => true, 'is_hover' => false, 'is_beginner' => true, 'is_ai_generated' => true],
                ['type' => 'image', 'path' => 'produkte/media-test/img-2.jpg', 'is_main' => false, 'is_hover' => true, 'is_beginner' => false, 'is_ai_generated' => true],
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
            ],
        ]);

        $component = Livewire::test(ProductCreate::class)
            ->call('edit', $product->id)
            ->set('currentStep', 2);

        // 1. Switch Neulingsbild to index 1
        $component->call('setBeginnerImage', 1);
        $fresh = $product->fresh();
        $this->assertFalse((bool) ($fresh->media_gallery[0]['is_beginner'] ?? false));
        $this->assertTrue((bool) ($fresh->media_gallery[1]['is_beginner'] ?? false));
        $this->assertSame('produkte/media-test/img-2.jpg', $fresh->resolveBeginnerMediaMeta()['path']);

        // 2. Verify all 8 seasonal slots in overview
        $overview = $fresh->getSeasonalMainImagesOverview();
        $this->assertCount(8, $overview);
        $this->assertArrayHasKey('Weihnachten', $overview);
        $this->assertArrayHasKey('Valentinstag', $overview);

        // 3. Upload custom seasonal main image for Weihnachten in Step 2
        $seasonalFile = UploadedFile::fake()->image('weihnachten-hauptbild.jpg', 800, 800);
        $component->set('seasonal_media_uploads.Weihnachten', $seasonalFile);

        $freshAfterUpload = $product->fresh();
        $customSeasonalPath = $freshAfterUpload->configurator_settings['seasonal_images']['Weihnachten']['path'] ?? null;
        $this->assertNotNull($customSeasonalPath);
        Storage::disk('public')->assertExists($customSeasonalPath);

        $resolvedXmas = $freshAfterUpload->resolveMainImageMeta('Weihnachten', true);
        $this->assertSame($customSeasonalPath, $resolvedXmas['path']);
        $this->assertTrue($resolvedXmas['is_ai_generated']);

        // 4. Toggle AI flag and remove custom seasonal image
        $component->call('toggleSeasonalImageAiGenerated', 'Weihnachten');
        $this->assertFalse((bool) ($product->fresh()->configurator_settings['seasonal_images']['Weihnachten']['is_ai_generated'] ?? true));

        $component->call('removeSeasonalMainImage', 'Weihnachten');
        $this->assertArrayNotHasKey('Weihnachten', $product->fresh()->configurator_settings['seasonal_images'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_maps_all_beginner_symbols_accurately_formats_talisman_studio_text_and_supports_multiline_wunschtext(): void
    {
        $talisman = Product::create([
            'name' => 'Der Seelen-Talisman',
            'slug' => 'seelen-talisman',
            'sku' => 'TALISMAN-TEST-01',
            'status' => 'active',
            'type' => 'physical',
            'price' => 3490,
            'is_personalizable' => true,
            'completion_step' => 4,
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'template_package' => 'seelen-talisman',
                'area_shape' => 'custom',
                'area_left' => 34,
                'area_top' => 45,
                'area_width' => 32,
                'area_height' => 44,
                'custom_points' => [
                    ['x' => 34.5, 'y' => 54.0],
                    ['x' => 65.5, 'y' => 54.0],
                    ['x' => 65.5, 'y' => 83.0],
                    ['x' => 34.5, 'y' => 83.0],
                ],
            ],
        ]);

        // 1. Profi-Modus Leerstart auf Der Seelen-Talisman: Textfeld ist mittig (50, 66) und kompakt (size 0.58 statt 1.0)
        $profiComp = Livewire::test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
            'product' => $talisman,
            'context' => 'add',
            'initialData' => ['personalization_mode' => 'studio'],
        ]);

        $texts = $profiComp->get('texts');
        $this->assertCount(1, $texts);
        $this->assertSame('', $texts[0]['text']);
        $this->assertEquals(50.0, $texts[0]['x']);
        $this->assertEquals(66.0, $texts[0]['y']);
        $this->assertEquals(0.58, $texts[0]['size']);

        // 2. Alle Wunschsymbole im Neulings-Modus prüfen (Kleeblatt != Smiley, Schutzengel != Schild, etc.)
        $beginnerComp = Livewire::test(\App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator::class, [
            'product' => $talisman,
            'context' => 'add',
            'initialData' => ['personalization_mode' => 'custom_fields'],
        ]);

        $expectedSymbolMap = [
            'Herz ♥'          => 'vectors/Liebe/herz.svg',
            'Pfote 🐾'        => 'vectors/Tiere/pfotenabdruck.svg',
            'Unendlichkeit ∞' => 'vectors/Liebe/unendlichkeit.svg',
            'Stern ★'         => 'vectors/Symbole/stern.svg',
            'Schutzengel 👼'  => 'vectors/Symbole/schutzengel.svg',
            'Lebensbaum 🌳'   => 'vectors/Natur/lebensbaum.svg',
            'Kleeblatt 🍀'    => 'vectors/Symbole/kleeblatt.svg',
            'Krone 👑'        => 'vectors/Symbole/krone.svg',
            'Anker ⚓'         => 'vectors/Symbole/anker.svg',
        ];

        foreach ($expectedSymbolMap as $label => $expectedVectorVal) {
            $beginnerComp->set('customFieldValues.cf_wunschsymbol', $label);
            $logos = $beginnerComp->get('logos');
            $this->assertSame($expectedVectorVal, $logos[0]['value'] ?? null, "Symbol {$label} resolved to wrong SVG.");

            $relFile = ltrim(substr($expectedVectorVal, strlen('vectors/')), '/');
            $publicSvgPath = public_path('shop/product/configurator/vectors/' . $relFile);
            $this->assertFileExists($publicSvgPath, "Vector file missing for {$label}: {$publicSvgPath}");
        }

        // 3. Mehrzeiliger Wunschtext (mit manuellen Zeilenumbrüchen \n) im Neulings-Modus
        $beginnerComp
            ->set('customFieldValues.cf_wunschsymbol', 'Kleeblatt 🍀')
            ->set('customFieldValues.cf_wunschtext', "Für immer\nim Herzen")
            ->set('customFieldValues.cf_schriftart', 'Dancing Script')
            ->set('config_confirmed', true)
            ->call('save', app(\App\Services\CartService::class));

        $beginnerTexts = $beginnerComp->get('texts');
        $this->assertSame("Für immer\nim Herzen", $beginnerTexts[0]['text'] ?? null);
        // Bei 2 Zeilen wird die Textgröße harmonisch skaliert (0.74 * 0.90 = 0.67)
        $this->assertEquals(0.67, $beginnerTexts[0]['size'] ?? null);

        $cart = app(\App\Services\CartService::class)->getCart();
        $cartItem = $cart->items()->where('product_id', $talisman->id)->latest()->first();
        $this->assertNotNull($cartItem);
        $this->assertSame("Für immer\nim Herzen", $cartItem->configuration['texts'][0]['text'] ?? null);
        $this->assertSame('vectors/Symbole/kleeblatt.svg', $cartItem->configuration['logos'][0]['value'] ?? null);
    }
}
