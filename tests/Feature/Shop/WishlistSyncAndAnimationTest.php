<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Product\ProductCalculator\ProductCalculator;
use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Livewire\Shop\Product\ProductReviews;
use App\Livewire\Shop\Product\ProductShow;
use App\Livewire\Shop\Wishlist\WishlistIcon;
use App\Livewire\Shop\Wishlist\WishlistPage;
use App\Models\Cart\WishlistItem;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\System\SystemSetting;
use App\Services\WishlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WishlistSyncAndAnimationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'quote_calculator_enabled'], ['value' => 'true']);
        Cache::forget('global_shop_settings');
    }

    private function createProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Seelenfunke Gravur Anhänger',
            'slug' => 'seelenfunke-gravur-anhaenger-' . Str::lower(Str::random(5)),
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 3490,
            'short_description' => '14 Karat vergoldeter Edelstahl (Ion-Plating).',
            'description' => 'Handveredelt in Deutschland (Manufaktur Gifhorn).',
            'track_quantity' => false,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
        ], $overrides));
    }

    private function createVerifiedCustomer(): Customer
    {
        return Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Testkundin',
            'email' => 'alina-wl-' . Str::lower(Str::random(6)) . '@example.org',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
            'needs_password_change' => false,
        ]);
    }

    public function test_rapid_multi_toggle_and_duplicate_self_healing_keep_wishlist_100_percent_consistent(): void
    {
        Session::start();
        $sessionId = (string) Session::getId();

        $productA = $this->createProduct(['name' => 'Unikat A']);
        $productDraft = $this->createProduct(['name' => 'Entwurf B', 'status' => 'draft']);
        $service = app(WishlistService::class);

        // 1. Schnelles Mehrfach-Toggeln (true -> false -> true -> false) + Idempotenz
        $this->assertTrue($service->setWishlisted($productA->id, true));
        $this->assertTrue($service->setWishlisted($productA->id, true));
        $this->assertSame(1, WishlistItem::where('product_id', $productA->id)->count());
        $this->assertSame(1, $service->getCount());
        $this->assertSame([$productA->id], $service->getProductIds());

        $this->assertFalse($service->setWishlisted($productA->id, false));
        $this->assertFalse($service->setWishlisted($productA->id, false));
        $this->assertSame(0, WishlistItem::where('product_id', $productA->id)->count());
        $this->assertSame(0, $service->getCount());
        $this->assertSame([], $service->getProductIds());
        $this->assertFalse($service->has($productA->id));

        $this->assertTrue($service->setWishlisted($productA->id, true));
        $this->assertFalse($service->setWishlisted($productA->id, false));
        $this->assertSame(0, $service->getCount());

        // 2. Kuenstliche Dubletten in wishlist_items -> getCount() liefert 1, setWishlisted(true) bereinigt auf 1, setWishlisted(false) loescht restlos alle!
        for ($i = 0; $i < 3; $i++) {
            WishlistItem::create([
                'customer_id' => null,
                'session_id' => $sessionId,
                'product_id' => $productA->id,
            ]);
        }
        WishlistItem::create([
            'customer_id' => null,
            'session_id' => $sessionId,
            'product_id' => $productDraft->id,
        ]);

        $this->assertSame(3, WishlistItem::where('product_id', $productA->id)->count());
        $this->assertSame(1, $service->getCount());
        $this->assertSame([$productA->id], $service->getProductIds());
        $this->assertFalse($service->has($productDraft->id));

        // Selbstheilung bei setWishlisted(true): reduziert 3 Zeilen auf exakt 1
        $this->assertTrue($service->setWishlisted($productA->id, true));
        $this->assertSame(1, WishlistItem::where('product_id', $productA->id)->count());

        // Nochmals 2 Dubletten simulieren und via klassischem toggle() / setWishlisted(false) restlos entfernen
        WishlistItem::create([
            'customer_id' => null,
            'session_id' => $sessionId,
            'product_id' => $productA->id,
        ]);
        $this->assertSame(2, WishlistItem::where('product_id', $productA->id)->count());

        $this->assertFalse($service->toggle($productA->id));
        $this->assertSame(0, WishlistItem::where('product_id', $productA->id)->count());
        $this->assertSame(0, $service->getCount());
        $this->assertSame([], $service->getProductIds());

        // 3. Gast-Session-Dubletten beim Login dublettenfrei zusammenfuehren
        WishlistItem::create(['customer_id' => null, 'session_id' => $sessionId, 'product_id' => $productA->id]);
        WishlistItem::create(['customer_id' => null, 'session_id' => $sessionId, 'product_id' => $productA->id]);

        $customer = $this->createVerifiedCustomer();
        $this->actingAs($customer, 'customer');

        $this->assertSame(1, $service->getCount());
        $this->assertSame(1, WishlistItem::where('customer_id', $customer->id)->where('product_id', $productA->id)->count());
        $this->assertSame(0, WishlistItem::whereNull('customer_id')->where('session_id', $sessionId)->count());
    }

    public function test_product_show_filter_area_wishlist_page_and_header_icon_sync_count_zero_and_animation_guards(): void
    {
        $customer = $this->createVerifiedCustomer();
        $this->actingAs($customer, 'customer');
        $product = $this->createProduct(['name' => 'Kristall Tropfen']);

        // 1. ProductShow::toggleWishlist(true / false) + atomarer JSON-Store sfWishlistButtonData
        $showTest = Livewire::test(ProductShow::class, ['product' => $product]);
        $showHtml = $showTest->html();
        $this->assertStringContainsString('wishlist-optimistic-delta', $showHtml);
        $this->assertStringContainsString('sfWishlistButtonData', $showHtml);

        $showTest->call('toggleWishlist', true)
            ->assertDispatched('wishlist-updated', count: 1, productIds: [$product->id]);

        $showTest->call('toggleWishlist', true)
            ->assertDispatched('wishlist-updated', count: 1, productIds: [$product->id]);

        // 2. Header WishlistIcon zeigt Zähler 1, nutzt <template x-if="localCount > 0"> (kein 0-Badge!) und enthaelt Cancel-Guard
        $iconTest = Livewire::test(WishlistIcon::class)
            ->assertSet('count', 1)
            ->assertSet('productIds', [$product->id]);

        $iconHtml = $iconTest->html();
        $this->assertStringContainsString('_sfActiveWishlistCanvasCancel', $iconHtml);
        $this->assertStringContainsString('scale(0.88)', $iconHtml);
        $this->assertStringContainsString('wishlist-optimistic-delta', $iconHtml);
        $this->assertStringContainsString('<template x-if="localCount > 0">', $iconHtml);
        $this->assertStringContainsString('/wunschliste/toggle', $iconHtml);
        $this->assertStringContainsString('mySeq !== this.seq', $iconHtml);

        // Entfernen ueber WishlistIcon::removeItem setzt Zähler sofort auf 0
        $iconTest->call('removeItem', $product->id)
            ->assertSet('count', 0)
            ->assertSet('productIds', [])
            ->assertDispatched('wishlist-updated', count: 0, productIds: []);

        // Erneutes ProductShow::toggleWishlist(false) bleibt hart auf 0 (toggelt NICHT versehentlich wieder auf 1!)
        $showTest->call('toggleWishlist', false)
            ->assertDispatched('wishlist-updated', count: 0, productIds: []);
        $this->assertSame(0, app(WishlistService::class)->getCount());

        // 3. Atomarer JSON-Endpunkt POST /wunschliste/toggle & GET /wunschliste/state mit client_seq-Schutz
        $this->postJson('/wunschliste/toggle', [
            'product_id' => $product->id,
            'active' => true,
            'client_seq' => 7,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('active', true)
            ->assertJsonPath('client_seq', 7)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('product_ids.0', $product->id);

        $this->getJson('/wunschliste/state')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('product_ids.0', $product->id);

        $this->postJson('/wunschliste/toggle', [
            'product_id' => $product->id,
            'active' => false,
            'client_seq' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('active', false)
            ->assertJsonPath('client_seq', 8)
            ->assertJsonPath('count', 0)
            ->assertJsonPath('product_ids', []);

        // 4. ProductFrontendFilterArea & WishlistPage unterstützen denselben expliziten State-Parameter
        Livewire::test(ProductFrontendFilterArea::class)
            ->call('toggleWishlist', $product->id, true)
            ->assertDispatched('wishlist-updated', count: 1, productIds: [$product->id]);

        $wlPage = Livewire::test(WishlistPage::class);
        $this->assertStringContainsString('sfWishlistButtonData', $wlPage->html());

        $wlPage->call('toggleWishlist', $product->id, false)
            ->assertDispatched('wishlist-updated', count: 0, productIds: []);
        $this->assertSame(0, app(WishlistService::class)->getCount());
    }

    public function test_calculator_step_zero_and_product_review_form_render_clean_thin_gold_aura_and_spark_flow(): void
    {
        // 1. Angebotskalkulator Schritt 0
        $calcHtml = html_entity_decode(Livewire::test(ProductCalculator::class)->html(), ENT_QUOTES);
        $this->assertStringContainsString('x-ref="calcAgbBox"', $calcHtml);
        $this->assertStringContainsString('x-ref="calcStartBtn"', $calcHtml);
        $this->assertStringContainsString('sf-magic-thin-aura-ring', $calcHtml);
        $this->assertStringContainsString('cursor-not-allowed', $calcHtml);
        $this->assertStringContainsString('onLockedStartHover()', $calcHtml);
        $this->assertStringContainsString("mode: 'unlock'", $calcHtml);
        $this->assertStringContainsString("mode: 'relock'", $calcHtml);
        $this->assertStringContainsString("mode: 'hint'", $calcHtml);

        // 2. Produktbewertungs-Formular (Light-Mode im Shop, kein bg-amber-600 Button!)
        $customer = $this->createVerifiedCustomer();
        $product = $this->createProduct();

        $order = OrderOrder::create([
            'order_number' => 'ORD-REV-' . Str::upper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 3490,
            'tax_amount' => 557,
            'shipping_price' => 0,
            'total_price' => 3490,
            'billing_address' => ['first_name' => 'Alina', 'last_name' => 'Testkundin'],
            'shipping_address' => ['first_name' => 'Alina', 'last_name' => 'Testkundin'],
        ]);
        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 3490,
            'total_price' => 3490,
        ]);

        $this->actingAs($customer, 'customer');
        $reviewHtml = html_entity_decode(Livewire::test(ProductReviews::class, ['product' => $product])->html(), ENT_QUOTES);

        $this->assertStringContainsString('data-product-review-experience-form="true"', $reviewHtml);
        $this->assertStringContainsString('x-ref="reviewTermsBox"', $reviewHtml);
        $this->assertStringContainsString('x-ref="reviewSubmitBtn"', $reviewHtml);
        $this->assertStringContainsString('sf-magic-thin-aura-ring', $reviewHtml);
        $this->assertStringContainsString('cursor-not-allowed', $reviewHtml);
        $this->assertStringContainsString('onLockedReviewSubmitHover()', $reviewHtml);
        $this->assertStringContainsString('bg-gray-900 text-white hover:bg-black', $reviewHtml);
        $this->assertStringNotContainsString('bg-amber-600', $reviewHtml);
        $this->assertStringNotContainsString('hover:bg-amber-700', $reviewHtml);
        $this->assertStringContainsString("mode: 'unlock'", $reviewHtml);
        $this->assertStringContainsString("mode: 'relock'", $reviewHtml);
        $this->assertStringContainsString("mode: 'hint'", $reviewHtml);

        // 3. 0 KI-Gedankenstriche in allen bearbeiteten Dateien
        $enDashPattern = ' ' . "\u{2013}" . ' ';
        $checkedFiles = [
            app_path('Services/WishlistService.php'),
            app_path('Livewire/Shop/Product/ProductShow.php'),
            app_path('Livewire/Shop/Product/ProductFrontendFilterArea.php'),
            app_path('Livewire/Shop/Wishlist/WishlistPage.php'),
            app_path('Livewire/Shop/Wishlist/WishlistIcon.php'),
            resource_path('views/livewire/shop/wishlist/wishlist-icon.blade.php'),
            resource_path('views/livewire/shop/wishlist/wishlist-page.blade.php'),
            resource_path('views/livewire/shop/product/partials/product-show/right.blade.php'),
            resource_path('views/livewire/shop/product/product-calculator/product-calculator.blade.php'),
            resource_path('views/components/shop/product-review-experience-form.blade.php'),
            base_path('tests/Feature/Shop/WishlistSyncAndAnimationTest.php'),
        ];

        foreach ($checkedFiles as $path) {
            $this->assertFileExists($path);
            $this->assertStringNotContainsString(
                $enDashPattern,
                (string) file_get_contents($path),
                'Verbotener KI-Gedankenstrich gefunden in: ' . $path
            );
        }
    }
}
