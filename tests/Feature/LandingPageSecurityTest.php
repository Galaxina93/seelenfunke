<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Product\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LandingPageSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_public_access_to_draft_landing_pages()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Test Kerze',
            'slug' => 'test-kerze-' . Str::random(5),
            'price' => 2000,
            'status' => 'active',
        ]);

        $landingPage = MarketingLandingPage::create([
            'product_id' => $product->id,
            'slug' => $product->slug,
            'title' => 'Entwurf Seite',
            'status' => 'draft',
        ]);

        // Unauthenticated guest should receive 404
        $response = $this->get('/l/' . $landingPage->slug);
        $response->assertStatus(404);

        // Authenticated admin should be able to preview
        $admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'email' => 'admin@test.de',
            'first_name' => 'Admin',
            'last_name' => 'Test',
            'password' => bcrypt('password'),
        ]);

        $adminResponse = $this->actingAs($admin, 'admin')->get('/l/' . $landingPage->slug);
        $adminResponse->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_denies_public_access_when_associated_product_is_inactive()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Inaktives Produkt',
            'slug' => 'inaktives-produkt-' . Str::random(5),
            'price' => 2000,
            'status' => 'draft',
        ]);

        $landingPage = MarketingLandingPage::create([
            'product_id' => $product->id,
            'slug' => $product->slug,
            'title' => 'Inaktive Landing Page',
            'status' => 'active',
        ]);

        // Unauthenticated guest should receive 404
        $response = $this->get('/l/' . $landingPage->slug);
        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_public_access_to_active_landing_pages_with_active_products()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Aktives Produkt',
            'slug' => 'aktives-produkt-' . Str::random(5),
            'price' => 2000,
            'status' => 'active',
        ]);

        $landingPage = MarketingLandingPage::create([
            'product_id' => $product->id,
            'slug' => $product->slug,
            'title' => 'Aktive Landing Page',
            'sales_copy' => 'Sicherer Text',
            'status' => 'active',
        ]);

        $response = $this->get('/l/' . $landingPage->slug);
        $response->assertStatus(200);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_automatically_provisions_and_adapts_landing_page_for_newly_created_product()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Neuer Schiefer Untersetzer',
            'slug' => 'neuer-schiefer-untersetzer',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'price' => 2990,
            'compare_at_price' => 3490,
            'status' => 'active',
            'description' => 'Handgefertigter Schiefer-Untersetzer mit individueller Lasergravur.',
            'attributes' => [
                'Material' => 'Naturschiefer',
                'Größe' => '10x10 cm',
            ],
            'configurator_settings' => [
                'auto_seasonal_main_image' => true,
                'autopilot_badge_text' => 'Herbst-Deal',
            ],
        ]);

        \App\Models\Product\ProductTemplate::create([
            'product_id' => $product->id,
            'name' => 'Frohe Weihnachten',
            'holiday' => 'Weihnachten',
            'is_active' => true,
            'is_ai_generated' => true,
            'preview_image' => 'produkte/product-templates/test-schiefer-weihnachten.jpg',
            'configuration' => [],
        ]);

        $response = $this->get('/l/neuer-schiefer-untersetzer?holiday=Weihnachten');
        $response->assertStatus(200);
        $response->assertSee('Neuer Schiefer Untersetzer');
        $response->assertSee('Naturschiefer');
        $response->assertSee('Herbst-Deal');
        $response->assertSee('KI-generiert');
        $response->assertSee('shop/frontend/banners/');
        $response->assertDontSee('configurator_settings');
        $response->assertDontSee('shipping_class');
        $response->assertDontSee('Design-Vorlagen');

        $this->assertDatabaseHas('marketing_landing_pages', [
            'product_id' => $product->id,
            'slug' => 'neuer-schiefer-untersetzer',
            'status' => 'active',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adapts_landing_page_dynamically_for_digital_products()
    {
        $product = Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Gravur Design Guide E-Book',
            'slug' => 'gravur-design-guide-ebook',
            'type' => 'digital',
            'is_personalizable' => false,
            'three_d_active' => false,
            'price' => 1990,
            'status' => 'active',
            'description' => 'Umfassendes digitales Handbuch für Gravur-Designs.',
            'attributes' => [
                'Format' => 'PDF + ZIP',
                'Seiten' => '120',
            ],
        ]);

        $response = $this->get('/l/gravur-design-guide-ebook');
        $response->assertStatus(200);
        $response->assertSee('Sofort-Download');
        $response->assertSee('PDF + ZIP');
        $response->assertSee('120');
    }
}
