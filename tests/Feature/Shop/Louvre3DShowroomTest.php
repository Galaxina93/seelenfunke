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
        $this->assertStringNotContainsString('WASD / Pfeiltasten · Maus ziehen', $html);
        $this->assertStringNotContainsString('z-[2147483500]', $html);
        $this->assertStringNotContainsString('bg-[#FAF8F5]/95', $html);

        // Schwebende Light-Mode Raum-Schilder (Produkte, Top-5-Helden, Ehrenportal, Außenwelt-Tor & 3-Stufen-Voraussetzungs-Modal)
        $this->assertStringContainsString('data-testid="louvre-floating-space-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-floating-hero-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-guest-portal-card"', $html);
        $this->assertStringContainsString('data-testid="lobby-outer-gate-card"', $html);
        $this->assertStringContainsString('Tor zur Außenwelt · Bald geöffnet', $html);
        $this->assertStringContainsString('data-testid="lobby-sanctum-gate-modal"', $html);
        $this->assertStringContainsString('data-testid="sanctum-check-login"', $html);
        $this->assertStringContainsString('data-testid="sanctum-check-order"', $html);
        $this->assertStringContainsString('data-testid="sanctum-check-magic"', $html);
        $this->assertStringContainsString('Angemeldet', $html);
        $this->assertStringContainsString('Mindestens 1 Bestellung', $html);
        $this->assertStringContainsString('✨ Magie aktivieren', $html);
        $this->assertStringContainsString('Kollektion ansehen', $html);
        $this->assertStringContainsString('data-testid="lobby-master-key-modal"', $html);
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

        // Permanente Spezialfähigkeiten-Leiste (Tasten 1-4), Magma-Welt, Clean-Design ESC-Menü & Anti-Manipulations-Closure
        $this->assertStringContainsString('x-show="currentZone === \'heroes\' && isCustomerAuthenticated() && !soloMode"', $html);
        $this->assertStringContainsString('handleZoneSyncTransition', $html);
        $this->assertStringContainsString('handleSanctumHttpResponse', $html);
        $this->assertStringContainsString('data-testid="lobby-audio-mute-btn"', $html);
        $this->assertStringContainsString('data-testid="louvre-settings-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-modal"', $html);
        $this->assertStringContainsString('data-testid="lobby-settings-volume-slider"', $html);
        $this->assertStringContainsString('data-testid="lobby-camera-sensitivity-slider"', $html);
        $this->assertStringContainsString('data-testid="lobby-use-real-name-checkbox"', $html);
        $this->assertStringContainsString('data-testid="lobby-keybind-expand-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-keybind-reset-btn"', $html);
        $this->assertStringContainsString('data-testid="lobby-lava-chest-card"', $html);
        $this->assertStringContainsString('Magma-Truhe · +5 ✨ Funken', $html);
        $this->assertStringContainsString('Truhe öffnen', $html);
        $this->assertStringContainsString('Heute bereits geöffnet ✓', $html);
        $this->assertStringContainsString('Lautstärke', $html);
        $this->assertStringContainsString('Kamera-Tempo', $html);
        $this->assertStringContainsString('Grafik-Stufe', $html);
        $this->assertStringContainsString('Weiterspielen', $html);
        $this->assertStringContainsString('Lobby verlassen', $html);
        $this->assertStringContainsString('Echten Vornamen nutzen', $html);
        $this->assertStringContainsString('Steuerung', $html);
        $this->assertStringContainsString('sf-ability-combo-badge', $html);
        $this->assertStringContainsString('data-testid="lobby-gold-crest-bar"', $html);
        $this->assertStringContainsString('&copy; Mein-Seelenfunke', $html);
        $this->assertStringContainsString('data-testid="louvre-abilities-dock"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-1-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-2-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-3-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-4-btn"', $html);
        $this->assertStringContainsString('data-testid="ability-slot-5-btn"', $html);
        $this->assertStringContainsString('Funken-Hieb', $html);
        $this->assertStringContainsString('Licht-Sprint', $html);
        $this->assertStringContainsString('Aufwind-Impuls', $html);
        $this->assertStringContainsString('Aegis-Schild', $html);
        $this->assertStringContainsString('Seelen-Nova', $html);
        $this->assertStringContainsString('sf-ability-tooltip', $html);
        $this->assertStringContainsString('sf-ability-svg', $html);
        $this->assertStringNotContainsString('data-testid="louvre-jump-btn"', $html);
        $this->assertStringNotContainsString('data-testid="louvre-weapon-btn"', $html);

        // Blauer Mana-Balken mit explizitem Label "Mana", 180px Minimap, 2-Zeilen Edel-Exponat-Modal + Übergangsanimation & Kontextmenü-Schutz
        $this->assertStringContainsString('sf-lobby-unit-mana-label', $html);
        $this->assertStringContainsString('>Mana<', $html);
        $this->assertStringContainsString('linear-gradient(90deg, #1d4ed8 0%, #38bdf8 100%)', $html);
        $this->assertStringContainsString('width="180" height="180"', $html);
        $this->assertStringContainsString('width: 180px;', $html);
        $this->assertStringContainsString('sf-lobby-prod-row1', $html);
        $this->assertStringContainsString('sf-lobby-prod-price-badge', $html);
        $this->assertStringContainsString('sf-lobby-prod-summary', $html);
        $this->assertStringContainsString('-webkit-line-clamp: 2;', $html);
        $this->assertStringContainsString('sf-lobby-ivory-transition', $html);
        $this->assertStringContainsString('launchProductTransition', $html);
        $this->assertStringContainsString('@contextmenu.prevent', $html);

        // Mobile UI: Steuerknüppel am Desktop ausgeblendet, mobil sichtbar & Fähigkeiten fließend in der goldenen Zierleiste
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
        $this->assertStringContainsString("import('/js/seelenfunke-louvre-showroom-3d.js?v=20261117v6')", $html);
        $this->assertStringContainsString('data-testid="lobby-vital-hud"', $html);
        $this->assertStringContainsString('data-testid="lobby-lava-demote-banner"', $html);
        $this->assertStringContainsString('data-testid="sanctum-login-password-eye"', $html);
        $this->assertStringContainsString('/funkenwerk-welt/inline-login', $html);
        $this->assertStringContainsString('/funkenwerk-welt/activate-magic', $html);
        $this->assertStringContainsString('applyLiveAccountUpdate', $html);
        $this->assertStringContainsString('focusNearestLobbyVitrine', $html);
        $this->assertStringContainsString('#FEF2F2', $html);
        $this->assertStringContainsString('#FECACA', $html);
        $this->assertStringContainsString('#DC2626', $html);
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

        // 5. Doppelsprung mit 360-Grad-Salto und Schlag-Animation (Faust & Waffe) auf Maustaste
        $this->assertStringContainsString('jumpCount', $js);
        $this->assertStringContainsString('isSomersault', $js);
        $this->assertStringContainsString('somersaultProgress', $js);
        $this->assertStringContainsString('triggerStrikeAction', $js);
        $this->assertStringContainsString('_spawnDoubleJumpBurst', $js);
        $this->assertStringContainsString('_spawnUnarmedPunchBurst', $js);

        // 5b. 15-Bone SkinnedMesh, 3D-Exoskelett, 0-mm-Fuß-Clipping, schleierfreie Torbögen, Magma-Prüfungskammer & Anti-Manipulation
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
        $this->assertStringContainsString('sf_lobby_keybindings_v3', $js);
        $this->assertStringContainsString("strike: 'Digit1'", $js);
        $this->assertStringContainsString("sprint: 'Digit2'", $js);
        $this->assertStringContainsString("titanJump: 'Digit3'", $js);
        $this->assertStringContainsString("shield: 'Digit4'", $js);
        $this->assertStringContainsString("burst: 'Digit5'", $js);
        $this->assertStringContainsString('LOBBY_ABILITY_DEFINITIONS', $js);
        $this->assertStringContainsString('getLobbyAbilitySvgMarkup', $js);
        $this->assertStringContainsString('proceduralExoMeshes', $js);
        $this->assertStringContainsString('u >= 0.58', $js);
        $this->assertStringContainsString('? (this.player.somersaultProgress * TWO_PI)', $js);
        $this->assertStringNotContainsString('? -(this.player.somersaultProgress * TWO_PI)', $js);
        $this->assertStringContainsString('_shatterAegisShield', $js);
        $this->assertStringContainsString('this.player.vy = 14.5;', $js);
        $this->assertStringContainsString('lavaShockRings', $js);
        $this->assertStringContainsString('addShockRingEmitter', $js);
        $this->assertStringNotContainsString('addSideBasaltColumn', $js);
        $this->assertStringNotContainsString('isSideColumn', $js);
        $this->assertStringContainsString('this.lavaChamberMinZ = -24.0', $js);
        $this->assertStringContainsString('this.lavaChamberMaxZ = 20.0', $js);
        $this->assertStringContainsString('this.lavaChamberMaxX = 104.0', $js);
        $this->assertStringContainsString('computeLavaSCurveAnchors', $js);
        $this->assertStringContainsString('_computeMaxReachableHorizontalGap', $js);
        $this->assertStringContainsString('_verifyAndClampJumpReachability', $js);
        $this->assertStringContainsString('eastMagmaPortalVeilMat', $js);
        $this->assertStringContainsString('eastMagmaNicheFloorMat', $js);
        $this->assertStringContainsString('depthWrite: false', $js);
        $this->assertStringContainsString('_triggerSanctumGateRepulse', $js);
        $this->assertStringContainsString('_spawnGateRepulseFieldBurst', $js);
        $this->assertStringContainsString('applyLiveAccountUpdate', $js);
        $this->assertStringContainsString('focusNearestExhibit', $js);
        $this->assertStringContainsString('rightHandWeaponSocket', $js);
        $this->assertStringContainsString('rightHandPalmBind', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/commit', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/leave', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/hit', $js);
        $this->assertStringContainsString('life_nonce', $js);
        $this->assertStringContainsString('level_demoted', $js);
        $this->assertStringContainsString('shield_saved', $js);
        $this->assertStringContainsString('freePedestalCrests', $js);
        $this->assertStringContainsString('refreshHeroesData', $js);
        $this->assertStringContainsString('startExhibitTransition', $js);
        $this->assertStringContainsString('triggerComboStrike', $js);
        $this->assertStringContainsString('_spawnComboStage2Burst', $js);
        $this->assertStringContainsString('_spawnComboStage3WhirlBurst', $js);
        $this->assertStringContainsString('_spawnComboFinisherExplosion', $js);
        $this->assertStringContainsString('_buildLavaChamberAndParkour', $js);
        $this->assertStringContainsString('cavernRoofCap', $js);
        $this->assertStringContainsString('westOuterGateGroup', $js);
        $this->assertStringContainsString('pilasterCapInst', $js);
        $this->assertStringContainsString('lavaGeysers', $js);
        $this->assertStringContainsString("p.type === 'crumble'", $js);
        $this->assertStringContainsString('_startPromise', $js);
        $this->assertStringContainsString('_cp1Promise', $js);
        $this->assertStringContainsString('_cp2Promise', $js);
        $this->assertStringContainsString('retryable === true', $js);
        $this->assertStringContainsString('Truhe entriegelt...', $js);
        $this->assertStringContainsString('MAGMA-PRÜFUNG', $js);
        $this->assertStringContainsString('this.lavaSurfaceY = -1.35', $js);
        $this->assertStringContainsString('const destX = 16.5;', $js);
        $this->assertStringContainsString('ShaderMaterial', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/start', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/checkpoint', $js);
        $this->assertStringContainsString('/funkenwerk-welt/lava-parkour/claim', $js);
        $this->assertStringContainsString('lavaHubPortals', $js);
        $this->assertStringContainsString('lavaEpicGate', $js);
        $this->assertStringContainsString('canEnterSanctums', $js);
        $this->assertStringContainsString('getSanctumAccessState', $js);
        $this->assertStringContainsString('_interceptSanctumResponse', $js);
        $this->assertStringContainsString('login_required', $js);
        $this->assertStringContainsString('order_required', $js);
        $this->assertStringContainsString('magic_activation_required', $js);
        $this->assertStringContainsString('getMinimapState', $js);
        $this->assertStringContainsString('releasePointerLock', $js);
        $this->assertStringNotContainsString('_buildMiniLabyrinthWestWing', $js);
        $this->assertStringContainsString("this.getCurrentZone() !== 'heroes'", $js);
        $this->assertStringContainsString('triggerAbility', $js);
        $this->assertStringContainsString('triggerEmote', $js);
        $this->assertStringContainsString('getAbilityState', $js);
        $this->assertStringContainsString('updateSettings', $js);
        $this->assertStringContainsString('_spawnAbilityBurst', $js);
        $this->assertStringContainsString('shieldMesh', $js);

        // Client-Seitiger Manipulations-Schutz (ag_20261117_083626_1275f93f): Private Klasse + Object.freeze-Fassade + 0 Cheat-Methoden
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
        $this->assertStringContainsString('/js/seelenfunke-louvre-showroom-3d.js?v=20261117v6', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-vital-hud"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-lava-demote-banner"', $hostJs);
        $this->assertStringContainsString('data-testid="sanctum-login-password-eye"', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/inline-login', $hostJs);
        $this->assertStringContainsString('/funkenwerk-welt/activate-magic', $hostJs);
        $this->assertStringContainsString('applyLiveAccountUpdate', $hostJs);
        $this->assertStringContainsString('focusNearestExhibit', $hostJs);
        $this->assertStringContainsString('_startLobbyMusicFromGestureSync', $hostJs);
        $this->assertStringContainsString('setBgmVolume', $hostJs);
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
        $this->assertStringContainsString('data-testid="lobby-sanctum-gate-modal"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-master-key-modal"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-player-unit-frame"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-minimap"', $hostJs);
        $this->assertStringContainsString('handleSanctumHttpResponse', $hostJs);
        $this->assertStringContainsString('_handleZoneSyncTransition', $hostJs);
        $this->assertStringContainsString('refreshHeroesData', $hostJs);
        $this->assertStringContainsString("this.currentZone === 'heroes' && this.lobbyAuth && !this.soloMode", $hostJs);
        $this->assertStringContainsString('data-testid="louvre-settings-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-modal"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-settings-volume-slider"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-camera-sensitivity-slider"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-use-real-name-checkbox"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-lava-chest-card"', $hostJs);
        $this->assertStringContainsString('data-testid="lobby-gold-crest-bar"', $hostJs);
        $this->assertStringContainsString('© Mein-Seelenfunke', $hostJs);
        $this->assertStringContainsString('data-testid="louvre-abilities-dock"', $hostJs);
        $this->assertStringContainsString('data-testid="ability-slot-1-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="ability-slot-2-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="ability-slot-3-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="ability-slot-4-btn"', $hostJs);
        $this->assertStringContainsString('data-testid="ability-slot-5-btn"', $hostJs);
        $this->assertStringContainsString('sf-ability-tooltip', $hostJs);
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

    public function test_lobby_audio_module_couples_with_sf_funki_bgm_and_has_zero_em_dashes(): void
    {
        $audioJsPath = public_path('js/seelenfunke-lobby-audio.js');
        $this->assertFileExists($audioJsPath);

        $audioJs = (string) file_get_contents($audioJsPath);
        $this->assertNotEmpty($audioJs);

        $this->assertStringContainsString('SeelenfunkeLobbyAudio', $audioJs);
        $this->assertStringContainsString('SfFunkiBgm', $audioJs);
        $this->assertStringContainsString('/shop/gamification/audio/bgm_funki_game.mp3', $audioJs);
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
        $this->assertStringContainsString('updateLavaAmbience', $audioJs);
        $this->assertStringNotContainsString('https://', $audioJs);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $audioJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $audioJs);
    }
}
