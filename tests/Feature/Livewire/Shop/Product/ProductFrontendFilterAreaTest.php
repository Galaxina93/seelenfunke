<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductFrontendFilterAreaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Cache::put('shop_capacity_level', 0);

        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function service_tab_is_hidden_when_no_active_service_products_exist()
    {
        // Ensure only physical products exist
        Product::query()->update(['status' => 'archived']);

        Product::create([
            'name' => 'Holzblock Physisch',
            'slug' => 'holzblock-physisch-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        $component->assertSee('Physisch');
        $component->assertDontSee('Service');
        $component->assertDontSee('Digital');
    }

    #[Test]
    public function digital_tab_appears_only_when_active_digital_products_exist()
    {
        Product::query()->update(['status' => 'archived']);

        // Only physical product initially
        $physical = Product::create([
            'name' => 'Holzblock Physisch',
            'slug' => 'holzblock-physisch-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);
        $component->assertDontSee('Digital');

        // Add an active digital product
        Product::create([
            'name' => 'E-Book Digital',
            'slug' => 'ebook-digital-' . uniqid(),
            'status' => 'active',
            'type' => 'digital',
            'price' => 1500,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);
        $component->assertSee('Digital');
        $component->assertDontSee('Service');
    }

    #[Test]
    public function service_tab_appears_when_active_service_product_exists()
    {
        Product::query()->update(['status' => 'archived']);

        Product::create([
            'name' => 'Beratung Service',
            'slug' => 'beratung-service-' . uniqid(),
            'status' => 'active',
            'type' => 'service',
            'price' => 5000,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);
        $component->assertSee('Service');
        $component->assertDontSee('Digital');
        $component->assertDontSee('Physisch');
    }

    #[Test]
    public function invalid_filter_type_in_query_string_falls_back_to_all()
    {
        Product::query()->update(['status' => 'archived']);

        Product::create([
            'name' => 'Holzblock Physisch',
            'slug' => 'holzblock-physisch-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);

        // Attempting to set filterType to service when no service products exist
        $component = Livewire::withQueryParams(['filterType' => 'service'])
            ->test(ProductFrontendFilterArea::class);

        $component->assertSet('filterType', 'all');
    }

    #[Test]
    public function occasions_dropdown_only_shows_holidays_from_active_templates_of_active_products()
    {
        Product::query()->update(['status' => 'archived']);
        ProductTemplate::query()->update(['is_active' => false]);

        $product = Product::create([
            'name' => 'Gravur Geschenk',
            'slug' => 'gravur-geschenk-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2500,
            'is_personalizable' => true,
        ]);

        // Create an active template for Weihnachten
        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Weihnachtsedition',
            'holiday' => 'Weihnachten',
            'is_active' => true,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        // Weihnachten must be in the dropdown
        $component->assertSeeHtml('<option value="Weihnachten">Weihnachten</option>');

        // Non-existent holidays (like Valentinstag, Trauer, Ostern) must NOT be present
        $component->assertDontSeeHtml('Valentinstag');
        $component->assertDontSeeHtml('Trauer');
        $component->assertDontSeeHtml('Vatertag');
        $component->assertDontSeeHtml('Muttertag');
    }

    #[Test]
    public function occasions_dropdown_is_hidden_when_no_active_holidays_exist()
    {
        Product::query()->update(['status' => 'archived']);
        ProductTemplate::query()->update(['is_active' => false]);

        Product::create([
            'name' => 'Einfacher Holzblock',
            'slug' => 'holzblock-ohne-template-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        // When no templates exist, Anlass dropdown must not appear
        $component->assertDontSee('Alle Anlässe');
    }

    #[Test]
    public function category_dropdown_only_shows_categories_with_active_products()
    {
        Product::query()->update(['status' => 'archived']);

        $catWithProducts = ProductCategory::create([
            'name' => 'Aktive Testkategorie ' . uniqid(),
            'slug' => 'aktive-kategorie-' . uniqid(),
        ]);

        $catWithoutProducts = ProductCategory::create([
            'name' => 'Leere Geisterkategorie ' . uniqid(),
            'slug' => 'leere-kategorie-' . uniqid(),
        ]);

        $product = Product::create([
            'name' => 'Kategorie Produkt',
            'slug' => 'kategorie-produkt-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);

        $product->categories()->attach($catWithProducts->id);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        $component->assertSeeHtml($catWithProducts->name);
        $component->assertDontSeeHtml($catWithoutProducts->name);
    }

    #[Test]
    public function filtering_by_holiday_filters_matching_products()
    {
        Product::query()->update(['status' => 'archived']);
        ProductTemplate::query()->update(['is_active' => false]);

        $xmasProduct = Product::create([
            'name' => 'Weihnachts-Artikel Speziell',
            'slug' => 'xmas-special-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'is_personalizable' => true,
        ]);

        ProductTemplate::create([
            'product_id' => $xmasProduct->id,
            'name' => 'Xmas Vorlage',
            'holiday' => 'Weihnachten',
            'is_active' => true,
        ]);

        $easterProduct = Product::create([
            'name' => 'Oster-Artikel Speziell',
            'slug' => 'easter-special-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'is_personalizable' => true,
        ]);

        ProductTemplate::create([
            'product_id' => $easterProduct->id,
            'name' => 'Oster Vorlage',
            'holiday' => 'Ostern',
            'is_active' => true,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);
        $component->set('filterHoliday', 'Weihnachten');

        $component->assertSee('Weihnachts-Artikel Speziell');
        $component->assertDontSee('Oster-Artikel Speziell');
    }

    #[Test]
    public function reset_filters_resets_all_filter_properties()
    {
        $component = Livewire::test(ProductFrontendFilterArea::class)
            ->set('search', 'Testsuche')
            ->set('filterType', 'physical')
            ->set('filterPersonalizable', 'yes')
            ->set('filterCategory', '1')
            ->call('resetFilters');

        $component->assertSet('search', '');
        $component->assertSet('filterType', 'all');
        $component->assertSet('filterPersonalizable', 'all');
        $component->assertSet('filterCategory', '');
        $component->assertSet('filterHoliday', '');
    }
}
