<?php

namespace Tests\Feature\Shop;

use App\Livewire\Shop\Product\ProductFrontendFilterArea;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Product\Product;
use App\Models\Product\ProductCategory;
use App\Models\System\SystemSetting;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class Louvre3DShowroomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'true']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
    }

    public function test_shop_page_renders_floating_space_card_cache_buster_and_zero_inline_3d_bloat(): void
    {
        $active = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'price' => 6990,
            'short_description' => 'Handveredeltes K9-Kristallglas mit feiner 3D-Lasergravur.',
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Geheimer Entwurf',
            'slug' => 'geheimer-entwurf',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 2990,
        ]);

        $response = $this->get('/shop');

        $response->assertStatus(200);
        $html = $response->getContent();

        $this->assertNotFalse($html);
        $this->assertStringContainsString('data-testid="hero-open-3d-showroom-btn"', $html);
        $response->assertSeeInOrder([
            'Unsere Kollektion',
            'Entdecke Unikate, die deine Seele berühren.',
            '3D shoppen',
            'Manufaktur-Kollektion',
        ]);
        $this->assertStringContainsString('id="sf-louvre-showroom-modal"', $html);
        $this->assertStringContainsString('MEIN SEELENFUNKE · LOUVRE SHOWROOM', $html);
        $this->assertStringContainsString('Zurück zum Shop', $html);
        $this->assertStringContainsString($active->name, $html);

        // Eigenständiges Scoped CSS + x-teleport="body" + z-index: 2147483000 + Fullscreen API + Capture-Escape-Close (0 unkompilierte Tailwind-Klassen!)
        $this->assertStringContainsString('<template x-teleport="body">', $html);
        $this->assertStringContainsString('id="sf-3d-lobby-scoped-css"', $html);
        $this->assertStringContainsString('z-index: 2147483000 !important', $html);
        $this->assertStringContainsString('width: 100vw !important', $html);
        $this->assertStringContainsString('height: 100dvh !important', $html);
        $this->assertStringContainsString('background: #FAF8F5 !important', $html);
        $this->assertStringContainsString('.sf-lobby-topbar', $html);
        $this->assertStringContainsString('.sf-lobby-floating-card', $html);
        $this->assertStringContainsString('data-testid="lobby-fullscreen-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-close-btn"', $html);
        $this->assertStringContainsString('toggleFullscreen()', $html);
        $this->assertStringContainsString('requestFullscreen', $html);
        $this->assertStringContainsString('exitFullscreen', $html);
        $this->assertStringContainsString('fullscreenchange', $html);
        $this->assertStringContainsString("window.addEventListener('keydown', this._captureKeydownBound, true)", $html);
        $this->assertStringContainsString('WASD / Pfeiltasten · Maus ziehen · ⛶ Vollbild · Esc Schließen', $html);
        $this->assertStringNotContainsString('z-[2147483500]', $html);
        $this->assertStringNotContainsString('bg-[#FAF8F5]/95', $html);

        // Schwebende Light-Mode Raum-Schilder (Produkte, Top-5-Helden & Gast-Ehrenportal) statt blockierender Popups
        $this->assertStringContainsString('data-testid="louvre-floating-space-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-floating-hero-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-guest-portal-card"', $html);
        $this->assertStringContainsString('Mitglieder-Lobby · Anmelden', $html);
        $this->assertStringContainsString('id="sf-lobby-instance-btn"', $html);
        $this->assertStringContainsString('id="sf-lobby-instance-menu"', $html);
        $this->assertStringContainsString('data-testid="lobby-zone-gallery-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-zone-heroes-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-stages-preview-btn"', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/join', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/sync', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/switch', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/leave', $html);
        $this->assertStringContainsString('Jetzt gestalten', $html);
        $this->assertStringContainsString('Zum Produkt', $html);

        // Spezialfähigkeiten Schnellwahl-Dock (Tasten 1-4) & Raum-Chat mit Slash-Befehlen und Freundesystem
        $this->assertStringContainsString('data-testid="louvre-abilities-dock"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-1-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-2-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-3-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-4-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-chat-toggle-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-room-chat-panel"', $html);
        $this->assertStringContainsString('/dance', $html);
        $this->assertStringContainsString('/wave', $html);
        $this->assertStringContainsString('friendsList', $html);
        $this->assertStringContainsString("import('/js/seelenfunke-louvre-showroom-3d.js?v=20261116v4')", $html);
        $this->assertStringNotContainsString('openCurrentExhibitDrawer', $html);
        $this->assertStringNotContainsString('lightboxOpen', $html);

        // 0 KB Inline-3D-Ballast beim regulären Seitenaufruf von /shop
        $this->assertStringNotContainsString('<script src="/vendor/three/three.min.js"', $html);
        $this->assertStringNotContainsString('<script src="/js/seelenfunke-louvre-showroom-3d.js"', $html);
        $this->assertStringNotContainsString('t_seelenk.glb', $html);

        // 0 KI-Gedankenstriche auf /shop
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $html);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $html);
    }

    public function test_louvre_3d_engine_js_contains_60fps_optimizations_articulated_rig_and_spatial_projection(): void
    {
        $jsPath = public_path('js/seelenfunke-louvre-showroom-3d.js');
        $this->assertFileExists($jsPath);

        $js = (string) file_get_contents($jsPath);
        $this->assertNotEmpty($js);

        // 1. 60-FPS Performance-Guards: PixelRatio <= 1.5, keine Shadow-Maps, keine PointLights/SpotLights, InstancedMesh & Staggered Queue
        $this->assertStringContainsString('Math.min(window.devicePixelRatio || 1, 1.5)', $js);
        $this->assertStringContainsString('this.renderer.shadowMap.enabled = false', $js);
        $this->assertStringNotContainsString('new THREE.PointLight', $js);
        $this->assertStringNotContainsString('new THREE.SpotLight', $js);
        $this->assertStringNotContainsString('https://', $js);
        $this->assertStringContainsString('new THREE.InstancedMesh(', $js);
        $this->assertStringContainsString('exhibitLoadQueue', $js);

        // 2. Voll artikuliertes Gelenk-Rig (Hüfte, Knie, Knöchel/Schuh, Schulter, Ellbogen, Handgelenk, Finger)
        foreach ([
            'hipPivotL',
            'hipPivotR',
            'kneePivotL',
            'kneePivotR',
            'anklePivotL',
            'anklePivotR',
            'shoulderPivotL',
            'shoulderPivotR',
            'elbowPivotL',
            'elbowPivotR',
            'wristPivotL',
            'wristPivotR',
            'fingerCurlL',
            'fingerCurlR',
        ] as $jointName) {
            $this->assertStringContainsString($jointName, $js);
        }

        // Distanzgekoppelte Schrittphase (0 % Foot-Sliding) & sanftes Gehen<->Stehen-Blending + Peer-Interpolation
        $this->assertStringContainsString('stepDist * strideFrequency', $js);
        $this->assertStringContainsString('Math.exp(-dt * 10.0)', $js);
        $this->assertStringContainsString('Math.cos(2.0 * phaseL)', $js);
        $this->assertStringContainsString('pData.peer_id', $js);

        // 3. 1:1 Goldene Pantheon "Halle der Helden" + Level 1..10 Funki-GLBs + individuelle Stufen-Animationen
        $this->assertStringContainsString('HALLE DER HELDEN', $js);
        $this->assertStringContainsString('#ECEAE4', $js);
        $this->assertStringContainsString('#133B5C', $js);
        $this->assertStringContainsString('const domeBaseY = entY + 5.35;', $js);
        $this->assertStringContainsString('((r + 0.5) / 6) * Math.PI', $js);
        $this->assertStringContainsString('FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5', $js);
        $this->assertStringContainsString('funki_lvl_1_rags.glb', $js);
        $this->assertStringContainsString('funki_lvl_10_god_of_soul.glb', $js);
        $this->assertStringContainsString('_updateHeroPedestalAnimations', $js);
        $this->assertStringContainsString('Freier Ehrenplatz', $js);

        // 4. Physisches Podest-Schild (Stufe 1) & GPU World-Space Schwebetafel (Stufe 2) ohne KI-Gedankenstriche
        $this->assertStringContainsString('buildStelePlaqueTexture', $js);
        $this->assertStringContainsString('.project(this.camera)', $js);
        $this->assertStringContainsString('translate3d(', $js);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $js);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $js);

        // 5. Doppelsprung mit 360-Grad-Salto und Schlag-Animation (Faust & Waffe) auf Maustaste
        $this->assertStringContainsString('jumpCount', $js);
        $this->assertStringContainsString('isSomersault', $js);
        $this->assertStringContainsString('somersaultProgress', $js);
        $this->assertStringContainsString('triggerStrikeAction', $js);
        $this->assertStringContainsString('_spawnDoubleJumpBurst', $js);
        $this->assertStringContainsString('_spawnUnarmedPunchBurst', $js);

        // 5b. Spezialfähigkeiten Tasten 1-4, Kugel-Morph Salto, Schutzschild, Emotes & Nero-Marquina Marmor
        $this->assertStringContainsString('buildHallOfHeroesMarbleMaps', $js);
        $this->assertStringContainsString('triggerAbility', $js);
        $this->assertStringContainsString('triggerEmote', $js);
        $this->assertStringContainsString('getAbilityState', $js);
        $this->assertStringContainsString('_spawnAbilityBurst', $js);
        $this->assertStringContainsString('shieldMesh', $js);

        // 6. Eigenständiges Scoped CSS, Fullscreen API & Capture-Escape im Engine-Modul + Standalone-Host
        $this->assertStringContainsString('sf-3d-lobby-scoped-css', $js);
        $this->assertStringContainsString('z-index: 2147483000 !important', $js);
        $this->assertStringContainsString('requestLobbyFullscreen', $js);
        $this->assertStringContainsString('fullscreenchange', $js);

        $hostJsPath = public_path('js/funkenwerk-heroes-3d.js');
        $this->assertFileExists($hostJsPath);
        $hostJs = (string) file_get_contents($hostJsPath);
        $this->assertStringContainsString('sf-3d-lobby-scoped-css', $hostJs);
        $this->assertStringContainsString('z-index:2147483000 !important', $hostJs);
        $this->assertStringContainsString('document.body.appendChild(root)', $hostJs);
        $this->assertStringContainsString('toggleFullscreen', $hostJs);
        $this->assertStringContainsString('requestFullscreen', $hostJs);
        $this->assertStringContainsString('exitFullscreen', $hostJs);
        $this->assertStringContainsString("window.addEventListener('keydown', this._boundKeydown, true)", $hostJs);
        $this->assertStringContainsString('/js/seelenfunke-louvre-showroom-3d.js?v=20261116v4', $hostJs);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $hostJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $hostJs);

        // 7. Fotorealistische Architektur-Texturen (Calacatta, Boiserie, Nero Marquina, Golden-Hour PMREM, Pantheon Boden & Kuppel)
        $this->assertStringContainsString('PMREMGenerator', $js);
        $this->assertStringContainsString('fromEquirectangular', $js);
        $this->assertStringContainsString('TextureLoader', $js);
        $this->assertStringContainsString('bumpMap', $js);

        foreach ([
            '/shop/showroom/textures/calacatta_gold_floor.jpg',
            '/shop/showroom/textures/louvre_boiserie_wall.jpg',
            '/shop/showroom/textures/nero_marquina_pedestal.jpg',
            '/shop/showroom/textures/museum_golden_hour_env.jpg',
            '/shop/showroom/textures/pantheon_carrara_gold_floor.jpg',
            '/shop/showroom/textures/pantheon_coffered_dome.jpg',
        ] as $texUrl) {
            $this->assertStringContainsString($texUrl, $js);
            $absTexPath = public_path(ltrim($texUrl, '/'));
            $this->assertFileExists($absTexPath);
            $this->assertGreaterThan(50 * 1024, (int) filesize($absTexPath));
        }
    }

    public function test_showroom_data_returns_active_products_gallery_three_benefits_and_guest_funki_level_2(): void
    {
        $category = ProductCategory::create([
            'name' => 'Glas & Kristall',
            'slug' => 'glas-kristall',
            'type' => 'physical',
        ]);

        $crystal = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'produkte/products/seelen-kristall/t_seelenk.glb',
            'price' => 6990,
            'tax_class' => 'standard',
            'short_description' => 'Kristallglas Unikat ' . "\u{2013}" . ' mit 3D-Lasergravur aus Gifhorn.',
            'media_gallery' => [
                [
                    'type' => 'image',
                    'path' => 'produkte/products/seelen-kristall/hauptbild.jpg',
                    'alt' => 'Der Seelen Kristall Hauptansicht',
                    'is_main' => true,
                    'is_ai_generated' => true,
                ],
                [
                    'type' => 'image',
                    'path' => 'produkte/products/seelen-kristall/massbild-mm.jpg',
                    'alt' => 'Technisches Maßbild in mm',
                    'is_ai_generated' => false,
                ],
                [
                    'type' => 'image',
                    'path' => 'produkte/products/seelen-kristall/detail.jpg',
                    'alt' => 'Detailansicht Schliff',
                    'is_ai_generated' => true,
                ],
            ],
            'attributes' => [
                'Material' => 'Optisch reines K9 Kristallglas',
            ],
        ]);
        $crystal->categories()->sync([$category->id]);

        $goldHeart = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Das Goldene Seelen-Herz',
            'slug' => 'goldenes-seelen-herz',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'three_d_active' => true,
            'three_d_model_path' => 'produkte/products/goldenes-seelen-herz/goldenes_seelen_herz_3d.glb',
            'price' => 3990,
            'attributes' => [
                'Material' => '14 Karat vergoldeter Edelstahl (Ion-Plating)',
            ],
        ]);

        // Entwurf & internes System-Artefakt müssen strikt ausgeblendet bleiben
        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Unfertiger Entwurf',
            'slug' => 'unfertiger-entwurf',
            'status' => 'draft',
            'type' => 'physical',
            'price' => 1990,
        ]);

        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ingame System Artefakt',
            'slug' => 'seelenfunke-ingame-artifact',
            'status' => 'active',
            'type' => 'digital',
            'price' => 100,
        ]);

        $response = $this->getJson('/shop/showroom-data');

        $response->assertStatus(200);
        $rawJson = $response->getContent();
        $this->assertNotFalse($rawJson);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $rawJson);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['total_products']);

        // Gast erhält Funki Level 2
        $this->assertTrue($data['player_funki']['is_guest']);
        $this->assertSame(2, $data['player_funki']['level']);
        $this->assertSame('/shop/customer/gamification/models/funki_lvl_2_basic.glb', $data['player_funki']['glb_url']);

        $productsBySlug = collect($data['products'])->keyBy('slug');
        $this->assertTrue($productsBySlug->has('seelen-kristall'));
        $this->assertTrue($productsBySlug->has('goldenes-seelen-herz'));
        $this->assertFalse($productsBySlug->has('unfertiger-entwurf'));
        $this->assertFalse($productsBySlug->has('seelenfunke-ingame-artifact'));

        $crystalPayload = $productsBySlug->get('seelen-kristall');
        $this->assertSame('69,90 €', $crystalPayload['price_formatted']);
        $this->assertSame('/produkt/seelen-kristall', $crystalPayload['product_url']);
        $this->assertStringStartsWith('/storage/produkte/products/seelen-kristall/t_seelenk.glb', (string) $crystalPayload['glb_url']);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $crystalPayload['short_description']);
        $this->assertCount(3, $crystalPayload['benefits']);

        // Maßbild muss zwingend auf der letzten Position der Galerie stehen
        $this->assertCount(3, $crystalPayload['gallery_images']);
        $lastGalleryImg = end($crystalPayload['gallery_images']);
        $this->assertTrue($lastGalleryImg['is_dimension_sheet']);
        $this->assertStringContainsString('massbild-mm.jpg', $lastGalleryImg['url']);

        // Abmahnsichere Materialangaben beim vergoldeten Edelstahl-Schmuck
        $heartPayload = $productsBySlug->get('goldenes-seelen-herz');
        $this->assertCount(3, $heartPayload['benefits']);
        $this->assertContains('Handveredelt in Deutschland (Manufaktur Gifhorn)', $heartPayload['benefits']);
        $this->assertContains('14 Karat vergoldeter Edelstahl (Ion-Plating)', $heartPayload['benefits']);
    }

    public function test_showroom_data_returns_logged_in_customer_funki_level_glb(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Lena',
            'last_name' => 'Muster',
            'email' => 'lena@example.com',
            'password' => bcrypt('secret123'),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => 8,
            'funken_balance' => 300,
            'funken_total_earned' => 2950,
        ]);

        $response = $this->actingAs($customer, 'customer')->getJson('/shop/showroom-data');

        $response->assertStatus(200);
        $funki = $response->json('player_funki');
        $this->assertFalse($funki['is_guest']);
        $this->assertSame(8, $funki['level']);
        $this->assertSame('/shop/customer/gamification/models/funki_lvl_8_master.glb', $funki['glb_url']);
    }

    public function test_showroom_data_is_protected_by_maintenance_mode_for_guests(): void
    {
        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'true']);
        $this->getJson('/shop/showroom-data')->assertStatus(503);

        SystemSetting::updateOrCreate(['key' => 'shop_maintenance_mode'], ['value' => 'false']);
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        $this->getJson('/shop/showroom-data')->assertStatus(503);

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
        $this->actingAs($admin, 'admin')->getJson('/shop/showroom-data')->assertStatus(200);
    }

    public function test_product_frontend_filter_area_direct_add_to_cart_works_for_standard_products_only(): void
    {
        $standardProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Pflegetuch Set Schwarz-Gold',
            'slug' => 'pflegetuch-set',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => false,
            'price' => 990,
            'track_quantity' => true,
            'quantity' => 25,
        ]);

        $personalizableProduct = Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Der Seelenanhänger',
            'slug' => 'seelen-anhaenger',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'price' => 2990,
            'track_quantity' => true,
            'quantity' => 25,
        ]);

        Livewire::test(ProductFrontendFilterArea::class)
            ->call('addToCart', $personalizableProduct->id)
            ->assertReturned(false);

        Livewire::test(ProductFrontendFilterArea::class)
            ->call('addToCart', $standardProduct->id)
            ->assertReturned(true)
            ->assertDispatched('cart-updated');

        $cart = app(CartService::class)->getCart(false);
        $this->assertNotNull($cart);
        $this->assertSame(1, $cart->items()->where('product_id', $standardProduct->id)->count());
    }
}
