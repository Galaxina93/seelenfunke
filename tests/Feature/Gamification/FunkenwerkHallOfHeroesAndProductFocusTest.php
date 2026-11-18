<?php

namespace Tests\Feature\Gamification;

use App\Livewire\Customer\CustomerDashboardComponent;
use App\Livewire\Customer\CustomerFunkiComponent;
use App\Livewire\Shop\Order\OrderCheckout\OrderCheckoutSuccess;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Services\AI\Functions\AiOrderFuncs;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\AI\MasterNextBestActionService;
use App\Services\Gamification\GameConfig;
use App\Services\Order\OrderProductionBatchService;
use App\Services\Storage\GlbOptimizerService;
use Carbon\Carbon;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FunkenwerkHallOfHeroesAndProductFocusTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina.heroes@mein-seelenfunke.de',
            'password' => bcrypt('secret1234'),
        ]);
    }

    public function test_production_focus_label_and_compass_name_quantity_and_product_instead_of_order_number(): void
    {
        $this->actingAs($this->admin, 'admin');
        $this->seed(AiAgentSeeder::class);

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Miriam',
            'last_name' => 'Mustermann',
            'email' => 'miriam@example.de',
            'password' => bcrypt('secret1234'),
        ]);

        $kristall = Product::create([
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall-' . Str::lower(Str::random(5)),
            'sku' => 'CRYSTAL-FOCUS-1',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 12,
        ]);

        $anhaenger = Product::create([
            'name' => 'Herz-Schlüsselanhänger',
            'slug' => 'herz-schluesselanhaenger-' . Str::lower(Str::random(5)),
            'sku' => 'HEART-FOCUS-2',
            'type' => 'physical',
            'status' => 'active',
            'price' => 1990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 20,
        ]);

        $schiefer = Product::create([
            'name' => 'Schieferplatte',
            'slug' => 'schieferplatte-' . Str::lower(Str::random(5)),
            'sku' => 'SLATE-FOCUS-3',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 15,
        ]);

        // 0 items fallback
        $emptyOrder = $this->createPaidOrder('ORD-EMPTY-001', $customer, false, 'cancelled');
        $this->assertSame('Auftrag #ORD-EMPTY-001', $emptyOrder->getProductionFocusLabel());

        // 1 item, 2 items, and >= 3 items formatting
        $multiOrder = $this->createPaidOrder('ORD-MULTI-002', $customer, false, 'completed');
        OrderOrderItem::create([
            'order_id' => $multiOrder->id,
            'product_id' => $kristall->id,
            'product_name' => $kristall->name,
            'quantity' => 1,
            'unit_price' => 4990,
            'total_price' => 4990,
        ]);
        $this->assertSame('1× Der Seelen Kristall', $multiOrder->fresh()->getProductionFocusLabel());

        OrderOrderItem::create([
            'order_id' => $multiOrder->id,
            'product_id' => $anhaenger->id,
            'product_name' => $anhaenger->name,
            'quantity' => 2,
            'unit_price' => 1990,
            'total_price' => 3980,
        ]);
        $this->assertSame('1× Der Seelen Kristall + 2× Herz-Schlüsselanhänger', $multiOrder->fresh()->getProductionFocusLabel());

        OrderOrderItem::create([
            'order_id' => $multiOrder->id,
            'product_id' => $schiefer->id,
            'product_name' => $schiefer->name,
            'quantity' => 1,
            'unit_price' => 2990,
            'total_price' => 2990,
        ]);
        $this->assertSame('1× Der Seelen Kristall + 2× Herz-Schlüsselanhänger (+1 weiterer)', $multiOrder->fresh()->getProductionFocusLabel());

        // Open Express priority order for MasterNextBestActionService & OrderProductionBatchService
        $expressOrder = $this->createPaidOrder('ORD-EXP-777', $customer, true, 'pending');
        OrderOrderItem::create([
            'order_id' => $expressOrder->id,
            'product_id' => $kristall->id,
            'product_name' => $kristall->name,
            'quantity' => 1,
            'completed_quantity' => 0,
            'is_completed' => false,
            'configuration' => ['text' => 'Für Mama'],
            'unit_price' => 4990,
            'total_price' => 4990,
        ]);

        $queue = app(OrderProductionBatchService::class)->buildSmartQueue(
            OrderOrder::with(['items.product', 'shipments'])->whereIn('status', ['pending', 'processing'])->get()
        );
        $this->assertStringContainsString('1× „Der Seelen Kristall“', $queue['funki_directive']['action_plain']);
        $this->assertStringNotContainsString('#ORD-EXP-777', $queue['funki_directive']['action_plain']);

        $compass = app(MasterNextBestActionService::class)->evaluate(null, Carbon::parse('2026-11-17 10:45:00', 'Europe/Berlin'));
        $this->assertSame('ORD-EXP-777', $compass['workshop_snapshot']['priority_order_number']);
        $this->assertSame('1× Der Seelen Kristall', $compass['workshop_snapshot']['priority_product_label']);
        $this->assertSame('EXPRESS-Fertigung: 1× Der Seelen Kristall sofort lasern', $compass['recommended_focus_now']['title']);
        $this->assertStringNotContainsString('#ORD-EXP-777', $compass['recommended_focus_now']['title']);

        // Executive prompts & orders SOP enforce product-first focus
        $pools = FunkiraSkillPoolService::getPoolDefinitions();
        $this->assertStringContainsString('PRODUKTNAME STATT BESTELLNUMMER IM FOKUS', $pools['orders']['sop']);
        $this->assertStringContainsString('PRODUKTNAME STATT BESTELLNUMMER IM FOKUS', (string) AiAgent::where('name', 'Falcone')->value('system_prompt'));
        $this->assertStringContainsString('PRODUKTNAME STATT BESTELLNUMMER IM FOKUS', (string) AiAgent::where('name', 'Funkira')->value('system_prompt'));
    }

    public function test_ai_order_funcs_resolves_open_priority_orders_directly_by_product_name(): void
    {
        $this->actingAs($this->admin, 'admin');

        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Jonas',
            'last_name' => 'Beispiel',
            'email' => 'jonas@example.de',
            'password' => bcrypt('secret1234'),
        ]);

        $kristall = Product::create([
            'name' => 'Der Seelen Kristall',
            'slug' => 'seelen-kristall-ai-' . Str::lower(Str::random(5)),
            'sku' => 'CRYSTAL-AI-1',
            'type' => 'physical',
            'status' => 'active',
            'price' => 4990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        $openOrder = $this->createPaidOrder('ORD-PROD-LOOKUP-99', $customer, false, 'pending');
        OrderOrderItem::create([
            'order_id' => $openOrder->id,
            'product_id' => $kristall->id,
            'product_name' => $kristall->name,
            'quantity' => 1,
            'completed_quantity' => 0,
            'is_completed' => false,
            'configuration' => ['text' => 'Sternenglanz'],
            'unit_price' => 4990,
            'total_price' => 4990,
        ]);

        // 1. order_get_details resolves open order when product name is passed in order_number or product_name
        $detailsRes = AiOrderFuncs::executeGetOrder(['order_number' => 'Der Seelen Kristall']);
        $this->assertSame('success', $detailsRes['status']);
        $this->assertNotEmpty($detailsRes['orders']);
        $this->assertSame('ORD-PROD-LOOKUP-99', $detailsRes['orders'][0]['order_number']);
        $this->assertSame('1× Der Seelen Kristall', $detailsRes['orders'][0]['production_focus_label']);

        // 2. order_workshop_checkin resolves open order by product name
        $checkinRes = AiOrderFuncs::executeOrderWorkshopCheckin([
            'action' => 'save_progress',
            'order_number' => '1× Der Seelen Kristall',
            'workshop_note' => '3D-Innengravur gestartet',
            'followup_mode' => '2h',
        ]);
        $this->assertSame('success', $checkinRes['status']);
        $this->assertSame('ORD-PROD-LOOKUP-99', $checkinRes['checkin']['order_number']);
        $this->assertSame('3D-Innengravur gestartet', $openOrder->fresh()->workshop_note);

        // 3. order_update_status resolves open order by product name while preserving Alina confirmation guard
        $guardRes = AiOrderFuncs::executeOrderUpdateStatus([
            'order_number' => 'Der Seelen Kristall',
            'status' => 'processing',
            'confirmed_by_alina' => false,
        ]);
        $this->assertSame('confirmation_required', $guardRes['status']);
        $this->assertSame('ORD-PROD-LOOKUP-99', $guardRes['order_number']);
        $this->assertSame('pending', $openOrder->fresh()->status);

        $confirmedRes = AiOrderFuncs::executeOrderUpdateStatus([
            'order_number' => 'Der Seelen Kristall',
            'status' => 'processing',
            'confirmed_by_alina' => true,
        ]);
        $this->assertSame('success', $confirmedRes['status']);
        $this->assertSame('processing', $openOrder->fresh()->status);
    }

    public function test_heroes_json_endpoint_enforces_opt_in_zero_pii_is_me_and_renders_3d_buttons(): void
    {
        // Hero 1: Opt-in with player_name (Level 10 -> Rank S + Magma Lvl 1 & 2 = +45 Prestige -> hero_score = 5045)
        $heroOneCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Maximilian',
            'last_name' => 'StrengGeheimerNachname Eins',
            'email' => 'streng.geheim.eins@private-mail.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-HERO-ONE-01', $heroOneCustomer, false, 'completed');
        CustomerGamification::create([
            'customer_id' => $heroOneCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'Sternenwanderer',
            'level' => 10,
            'funken_total_earned' => 5000,
            'galactic_conquest_points' => 900,
            'titles_progress' => [
                'lava_cleared_levels' => [1, 2],
            ],
        ]);

        // Hero 2: ranking_opt_in = false, aber frei gewaehltes Pseudonym (0 Klardaten) -> erscheint als "HiddenShadow99" auf Platz #2
        $optOutCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Versteckt',
            'last_name' => 'Unsichtbar',
            'email' => 'unsichtbar@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-HERO-TWO-02', $optOutCustomer, false, 'completed');
        CustomerGamification::create([
            'customer_id' => $optOutCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => false,
            'player_name' => 'HiddenShadow99',
            'level' => 9,
            'funken_total_earned' => 4000,
        ]);

        // Hero 3 (Logged-in customer): Opt-in with use_real_name = true -> ONLY "Clara S.", NEVER full last name!
        $meCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Clara',
            'last_name' => 'SuperGeheimNachname',
            'email' => 'clara.privat@geheim.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $meOrder = $this->createPaidOrder('ORD-HERO-CLARA-03', $meCustomer, false, 'completed');
        $meOrder->timestamps = false;
        $meOrder->forceFill(['created_at' => now()->subMinutes(10), 'updated_at' => now()->subMinutes(10)])->save();
        CustomerGamification::create([
            'customer_id' => $meCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => true,
            'player_name' => '',
            'level' => 6,
            'funken_total_earned' => 1600,
            'galactic_conquest_points' => 450,
        ]);

        // Hero 4: ranking_opt_in = false UND kein player_name -> fuer Dritte "Funken-Hüter #4", fuer sich selbst eigener Vorname!
        $anonNoPseudoCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Helena',
            'last_name' => 'GeheimPrivatNachname',
            'email' => 'helena.geheim@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-HERO-HELENA-04', $anonNoPseudoCustomer, false, 'completed');
        CustomerGamification::create([
            'customer_id' => $anonNoPseudoCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => false,
            'player_name' => null,
            'level' => 2,
            'funken_total_earned' => 271,
        ]);

        // Storno-Leiche: Hat zwar magma_cleared_count > 0, aber seine einzige Bestellung wurde widerrufen -> darf NICHT auf Podest erscheinen!
        $revokedGhostCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Storno',
            'last_name' => 'Geist',
            'email' => 'storno.geist@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-REVOKED-GHOST-99', $revokedGhostCustomer, false, 'completed');
        \App\Models\Order\OrderRevocation::create([
            'name' => 'Storno Geist',
            'email' => $revokedGhostCustomer->email,
            'order_number' => 'ORD-REVOKED-GHOST-99',
            'status' => 'approved',
        ]);
        CustomerGamification::create([
            'customer_id' => $revokedGhostCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'StornoGeist999',
            'level' => 10,
            'funken_total_earned' => 9000,
            'magma_unlocked_level' => 5,
            'titles_progress' => [
                'lava_cleared_levels' => [1, 2, 3, 4],
            ],
        ]);

        // 1. Public guest request works on BOTH routes and exposes 0 PII (inkl. Sanctum-Gate fuer Gast: login_required)
        $guestResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $guestResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('founding_era', true)
            ->assertJsonPath('total_heroes', 4)
            ->assertJsonPath('customer_logged_in', false)
            ->assertJsonPath('can_enter_heroes', false)
            ->assertJsonPath('can_enter_sanctums', false)
            ->assertJsonPath('sanctum_block_reason', 'login_required')
            ->assertJsonPath('my_hero_slot', null)
            ->assertJsonPath('heroes.0.display_name', 'Sternenwanderer')
            ->assertJsonPath('heroes.0.hero_score', 5045)
            ->assertJsonPath('heroes.0.hero_score_formatted', '5.045')
            ->assertJsonPath('heroes.0.magma_highest_level', 2)
            ->assertJsonPath('heroes.0.magma_cleared_count', 2)
            ->assertJsonPath('heroes.0.magma_prestige_points', 45)
            ->assertJsonPath('heroes.1.display_name', 'HiddenShadow99')
            ->assertJsonPath('heroes.2.display_name', 'Clara S.')
            ->assertJsonPath('heroes.3.display_name', 'Funken-Hüter #4')
            ->assertJsonPath('heroes.3.occupied', true)
            ->assertJsonPath('heroes.4.occupied', false)
            ->assertJsonPath('heroes.4.is_placeholder', true)
            ->assertJsonPath('heroes.4.model_url', null);

        $this->getJson('/api/gamification/funkenwerk-heroes')
            ->assertOk()
            ->assertJsonPath('total_heroes', 4)
            ->assertJsonPath('can_enter_heroes', false)
            ->assertJsonPath('sanctum_block_reason', 'login_required');

        $rawGuestBody = $guestResp->getContent();
        $this->assertStringNotContainsString('streng.geheim.eins@private-mail.de', $rawGuestBody);
        $this->assertStringNotContainsString('clara.privat@geheim.de', $rawGuestBody);
        $this->assertStringNotContainsString('unsichtbar@example.de', $rawGuestBody);
        $this->assertStringNotContainsString('helena.geheim@example.de', $rawGuestBody);
        $this->assertStringNotContainsString('StrengGeheimerNachname', $rawGuestBody);
        $this->assertStringNotContainsString('SuperGeheimNachname', $rawGuestBody);
        $this->assertStringNotContainsString('Versteckt', $rawGuestBody);
        $this->assertStringNotContainsString('Unsichtbar', $rawGuestBody);
        $this->assertStringNotContainsString('Helena', $rawGuestBody);
        $this->assertStringNotContainsString('GeheimPrivatNachname', $rawGuestBody);
        $this->assertStringNotContainsString((string) $heroOneCustomer->id, $rawGuestBody);
        $this->assertStringNotContainsString((string) $meCustomer->id, $rawGuestBody);
        $this->assertStringNotContainsString((string) $anonNoPseudoCustomer->id, $rawGuestBody);
        $this->assertStringNotContainsString('StornoGeist999', $rawGuestBody);

        // 15s Public-Cache ist gefuellt
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has(\App\Http\Controllers\Api\FunkenwerkHeroesController::PUBLIC_CACHE_KEY));

        // 2. Logged-in customer ($meCustomer auf Platz #3) sieht sich mit is_me = true, waehrend Platz #4 weiterhin "Funken-Hüter #4" bleibt
        $this->actingAs($meCustomer, 'customer');
        $authResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $authResp->assertOk()
            ->assertJsonPath('customer_logged_in', true)
            ->assertJsonPath('customer_opt_in', true)
            ->assertJsonPath('has_completed_order', true)
            ->assertJsonPath('gamification_active', true)
            ->assertJsonPath('can_enter_heroes', true)
            ->assertJsonPath('can_enter_sanctums', true)
            ->assertJsonPath('sanctum_block_reason', null)
            ->assertJsonPath('my_hero_slot', 3)
            ->assertJsonPath('level_models.1', '/shop/customer/gamification/models/funki_lvl_1_rags.glb')
            ->assertJsonPath('level_models.10', '/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb')
            ->assertJsonPath('heroes.0.display_name', 'Sternenwanderer')
            ->assertJsonPath('heroes.0.rank_class', 'S')
            ->assertJsonPath('heroes.0.is_me', false)
            ->assertJsonPath('heroes.2.display_name', 'Clara S.')
            ->assertJsonPath('heroes.2.rank_class', 'A')
            ->assertJsonPath('heroes.2.is_me', true)
            ->assertJsonPath('heroes.3.display_name', 'Funken-Hüter #4')
            ->assertJsonPath('heroes.3.is_me', false);

        $authJson = $authResp->json();
        $this->assertCount(10, $authJson['level_models']);
        $this->assertStringEndsWith('/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb', (string) $authJson['heroes'][0]['model_url']);
        $this->assertStringEndsWith('/shop/customer/gamification/models/funki_lvl_6_craftsman.glb', (string) $authJson['heroes'][2]['model_url']);
        $this->assertStringEndsWith('/shop/customer/gamification/models/funki_lvl_2_basic.glb', (string) $authJson['heroes'][3]['model_url']);

        // 2b. Schaut Hero 4 ($anonNoPseudoCustomer, ranking_opt_in = false, kein player_name) selbst hinein, sieht sie ihren eigenen Vornamen "Helena"!
        $this->actingAs($anonNoPseudoCustomer, 'customer');
        $selfAnonResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $selfAnonResp->assertOk()
            ->assertJsonPath('my_hero_slot', 4)
            ->assertJsonPath('customer_opt_in', false)
            ->assertJsonPath('heroes.3.is_me', true)
            ->assertJsonPath('heroes.3.display_name', 'Helena');
        $this->assertStringNotContainsString('GeheimPrivatNachname', $selfAnonResp->getContent());

        $this->actingAs($meCustomer, 'customer');

        // 3. Verify 3D User Lobby loader & engine adhere to Clean-Design, 1:1 Pantheon Hall of Heroes & Multiplayer Instances (0 Water City!)
        $loaderJs = (string) file_get_contents(public_path('js/funkenwerk-heroes-3d.js'));
        $engineJs = (string) file_get_contents(public_path('js/seelenfunke-louvre-showroom-3d.js'));

        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $loaderJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $loaderJs);
        $this->assertStringNotContainsString('https://', $loaderJs);
        $this->assertStringNotContainsString('_buildWaterMetropolis', $loaderJs);
        $this->assertStringNotContainsString('startOrbitalFlyIn', $loaderJs);
        $this->assertStringContainsString('/js/seelenfunke-louvre-showroom-3d.js', $loaderJs);
        $this->assertStringContainsString('sf-lobby-instance-btn', $loaderJs);
        $this->assertStringContainsString('sf-lobby-instance-menu', $loaderJs);
        $this->assertStringContainsString('data-testid="lobby-current-zone-badge"', $loaderJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-gallery-btn"', $loaderJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-heroes-btn"', $loaderJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-lava-btn"', $loaderJs);
        $this->assertStringNotContainsString('data-testid="lobby-zone-labyrinth-btn"', $loaderJs);
        $this->assertStringContainsString('_handleZoneSyncTransition', $loaderJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/join', $loaderJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/sync', $loaderJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/switch', $loaderJs);
        $this->assertStringContainsString('/funkenwerk-welt/lobby/leave', $loaderJs);

        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $engineJs);
        $this->assertStringNotContainsString('https://', $engineJs);
        $this->assertStringNotContainsString('Herrscherin über das Seelenfunken-Universum', $engineJs);
        $this->assertStringContainsString('HALLE DER HELDEN', $engineJs);
        $this->assertStringContainsString('#ECEAE4', $engineJs);
        $this->assertStringContainsString('#133B5C', $engineJs);
        $this->assertStringContainsString('const domeBaseY = entY + 5.35;', $engineJs);
        $this->assertStringContainsString('((r + 0.5) / 6) * Math.PI', $engineJs);
        $this->assertStringContainsString('parseGlbBinary', $engineJs);
        $this->assertStringContainsString('0x46546C67', $engineJs);
        $this->assertStringContainsString('FUNKI_GLB_FRONT_ROTATION_Y = -Math.PI * 0.5', $engineJs);
        $this->assertStringContainsString('HemisphereLight', $engineJs);
        $this->assertStringContainsString('glbTemplateCache = new Map()', $engineJs);
        $this->assertStringContainsString('_createFunkiCharacterByLevel', $engineJs);
        $this->assertStringContainsString('Freier Ehrenplatz', $engineJs);

        // 4. Genau EIN 3D-Einstieg im Kundenbereich: "Ins Funkenreich" auf dem Dashboard (0 internal Funkenwerk references!).
        //    "Halle der Helden 3D" & "Funken im Funkenreich sammeln" sind entfernt und bleiben auch im Checkout Success entfernt.
        $dashTest = Livewire::actingAs($meCustomer, 'customer')
            ->test(CustomerDashboardComponent::class)
            ->assertSee('Ins Funkenreich')
            ->assertDontSee('3D-Lobby öffnen')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertDontSee('Halle der Helden 3D')
            ->assertDontSee('Funken im Funkenreich sammeln')
            ->assertSeeHtml('data-testid="dashboard-open-user-lobby-3d"')
            ->assertSeeHtml('funkenwerk-heroes-3d.js?v=')
            ->assertSeeHtml("open('lobby')")
            ->assertDontSeeHtml("open('hall')")
            ->assertDontSeeHtml('data-testid="modal-open-hall-of-heroes-3d"');

        $dashHtml = (string) $dashTest->html();
        $visibleDashHtml = explode('<template x-teleport="body">', $dashHtml)[0] ?? '';
        $this->assertStringContainsString('data-testid="dashboard-open-user-lobby-3d"', $visibleDashHtml);
        $this->assertStringContainsString('Ins Funkenreich', $visibleDashHtml);
        $this->assertStringNotContainsString('3D-Lobby öffnen', $visibleDashHtml);
        $this->assertSame(1, substr_count($dashHtml, 'SeelenfunkeHeroes3D'));

        $this->actingAs($meCustomer, 'customer')
            ->get('/customer/dashboard')
            ->assertOk()
            ->assertSee('Ins Funkenreich')
            ->assertDontSee('3D-Lobby öffnen')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertSee('data-testid="dashboard-open-user-lobby-3d"', false);

        Livewire::actingAs($meCustomer, 'customer')
            ->test(CustomerFunkiComponent::class)
            ->assertSee('Halle der Helden')
            ->assertDontSee('Halle der Helden 3D')
            ->assertDontSee('Funken im Funkenreich sammeln')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertDontSeeHtml('data-testid="funki-open-hall-of-heroes-3d"')
            ->assertDontSeeHtml('data-testid="funki-vouchers-open-3d-lobby"')
            ->assertDontSeeHtml('SeelenfunkeHeroes3D');

        $this->createPaidOrder('ORD-SUCC-CLEAN-01', $meCustomer, false, 'pending');

        // Nach bezahlter Bestellung + aktiver Magie sind alle 3 Voraussetzungen fuer Halle der Helden erfuellt
        $this->actingAs($meCustomer, 'customer')
            ->getJson('/funkenwerk-welt/heroes-data')
            ->assertOk()
            ->assertJsonPath('has_completed_order', true)
            ->assertJsonPath('gamification_active', true)
            ->assertJsonPath('can_enter_heroes', true)
            ->assertJsonPath('can_enter_sanctums', true)
            ->assertJsonPath('sanctum_block_reason', null);

        Livewire::actingAs($meCustomer, 'customer')
            ->test(OrderCheckoutSuccess::class)
            ->assertSee('ORD-SUCC-CLEAN-01')
            ->assertSee('Zur Bestellung')
            ->assertSee('Weiter einkaufen')
            ->assertDontSee('Ins Funkenreich')
            ->assertDontSee('3D-Lobby öffnen')
            ->assertDontSee('Halle der Helden 3D')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertDontSeeHtml('funkenwerk-heroes-3d.js')
            ->assertDontSeeHtml('SeelenfunkeHeroes3D');

        $this->actingAs($meCustomer, 'customer')
            ->get('/checkout/success')
            ->assertOk()
            ->assertDontSee('Ins Funkenreich')
            ->assertDontSee('3D-Lobby öffnen')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertDontSee('SeelenfunkeHeroes3D', false);
    }

    public function test_all_ten_compressed_funki_glb_models_pass_glb_optimizer_inspection_and_watermark(): void
    {
        $levels = GameConfig::getFunkiLevels();
        $this->assertCount(10, $levels);

        $inspector = app(GlbOptimizerService::class);
        $totalBytes = 0;

        for ($lvl = 1; $lvl <= 10; $lvl++) {
            $this->assertArrayHasKey($lvl, $levels);
            $relPath = (string) ($levels[$lvl]['model_path'] ?? '');
            $this->assertNotEmpty($relPath, "Level {$lvl} fehlt model_path in GameConfig.");
            $this->assertStringStartsWith("shop/customer/gamification/models/funki_lvl_{$lvl}_", $relPath);
            $this->assertStringEndsWith('.glb', $relPath);

            $absPath = public_path($relPath);
            $this->assertFileExists($absPath, "Komprimierte GLB-Datei für Level {$lvl} fehlt: {$absPath}");

            $info = $inspector->inspectGlb($absPath);
            $this->assertTrue($info['exists'] ?? false);
            $this->assertTrue($info['is_optimized'] ?? false);
            $this->assertTrue($info['is_marked_optimized'] ?? false, "Level {$lvl} muss seelenfunke_optimized = true tragen.");
            $this->assertTrue($info['has_copyright_watermark'] ?? false, "Level {$lvl} muss UrhG-Wasserzeichen von Alina tragen.");
            $this->assertFalse($info['needs_optimization'] ?? true);
            $this->assertLessThan(2 * 1024 * 1024, (int) $info['size_bytes'], "Level {$lvl} muss unter 2.0 MB liegen.");
            $this->assertGreaterThan(200 * 1024, (int) $info['size_bytes']);
            $this->assertLessThanOrEqual(26500, (int) $info['triangles']);
            $this->assertGreaterThanOrEqual(15000, (int) $info['triangles']);
            $this->assertGreaterThanOrEqual(95.0, (float) ($info['saved_percent'] ?? 0));

            $totalBytes += (int) $info['size_bytes'];
        }

        // Alle 10 Modelle zusammen ca. 9.7 MB (< 14 MB statt 468 MB!)
        $this->assertLessThan(14 * 1024 * 1024, $totalBytes);
    }

    public function test_hall_of_heroes_ranking_sync_funki_3d_lobby_cta_and_legal_pages(): void
    {
        $cAlpha = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alpha',
            'last_name' => 'NachnameAlpha',
            'email' => 'alpha.sync@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-ALPHA-SYNC-1', $cAlpha, false, 'completed');
        $pAlpha = CustomerGamification::create([
            'customer_id' => $cAlpha->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'FunkenAlpha',
            'level' => 6,
            'funken_total_earned' => 3100,
            'galactic_conquest_points' => 10,
        ]);
        $pAlpha->timestamps = false;
        $pAlpha->forceFill(['updated_at' => Carbon::parse('2026-11-17 10:00:00')])->save();

        $cBeta = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Beta',
            'last_name' => 'NachnameBeta',
            'email' => 'beta.sync@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-BETA-SYNC-2', $cBeta, false, 'completed');
        $pBeta = CustomerGamification::create([
            'customer_id' => $cBeta->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'FunkenBeta',
            'level' => 9,
            'funken_total_earned' => 2900,
            'galactic_conquest_points' => 99999,
        ]);
        $pBeta->timestamps = false;
        $pBeta->forceFill(['updated_at' => Carbon::parse('2026-11-17 09:00:00')])->save();

        $cGamma = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Gamma',
            'last_name' => 'NachnameGamma',
            'email' => 'gamma.sync@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        $this->createPaidOrder('ORD-GAMMA-SYNC-3', $cGamma, false, 'completed');
        $pGamma = CustomerGamification::create([
            'customer_id' => $cGamma->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'FunkenGamma',
            'level' => 6,
            'funken_total_earned' => 3100,
            'galactic_conquest_points' => 5000,
        ]);
        $pGamma->timestamps = false;
        $pGamma->forceFill(['updated_at' => Carbon::parse('2026-11-17 08:00:00')])->save();

        // Identische Sortierung ohne Magma: hero_score (= funken_total_earned) DESC -> level DESC -> updated_at ASC
        // Gamma (3100, Lvl 6, 08:00) -> Alpha (3100, Lvl 6, 10:00) -> Beta (2900, Lvl 9)
        $dashboardNames = collect(app(\App\Services\Gamification\GamificationService::class)->getHallOfFamePaginated(1, 10)['items'])
            ->pluck('display_name')
            ->all();
        $apiNames = collect($this->getJson('/funkenwerk-welt/heroes-data')->assertOk()->json('heroes'))
            ->where('occupied', true)
            ->pluck('display_name')
            ->values()
            ->all();

        $this->assertSame(['FunkenGamma', 'FunkenAlpha', 'FunkenBeta'], $dashboardNames);
        $this->assertSame($dashboardNames, $apiNames);

        // Punkt 5b: Magma-Level fliesst logisch in die Halle-der-Helden-Bewertungslogik ein!
        // FunkenBeta (2900 Funken) meistert Magma-Raeume 1..5 -> +15 +30 +45 +60 +75 = +225 Prestige -> hero_score = 3125 > 3100!
        $pBeta->forceFill([
            'magma_unlocked_level' => 6,
            'titles_progress' => [
                'lava_cleared_levels' => [1, 2, 3, 4, 5],
            ],
        ])->save();

        $dashboardItemsAfterMagma = app(\App\Services\Gamification\GamificationService::class)->getHallOfFamePaginated(1, 10)['items'];
        $dashboardNamesAfterMagma = collect($dashboardItemsAfterMagma)->pluck('display_name')->all();
        $heroesAfterMagmaResp = $this->getJson('/funkenwerk-welt/heroes-data')->assertOk();
        $apiNamesAfterMagma = collect($heroesAfterMagmaResp->json('heroes'))
            ->where('occupied', true)
            ->pluck('display_name')
            ->values()
            ->all();

        $this->assertSame(['FunkenBeta', 'FunkenGamma', 'FunkenAlpha'], $dashboardNamesAfterMagma);
        $this->assertSame($dashboardNamesAfterMagma, $apiNamesAfterMagma);
        $this->assertSame(3125, $heroesAfterMagmaResp->json('heroes.0.hero_score'));
        $this->assertSame('3.125', $heroesAfterMagmaResp->json('heroes.0.hero_score_formatted'));
        $this->assertSame(5, $heroesAfterMagmaResp->json('heroes.0.magma_highest_level'));
        $this->assertSame(5, $heroesAfterMagmaResp->json('heroes.0.magma_cleared_count'));
        $this->assertSame(225, $heroesAfterMagmaResp->json('heroes.0.magma_prestige_points'));
        $this->assertSame(3125, $dashboardItemsAfterMagma[0]['hero_score']);

        // Gutschein-Bereich von CustomerFunkiComponent ohne zusaetzlichen 3D-Einstieg (einziger Einstieg: Dashboard "Ins Funkenreich")
        Livewire::actingAs($cAlpha, 'customer')
            ->test(CustomerFunkiComponent::class)
            ->assertDontSee('Funken im Funkenreich sammeln')
            ->assertSee('250')
            ->assertSee('600')
            ->assertSee('1.200');

        // Rechtstexte: neue Gutschein-Staffel, Halle der Helden, Magma-Spiel/Meisterschluessel ausgelagert, 0 Mini-Labyrinth & 0 KI-Gedankenstriche
        $agbHtml = (string) $this->get('/agb')->assertOk()->getContent();
        $this->assertStringNotContainsString('Manufaktur-Meisterschlüssel', $agbHtml);
        $this->assertStringContainsString('Halle der Helden', $agbHtml);
        $this->assertStringContainsString('250 Seelenfunken', $agbHtml);
        $this->assertStringContainsString('600 Seelenfunken', $agbHtml);
        $this->assertStringContainsString('1.200 Seelenfunken', $agbHtml);
        $this->assertStringNotContainsString('Mini-Labyrinth', $agbHtml);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $agbHtml);

        $widerrufHtml = (string) $this->get('/widerruf')->assertOk()->getContent();
        $this->assertStringNotContainsString('Manufaktur-Meisterschlüssel', $widerrufHtml);
        $this->assertStringContainsString('Halle der Helden', $widerrufHtml);
        $this->assertStringNotContainsString('Mini-Labyrinth', $widerrufHtml);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $widerrufHtml);

        $dsHtml = (string) $this->get('/datenschutz')->assertOk()->getContent();
        $this->assertStringContainsString('250 / 600 / 1.200 Seelenfunken', $dsHtml);
        $this->assertStringContainsString('sparks_total', $dsHtml);
        $this->assertStringContainsString('sf_lobby_*', $dsHtml);
        $this->assertStringContainsString('sf_funki_*', $dsHtml);
        $this->assertStringNotContainsString('Mini-Labyrinth', $dsHtml);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $dsHtml);
    }

    public function test_single_active_player_without_opt_in_stands_on_podium_one_anonymized_for_visitors_and_named_for_self(): void
    {
        $alinaCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'SteinhauerPrivat',
            'email' => 'alina.podium@example.de',
            'email_verified_at' => now(),
            'password' => bcrypt('secret1234'),
        ]);
        CustomerGamification::create([
            'customer_id' => $alinaCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => false,
            'player_name' => null,
            'level' => 2,
            'funken_balance' => 271,
            'funken_total_earned' => 271,
        ]);
        $this->createPaidOrder('ORD-ALINA-PODIUM-1', $alinaCustomer, false, 'completed');

        // 1. Gast / Dritter sieht Ehrenplatz #1 belegt mit Funki Lvl 2 als "Funken-Hüter #1" (0 Klardaten, kein leeres Podest!)
        $visitorResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $visitorResp->assertOk()
            ->assertJsonPath('total_heroes', 1)
            ->assertJsonPath('my_hero_slot', null)
            ->assertJsonPath('heroes.0.slot', 1)
            ->assertJsonPath('heroes.0.occupied', true)
            ->assertJsonPath('heroes.0.is_placeholder', false)
            ->assertJsonPath('heroes.0.display_name', 'Funken-Hüter #1')
            ->assertJsonPath('heroes.0.level', 2)
            ->assertJsonPath('heroes.0.hero_score', 271)
            ->assertJsonPath('heroes.1.slot', 2)
            ->assertJsonPath('heroes.1.occupied', false)
            ->assertJsonPath('heroes.1.is_placeholder', true)
            ->assertJsonPath('heroes.1.model_url', null);
        $this->assertStringNotContainsString('Alina', $visitorResp->getContent());
        $this->assertStringNotContainsString('SteinhauerPrivat', $visitorResp->getContent());

        // 2. Eingeloggte Spielerin selbst ($isMe === true) sieht sich auf Ehrenplatz #1 mit ihrem eigenen Vornamen "Alina"!
        $selfResp = $this->actingAs($alinaCustomer, 'customer')->getJson('/funkenwerk-welt/heroes-data');
        $selfResp->assertOk()
            ->assertJsonPath('total_heroes', 1)
            ->assertJsonPath('my_hero_slot', 1)
            ->assertJsonPath('can_enter_heroes', true)
            ->assertJsonPath('heroes.0.slot', 1)
            ->assertJsonPath('heroes.0.occupied', true)
            ->assertJsonPath('heroes.0.is_me', true)
            ->assertJsonPath('heroes.0.display_name', 'Alina');
        $this->assertStringNotContainsString('SteinhauerPrivat', $selfResp->getContent());

        // Auch in der 2D-Halle-der-Helden steht sie fuer sich selbst als "Alina" auf Platz #1
        $paginatedSelf = app(\App\Services\Gamification\GamificationService::class)->getHallOfFamePaginated(1, 10);
        $this->assertSame(1, $paginatedSelf['total']);
        $this->assertSame('Alina', $paginatedSelf['items'][0]['display_name']);
        $this->assertTrue($paginatedSelf['items'][0]['is_current_user']);
    }

    private function createPaidOrder(string $orderNumber, Customer $customer, bool $isExpress, string $status): OrderOrder
    {
        return OrderOrder::create([
            'order_number' => $orderNumber,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => $status,
            'payment_status' => 'paid',
            'is_express' => $isExpress,
            'subtotal_price' => 4990,
            'tax_amount' => 797,
            'shipping_price' => 0,
            'total_price' => 4990,
            'billing_address' => [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'address' => 'Geheimweg 12',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'street' => 'Geheimweg 12',
                'zip' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ]);
    }
}
