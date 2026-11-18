<?php

namespace Tests\Feature\Shop;

use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedShopAndHomepageShowcaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        DB::table('tax_rates')->insertOrIgnore([
            ['name' => 'Standard DE', 'rate' => 19.00, 'country_code' => 'DE', 'tax_class' => 'standard', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Legt die 8 aktiven Seelenfunke-Produkte plus 1 Entwurf und das interne System-Artefakt an.
     */
    private function seedEightStorefrontProducts(): void
    {
        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'price' => 6990,
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
            'media_gallery' => [
                ['type' => 'image', 'path' => 'produkte/products/seelen-kristall/main.jpg', 'is_main' => true, 'is_ai_generated' => true],
            ],
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'price' => 2990,
            'track_quantity' => true,
            'quantity' => 150,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen-Talisman',
            'slug' => 'seelen-talisman',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'price' => 3490,
            'track_quantity' => true,
            'quantity' => 16,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Die Seelen-Pfote',
            'slug' => 'seelen-pfote-hundemarke',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'price' => 2490,
            'track_quantity' => true,
            'quantity' => 30,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Das Goldene Seelen-Herz',
            'slug' => 'goldenes-seelen-herz',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'manufaktur',
            'is_personalizable' => true,
            'price' => 3990,
            'compare_at_price' => 4490,
            'track_quantity' => true,
            'quantity' => 10,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Das Echtsilber Seelenarmband',
            'slug' => 'id-armband-silber-herz',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'is_personalizable' => true,
            'price' => 6990,
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Das Goldene Seelenarmband',
            'slug' => 'id-armband-gold-herz',
            'status' => 'active',
            'type' => 'physical',
            'collection_line' => 'atelier',
            'is_personalizable' => true,
            'price' => 7990,
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'status' => 'active',
            'type' => 'digital',
            'collection_line' => 'voucher',
            'is_personalizable' => true,
            'price' => 0,
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Feierabend-Funke Entwurf',
            'slug' => 'weizenglas-personalisiert',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 2990,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ingame Artefakt Versteckt',
            'slug' => 'seelenfunke-ingame-artifact',
            'status' => 'active',
            'type' => 'digital',
            'price' => 100,
        ]);
    }

    public function test_homepage_renders_three_curated_products_with_direct_product_links_and_single_shop_cta(): void
    {
        $this->seedEightStorefrontProducts();

        $response = $this->get('/');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        // Neue Conversion-Bühne statt Kategorie-Filterkacheln
        $this->assertStringContainsString('id="services"', $html);
        $this->assertStringContainsString('id="services-heading"', $html);
        $this->assertStringContainsString('Unsere beliebtesten Unikate', $html);
        $this->assertStringNotContainsString('Veredelungen &amp; Materialien für Ihre Unikate', $html);
        $this->assertStringNotContainsString('Veredelungen & Materialien für Ihre Unikate', $html);
        $this->assertStringNotContainsString('Manufaktur-Kollektion', $html);
        $this->assertStringNotContainsString('Atelier Echtgold &amp; Silber', $html);

        // Exakt 3 echte Produktkarten in der kuratierten Reihenfolge
        $this->assertSame(3, substr_count($html, 'data-testid="homepage-showcase-card"'));
        $response->assertSeeInOrder([
            'Unsere beliebtesten Unikate',
            'Der Seelen Kristall',
            'Das Goldene Seelen-Herz',
            'Der Seelenanhänger',
            'Alle Unikate im Shop entdecken',
        ]);

        // Direktlinks auf /produkt/{slug} und niemals /shop?filterCategory=
        $this->assertStringContainsString(route('product.show', 'seelen-kristall'), $html);
        $this->assertStringContainsString(route('product.show', 'goldenes-seelen-herz'), $html);
        $this->assertStringContainsString(route('product.show', 'seelen-anhaenger'), $html);
        $this->assertStringContainsString('Jetzt gestalten →', $html);
        $this->assertStringNotContainsString('filterCategory=', $html);

        // Prominenter CTA direkt nach /shop + Preis- & KI-Kennzeichnung + 0 KI-Gedankenstriche
        $this->assertStringContainsString('href="' . route('shop') . '"', $html);
        $this->assertStringContainsString('69,90 €', $html);
        $this->assertStringContainsString('39,90 €', $html);
        $this->assertStringContainsString('44,90 €', $html);
        $this->assertStringContainsString('KI-generiert', $html);
        $this->assertStringContainsString('zzgl. Versandkosten', $html);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $html);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $html);
    }

    public function test_homepage_falls_back_cleanly_to_next_available_product_when_priority_item_is_sold_out(): void
    {
        $this->seedEightStorefrontProducts();

        Product::where('slug', 'goldenes-seelen-herz')->update([
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => false,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        $this->assertMatchesRegularExpression('/<section id="services"[\s\S]*?<\/section>/', $html);
        preg_match('/<section id="services"[\s\S]*?<\/section>/', $html, $sectionMatches);
        $servicesSectionHtml = $sectionMatches[0] ?? '';

        $this->assertSame(3, substr_count($servicesSectionHtml, 'data-testid="homepage-showcase-card"'));
        $this->assertStringNotContainsString('Das Goldene Seelen-Herz', $servicesSectionHtml);
        $this->assertStringContainsString('Der Seelen Kristall', $servicesSectionHtml);
        $this->assertStringContainsString('Der Seelenanhänger', $servicesSectionHtml);
        $this->assertStringContainsString('Die Seelen-Pfote', $servicesSectionHtml);
    }

    public function test_shop_renders_all_eight_active_products_together_with_atelier_silver_badges_and_voucher_last(): void
    {
        $this->seedEightStorefrontProducts();

        $response = $this->get('/shop');

        $response->assertStatus(200);
        $html = (string) $response->getContent();

        // Alle 8 aktiven Produkte sofort gemeinsam sichtbar (Gutschein am Ende)
        $response->assertSeeInOrder([
            'Unsere Kollektion',
            'Der Seelen Kristall',
            'Das Goldene Seelen-Herz',
            'Der Seelenanhänger',
            'Das Echtsilber Seelenarmband',
            'Das Goldene Seelenarmband',
            'Die Seelen-Pfote',
            'Der Seelen-Talisman',
            'Geschenkgutschein',
        ]);

        // Kein „Gold-Vermeil“-Begriff und kein „925er Silber · Atelier“-Badge auf den Karten
        $this->assertStringNotContainsString('Gold-Vermeil', $html);
        $this->assertStringNotContainsString('925er Silber · Atelier', $html);

        // Entwürfe & System-Artefakte bleiben strikt ausgeblendet
        $response->assertDontSee('Der Feierabend-Funke Entwurf');
        $response->assertDontSee('Ingame Artefakt Versteckt');

        // Keine 3-Welten-Tabs und keine Personalisiert/Standard-Pills mehr
        $this->assertStringNotContainsString('Manufaktur-Kollektion', $html);
        $this->assertStringNotContainsString('Atelier Echtgold &amp; Silber', $html);
        $this->assertStringNotContainsString("set('filterPersonalizable'", $html);
        $this->assertStringNotContainsString('>Personalisiert<', $html);
        $this->assertStringNotContainsString('>Standard<', $html);

        // 0 KI-Gedankenstriche
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $html);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $html);
    }

    public function test_legacy_shop_collection_world_urls_return_200_with_canonical_shop_url_and_all_products(): void
    {
        $this->seedEightStorefrontProducts();

        foreach (['/shop?collectionWorld=atelier', '/shop?collectionWorld=voucher', '/shop?filterType=digital'] as $legacyUrl) {
            $response = $this->get($legacyUrl);

            $response->assertStatus(200);
            $response->assertSee('Der Seelen Kristall');
            $response->assertSee('Das Echtsilber Seelenarmband');
            $response->assertSee('Das Goldene Seelenarmband');
            $response->assertSee('Geschenkgutschein');
            $this->assertStringContainsString('<link rel="canonical" href="' . route('shop') . '"', (string) $response->getContent());
        }
    }
}
