<?php

namespace Tests\Feature\Shop;

use App\Livewire\Frontend\Support\AiFrontendSupportChat;
use App\Livewire\Shop\Cart\CartIcon;
use App\Livewire\Shop\Marketing\MarketingVoucherPurchasePage;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckout;
use App\Models\Customer\Customer;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutAndSupportSparkFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');
    }

    public function test_cart_icon_and_voucher_purchase_page_expose_to_cart_spark_flow_hooks(): void
    {
        Product::create([
            'name' => 'Geschenkgutschein',
            'slug' => 'geschenkgutschein',
            'type' => 'digital',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 5000,
            'track_quantity' => false,
            'quantity' => 99,
        ]);

        Livewire::test(CartIcon::class)
            ->assertStatus(200)
            ->assertSee('data-sf-cart-header-icon', false);

        $this->get('/shop')
            ->assertStatus(200)
            ->assertSee('data-sf-cart-header-icon', false)
            ->assertSee('window.sfSparkFlow', false);

        Livewire::test(MarketingVoucherPurchasePage::class)
            ->set('amount', 50)
            ->set('recipientName', 'Alina Steinhauer')
            ->set('deliveryMethod', 'email')
            ->set('recipientEmail', 'alina@example.com')
            ->set('currentStep', 3)
            ->assertStatus(200)
            ->assertSee("window.sfSparkFlow?.fly(\$el, '[data-sf-cart-header-icon]', { mode: 'to-cart' })", false);
    }

    public function test_order_checkout_renders_legal_spark_flow_and_cursor_not_allowed_for_physical_and_digital_carts(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer');

        $physicalProduct = Product::create([
            'name' => 'Seelen Kristall Herz',
            'slug' => 'seelen-kristall-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 4990,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $digitalProduct = Product::create([
            'name' => 'Digitales Gravur-Handbuch',
            'slug' => 'digital-handbuch-' . Str::lower(Str::random(5)),
            'type' => 'digital',
            'status' => 'active',
            'is_personalizable' => false,
            'price' => 990,
            'digital_download_path' => 'downloads/handbuch.pdf',
            'track_quantity' => false,
            'quantity' => 99,
        ]);

        app(CartService::class)->addItem($physicalProduct, 1, ['text' => 'Für dich']);
        app(CartService::class)->addItem($digitalProduct, 1, []);

        $resp = $this->actingAs($customer, 'customer')->get(route('checkout'));
        $resp->assertStatus(200)
            ->assertSee('window.sfSparkFlow', false)
            ->assertSee('checkout-legal-flow', false)
            ->assertSee('checkout-cta-hint', false)
            ->assertSee('isLegalConfirmed()', false)
            ->assertSee('cursor-not-allowed', false)
            ->assertSee('sf-magic-thin-aura-ring', false)
            ->assertSee('sf-magic-thin-aura-cta', false)
            ->assertSee('id="digital_waiver"', false);

        Livewire::actingAs($customer, 'customer')
            ->test(OrderCheckout::class)
            ->assertStatus(200)
            ->assertSee("mode: 'unlock'", false)
            ->assertSee("mode: 'relock'", false)
            ->assertSee("mode: 'hint'", false);
    }

    public function test_ai_frontend_support_chat_uses_spark_flow_guidance_without_hard_disabled_attribute(): void
    {
        $component = Livewire::test(AiFrontendSupportChat::class)
            ->assertStatus(200)
            ->assertSet('hasAcceptedPrivacy', false)
            ->assertSet('privacyChecked', false)
            ->assertSee('sfSparkFlow', false)
            ->assertSee('support-chat-privacy-flow', false)
            ->assertSee('support-chat-privacy-hint', false)
            ->assertSee('sf-magic-thin-aura-ring', false)
            ->assertSee('cursor-not-allowed', false)
            ->assertSee('handlePrivacySubmit($el)', false);

        $html = $component->html();
        // Der Start-Button darf kein hartes HTML-disabled tragen, damit Hover- & Klick-Hint-Funkenflug aktiv bleiben
        $this->assertDoesNotMatchRegularExpression('/x-ref="privacyStartBtn"[^>]*\sdisabled(\s|>|=)/', $html);

        $component->set('privacyChecked', true)
            ->call('acceptPrivacy')
            ->assertSet('hasAcceptedPrivacy', true)
            ->assertSee('Schreibe deine Nachricht...');
    }

    public function test_modified_spark_flow_views_comply_with_clean_design_baustein_rules(): void
    {
        $files = [
            resource_path('views/livewire/shop/cart/cart-icon.blade.php'),
            resource_path('views/livewire/shop/marketing/marketing-voucher-purchase-page.blade.php'),
            resource_path('views/livewire/shop/order/order-checkout/partials/right-column-summary.blade.php'),
            resource_path('views/livewire/frontend/support/ai-frontend-support-chat.blade.php'),
        ];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $content, "KI-Gedankenstrich gefunden in {$file}");
            $this->assertDoesNotMatchRegularExpression('/x-init="[^"]*\btry\s*\{/', $content, "Inline try/catch in x-init gefunden in {$file}");
            $this->assertStringNotContainsString('dark:', $content, "Darkmode-Klasse im Frontend gefunden in {$file}");
        }
    }
}
