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
        // Hero 1: Opt-in with player_name (Level 10 -> Rank S)
        $heroOneCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Maximilian',
            'last_name' => 'StrengGeheimerNachname Eins',
            'email' => 'streng.geheim.eins@private-mail.de',
            'password' => bcrypt('secret1234'),
        ]);
        CustomerGamification::create([
            'customer_id' => $heroOneCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'use_real_name' => false,
            'player_name' => 'Sternenwanderer',
            'level' => 10,
            'funken_total_earned' => 5000,
            'galactic_conquest_points' => 900,
        ]);

        // Hero 2 (Logged-in customer): Opt-in with use_real_name = true -> ONLY "Clara S.", NEVER full last name!
        $meCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Clara',
            'last_name' => 'SuperGeheimNachname',
            'email' => 'clara.privat@geheim.de',
            'password' => bcrypt('secret1234'),
        ]);
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

        // Customer 3: ranking_opt_in = false -> MUST NEVER appear!
        $optOutCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Versteckt',
            'last_name' => 'Unsichtbar',
            'email' => 'unsichtbar@example.de',
            'password' => bcrypt('secret1234'),
        ]);
        CustomerGamification::create([
            'customer_id' => $optOutCustomer->id,
            'is_active' => true,
            'ranking_opt_in' => false,
            'use_real_name' => false,
            'player_name' => 'HiddenShadow99',
            'level' => 9,
            'funken_total_earned' => 4000,
        ]);

        // 1. Public guest request works on BOTH routes and exposes 0 PII
        $guestResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $guestResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('founding_era', true)
            ->assertJsonPath('total_heroes', 2)
            ->assertJsonPath('customer_logged_in', false)
            ->assertJsonPath('my_hero_slot', null);

        $this->getJson('/api/gamification/funkenwerk-heroes')
            ->assertOk()
            ->assertJsonPath('total_heroes', 2);

        $rawGuestBody = $guestResp->getContent();
        $this->assertStringNotContainsString('streng.geheim.eins@private-mail.de', $rawGuestBody);
        $this->assertStringNotContainsString('clara.privat@geheim.de', $rawGuestBody);
        $this->assertStringNotContainsString('StrengGeheimerNachname', $rawGuestBody);
        $this->assertStringNotContainsString('SuperGeheimNachname', $rawGuestBody);
        $this->assertStringNotContainsString('HiddenShadow99', $rawGuestBody);
        $this->assertStringNotContainsString((string) $heroOneCustomer->id, $rawGuestBody);
        $this->assertStringNotContainsString((string) $meCustomer->id, $rawGuestBody);

        // 2. Logged-in customer receives is_me = true, my_hero_slot = 2, level_models (1..10) & model_url
        $this->actingAs($meCustomer, 'customer');
        $authResp = $this->getJson('/funkenwerk-welt/heroes-data');
        $authResp->assertOk()
            ->assertJsonPath('customer_logged_in', true)
            ->assertJsonPath('customer_opt_in', true)
            ->assertJsonPath('my_hero_slot', 2)
            ->assertJsonPath('level_models.1', '/shop/customer/gamification/models/funki_lvl_1_rags.glb')
            ->assertJsonPath('level_models.10', '/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb')
            ->assertJsonPath('heroes.0.display_name', 'Sternenwanderer')
            ->assertJsonPath('heroes.0.rank_class', 'S')
            ->assertJsonPath('heroes.0.is_me', false)
            ->assertJsonPath('heroes.1.display_name', 'Clara S.')
            ->assertJsonPath('heroes.1.rank_class', 'A')
            ->assertJsonPath('heroes.1.is_me', true);

        $authJson = $authResp->json();
        $this->assertCount(10, $authJson['level_models']);
        $this->assertStringEndsWith('/shop/customer/gamification/models/funki_lvl_10_god_of_soul.glb', (string) $authJson['heroes'][0]['model_url']);
        $this->assertStringEndsWith('/shop/customer/gamification/models/funki_lvl_6_craftsman.glb', (string) $authJson['heroes'][1]['model_url']);

        // 3. Verify 3D User Lobby loader & engine adhere to Clean-Design, 1:1 Pantheon Hall of Heroes & Multiplayer Instances (0 Water City!)
        $loaderJs = (string) file_get_contents(public_path('js/funkenwerk-heroes-3d.js'));
        $engineJs = (string) file_get_contents(public_path('js/seelenfunke-louvre-showroom-3d.js'));

        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $loaderJs);
        $this->assertStringNotContainsString(' ' . "\u{2014}" . ' ', $loaderJs);
        $this->assertStringNotContainsString('https://', $loaderJs);
        $this->assertStringNotContainsString('_buildWaterMetropolis', $loaderJs);
        $this->assertStringNotContainsString('startOrbitalFlyIn', $loaderJs);
        $this->assertStringContainsString('/js/seelenfunke-louvre-showroom-3d.js?v=20261116v3', $loaderJs);
        $this->assertStringContainsString('sf-lobby-instance-btn', $loaderJs);
        $this->assertStringContainsString('sf-lobby-instance-menu', $loaderJs);
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

        // 4. Verify "3D-Lobby öffnen" / "Halle der Helden 3D" buttons render in Customer Dashboard & Funki page (0 internal Funkenwerk references!), and remain REMOVED from Checkout Success
        $dashTest = Livewire::actingAs($meCustomer, 'customer')
            ->test(CustomerDashboardComponent::class)
            ->assertSee('3D-Lobby öffnen')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertSeeHtml('data-testid="dashboard-open-user-lobby-3d"')
            ->assertSeeHtml('funkenwerk-heroes-3d.js')
            ->assertSeeHtml("open('lobby')")
            ->assertSeeHtml("open('hall')");

        $dashHtml = (string) $dashTest->html();
        $visibleDashHtml = explode('<template x-teleport="body">', $dashHtml)[0] ?? '';
        $this->assertStringContainsString('data-testid="dashboard-open-user-lobby-3d"', $visibleDashHtml);
        $this->assertStringContainsString('3D-Lobby öffnen', $visibleDashHtml);

        $this->actingAs($meCustomer, 'customer')
            ->get('/customer/dashboard')
            ->assertOk()
            ->assertSee('3D-Lobby öffnen')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertSee('data-testid="dashboard-open-user-lobby-3d"', false);

        Livewire::actingAs($meCustomer, 'customer')
            ->test(CustomerFunkiComponent::class)
            ->assertSee('Halle der Helden 3D')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertSeeHtml('data-testid="funki-open-hall-of-heroes-3d"')
            ->assertSeeHtml("open('hall')");

        $this->createPaidOrder('ORD-SUCC-CLEAN-01', $meCustomer, false, 'pending');

        Livewire::actingAs($meCustomer, 'customer')
            ->test(OrderCheckoutSuccess::class)
            ->assertSee('ORD-SUCC-CLEAN-01')
            ->assertSee('Zur Bestellung')
            ->assertSee('Weiter einkaufen')
            ->assertDontSee('3D-Lobby öffnen')
            ->assertDontSee('Halle der Helden 3D')
            ->assertDontSee('Funkenwerk 3D öffnen')
            ->assertDontSeeHtml('funkenwerk-heroes-3d.js')
            ->assertDontSeeHtml('SeelenfunkeHeroes3D');

        $this->actingAs($meCustomer, 'customer')
            ->get('/checkout/success')
            ->assertOk()
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
