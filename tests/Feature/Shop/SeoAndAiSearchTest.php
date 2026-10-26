<?php

namespace Tests\Feature\Shop;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingBlogPost;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoAndAiSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_xml_lists_static_pages_active_products_and_published_blog_posts(): void
    {
        $activeProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Kristallglas Trophäe Stern',
            'slug' => 'kristallglas-trophaee-stern',
            'status' => 'active',
            'type' => 'physical',
            'price' => 4990,
            'short_description' => 'Edle K9 Kristallglas Trophäe mit Lasergravur.',
        ]);

        $draftProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Geheimer Entwurf',
            'slug' => 'geheimer-entwurf',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 1990,
        ]);

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);

        $blogPost = MarketingBlogPost::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => 'Wie Lasergravur auf K9-Kristallglas funktioniert',
            'slug' => 'lasergravur-k9-kristallglas',
            'excerpt' => 'Ein Blick hinter die Kulissen unserer Manufaktur.',
            'content' => '<p>Hochpräzise Lasergravur in Gifhorn.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee(route('shop'), false);
        $response->assertSee(route('manufacture'), false);
        $response->assertSee(route('product.show', $activeProduct->slug), false);
        $response->assertDontSee(route('product.show', $draftProduct->slug), false);
        $response->assertSee(route('blog.show', $blogPost->slug), false);
    }

    public function test_llms_txt_and_llms_full_txt_provide_structured_ai_search_knowledge(): void
    {
        $activeProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Naturschiefer Gedenktafel Herz',
            'slug' => 'naturschiefer-gedenktafel-herz',
            'sku' => 'MSF-SLATE-01',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 3490,
            'short_description' => 'Handgebrochene Schieferplatte mit individueller Gravur.',
            'attributes' => [
                'Material' => 'Echter Naturschiefer',
                'Maße' => '200 x 200 mm',
            ],
        ]);

        $draftProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Unveröffentlichtes Testprodukt',
            'slug' => 'unveroeffentlichtes-testprodukt',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 9900,
        ]);

        $resSummary = $this->get('/llms.txt');
        $resSummary->assertStatus(200);
        $resSummary->assertSee('Mein Seelenfunke');
        $resSummary->assertSee('Alina Steinhauer');
        $resSummary->assertSee('Gifhorn');
        $resSummary->assertSee('Naturschiefer Gedenktafel Herz');
        $resSummary->assertSee('34,90 €');
        $resSummary->assertSee(route('product.show', $activeProduct->slug));
        $resSummary->assertDontSee('Unveröffentlichtes Testprodukt');

        $resFull = $this->get('/llms-full.txt');
        $resFull->assertStatus(200);
        $resFull->assertSee('MSF-SLATE-01');
        $resFull->assertSee('Echter Naturschiefer');
        $resFull->assertSee('200 x 200 mm');
        $resFull->assertSee('Häufig gestellte Fragen (FAQ)');
        $resFull->assertDontSee('Unveröffentlichtes Testprodukt');
    }

    public function test_product_detail_page_renders_dynamic_title_meta_and_product_json_ld_schema(): void
    {
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'K9 Glas-Trophäe Tropfen',
            'slug' => 'k9-glas-trophaee-tropfen',
            'sku' => 'MSF-DROP-01',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 5990,
            'seo_title' => 'K9 Glas-Trophäe Tropfen mit Wunschgravur',
            'seo_description' => 'Exklusive K9 Kristallglas-Trophäe in Tropfenform mit 3D-Konfigurator.',
            'description' => 'Handveredeltes K9-Kristallglas mit präziser Lasergravur.',
        ]);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => bcrypt('secret123'),
        ]);

        ProductReview::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'title' => 'Wunderschöne Gravur',
            'content' => 'Absolut edle Verarbeitung und schneller Versand!',
            'status' => 'approved',
        ]);

        $response = $this->get(route('product.show', $product->slug));

        $response->assertStatus(200);
        $response->assertSee('<title>K9 Glas-Trophäe Tropfen mit Wunschgravur | Mein Seelenfunke</title>', false);
        $response->assertSee('Exklusive K9 Kristallglas-Trophäe in Tropfenform mit 3D-Konfigurator.', false);
        $response->assertSee('<meta property="og:type" content="product">', false);
        $response->assertSee('<meta property="product:price:amount" content="59.90">', false);
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"sku":"MSF-DROP-01"', false);
        $response->assertSee('"price":"59.90"', false);
        $response->assertSee('"@type":"MerchantReturnPolicy"', false);
        $response->assertSee('"@type":"AggregateRating"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('data-nosnippet', false);
    }

    public function test_product_with_variants_renders_aggregate_offer_and_blog_post_renders_recommendations(): void
    {
        $variantProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Schieferplatte Gravur Edition',
            'slug' => 'schieferplatte-gravur-edition',
            'sku' => 'MSF-SLATE-VAR',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 2990,
            'attributes' => [
                'Größe' => 'Klein, Groß',
            ],
            'variants_data' => [
                ['id' => 'v1', 'name' => 'Klein', 'price' => '29.90', 'is_active' => true],
                ['id' => 'v2', 'name' => 'Groß', 'price' => '39.90', 'is_active' => true],
            ],
        ]);

        $prodRes = $this->get(route('product.show', $variantProduct->slug));
        $prodRes->assertStatus(200);
        $prodRes->assertSee('"@type":"AggregateOffer"', false);
        $prodRes->assertSee('"lowPrice":"29.90"', false);
        $prodRes->assertSee('"highPrice":"39.90"', false);
        $prodRes->assertSee('"offerCount":2', false);

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-blog@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);

        $blogPost = MarketingBlogPost::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => 'Geschenkideen aus Naturschiefer',
            'slug' => 'geschenkideen-aus-naturschiefer',
            'excerpt' => 'Warum Naturschiefer ein besonderes Unikat ist.',
            'content' => '<p>Echter Naturschiefer überzeugt durch seine einzigartige Bruchkante und edle Kontraste bei der Lasergravur.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        $blogRes = $this->get(route('blog.show', $blogPost->slug));
        $blogRes->assertStatus(200);
        $blogRes->assertSee('"@type":"BlogPosting"', false);
        $blogRes->assertSee('"wordCount":', false);
        $blogRes->assertSee('Min. Lesezeit', false);
        $blogRes->assertSee('Passende Unikate aus unserer Manufaktur', false);
        $blogRes->assertSee('Schieferplatte Gravur Edition', false);
    }

    public function test_cart_login_and_emergency_pages_have_noindex_nofollow_robots_meta(): void
    {
        $cartResponse = $this->get(route('cart'));
        $cartResponse->assertStatus(200);
        $cartResponse->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $loginResponse = $this->get(route('login'));
        $loginResponse->assertStatus(200);
        $loginResponse->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $emergencyResponse = $this->get(route('emergency.index'));
        $emergencyResponse->assertStatus(200);
        $emergencyResponse->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_robots_txt_and_tracking_redirect_enforce_ai_geo_and_open_redirect_security(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('User-agent: OAI-SearchBot', $robots);
        $this->assertStringContainsString('User-agent: PerplexityBot', $robots);
        $this->assertStringContainsString('Allow: /llms.txt', $robots);
        $this->assertStringContainsString('Allow: /llms-full.txt', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /employee', $robots);
        $this->assertStringContainsString('Disallow: /partner/einladung/', $robots);
        $this->assertStringContainsString('Disallow: /t/', $robots);

        $link = \App\Models\Marketing\MarketingTrackingLink::create([
            'name' => 'Phishing Test Link',
            'slug' => 'evil-redirect-test',
            'target_url' => 'https://evil.example.com/phishing',
            'utm_source' => 'instagram',
            'utm_medium' => 'social',
            'utm_campaign' => 'test',
            'is_active' => true,
        ]);

        // Open Redirect muss auf die eigene Domain (/shop) neutralisiert werden
        $this->assertEquals(url('/shop'), \App\Models\Marketing\MarketingTrackingLink::resolveInternalTargetUrl($link->target_url));

        $redirectRes = $this->get('/t/evil-redirect-test');
        $redirectRes->assertRedirect();
        $this->assertStringStartsWith(url('/'), (string) $redirectRes->headers->get('Location'));
        $this->assertStringNotContainsString('evil.example.com', (string) $redirectRes->headers->get('Location'));
        $redirectRes->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $redirectRes->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
