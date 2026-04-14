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
        Livewire::test(ProductCreate::class)
            ->call('createDraft')
            ->assertSet('viewMode', 'edit')
            ->assertSet('currentStep', 1)
            ->assertStatus(200);

        $this->assertDatabaseCount('products', 1);
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
}
