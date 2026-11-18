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
        $this->assertStringContainsString('bg-primary text-gray-900 px-8 py-4 rounded-full font-bold text-lg', $html);
        $this->assertStringNotContainsString('ZIP Blueprint', $html);
        $this->assertStringNotContainsString('Architektur-Blueprint', $html);
        $response->assertSeeInOrder([
            'Unsere Kollektion',
            'Entdecke Unikate, die deine Seele berühren.',
            '3D Shoppen',
            $active->name,
        ]);
        $this->assertStringContainsString('id="sf-louvre-showroom-modal"', $html);
        $this->assertStringContainsString('data-sf-3d-lobby-modal="showroom"', $html);
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
        $this->assertStringNotContainsString('data-testid="lobby-fullscreen-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-close-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-audio-mute-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-fullscreen-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-mute-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-exit-btn"', $html);
        $this->assertStringContainsString('toggleFullscreen()', $html);
        $this->assertStringContainsString('requestFullscreen', $html);
        $this->assertStringContainsString('exitFullscreen', $html);
        $this->assertStringContainsString('fullscreenchange', $html);
        $this->assertStringContainsString("window.addEventListener('keydown', this._captureKeydownBound, true)", $html);
        $this->assertStringNotContainsString('WASD / Pfeiltasten · Maus ziehen', $html);
        $this->assertStringNotContainsString('z-[2147483500]', $html);
        $this->assertStringNotContainsString('bg-[#FAF8F5]/95', $html);

        // Schwebende Light-Mode Raum-Schilder (Produkte, Top-5-Helden, Ehrenportal, 3D-Bewertungsstation, Tor zur Außenwelt & 2-Stufen-Voraussetzungs-Modal)
        $this->assertStringContainsString('data-testid="louvre-floating-space-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-floating-hero-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-guest-portal-card"', $html);
        $this->assertStringContainsString('data-testid="atelier-star-rating-card"', $html);
        $this->assertStringContainsString('data-testid="atelier-star-login-btn"', $html);
        $this->assertStringContainsString('Wie gefällt dir unser Atelier?', $html);
        $this->assertStringContainsString('Anmelden &amp; bewerten', $html);
        $this->assertStringContainsString('submitAtelierStarRating', $html);
        $this->assertStringContainsString('submitAtelierStarComment', $html);
        $this->assertStringContainsString('keep_existing_comment: true', $html);
        $this->assertStringContainsString('data-testid="lobby-outer-gate-card"', $html);
        $this->assertStringContainsString('Tor zur Außenwelt', $html);
        $this->assertStringContainsString('Fantastische Welt &amp; Abenteuer in Planung', $html);
        $this->assertStringContainsString('Hinter diesem Tor warten bald eine fantastische Welt und spannende Abenteuer auf dich', $html);
        $this->assertStringNotContainsString('Hier entsteht ein MMORPG', $html);
        $this->assertStringContainsString('data-testid="lobby-outer-gate-modal"', $html);
        $this->assertStringContainsString('data-testid="outer-gate-enter-mmorpg-btn"', $html);
        $this->assertStringContainsString('In die Außenwelt starten', $html);
        $this->assertStringContainsString('/funkenreich/enter', $html);
        $this->assertStringContainsString('data-testid="lobby-sanctum-gate-modal"', $html);
        $this->assertStringContainsString('data-testid="sanctum-check-login"', $html);
        $this->assertStringContainsString('data-testid="sanctum-check-magic"', $html);
        $this->assertStringNotContainsString('data-testid="sanctum-check-order"', $html);
        $this->assertStringNotContainsString('Mindestens 1 Bestellung', $html);
        $this->assertStringNotContainsString('Kollektion ansehen', $html);
        $this->assertStringNotContainsString('focusNearestLobbyVitrine', $html);
        $this->assertStringContainsString('Angemeldet', $html);
        $this->assertStringContainsString('✨ Magie aktivieren', $html);

        // Baustein 0 (Saubere Trennung): Null Spiel-Ballast im Shop (kein Händler, keine Tränke/Rucksack, keine Herzen/Mana, keine Kampfleiste, kein Magma-Level)
        $this->assertStringNotContainsString('data-testid="lobby-merchant-modal"', $html);
        $this->assertStringNotContainsString('data-testid="merchant-buy-potion-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-inventory-modal"', $html);
        $this->assertStringNotContainsString('data-testid="item-slot-1-btn"', $html);
        $this->assertStringNotContainsString('data-testid="item-slot-2-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-inventory-toggle-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-master-key-modal"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-lava-chest-card"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-lava-demote-banner"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-unit-hearts"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-vital-hud"', $html);
        $this->assertStringNotContainsString('>Mana<', $html);
        $this->assertStringNotContainsString('data-testid="louvre-abilities-dock"', $html);
        $this->assertStringNotContainsString('data-testid="ability-slot-1-btn"', $html);
        $this->assertStringNotContainsString('data-testid="ability-slot-2-btn"', $html);
        $this->assertStringNotContainsString('data-testid="ability-slot-3-btn"', $html);
        $this->assertStringNotContainsString('data-testid="ability-slot-4-btn"', $html);
        $this->assertStringNotContainsString('data-testid="ability-slot-5-btn"', $html);

        $this->assertStringContainsString('data-testid="lobby-player-unit-frame"', $html);
        $this->assertStringContainsString('data-testid="lobby-minimap"', $html);
        $this->assertStringContainsString('id="sf-lobby-instance-btn"', $html);
        $this->assertStringContainsString('id="sf-lobby-instance-menu"', $html);
        $this->assertStringContainsString('data-testid="lobby-current-zone-badge"', $html);
        $this->assertStringContainsString('sf-lobby-zone-badge', $html);
        $this->assertStringNotContainsString('data-testid="lobby-zone-gallery-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-zone-heroes-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-zone-lava-btn"', $html);
        $this->assertStringNotContainsString('data-testid="lobby-zone-labyrinth-btn"', $html);
        $this->assertStringNotContainsString('data-lava-back-gallery', $html);
        $this->assertStringNotContainsString('data-labyrinth-to-heroes', $html);
        $this->assertStringNotContainsString('data-labyrinth-back-gallery', $html);
        $this->assertStringNotContainsString('data-testid="lobby-labyrinth-buzzer-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-stages-preview-btn"', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/join', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/sync', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/switch', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/leave', $html);
        $this->assertStringContainsString('Jetzt gestalten', $html);
        $this->assertStringContainsString('Zum Produkt', $html);

        // Clean-Design ESC-Menü, ruhige Gold-Zierleiste & Anti-Manipulations-Closure
        $this->assertStringContainsString('x-show="currentZone === \'heroes\' && isCustomerAuthenticated() && !soloMode && !multiplayerDisabled"', $html);
        $this->assertStringContainsString('handleZoneSyncTransition', $html);
        $this->assertStringContainsString('handleSanctumHttpResponse', $html);
        $this->assertStringContainsString('data-testid="louvre-settings-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-modal"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-volume-slider"', $html);
        $this->assertStringContainsString('data-testid="lobby-camera-sensitivity-slider"', $html);
        $this->assertStringContainsString('data-testid="lobby-use-real-name-checkbox"', $html);
        $this->assertStringContainsString('data-testid="lobby-keybind-expand-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-keybind-reset-btn"', $html);
        $this->assertStringContainsString('Lautstärke', $html);
        $this->assertStringContainsString('Vollbild', $html);
        $this->assertStringContainsString('Kamera-Tempo', $html);
        $this->assertStringContainsString('Grafik-Stufe', $html);
        $this->assertStringContainsString('Weiter erkunden', $html);
        $this->assertStringContainsString('Atelier verlassen', $html);
        $this->assertStringContainsString('Echten Vornamen nutzen', $html);
        $this->assertStringContainsString('Steuerung', $html);
        $this->assertStringContainsString('data-testid="lobby-gold-crest-bar"', $html);
        $this->assertStringContainsString('&copy; Mein-Seelenfunke', $html);
        $this->assertStringNotContainsString('data-testid="louvre-jump-btn"', $html);
        $this->assertStringNotContainsString('data-testid="louvre-weapon-btn"', $html);
        $this->assertStringNotContainsString('SfFunkiBgm', $html);

        // 232px Minimap (mobil 138px), 2-Zeilen Edel-Exponat-Modal + Übergangsanimation & Kontextmenü-Schutz
        $this->assertStringContainsString('width="232" height="232"', $html);
        $this->assertStringContainsString('width: 232px;', $html);
        $this->assertStringContainsString('width: 138px;', $html);
        $this->assertStringContainsString('sf-lobby-prod-row1', $html);
        $this->assertStringContainsString('sf-lobby-prod-price-badge', $html);
        $this->assertStringContainsString('sf-lobby-prod-summary', $html);
        $this->assertStringContainsString('-webkit-line-clamp: 2;', $html);
        $this->assertStringContainsString('sf-lobby-ivory-transition', $html);
        $this->assertStringContainsString('launchProductTransition', $html);
        $this->assertStringContainsString('@contextmenu.prevent', $html);

        // Mobile UI: Steuerknüppel am Desktop ausgeblendet, mobil sichtbar
        $this->assertStringContainsString('.sf-lobby-stick-wrap { display: none;', $html);
        $this->assertStringContainsString('@media (max-width: 900px), (pointer: coarse)', $html);
        $this->assertStringContainsString('.sf-lobby-gold-crest-bar', $html);
        $this->assertStringContainsString('data-testid="louvre-chat-toggle-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-room-chat-panel"', $html);
        $this->assertStringContainsString('/dance', $html);
        $this->assertStringContainsString('/wave', $html);
        $this->assertStringContainsString('friendsList', $html);
        $this->assertStringContainsString('privateShowroomFacades = typeof WeakMap', $html);
        $this->assertStringNotContainsString('this._engine', $html);
        $this->assertStringContainsString('startLobbyMusicFromGesture', $html);
        $this->assertStringContainsString('setBgmVolume', $html);
        $this->assertStringContainsString("import('/js/seelenfunke-louvre-showroom-3d.js?v=20261118v10')", $html);
        $this->assertStringContainsString('window.__sfForceStopAllLobbyAudio', $html);
        $this->assertStringContainsString('window.__sfLobbyAudioRegistry', $html);
        $this->assertStringContainsString('volume: 5,', $html);
        $this->assertStringContainsString('sync_interval_ms: 165,', $html);
        $this->assertStringNotContainsString('sync_interval_ms: 950,', $html);
        $this->assertStringContainsString('data-testid="lobby-minimap-ping-dot"', $html);
        $this->assertStringContainsString('data-testid="lobby-minimap-ping-tooltip"', $html);
        $this->assertStringContainsString('Serververbindung', $html);
        $this->assertStringContainsString('/lobby-ping.php', $html);
        $this->assertStringContainsString('Echtzeit-WS (30 Hz)', $html);
        $this->assertStringNotContainsString('Echtzeit-WS (20 Hz)', $html);
        $this->assertStringContainsString('_startWsTickLoop', $html);
        $this->assertStringContainsString('URL.revokeObjectURL', $html);
        $this->assertStringContainsString('_rttWindow', $html);
        $this->assertStringContainsString('ws_relay', $html);
        $this->assertStringContainsString('connectLobbyWebSocket', $html);
        $this->assertStringContainsString('facade.upsertPeer(', $html);
        $this->assertStringContainsString('facade.removePeer(', $html);
        $this->assertStringContainsString('wall_cling: Boolean(st.wall_cling),', $html);
        $this->assertStringContainsString('somersault_seq: st.somersault_seq || 0,', $html);
        $this->assertStringContainsString('somersault_dir: st.somersault_dir || 0,', $html);
        $this->assertStringContainsString('vx: st.vx,', $html);
        $this->assertStringContainsString('vz: st.vz,', $html);
        $this->assertStringContainsString('return isActive ? 165 : 450;', $html);
        $this->assertStringContainsString('requestImmediateMultiplayerSync', $html);
        $this->assertStringContainsString('_syncPendingImmediate', $html);
        $this->assertStringContainsString('dispatchLivewireAuthSync', $html);
        $this->assertStringContainsString("window.Livewire.dispatch('auth-status-changed')", $html);
        $this->assertStringContainsString("window.Livewire.dispatch('cart-updated')", $html);
        $this->assertStringContainsString("window.Livewire.dispatch('wishlist-updated')", $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/chat', $html);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/report', $html);
        $this->assertStringContainsString('data-testid="sanctum-login-password-eye"', $html);
        $this->assertStringContainsString('/funkenwerk-welt/inline-login', $html);
        $this->assertStringContainsString('/funkenwerk-welt/activate-magic', $html);
        $this->assertStringContainsString('applyLiveAccountUpdate', $html);
        $this->assertStringContainsString('x-ref="inlineMagicTermsBox"', $html);
        $this->assertStringContainsString('x-ref="inlineMagicActivateBtn"', $html);
        $this->assertStringContainsString('sf-magic-thin-aura-ring', $html);
        $this->assertStringContainsString('onInlineMagicTermsChange()', $html);
        $this->assertStringContainsString('nudgeInlineMagicTermsHint', $html);
        $this->assertStringContainsString('sparkMountRoot()', $html);
        $this->assertStringNotContainsString(':disabled="inlineAuthBusy || !inlineMagicAcceptTerms"', $html);
        $this->assertStringContainsString('@wishlist-optimistic-delta.window', $html);
        $this->assertStringContainsString('@sf-wishlist-sync.window', $html);
        $this->assertStringContainsString('sfWishlistButtonData', $html);
        $this->assertStringContainsString('#FEF2F2', $html);
        $this->assertStringContainsString('#FECACA', $html);
        $this->assertStringContainsString('#DC2626', $html);
        $this->assertStringNotContainsString('digital.' . 'mein-seelenfunke.de', $html);
        $this->assertStringNotContainsString('openCurrentExhibitDrawer', $html);
        $this->assertStringNotContainsString('lightboxOpen', $html);

        // 0 KB Inline-3D-Ballast beim regulären Seitenaufruf von /shop
        $this->assertStringNotContainsString('<script src="/vendor/three/three.min.js"', $html);
        $this->assertStringNotContainsString('<script src="/js/seelenfunke-louvre-showroom-3d.js"', $html);
        $this->assertStringNotContainsString('t_seelenk.glb', $html);

        // Produktkacheln in "Unsere Kollektion" ohne "Handveredelt in Deutschland"-Badge (Clean Design)
        $this->assertStringNotContainsString('Handveredelt in Deutschland', $html);

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

        // 1. 60-FPS Performance-Guards: PixelRatio <= 1.5 (bzw. 1.0 im Ausgewogen-Modus), keine Shadow-Maps, keine PointLights/SpotLights, InstancedMesh & Staggered Queue
        $this->assertStringContainsString('Math.min(window.devicePixelRatio || 1, isBalancedQuality ? 1.0 : 1.5)', $js);
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

        // 5. Sanftes Springen & Doppelsprung mit 360-Grad-Salto zum angenehmen Spazieren (0 Schwert/Kampfleiste im Shop!)
        $this->assertStringContainsString('jumpCount', $js);
        $this->assertStringContainsString('isSomersault', $js);
        $this->assertStringContainsString('somersaultProgress', $js);
        $this->assertStringContainsString('_spawnDoubleJumpBurst', $js);

        // 5b. 15-Bone SkinnedMesh, 3D-Exoskelett, 0-mm-Fuß-Clipping, schleierfreie Torbögen, Tor zur Außenwelt & Anti-Manipulation
        $this->assertStringContainsString('buildHallOfHeroesMarbleMaps', $js);
        $this->assertStringContainsString('pantheonNeroBorder', $js);
        $this->assertStringContainsString('46.8 / 3.8', $js);
        $this->assertStringNotContainsString('pantheon_carrara_gold_floor.jpg', $js);
        $this->assertStringNotContainsString('northLunette', $js);
        $this->assertStringNotContainsString('promVaultGlass', $js);
        $this->assertStringNotContainsString('FogExp2', $js);
        $this->assertStringContainsString('this.scene.fog = null;', $js);
        $this->assertStringContainsString('barrelGlass.rotation.set(-Math.PI * 0.5, 0, 0);', $js);
        $this->assertStringContainsString('opacity: 0.10,', $js);
        $this->assertStringContainsString('const isFirstPerson = this.camControl.distance < 0.55;', $js);
        $this->assertStringContainsString('if (next < 0.95) next = 0;', $js);
        $this->assertStringContainsString('this.camControl.desiredDistance = Math.max(0, next);', $js);
        $this->assertStringContainsString('this.player.root.visible = !isFirstPerson;', $js);
        $this->assertStringNotContainsString('lightConeInst', $js);
        $this->assertStringNotContainsString('sunbeamCone', $js);
        $this->assertStringContainsString('_computeAvatarMinSoleY', $js);
        $this->assertStringContainsString('_computeFloorBaseY', $js);
        $this->assertStringContainsString('0.004 - minSoleLocalY', $js);
        $this->assertStringContainsString('0.004 - minSolePeerY', $js);
        $this->assertStringContainsString('BoxGeometry(46.8, 0.28, 48.8)', $js);
        $this->assertStringContainsString('polygonOffset: false', $js);
        $this->assertStringContainsString('return 0.026;', $js);
        $this->assertStringContainsString('BoxGeometry(2.6, 0.014, carpetLen)', $js);
        $this->assertStringContainsString('carpetStrip.position.set(0, 0.029, carpetCenterZ)', $js);
        $this->assertStringContainsString('BoxGeometry(2.82, 0.018, 0.12)', $js);
        $this->assertStringContainsString('this.audio.setZone(this.currentZone)', $js);
        $this->assertStringContainsString('./seelenfunke-lobby-audio.js?v=20261118v9', $js);
        $this->assertStringContainsString('THREE.SkinnedMesh', $js);
        $this->assertStringContainsString('THREE.Skeleton', $js);
        $this->assertStringContainsString('skinIndex', $js);
        $this->assertStringContainsString('skinWeight', $js);
        $this->assertStringContainsString('_createSkinnedFunkiFromTemplate', $js);
        $this->assertStringContainsString('_sanitizeStandingFunkiUrl', $js);
        $this->assertStringContainsString('exoBronzeUrsprung', $js);
        $this->assertStringContainsString('#A8947B', $js);
        $this->assertStringContainsString('const legSync = 0.84;', $js);
        $this->assertStringNotContainsString('const isSitting =', $js);
        $this->assertStringContainsString('exoReactorHex', $js);
        $this->assertStringContainsString('thrusterFlameL', $js);
        $this->assertStringContainsString('bothMouseWalk', $js);
        $this->assertStringContainsString('clickMoveRing', $js);
        $this->assertStringContainsString('sf_lobby_keybindings_v4', $js);
        $this->assertStringContainsString('sf_lobby_user_settings_v2', $js);
        $this->assertStringContainsString('volume: 5,', $js);
        $this->assertStringContainsString('proceduralExoMeshes', $js);
        $this->assertStringContainsString('? ((this.player.somersaultDir || 1) * this.player.somersaultProgress * TWO_PI)', $js);
        $this->assertStringContainsString('this.player.somersaultDir = 1;', $js);
        $this->assertStringContainsString('this.player.somersaultDir = -1;', $js);
        $this->assertStringNotContainsString('? -(this.player.somersaultProgress * TWO_PI)', $js);
        $this->assertStringContainsString('depthWrite: false', $js);
        $this->assertStringContainsString('_triggerSanctumGateRepulse', $js);
        $this->assertStringContainsString('_triggerOuterGateRepulse', $js);
        $this->assertStringContainsString('_spawnGateRepulseFieldBurst', $js);
        $this->assertStringContainsString('applyLiveAccountUpdate', $js);
        $this->assertStringContainsString('focusNearestExhibit', $js);
        $this->assertStringContainsString('freePedestalCrests', $js);
        $this->assertStringContainsString('refreshHeroesData', $js);
        $this->assertStringContainsString('startExhibitTransition', $js);
        $this->assertStringContainsString('westOuterGateGroup', $js);
        $this->assertStringContainsString('Tor zur Außenwelt', $js);
        $this->assertStringContainsString('Bald warten hier fantastische Welten & Abenteuer', $js);
        $this->assertStringNotContainsString('Hier entsteht ein MMORPG', $js);
        $this->assertStringContainsString('openOuterGateModal', $js);
        $this->assertStringContainsString('getMmorpgAccessState', $js);
        $this->assertStringContainsString('/funkenreich/enter', $js);

        // 5c. 3D-Sterne-Bewertungsstation am Goldenen Ehrenportal + realistische Wandkollision + Ninja-Wall-Cling & Wall-Backflip
        $this->assertStringContainsString('this.hallHalfW = 12.0;', $js);
        $this->assertStringContainsString('_galleryPierPlacements', $js);
        $this->assertStringContainsString('const wallMargin = 0.24;', $js);
        $this->assertStringContainsString('_buildAtelierStarRatingStation', $js);
        $this->assertStringContainsString('new THREE.ExtrudeGeometry(', $js);
        $this->assertStringContainsString('Atelier bewerten', $js);
        $this->assertStringContainsString('Deine Meinung zählt', $js);
        $this->assertStringContainsString('setAtelierReviewState', $js);
        $this->assertStringContainsString('previewAtelierStarHover', $js);
        $this->assertStringContainsString('goToAtelierStarStation', $js);
        $this->assertStringContainsString('normalizeAngleSigned', $js);
        $this->assertStringContainsString('shortestAngleDiff', $js);
        $this->assertStringContainsString('_hasActiveMovementKeys', $js);
        $this->assertStringContainsString('_detectWallForCling', $js);
        $this->assertStringContainsString('isWallClinging', $js);
        $this->assertStringContainsString('this.player.wallClingTimer = 1.0;', $js);
        $this->assertStringContainsString('_spawnWallKickBurst', $js);
        $this->assertStringContainsString('pierInst', $js);
        $this->assertStringContainsString('canEnterSanctums', $js);
        $this->assertStringContainsString('getSanctumAccessState', $js);
        $this->assertStringContainsString('_interceptSanctumResponse', $js);
        $this->assertStringContainsString('login_required', $js);
        $this->assertStringContainsString('magic_activation_required', $js);
        $this->assertStringContainsString('_spawnMagicAwakenAura', $js);
        $this->assertStringContainsString('_updateMagicAwakenChoreo', $js);
        $this->assertStringContainsString('seelenfunkeStar', $js);
        $this->assertStringContainsString('createArchPortalVeilGeometry', $js);
        $this->assertStringContainsString('setLocalPeerId', $js);
        $this->assertStringContainsString('upsertPeer(', $js);
        $this->assertStringContainsString('removePeer(', $js);
        $this->assertStringContainsString('_applyPeerPacket(', $js);
        $this->assertStringContainsString('somersault_seq:', $js);
        $this->assertStringContainsString('somersault_dir:', $js);
        $this->assertStringContainsString('somersault_prog:', $js);
        $this->assertStringContainsString('wall_cling:', $js);
        $this->assertStringContainsString('peerSomersaultTuck', $js);
        $this->assertStringContainsString('wallClingBlend: peer.wallClingBlend || 0', $js);
        $this->assertStringContainsString('vx: cleanVx,', $js);
        $this->assertStringContainsString('vz: cleanVz,', $js);
        $this->assertStringContainsString('onLocalUrgentSyncNeeded', $js);
        $this->assertStringContainsString('const minPeerX = -this.pantheonHalfW + 0.42;', $js);
        $this->assertStringContainsString('const maxPeerZ = this.heroesEntryZ + 0.6;', $js);
        $this->assertStringContainsString('peer.packetTime = this.elapsedTime;', $js);
        $this->assertStringContainsString('if (distJump > 4.5)', $js);
        $this->assertStringContainsString('peer.snapshots', $js);
        $this->assertStringContainsString('lastPktSeq', $js);
        $this->assertStringContainsString('const INTERP_DELAY = 0.045;', $js);
        $this->assertStringContainsString('const renderTime = this.elapsedTime - INTERP_DELAY;', $js);
        $this->assertStringContainsString('1.0 - Math.exp(-dt * 38.0)', $js);
        $this->assertStringContainsString('if (skinnedRig.actions && skinnedRig.actions.run && effectiveSpeed > 6.2)', $js);
        $this->assertStringNotContainsString('digital.' . 'mein-seelenfunke.de', $js);
        $this->assertStringContainsString('getMinimapState', $js);
        $this->assertStringContainsString('releasePointerLock', $js);
        $this->assertStringNotContainsString('_buildMiniLabyrinthWestWing', $js);
        $this->assertStringContainsString("this.getCurrentZone() !== 'heroes'", $js);
        $this->assertStringContainsString('triggerEmote', $js);
        $this->assertStringContainsString('updateSettings', $js);

        // Shift-Sprint (8.8 < MAX_SPEED 9.5), 3D Schwarzer Edel-Gutscheinumschlag mit Herz-Wachssiegel & freie Schild-Positionierung am West-Tor
        $this->assertStringContainsString('const isShiftHeld = Boolean(this.keys.ShiftLeft || this.keys.ShiftRight || this.keys.Shift);', $js);
        $this->assertStringContainsString('const moveSpeed = isShiftHeld ? 8.8 : 5.2;', $js);
        $this->assertStringContainsString('this.player.isSprinting = isShiftHeld && this.player.isMoving;', $js);
        $this->assertStringContainsString('const sprintBoost = clamp(this.player.sprintBoost || 0, 0, 1.0);', $js);
        $this->assertStringContainsString('const strideFrequency = 4.48 + 0.72 * sprintBoost;', $js);
        $this->assertStringContainsString('if (e.shiftKey === false)', $js);
        $this->assertStringContainsString('setEffectiveTimeScale(runTimeScale)', $js);
        $this->assertStringContainsString('_populateVoucherEnvelope3D', $js);
        $this->assertStringContainsString("nextStele.product.slug === 'geschenkgutschein' || nextStele.product.collection_world === 'voucher'", $js);
        $this->assertStringContainsString('color: 0x141518', $js);
        $this->assertStringContainsString('buildVoucherBackCrestTexture', $js);
        $this->assertStringContainsString('keystone.position.set(0.14, springY + halfSpan - 0.04, 0);', $js);
        $this->assertStringContainsString('const outerFriezeY = springY + halfSpan + 0.68;', $js);
        $this->assertStringContainsString('BoxGeometry(0.22, 1.06, 5.2)', $js);
        $this->assertStringContainsString('PlaneGeometry(4.96, 0.92)', $js);
        $this->assertStringContainsString("'600 19px system-ui", $js);
        $this->assertStringContainsString("'600 22px system-ui", $js);
        $this->assertStringContainsString("ctx.fillStyle = '#E6C687';", $js);

        // Baustein 0: Kein Händler, kein Magma-Portal/Kammer, kein Heiltrank/Inventar & keine Kampfklinge im Shop-JS!
        foreach ([
            '_buildHallOfHeroesMerchant',
            '_buildEastMagmaEntrancePortal',
            '_buildLavaChamberAndParkour',
            'buyMerchantPotion',
            'useQuickSlotItem',
            'equipInventorySlot',
            'triggerComboStrike',
            'gold_blade',
            '/funkenwerk-welt/lava-parkour/',
        ] as $removedRpgSymbol) {
            $this->assertStringNotContainsString($removedRpgSymbol, $js);
        }

        // Client-Seitiger Manipulations-Schutz: Private Klasse + Object.freeze-Fassade + 0 Cheat-Methoden
        $this->assertStringNotContainsString('export class SeelenfunkeLouvreShowroom3D', $js);
        $this->assertStringContainsString('class SeelenfunkeLouvreShowroom3D', $js);
        $this->assertStringContainsString('return Object.freeze({', $js);
        $this->assertStringContainsString('Stufen-Ansicht · Stufe ', $js);
        foreach (['setLevel(', 'giveSparks(', 'teleport(', 'noclip(', 'godMode('] as $forbiddenHook) {
            $this->assertStringNotContainsString($forbiddenHook, $js);
        }

        // 6. Eigenständiges Scoped CSS, Fullscreen API & 100 % Parität + Anti-Manipulation im Standalone-Host (/mein-konto)
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
        $this->assertStringContainsString('/js/seelenfunke-louvre-showroom-3d.js?v=20261118v10', $hostJs);
        $this->assertStringContainsString("root.setAttribute('data-sf-3d-lobby-modal', 'heroes')", $hostJs);
        $this->assertStringContainsString('stopPrewarmAndForceLobbySilence', $hostJs);
        $this->assertStringContainsString('window.__sfForceStopAllLobbyAudio', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-vital-hud"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-unit-hearts"', $hostJs);
        $this->assertStringNotContainsString('Mindestens 1 Bestellung', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-merchant-modal"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-inventory-modal"', $hostJs);
        $this->assertStringNotContainsString('data-testid="item-slot-1-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="item-slot-2-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-lava-demote-banner"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-master-key-modal"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-lava-chest-card"', $hostJs);
        $this->assertStringNotContainsString('data-testid="louvre-abilities-dock"', $hostJs);
        $this->assertStringNotContainsString('data-testid="ability-slot-1-btn"', $hostJs);
        $this->assertStringContainsString('dispatchLivewireAuthSync', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/chat', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/report', $hostJs);
        $this->assertStringContainsString('data-testid="sanctum-login-password-eye"', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/inline-login', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/activate-magic', $hostJs);
        $this->assertStringContainsString('applyLiveAccountUpdate', $hostJs);
        $this->assertStringContainsString('_startLobbyMusicFromGestureSync', $hostJs);
        $this->assertStringContainsString('setBgmVolume', $hostJs);
        $this->assertStringNotContainsString('digital.' . 'mein-seelenfunke.de', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-current-zone-badge"', $hostJs);
        $this->assertStringContainsString('sf-lobby-zone-badge', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-gallery-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-heroes-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-lava-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-labyrinth-btn"', $hostJs);
        $this->assertStringNotContainsString('data-lava-back-gallery', $hostJs);
        $this->assertStringNotContainsString('data-labyrinth-to-heroes', $hostJs);
        $this->assertStringNotContainsString('data-labyrinth-back-gallery', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-labyrinth-buzzer-card"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-outer-gate-card"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-outer-gate-modal"', $hostJs);
        $this->assertStringContainsString('outer-gate-enter-mmorpg-btn', $hostJs);
        $this->assertStringContainsString('Tor zur Außenwelt', $hostJs);
        $this->assertStringContainsString('Fantastische Welt &amp; Abenteuer in Planung', $hostJs);
        $this->assertStringContainsString('Hinter diesem Tor warten bald eine fantastische Welt und spannende Abenteuer auf dich', $hostJs);
        $this->assertStringContainsString('In die Außenwelt starten', $hostJs);
        $this->assertStringNotContainsString('Hier entsteht ein MMORPG', $hostJs);
        $this->assertStringContainsString('data-testid="atelier-star-rating-card"', $hostJs);
        $this->assertStringContainsString('atelier-star-login-btn', $hostJs);
        $this->assertStringContainsString('_submitAtelierRating', $hostJs);
        $this->assertStringContainsString('_submitAtelierFeedback', $hostJs);
        $this->assertStringContainsString('/funkenreich/enter', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-sanctum-gate-modal"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-player-unit-frame"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-minimap"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-minimap-ping-dot"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-minimap-ping-tooltip"', $hostJs);
        $this->assertStringContainsString('/lobby-ping.php', $hostJs);
        $this->assertStringContainsString('Echtzeit-WS (30 Hz)', $hostJs);
        $this->assertStringNotContainsString('Echtzeit-WS (20 Hz)', $hostJs);
        $this->assertStringContainsString('_startWsTickLoop', $hostJs);
        $this->assertStringContainsString('URL.revokeObjectURL', $hostJs);
        $this->assertStringContainsString('_rttWindow', $hostJs);
        $this->assertStringContainsString('ws_relay', $hostJs);
        $this->assertStringContainsString('_connectLobbyWebSocket', $hostJs);
        $this->assertStringContainsString('facade.upsertPeer(', $hostJs);
        $this->assertStringContainsString('facade.removePeer(', $hostJs);
        $this->assertStringContainsString('wall_cling: Boolean(st.wall_cling),', $hostJs);
        $this->assertStringContainsString('somersault_seq: st.somersault_seq || 0,', $hostJs);
        $this->assertStringContainsString('somersault_dir: st.somersault_dir || 0,', $hostJs);
        $this->assertStringContainsString('vx: st.vx || 0,', $hostJs);
        $this->assertStringContainsString('vz: st.vz || 0,', $hostJs);
        $this->assertStringContainsString('return isActive ? 165 : 450;', $hostJs);
        $this->assertStringContainsString('_requestImmediateSync', $hostJs);
        $this->assertStringContainsString('handleSanctumHttpResponse', $hostJs);
        $this->assertStringContainsString('_handleZoneSyncTransition', $hostJs);
        $this->assertStringContainsString('refreshHeroesData', $hostJs);
        $this->assertStringContainsString("this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode", $hostJs);
        $this->assertStringContainsString('data-testid="louvre-settings-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-fullscreen-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-close-btn"', $hostJs);
        $this->assertStringNotContainsString('data-testid="lobby-audio-mute-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-fullscreen-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-mute-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-exit-btn"', $hostJs);
        $this->assertStringContainsString('width="232" height="232"', $hostJs);
        $this->assertStringNotContainsString('SfFunkiBgm', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-modal"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-volume-slider"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-camera-sensitivity-slider"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-use-real-name-checkbox"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-gold-crest-bar"', $hostJs);
        $this->assertStringContainsString('© Mein-Seelenfunke', $hostJs);
        $this->assertStringContainsString('sf-lobby-prod-row1', $hostJs);
        $this->assertStringContainsString('sf-lobby-prod-summary', $hostJs);
        $this->assertStringContainsString('_startProductTransition', $hostJs);
        $this->assertStringNotContainsString('data-lobby-jump', $hostJs);
        $this->assertStringNotContainsString('data-lobby-strike', $hostJs);
        $this->assertStringContainsString('privateFacadeMap', $hostJs);
        $this->assertStringContainsString('Object.freeze({', $hostJs);
        $this->assertStringNotContainsString('this._engine', $hostJs);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $hostJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $hostJs);

        // 7. Fotorealistische Architektur-Texturen (Calacatta-Gold in Galerie + Ehrenpromenade + Halle der Helden, Boiserie, Nero Marquina, Golden-Hour PMREM, Pantheon Kuppel)
        $this->assertStringContainsString('PMREMGenerator', $js);
        $this->assertStringContainsString('fromEquirectangular', $js);
        $this->assertStringContainsString('TextureLoader', $js);
        $this->assertStringContainsString('bumpMap', $js);

        foreach ([
            '/shop/showroom/textures/calacatta_gold_floor.jpg',
            '/shop/showroom/textures/louvre_boiserie_wall.jpg',
            '/shop/showroom/textures/nero_marquina_pedestal.jpg',
            '/shop/showroom/textures/museum_golden_hour_env.jpg',
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

        // Gast erhält Funki Level 2 + Jahreszeiten-Environment + 10-Stufen Magma-Parkour-Payload (0 Mini-Labyrinth!)
        $this->assertTrue($data['player_funki']['is_guest']);
        $this->assertSame(2, $data['player_funki']['level']);
        $this->assertSame('/shop/customer/gamification/models/funki_lvl_2_basic.glb', $data['player_funki']['glb_url']);
        $this->assertArrayNotHasKey('mini_labyrinth', $data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('season_key', $data['environment']);
        $this->assertArrayHasKey('server_time', $data['environment']);
        $this->assertArrayHasKey('player', $data);
        $this->assertFalse($data['player']['is_authenticated']);
        $this->assertArrayHasKey('lava_parkour', $data);
        $this->assertCount(10, $data['lava_parkour']['levels']);

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
        Product::create([
            'id' => (string) Str::uuid(),
            'name' => 'Pflegetuch Set Schwarz-Gold',
            'slug' => 'pflegetuch-set-wartung',
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => false,
            'price' => 990,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        // Stufe 2 (Schaufenster-Wartungsmodus bei shop_is_live = true): 3D-Rundgang liefert HTTP 200, Direktkauf ist gesperrt
        SystemSetting::updateOrCreate(['key' => 'maintenance_mode'], ['value' => 'true']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

        $showcaseResp = $this->getJson('/shop/showroom-data')->assertStatus(200);
        $showcaseResp->assertJsonPath('maintenance_mode', true);
        $this->assertFalse((bool) collect($showcaseResp->json('products'))->firstWhere('slug', 'pflegetuch-set-wartung')['can_direct_add_to_cart']);

        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
        $this->actingAs($admin, 'admin')->getJson('/shop/showroom-data')->assertStatus(200);

        // Stufe 1 (Vor-Livegang-Sperre shop_is_live = false): Harte HTTP-503-Sperre im Frontend (auch für Admins; Admin-Cockpit bleibt 200)
        $this->app['auth']->forgetGuards();
        SystemSetting::updateOrCreate(['key' => 'shop_is_live'], ['value' => 'false']);
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');
        $this->getJson('/shop/showroom-data')->assertStatus(503);
        $this->actingAs($admin, 'admin')->getJson('/shop/showroom-data')->assertStatus(503);
        $this->actingAs($admin, 'admin')->get('/admin/settings')->assertStatus(200);
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

    public function test_outer_gate_mmorpg_access_is_role_based_for_guests_standard_customers_enabled_testers_and_admins(): void
    {
        // 1. Gast: mmorpg_can_enter = false
        $guestJson = $this->getJson('/shop/showroom-data')->assertStatus(200);
        $guestJson->assertJsonPath('mmorpg_can_enter', false);
        $guestJson->assertJsonPath('mmorpg_enter_url', '/funkenreich/enter');
        $guestJson->assertJsonPath('player.mmorpg_can_enter', false);
        $guestShopHtml = (string) $this->get('/shop')->assertStatus(200)->getContent();
        $this->assertStringContainsString('mmorpgCanEnter: false', $guestShopHtml);

        // 2. Regulärer verifizierter Shop-Kunde (mmorpg_access_enabled = false): mmorpg_can_enter = false
        $standardCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Marie',
            'last_name' => 'Standard',
            'email' => 'marie.standard@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'mmorpg_access_enabled' => false,
        ]);

        $stdJson = $this->actingAs($standardCustomer, 'customer')
            ->getJson('/shop/showroom-data')
            ->assertStatus(200);
        $stdJson->assertJsonPath('mmorpg_can_enter', false);
        $stdJson->assertJsonPath('player.mmorpg_can_enter', false);
        $stdShopHtml = (string) $this->actingAs($standardCustomer, 'customer')->get('/shop')->assertStatus(200)->getContent();
        $this->assertStringContainsString('mmorpgCanEnter: false', $stdShopHtml);

        // 3. In /admin/users freigeschalteter Tester (mmorpg_access_enabled = true): mmorpg_can_enter = true + /funkenreich/enter
        $testerCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Lukas',
            'last_name' => 'Tester',
            'email' => 'lukas.tester@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'mmorpg_access_enabled' => true,
        ]);

        $testerJson = $this->actingAs($testerCustomer, 'customer')
            ->getJson('/shop/showroom-data')
            ->assertStatus(200);
        $testerJson->assertJsonPath('mmorpg_can_enter', true);
        $testerJson->assertJsonPath('mmorpg_enter_url', '/funkenreich/enter');
        $testerJson->assertJsonPath('player.mmorpg_can_enter', true);
        $testerJson->assertJsonPath('player.mmorpg_enter_url', '/funkenreich/enter');
        $testerShopHtml = (string) $this->actingAs($testerCustomer, 'customer')->get('/shop')->assertStatus(200)->getContent();
        $this->assertStringContainsString('mmorpgCanEnter: true', $testerShopHtml);

        // 4. Sicherheits-Guard: Freigeschalteter Tester MIT erzwungenem Passwortwechsel bleibt am Tor blockiert (false)
        $lockedTester = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Nora',
            'last_name' => 'Reset',
            'email' => 'nora.reset@example.com',
            'email_verified_at' => now(),
            'needs_password_change' => true,
            'password' => bcrypt('secret123'),
            'mmorpg_access_enabled' => true,
        ]);
        $this->actingAs($lockedTester, 'customer')
            ->getJson('/shop/showroom-data')
            ->assertStatus(200)
            ->assertJsonPath('mmorpg_can_enter', false);

        // 5. Eingeloggter Admin: mmorpg_can_enter = true (auch ohne Customer-Session)
        $this->app['auth']->forgetGuards();
        $admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina.mmorpg@mein-seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);

        $adminJson = $this->actingAs($admin, 'admin')
            ->getJson('/shop/showroom-data')
            ->assertStatus(200);
        $adminJson->assertJsonPath('mmorpg_can_enter', true);
        $adminJson->assertJsonPath('mmorpg_enter_url', '/funkenreich/enter');
        $adminJson->assertJsonPath('player.mmorpg_can_enter', true);
        $adminShopHtml = (string) $this->actingAs($admin, 'admin')->get('/shop')->assertStatus(200)->getContent();
        $this->assertStringContainsString('mmorpgCanEnter: true', $adminShopHtml);
    }

    public function test_lobby_audio_module_couples_with_sf_funki_bgm_and_has_zero_em_dashes(): void
    {
        $audioJsPath = public_path('js/seelenfunke-lobby-audio.js');
        $this->assertFileExists($audioJsPath);

        $audioJs = (string) file_get_contents($audioJsPath);
        $this->assertNotEmpty($audioJs);

        $this->assertStringContainsString('SeelenfunkeLobbyAudio', $audioJs);
        $this->assertStringNotContainsString('SfFunkiBgm', $audioJs);
        $this->assertStringContainsString('/shop/gamification/audio/bgm_funki_game.mp3', $audioJs);
        $this->assertStringContainsString('/shop/gamification/audio/bgm_funki_magma_action.mp3', $audioJs);
        $this->assertFileExists(public_path('shop/gamification/audio/bgm_funki_game.mp3'));
        $this->assertFileExists(public_path('shop/gamification/audio/bgm_funki_magma_action.mp3'));
        $this->assertStringContainsString('BGM_CROSSFADE_DURATION_MS = 700', $audioJs);
        $this->assertStringContainsString('_calmBgmEl', $audioJs);
        $this->assertStringContainsString('_magmaBgmEl', $audioJs);
        $this->assertStringContainsString('setZone(', $audioJs);
        $this->assertStringContainsString('/shop/customer/gamification/sounds/shield_active.mp3', $audioJs);
        $this->assertStringContainsString('/shop/customer/gamification/sounds/teleport_active.mp3', $audioJs);
        $this->assertStringContainsString('sf_funki_audio_volume', $audioJs);
        $this->assertStringContainsString('sf_funki_audio_muted', $audioJs);
        $this->assertStringContainsString('sf_lobby_bgm_explicit_muted', $audioJs);
        $this->assertStringContainsString('sf_lobby_sfx_volume', $audioJs);
        $this->assertStringContainsString('startLobbyMusicFromGesture', $audioJs);
        $this->assertStringContainsString('setLobbyActive', $audioJs);
        $this->assertStringContainsString('playComboStrike', $audioJs);
        $this->assertStringContainsString('playComboFinisherImpact', $audioJs);
        $this->assertStringContainsString('playChestOpenFanfare', $audioJs);
        $this->assertStringContainsString('playShieldShatter', $audioJs);
        $this->assertStringContainsString('playShockRingHit', $audioJs);
        $this->assertStringContainsString('playProductTransition', $audioJs);
        $this->assertStringContainsString('playGateRepulse', $audioJs);
        $this->assertStringContainsString('playMagicAwaken', $audioJs);
        $this->assertStringContainsString('playHeartLost', $audioJs);
        $this->assertStringContainsString('playLevelDemoted', $audioJs);
        $this->assertStringContainsString('playSparkCollect', $audioJs);
        $this->assertStringContainsString('playLavaSplash', $audioJs);
        $this->assertStringContainsString('playDrinkPotion', $audioJs);
        $this->assertStringContainsString('DEFAULT_LOBBY_BGM_VOLUME = 5', $audioJs);
        $this->assertStringContainsString('LOBBY_DOM_WATCHDOG_INTERVAL_MS = 400', $audioJs);
        $this->assertStringContainsString('LOBBY_OPEN_GRACE_MS = 1200', $audioJs);
        $this->assertStringContainsString('window.__sfLobbyAudioSingleton', $audioJs);
        $this->assertStringNotContainsString('let sharedLobbyAudioSingleton', $audioJs);
        $this->assertStringContainsString('window.__sfLobbyAudioRegistry', $audioJs);
        $this->assertStringContainsString('window.__sfForceStopAllLobbyAudio', $audioJs);
        $this->assertStringContainsString('isAny3dLobbyModalVisibleInDom', $audioJs);
        $this->assertStringContainsString('livewire:navigating', $audioJs);
        $this->assertStringContainsString('livewire:navigated', $audioJs);
        $this->assertStringContainsString('popstate', $audioJs);
        $this->assertStringContainsString('pagehide', $audioJs);
        $this->assertStringContainsString('beforeunload', $audioJs);
        $this->assertStringContainsString('visibilitychange', $audioJs);
        $this->assertStringContainsString('783.99', $audioJs);
        $this->assertStringContainsString('updateLavaAmbience', $audioJs);
        $this->assertStringNotContainsString('digital.' . 'mein-seelenfunke.de', $audioJs);
        $this->assertStringNotContainsString('https://', $audioJs);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $audioJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $audioJs);
    }

    public function test_customer_2d_pages_never_embed_global_lobby_audio_module_and_prevent_split_brain(): void
    {
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Elena',
            'last_name' => 'Stille',
            'email' => 'elena.audio.guard@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('SecretAudioGuard2026!'),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => 3,
            'funken_balance' => 150,
            'funken_total_earned' => 150,
        ]);

        $dashboardBlade = (string) file_get_contents(resource_path('views/livewire/customer/customer-dashboard-component.blade.php'));
        $funkiBlade = (string) file_get_contents(resource_path('views/livewire/customer/customer-funki-component.blade.php'));

        $this->assertStringNotContainsString('seelenfunke-lobby-audio.js', $dashboardBlade);
        $this->assertStringNotContainsString('seelenfunke-lobby-audio.js', $funkiBlade);

        $dashHtml = (string) $this->actingAs($customer, 'customer')
            ->get('/customer/dashboard')
            ->assertStatus(200)
            ->getContent();
        $this->assertStringNotContainsString('seelenfunke-lobby-audio.js', $dashHtml);

        $funkiCompHtml = (string) Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Customer\CustomerFunkiComponent::class)
            ->html();
        $this->assertStringNotContainsString('seelenfunke-lobby-audio.js', $funkiCompHtml);

        $shopHtml = (string) $this->get('/shop')->assertStatus(200)->getContent();
        $this->assertStringNotContainsString('seelenfunke-lobby-audio.js', $shopHtml);
    }

    public function test_atelier_3d_star_rating_station_guest_to_inline_login_and_one_click_review_flow(): void
    {
        // 1. Gast ruft Showroom-Daten auf: atelier_review ist vorhanden, aber can_rate = false
        $guestBoot = $this->getJson('/shop/showroom-data')->assertStatus(200);
        $guestBoot->assertJsonPath('atelier_review.area', 'funkenreich');
        $guestBoot->assertJsonPath('atelier_review.can_rate', false);
        $guestBoot->assertJsonPath('atelier_review.has_rated', false);
        $guestBoot->assertJsonPath('atelier_review.my_rating', 0);
        $guestBoot->assertJsonPath('atelier_review.submit_url', '/customer/experience-reviews');

        // 2. Verifizierter Kunde meldet sich nahtlos via Inline-Login an (0 Seiten-Reload)
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Sophie',
            'last_name' => 'Sternenglanz',
            'email' => 'sophie.atelier@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('AtelierSecret2026!'),
        ]);

        $loginResp = $this->postJson('/funkenwerk-welt/inline-login', [
            'email' => 'sophie.atelier@example.com',
            'password' => 'AtelierSecret2026!',
        ])->assertStatus(200);

        $loginResp->assertJsonPath('success', true);
        $loginResp->assertJsonPath('atelier_review.can_rate', true);
        $loginResp->assertJsonPath('atelier_review.has_rated', false);

        // 3. 1-Click 5-Sterne-Bewertung an der 3D-Station speichert sofort ohne Textfeld-Zwang
        $rateFive = $this->actingAs($customer, 'customer')->postJson('/customer/experience-reviews', [
            'area' => 'funkenreich',
            'rating' => 5,
            'keep_existing_comment' => true,
        ])->assertStatus(200);

        $rateFive->assertJsonPath('ok', true);
        $rateFive->assertJsonPath('review.area', 'funkenreich');
        $rateFive->assertJsonPath('review.rating', 5);

        $afterFiveBoot = $this->actingAs($customer, 'customer')->getJson('/shop/showroom-data')->assertStatus(200);
        $afterFiveBoot->assertJsonPath('atelier_review.can_rate', true);
        $afterFiveBoot->assertJsonPath('atelier_review.has_rated', true);
        $afterFiveBoot->assertJsonPath('atelier_review.my_rating', 5);

        // 4. Korrektur auf 3 Sterne + konstruktives Inline-Feedback aktualisiert denselben Eintrag
        $rateThreeWithFeedback = $this->actingAs($customer, 'customer')->postJson('/customer/experience-reviews', [
            'area' => 'funkenreich',
            'rating' => 3,
            'comment' => 'Noch mehr Schmuck-Vitrinen im Nordflügel wären wunderbar.',
        ])->assertStatus(200);

        $rateThreeWithFeedback->assertJsonPath('ok', true);
        $rateThreeWithFeedback->assertJsonPath('review.rating', 3);
        $rateThreeWithFeedback->assertJsonPath('review.comment', 'Noch mehr Schmuck-Vitrinen im Nordflügel wären wunderbar.');

        $afterThreeBoot = $this->actingAs($customer, 'customer')->getJson('/shop/showroom-data')->assertStatus(200);
        $afterThreeBoot->assertJsonPath('atelier_review.my_rating', 3);
        $afterThreeBoot->assertJsonPath('atelier_review.my_comment', 'Noch mehr Schmuck-Vitrinen im Nordflügel wären wunderbar.');
    }
}
