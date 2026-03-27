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

class ProductCreationTest extends TestCase
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

        // Filter by Type
        Livewire::test(ProductIndex::class)
            ->set('filterType', 'digital')
            ->assertSee('Ebook PDF')
            ->assertDontSee('Physical Book');

        // Filter by Category
        Livewire::test(ProductIndex::class)
            ->set('filterCategory', $c1->id)
            ->assertSee('Physical Book')
            ->assertDontSee('Ebook PDF');

        // Filter by Search
        Livewire::test(ProductIndex::class)
            ->set('search', 'Physical')
            ->assertSee('Physical Book')
            ->assertDontSee('Ebook PDF');
    }
}
