<?php

namespace Tests\Feature\Livewire\Shop\Product;

use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\Product\ProductTemplate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function unified_storefront_shows_manufaktur_atelier_and_digital_vouchers_together_without_world_tabs()
    {
        Product::query()->update(['status' => 'archived']);

        Product::create([
            'name' => 'Holzblock Manufaktur',
            'slug' => 'holzblock-manufaktur-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'price' => 2000,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        Product::create([
            'name' => 'Echtsilber Armband 925',
            'slug' => 'echtsilber-armband-925-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'price' => 5990,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        Product::create([
            'name' => 'Seelenfunke Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'status' => 'active',
            'type' => 'digital',
            'collection_line' => 'voucher',
            'price' => 2500,
            'track_quantity' => false,
            'quantity' => 0,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        // Gemeinsames Schaufenster: alle 3 Produktlinien sofort sichtbar (ohne Atelier-Kartenbadge)
        $component->assertSeeInOrder([
            'Holzblock Manufaktur',
            'Echtsilber Armband 925',
            'Seelenfunke Geschenkgutschein',
        ]);
        $component->assertDontSee('925er Silber · Atelier');

        // Alte 3-Welten-Tabs und Personalisiert/Standard-Pills sind nicht mehr vorhanden
        $component->assertDontSee('Manufaktur-Kollektion');
        $component->assertDontSee('Atelier Echtgold & Silber');
        $component->assertDontSeeHtml("set('filterPersonalizable'");
        $component->assertDontSeeHtml('>Personalisiert<');
        $component->assertDontSeeHtml('>Standard<');
    }

    #[Test]
    public function legacy_collection_world_and_filter_type_query_params_normalize_gracefully_and_keep_all_products_visible()
    {
        Product::query()->update(['status' => 'archived']);

        Product::create([
            'name' => 'Kristall Unikat Physisch',
            'slug' => 'kristall-unikat-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 4990,
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        Product::create([
            'name' => 'Atelier Silber Herz',
            'slug' => 'atelier-silber-herz-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'price' => 6490,
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        Product::create([
            'name' => 'Digitaler Gutschein',
            'slug' => 'digitaler-gutschein-' . uniqid(),
            'status' => 'active',
            'type' => 'digital',
            'price' => 2500,
            'track_quantity' => false,
        ]);

        foreach (['atelier', 'voucher', 'manufaktur', 'invalid'] as $legacyWorld) {
            Livewire::withQueryParams(['collectionWorld' => $legacyWorld])
                ->test(ProductFrontendFilterArea::class)
                ->assertSet('collectionWorld', 'all')
                ->assertSee('Kristall Unikat Physisch')
                ->assertSee('Atelier Silber Herz')
                ->assertSee('Digitaler Gutschein');
        }

        foreach (['digital', 'service', 'physical'] as $legacyType) {
            Livewire::withQueryParams(['filterType' => $legacyType])
                ->test(ProductFrontendFilterArea::class)
                ->assertSet('filterType', 'all')
                ->assertSee('Kristall Unikat Physisch')
                ->assertSee('Atelier Silber Herz')
                ->assertSee('Digitaler Gutschein');
        }

        // Auch ein direkter Legacy-Methodenaufruf sperrt keine Produkte mehr weg
        Livewire::test(ProductFrontendFilterArea::class)
            ->call('setCollectionWorld', 'voucher')
            ->assertSet('collectionWorld', 'all')
            ->assertSee('Kristall Unikat Physisch')
            ->assertSee('Digitaler Gutschein');
    }

    #[Test]
    public function sale_button_only_appears_when_discounted_products_exist_or_sale_filter_is_active()
    {
        Product::query()->update(['status' => 'archived']);

        $regular = Product::create([
            'name' => 'Regulaeres Produkt',
            'slug' => 'regulaer-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2990,
            'compare_at_price' => null,
            'track_quantity' => true,
            'quantity' => 5,
        ]);

        Livewire::test(ProductFrontendFilterArea::class)
            ->assertDontSeeHtml('wire:click="toggleSaleFilter"');

        $regular->update(['compare_at_price' => 3990]);

        Livewire::test(ProductFrontendFilterArea::class)
            ->assertSeeHtml('wire:click="toggleSaleFilter"')
            ->assertSee('Angebote');
    }

    #[Test]
    public function occasions_dropdown_only_shows_holidays_from_active_templates_of_active_products()
    {
        Carbon::setTestNow('2026-12-01 12:00:00');

        Product::query()->update(['status' => 'archived']);
        ProductTemplate::query()->update(['is_active' => false]);

        $product = Product::create([
            'name' => 'Gravur Geschenk',
            'slug' => 'gravur-geschenk-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'price' => 2500,
            'is_personalizable' => true,
        ]);

        ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Weihnachtsedition',
            'holiday' => 'Weihnachten',
            'is_active' => true,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        $component->assertSeeHtml('<option value="Weihnachten">Weihnachten</option>');
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

        $component->assertDontSee('Alle Anlässe');
    }

    #[Test]
    public function category_dropdown_shows_categories_across_all_active_products_in_unified_storefront()
    {
        Product::query()->update(['status' => 'archived']);

        $catManufaktur = ProductCategory::create([
            'name' => 'Manufaktur Kategorie ' . uniqid(),
            'slug' => 'manufaktur-kat-' . uniqid(),
        ]);

        $catAtelier = ProductCategory::create([
            'name' => 'Atelier Kategorie ' . uniqid(),
            'slug' => 'atelier-kat-' . uniqid(),
        ]);

        $catEmpty = ProductCategory::create([
            'name' => 'Leere Geisterkategorie ' . uniqid(),
            'slug' => 'leere-kategorie-' . uniqid(),
        ]);

        $p1 = Product::create([
            'name' => 'Manufaktur Produkt',
            'slug' => 'manufaktur-prod-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 2000,
        ]);
        $p1->categories()->attach($catManufaktur->id);

        $p2 = Product::create([
            'name' => 'Atelier Produkt',
            'slug' => 'atelier-prod-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'price' => 5500,
        ]);
        $p2->categories()->attach($catAtelier->id);

        $component = Livewire::test(ProductFrontendFilterArea::class);

        $component->assertSeeHtml($catManufaktur->name);
        $component->assertSeeHtml($catAtelier->name);
        $component->assertDontSeeHtml($catEmpty->name);
    }

    #[Test]
    public function filtering_by_holiday_filters_matching_products()
    {
        Carbon::setTestNow('2026-12-01 12:00:00');

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

        $bdayProduct = Product::create([
            'name' => 'Geburtstags-Artikel Speziell',
            'slug' => 'bday-special-' . uniqid(),
            'status' => 'active',
            'type' => 'physical',
            'price' => 3000,
            'is_personalizable' => true,
        ]);

        ProductTemplate::create([
            'product_id' => $bdayProduct->id,
            'name' => 'Geburtstag Vorlage',
            'holiday' => 'Geburtstag',
            'is_active' => true,
        ]);

        $component = Livewire::test(ProductFrontendFilterArea::class);
        $component->set('filterHoliday', 'Weihnachten');

        $component->assertSee('Weihnachts-Artikel Speziell');
        $component->assertDontSee('Geburtstags-Artikel Speziell');
    }

    #[Test]
    public function reset_filters_resets_all_filter_properties()
    {
        $component = Livewire::test(ProductFrontendFilterArea::class)
            ->set('search', 'Testsuche')
            ->set('filterSale', true)
            ->set('filterCategory', '1')
            ->call('resetFilters');

        $component->assertSet('search', '');
        $component->assertSet('collectionWorld', 'all');
        $component->assertSet('filterType', 'all');
        $component->assertSet('filterPersonalizable', 'all');
        $component->assertSet('filterSale', false);
        $component->assertSet('filterCategory', '');
        $component->assertSet('filterHoliday', '');
    }
}
