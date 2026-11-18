<?php

namespace Tests\Feature\Support;

use App\Livewire\Shop\Support\SupportReviews;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Support\SupportExperienceReview;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtelierExperienceReviewShowroomPayloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
    }

    public function test_guest_receives_ineligible_atelier_review_payload_on_showroom_data(): void
    {
        $response = $this->getJson('/shop/showroom-data');

        $response->assertOk()
            ->assertJsonPath('atelier_review.area', 'funkenreich')
            ->assertJsonPath('atelier_review.label', '3D-Atelier')
            ->assertJsonPath('atelier_review.can_rate', false)
            ->assertJsonPath('atelier_review.has_rated', false)
            ->assertJsonPath('atelier_review.my_rating', 0)
            ->assertJsonPath('atelier_review.my_comment', '')
            ->assertJsonPath('atelier_review.submit_url', '/customer/experience-reviews');
    }

    public function test_verified_customer_atelier_rating_updates_showroom_payload_inline_login_and_support_reviews(): void
    {
        $customer = Customer::create([
            'first_name' => 'Elena',
            'last_name' => 'Atelier',
            'email' => 'elena-atelier@example.com',
            'password' => bcrypt('Secret123!'),
            'email_verified_at' => now(),
        ]);

        // 1. Inline-Login im 3D-Atelier liefert atelier_review fuer frisch eingeloggten Kunden (noch unbewertet)
        $loginResponse = $this->postJson('/funkenwerk-welt/inline-login', [
            'email' => 'elena-atelier@example.com',
            'password' => 'Secret123!',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('atelier_review.area', 'funkenreich')
            ->assertJsonPath('atelier_review.label', '3D-Atelier')
            ->assertJsonPath('atelier_review.can_rate', true)
            ->assertJsonPath('atelier_review.has_rated', false)
            ->assertJsonPath('atelier_review.my_rating', 0)
            ->assertJsonPath('atelier_review.my_comment', '')
            ->assertJsonPath('atelier_review.submit_url', '/customer/experience-reviews');

        // 2. Verifizierter Kunde gibt per POST /customer/experience-reviews 5 Sterne fuer das 3D-Atelier ab
        $this->actingAs($customer, 'customer')
            ->postJson('/customer/experience-reviews', [
                'area' => 'funkenreich',
                'rating' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('review.area', 'funkenreich')
            ->assertJsonPath('review.rating', 5);

        // 3. GET /shop/showroom-data spiegelt die abgegebene 5-Sterne-Bewertung wider
        $showroomResponse = $this->actingAs($customer, 'customer')
            ->getJson('/shop/showroom-data');

        $showroomResponse->assertOk()
            ->assertJsonPath('atelier_review.area', 'funkenreich')
            ->assertJsonPath('atelier_review.label', '3D-Atelier')
            ->assertJsonPath('atelier_review.can_rate', true)
            ->assertJsonPath('atelier_review.has_rated', true)
            ->assertJsonPath('atelier_review.my_rating', 5)
            ->assertJsonPath('atelier_review.my_comment', '')
            ->assertJsonPath('atelier_review.submit_url', '/customer/experience-reviews');

        // 4. Bereichs-Label in Model & SupportReviews zeigt konsistent '3D-Atelier'
        $this->assertSame('3D-Atelier', SupportExperienceReview::areaLabel('funkenreich'));

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-atelier-support@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(SupportReviews::class)
            ->assertSee('3D-Atelier')
            ->assertSee('Elena Atelier')
            ->assertSee('5,0');
    }

    public function test_touched_atelier_review_files_contain_zero_ai_dashes(): void
    {
        $files = [
            base_path('app/Models/Support/SupportExperienceReview.php'),
            base_path('app/Http/Controllers/Frontend/ShopShowroomController.php'),
            base_path('resources/views/components/shop/experience-star-widget.blade.php'),
            __FILE__,
        ];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            $this->assertStringNotContainsString(" \u{2013} ", $contents, 'KI-Gedankenstrich gefunden in: ' . $file);
            $this->assertStringNotContainsString(" \u{2014} ", $contents, 'Geleerter Geviertstrich gefunden in: ' . $file);
        }
    }
}
