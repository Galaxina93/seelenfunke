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
}
