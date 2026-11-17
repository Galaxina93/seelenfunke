<?php

namespace Tests\Feature\Marketing;

use App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess;
use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleTagAndShoppingGatekeeperTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('global_shop_settings');
    }

    public function test_frontend_hides_google_tag_by_default_when_disabled(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'google_tag_enabled'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'google_tag_id'], ['value' => 'AW-987654321']);
        Cache::forget('global_shop_settings');

        $response = $this->get('/datenschutz');
        $response->assertOk();
        $response->assertDontSee("gtag('consent', 'default'", false);
        $response->assertDontSee('AW-987654321');
        $response->assertSee('window.sfTrackGooglePurchaseConversion', false);
        $response->assertSee('Google Tag &amp; Google Ads Conversion-Tracking', false);
    }

    public function test_frontend_renders_google_consent_mode_v2_and_blocks_static_script_until_marketing_opt_in(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'google_tag_enabled'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'google_tag_id'], ['value' => 'AW-123456789']);
        SystemSetting::updateOrCreate(['key' => 'google_ads_conversion_label'], ['value' => 'BuyLabelABC']);
        Cache::forget('global_shop_settings');

        $response = $this->get('/datenschutz');
        $response->assertOk();
        $response->assertSee("ad_storage: 'denied'", false);
        $response->assertSee("ad_user_data: 'denied'", false);
        $response->assertSee("ad_personalization: 'denied'", false);
        $response->assertSee("analytics_storage: 'denied'", false);
        $response->assertSee('AW-123456789');
        $response->assertSee('BuyLabelABC');
        $response->assertDontSee('<script async src="https://www.googletagmanager.com/gtag/js', false);
    }

    public function test_google_shopping_feed_enforces_real_photo_gatekeeper_and_excludes_ai_images(): void
    {
        SystemSetting::updateOrCreate(['key' => 'google_shopping_require_real_photos'], ['value' => 'true']);
        Cache::forget('global_shop_settings');

        Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'KI Test Kristall',
            'slug' => 'ki-test-kristall',
            'sku' => 'KI-001',
            'type' => 'physical',
            'price' => 4990,
            'status' => 'active',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/ki-bild.jpg',
                    'is_main' => true,
                    'is_ai_generated' => true,
                ],
            ],
        ]);

        Product::create([
            'id' => Str::uuid()->toString(),
            'name' => 'Echtfoto Kristall',
            'slug' => 'echtfoto-kristall',
            'sku' => 'REAL-001',
            'type' => 'physical',
            'price' => 5990,
            'status' => 'active',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/echt-hauptbild.jpg',
                    'is_main' => true,
                    'is_ai_generated' => false,
                ],
                [
                    'type' => 'image',
                    'path' => 'produkte/echt-detail.jpg',
                    'is_main' => false,
                    'is_ai_generated' => false,
                ],
                [
                    'type' => 'image',
                    'path' => 'produkte/verstecktes-ki-bild.jpg',
                    'is_main' => false,
                    'is_ai_generated' => true,
                ],
            ],
        ]);

        $response = $this->get('/feed/google-shopping.xml');
        $response->assertOk();

        $xml = $response->getContent();
        $this->assertStringNotContainsString('KI Test Kristall', $xml);
        $this->assertStringNotContainsString('verstecktes-ki-bild.jpg', $xml);
        $this->assertStringContainsString('Echtfoto Kristall', $xml);
        $this->assertStringContainsString('echt-hauptbild.jpg', $xml);
        $this->assertStringContainsString('echt-detail.jpg', $xml);
        $this->assertStringNotContainsString(' – ', $xml);

        // Wenn Echtfoto-Gatekeeper deaktiviert ist, erscheint auch das KI-Produkt
        SystemSetting::updateOrCreate(['key' => 'google_shopping_require_real_photos'], ['value' => 'false']);
        Cache::forget('global_shop_settings');

        $relaxedXml = $this->get('/feed/google-shopping.xml')->getContent();
        $this->assertStringContainsString('KI Test Kristall', $relaxedXml);
    }

    public function test_checkout_success_exposes_conversion_total_eur_and_order_number(): void
    {
        $customer = Customer::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Kundin',
            'email' => 'conversion-test@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $order = OrderOrder::create([
            'id' => Str::uuid()->toString(),
            'order_number' => 'SF-2026-9991',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'processing',
            'payment_status' => 'paid',
            'subtotal_price' => 4500,
            'tax_amount' => 718,
            'shipping_price' => 490,
            'total_price' => 4990,
            'billing_address' => [
                'first_name' => 'Test',
                'last_name' => 'Kundin',
                'email' => $customer->email,
            ],
        ]);

        $this->actingAs($customer, 'customer');

        Livewire::test(OrderCheckoutSuccess::class)
            ->assertSet('finalOrderNumber', 'SF-2026-9991')
            ->assertSet('conversionTotalEur', 49.9)
            ->assertSeeHtml('data-order-number="SF-2026-9991"')
            ->assertSeeHtml('data-conversion-value="49.90"');
    }

    public function test_system_shop_config_validates_and_saves_google_tag_settings(): void
    {
        $admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-gtag-test@example.com',
            'password' => bcrypt('Secret123!'),
        ]);

        $this->actingAs($admin, 'admin');

        Livewire::test(SystemShopConfig::class)
            ->set('settings.google_tag_enabled', true)
            ->set('settings.google_tag_id', 'aw-556677889')
            ->set('settings.google_ads_conversion_label', 'Conv_Label-01')
            ->set('settings.google_shopping_require_real_photos', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('true', SystemSetting::where('key', 'google_tag_enabled')->value('value'));
        $this->assertSame('AW-556677889', SystemSetting::where('key', 'google_tag_id')->value('value'));
        $this->assertSame('Conv_Label-01', SystemSetting::where('key', 'google_ads_conversion_label')->value('value'));
        $this->assertSame('true', SystemSetting::where('key', 'google_shopping_require_real_photos')->value('value'));

        Livewire::test(SystemShopConfig::class)
            ->set('settings.google_tag_id', 'INVALID-TAG-ID')
            ->call('save')
            ->assertHasErrors(['settings.google_tag_id']);
    }
}
