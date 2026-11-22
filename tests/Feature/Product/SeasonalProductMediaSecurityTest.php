<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductConfigurator\ProductConfigurator;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Customer\Customer;
use App\Models\Marketing\MarketingLandingPage;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonalProductMediaSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'shop_live_unlocked'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        Cache::forget('global_shop_settings');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTinyJpeg(): string
    {
        $im = imagecreatetruecolor(8, 8);
        $gold = imagecolorallocate($im, 197, 160, 89);
        imagefilledrectangle($im, 0, 0, 8, 8, $gold);
        ob_start();
        imagejpeg($im, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);
        return $bytes;
    }

    private function createProductWithAllTwelvePrivateSeasonalImages(): Product
    {
        $slug = 'sec-anhaenger-' . Str::lower(Str::random(6));
        Storage::disk('public')->put("produkte/products/{$slug}/{$slug}-standard.jpg", $this->makeTinyJpeg());

        $seasonalMap = [];
        foreach (array_keys(Product::getAllSeasonalAndHolidaySlots()) as $holidayKey) {
            $slotSlug = ProductTemplate::resolveSeasonalSlotFileSlug($holidayKey);
            $relPath = trim(ProductTemplate::PRIVATE_SEASONAL_PREFIX, '/') . "/{$slug}/{$slotSlug}.jpg";
            Storage::disk('local')->put($relPath, $this->makeTinyJpeg());
            $seasonalMap[$holidayKey] = [
                'path' => $relPath,
                'is_ai_generated' => true,
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        return Product::create([
            'name' => 'Test Seelenanhänger Security',
            'slug' => $slug,
            'type' => 'physical',
            'status' => 'active',
            'is_personalizable' => true,
            'price' => 2990,
            'short_description' => 'Handveredelt in Deutschland.',
            'description' => 'Beschreibung',
            'track_quantity' => true,
            'quantity' => 5,
            'continue_selling_when_out_of_stock' => true,
            'width' => 45,
            'height' => 40,
            'length' => 2,
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => "produkte/products/{$slug}/{$slug}-standard.jpg",
                    'is_main' => true,
                    'is_hover' => false,
                    'is_beginner' => true,
                    'is_ai_generated' => false,
                    'alt' => 'Standard Foto',
                ],
            ],
            'configurator_settings' => [
                'personalization_mode' => 'studio',
                'auto_seasonal_main_image' => true,
                'seasonal_images' => $seasonalMap,
            ],
        ]);
    }

    public function test_guests_only_access_active_seasonal_image_and_receive_404_for_all_inactive_slots(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));

        $product = $this->createProductWithAllTwelvePrivateSeasonalImages();
        $overview = $product->getSeasonalMainImagesOverview();

        $this->assertTrue($product->getSeasonalImageSetStatus()['is_complete']);
        $this->assertSame('Weihnachten', Product::resolveActivePrimarySeasonalSlot()['slot']);

        // 1) Nur Weihnachten (heute aktiv) liefert 200 OK; alle anderen 11 Slots liefern 404
        foreach ($overview as $holidayKey => $slot) {
            $urlPath = parse_url($slot['url'], PHP_URL_PATH);
            $res = $this->get($urlPath);

            if ($holidayKey === 'Weihnachten') {
                $res->assertStatus(200);
                $this->assertStringContainsString('no-cache', (string) $res->headers->get('Cache-Control'));
            } else {
                $res->assertStatus(404);
            }
        }

        // 2) Frontend-Produktseite & Configurator-Snapshot duerfen keine inaktiven Tokens oder Pfade leaken
        $page = $this->get(route('product.show', $product->slug));
        $page->assertStatus(200);
        $html = $page->getContent();
        $this->assertStringNotContainsString('seasonal_images', $html);
        $this->assertStringNotContainsString('products-secure', $html);

        $weihnachtenToken = basename((string) parse_url($overview['Weihnachten']['url'], PHP_URL_PATH));
        $osternToken = basename((string) parse_url($overview['Ostern']['url'], PHP_URL_PATH));
        $this->assertStringContainsString($weihnachtenToken, $html);
        $this->assertStringNotContainsString($osternToken, $html);

        Livewire::test(ProductConfigurator::class, ['product' => $product])
            ->assertSet('configSettings.seasonal_images', null);

        // 3) LandingPage ?holiday=ostern fuer Gaeste ignoriert inaktiven Feiertag fuer Medien
        MarketingLandingPage::create([
            'product_id' => $product->id,
            'slug' => $product->slug,
            'title' => 'Landing Test',
            'status' => 'active',
        ]);
        $lpResp = $this->get('/l/' . $product->slug . '?holiday=ostern');
        $lpResp->assertStatus(200);
        $this->assertStringNotContainsString($osternToken, $lpResp->getContent());

        // 4) Admin darf im Backend alle 12 Slots einsehen (200 OK)
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-sec-' . Str::lower(Str::random(5)) . '@mein-seelenfunke.de',
            'password' => Hash::make('Secret123!'),
        ]);
        AdminProfile::firstOrCreate(['admin_id' => $admin->id], ['city' => 'Gifhorn', 'country' => 'DE']);

        foreach ($overview as $slot) {
            $urlPath = parse_url($slot['url'], PHP_URL_PATH);
            $this->actingAs($admin, 'admin')->get($urlPath)->assertStatus(200);
        }
    }

    public function test_customer_with_historical_order_can_view_own_purchased_seasonal_image_only(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 2, 12, 0, 0));

        $product = $this->createProductWithAllTwelvePrivateSeasonalImages();
        $winterPath = $product->configurator_settings['seasonal_images']['Winter']['path'];
        $halloweenPath = $product->configurator_settings['seasonal_images']['Halloween']['path'];
        $winterToken = ProductTemplate::makeSeasonalMediaToken($product->slug, $winterPath);
        $halloweenToken = ProductTemplate::makeSeasonalMediaToken($product->slug, $halloweenPath);

        $buyer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Muster',
            'email' => 'buyer-' . Str::lower(Str::random(5)) . '@example.de',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);
        $otherCustomer = Customer::create([
            'first_name' => 'Erika',
            'last_name' => 'Fremd',
            'email' => 'other-' . Str::lower(Str::random(5)) . '@example.de',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $order = OrderOrder::create([
            'order_number' => 'ORD-SEC-' . Str::upper(Str::random(6)),
            'customer_id' => $buyer->id,
            'email' => $buyer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Max', 'last_name' => 'Muster'],
            'shipping_address' => ['first_name' => 'Max', 'last_name' => 'Muster'],
        ]);

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
            'configuration' => ['purchased_image_path' => $winterPath],
        ]);

        $winterRoute = route('product.seasonal-media', ['product' => $product->slug, 'token' => $winterToken], false);
        $halloweenRoute = route('product.seasonal-media', ['product' => $product->slug, 'token' => $halloweenToken], false);

        // Eigener Kaeufer sieht Winter-Bestellbild, aber weiterhin kein fremdes Halloween-Bild
        $this->actingAs($buyer, 'customer')->get($winterRoute)->assertStatus(200);
        $this->actingAs($buyer, 'customer')->get($halloweenRoute)->assertStatus(404);

        // Fremder Kunde darf weder Winter noch Halloween einsehen
        $this->actingAs($otherCustomer, 'customer')->get($winterRoute)->assertStatus(404);
    }

    public function test_seo_main_image_skips_dimension_sheet_in_favor_of_active_seasonal_image(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 19, 12, 0, 0));

        $product = $this->createProductWithAllTwelvePrivateSeasonalImages();
        $dimPath = "produkte/products/{$product->slug}/{$product->slug}-dimensions.jpg";
        Storage::disk('public')->put($dimPath, $this->makeTinyJpeg());

        $product->media_gallery = [
            [
                'type' => 'image',
                'path' => $dimPath,
                'is_main' => false,
                'is_hover' => false,
                'is_beginner' => false,
                'is_ai_generated' => false,
                'alt' => 'Massblatt',
            ],
        ];
        $product->save();

        $seoMeta = $product->resolveSeoMainImageMeta();
        $this->assertFalse(Product::isDimensionSheetPath($seoMeta['path'] ?? null));
        $this->assertStringContainsString('Weihnachten', (string) ($seoMeta['label'] ?? ''));
    }
}
