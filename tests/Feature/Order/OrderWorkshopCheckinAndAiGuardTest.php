<?php

namespace Tests\Feature\Order;

use App\Livewire\Shop\Order\OrderOverview;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Order\OrderShipment;
use App\Models\Product\Product;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiOrderFuncs;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\Order\OrderProductionBatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class OrderWorkshopCheckinAndAiGuardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Customer $customer;
    private Product $laserProduct;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->admin = Admin::create([
            'id' => Str::uuid()->toString(),
            'email' => 'alina@mein-seelenfunke.de',
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'password' => bcrypt('password'),
        ]);

        $this->customer = Customer::create([
            'id' => Str::uuid()->toString(),
            'email' => 'geheim.kunde@example.de',
            'first_name' => 'Sabine',
            'last_name' => 'KundenGeheimname',
            'password' => bcrypt('password'),
        ]);

        $this->laserProduct = Product::create([
            'id' => Str::uuid()->toString(),
            'sku' => 'SLATE-HEART-001',
            'name' => 'Schieferplatte Herz',
            'slug' => 'schieferplatte-herz-' . Str::random(5),
            'description' => 'Handveredelt in Deutschland (Manufaktur Gifhorn).',
            'price' => 2990,
            'status' => 'active',
            'type' => 'physical',
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 25,
            'weight' => 380,
        ]);
    }

    private function createOrderWithItem(string $orderNumber, array $overrides = []): OrderOrder
    {
        $order = OrderOrder::create(array_merge([
            'id' => Str::uuid()->toString(),
            'order_number' => $orderNumber,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => [
                'first_name' => 'Sabine',
                'last_name' => 'KundenGeheimname',
                'address' => 'Geheimweg 99',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ], $overrides));

        if (!empty($overrides['created_at'])) {
            $order->created_at = $overrides['created_at'];
            $order->save();
        }

        OrderOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->laserProduct->id,
            'product_name' => $this->laserProduct->name,
            'quantity' => 1,
            'completed_quantity' => 0,
            'is_completed' => false,
            'unit_price' => 2990,
            'total_price' => 2990,
            'configuration' => [
                'texts' => [['text' => 'Für Oma']],
            ],
        ]);

        return $order->fresh(['items.product.supplier', 'shipments']);
    }

    public function test_manual_order_overview_saves_pauses_and_clears_workshop_stand_without_ai(): void
    {
        $this->actingAs($this->admin, 'admin');
        $order = $this->createOrderWithItem('ORD-MAN-100', ['status' => 'pending']);

        $component = Livewire::test(OrderOverview::class)
            ->call('openDetail', $order->id)
            ->assertSee('Werkstatt-Stand')
            ->set('workshopNote', 'Vorderseite gelasert, Rückseite läuft gerade')
            ->set('workshopFollowupMode', '2h')
            ->call('saveWorkshopCheckin');

        $fresh = $order->fresh();
        $this->assertSame('Vorderseite gelasert, Rückseite läuft gerade', $fresh->workshop_note);
        $this->assertNotNull($fresh->workshop_note_updated_at);
        $this->assertNotNull($fresh->workshop_next_check_at);
        $this->assertFalse($fresh->workshop_checkin_paused);

        // Status MUST remain untouched ('pending') until Alina manually decides to change it!
        $this->assertSame('pending', $fresh->status);

        // Manual UI also surfaces the helpful status discrepancy suggestion without forcing it
        $component->assertSee('Auf „In Bearbeitung“ setzen');

        // Pause check-in manually
        $component->set('workshopFollowupMode', 'pause')
            ->call('saveWorkshopCheckin');

        $paused = $order->fresh();
        $this->assertTrue($paused->workshop_checkin_paused);
        $this->assertNull($paused->workshop_next_check_at);

        // Clear workshop stand manually
        $component->call('clearWorkshopCheckin');
        $cleared = $order->fresh();
        $this->assertNull($cleared->workshop_note);
        $this->assertNull($cleared->workshop_note_updated_at);
        $this->assertNull($cleared->workshop_next_check_at);
        $this->assertFalse($cleared->workshop_checkin_paused);

        // Customer formatted array MUST NEVER expose internal workshop_note
        $formatted = $fresh->toFormattedArray();
        $this->assertArrayNotHasKey('workshop_note', $formatted);
        $this->assertStringNotContainsString('Vorderseite gelasert', json_encode($formatted));
    }

    public function test_two_hour_checkin_schedule_friday_to_monday_rollover_and_shipped_discrepancy(): void
    {
        $service = app(OrderProductionBatchService::class);
        $fridayAfternoon = Carbon::create(2026, 11, 13, 15, 0, 0, 'Europe/Berlin'); // Friday

        // 1. Friday 'tomorrow' / waiting_customer MUST land on Monday 09:30 (skipping Saturday & Sunday)
        $nextWorkday = OrderProductionBatchService::resolveNextWorkshopCheckinAt('tomorrow', $fridayAfternoon);
        $mondayBerlin = $nextWorkday['next_check_at']->copy()->timezone('Europe/Berlin');
        $this->assertTrue($mondayBerlin->isMonday());
        $this->assertSame('09:30', $mondayBerlin->format('H:i'));

        // 2. Newly open order >= 30 min old triggers initial check-in
        $order = $this->createOrderWithItem('ORD-CHK-200', [
            'status' => 'processing',
            'created_at' => $fridayAfternoon->copy()->subMinutes(45),
        ]);

        $snapInitial = $service->buildWorkshopCheckinSnapshot($order, $fridayAfternoon);
        $this->assertTrue($snapInitial['is_checkin_due']);
        $this->assertSame('initial_open', $snapInitial['checkin_reason']);

        // 3. Save 2-hour progress -> NOT due after 60 min, DUE after 121 min
        $sched2h = OrderProductionBatchService::resolveNextWorkshopCheckinAt('2h', $fridayAfternoon);
        $order->update([
            'workshop_note' => 'Erster Durchgang fertig',
            'workshop_note_updated_at' => $fridayAfternoon,
            'workshop_next_check_at' => $sched2h['next_check_at'],
            'workshop_checkin_paused' => false,
        ]);

        $snapAfter60m = $service->buildWorkshopCheckinSnapshot($order->fresh(), $fridayAfternoon->copy()->addMinutes(60));
        $this->assertFalse($snapAfter60m['is_checkin_due']);

        $snapAfter121m = $service->buildWorkshopCheckinSnapshot($order->fresh(), $fridayAfternoon->copy()->addMinutes(121));
        $this->assertTrue($snapAfter121m['is_checkin_due']);
        $this->assertSame('followup_due', $snapAfter121m['checkin_reason']);

        // 4. All items completed + DHL label present while status is still 'processing' -> asks Alina about 'shipped'
        $order->items()->update(['is_completed' => true, 'completed_quantity' => 1]);
        OrderShipment::create([
            'order_id' => $order->id,
            'tracking_number' => '00340434161000001234',
            'carrier' => 'DHL',
            'shipping_label_path' => 'dhl_labels/test_label.pdf',
        ]);

        $snapReadyShip = $service->buildWorkshopCheckinSnapshot($order->fresh(['items.product.supplier', 'shipments']), $fridayAfternoon->copy()->addMinutes(125));
        $this->assertTrue($snapReadyShip['has_discrepancy']);
        $this->assertSame('ready_for_shipped', $snapReadyShip['discrepancy_code']);
        $this->assertSame('shipped', $snapReadyShip['suggested_status']);
        $this->assertStringContainsString('Versendet', (string) $snapReadyShip['ask_alina_prompt']);
    }

    public function test_ai_mcp_tools_never_change_status_without_explicit_alina_confirmation(): void
    {
        $this->actingAs($this->admin, 'admin');
        $order = $this->createOrderWithItem('ORD-AI-300', ['status' => 'pending']);

        // 1. Funkira skill pool equips order_workshop_checkin automatically on workshop keywords
        $pools = FunkiraSkillPoolService::detectPoolsForRequest('Wie ist der aktuelle Werkstatt-Stand der Bestellung?');
        $this->assertContains('orders', $pools);
        $activeTools = FunkiraSkillPoolService::getActiveToolIdentifiers($pools);
        $this->assertContains('order_workshop_checkin', $activeTools);

        // 2. Funkira records Alina's spoken stand via order_workshop_checkin -> status MUST stay 'pending', discrepancy prompt returned!
        $saveRes = AIFunctionsRegistry::execute('order_workshop_checkin', [
            'action' => 'save_progress',
            'workshop_note' => 'Ich lasere gerade die Vorderseite am xTool',
            'followup_mode' => '2h',
        ]);

        $this->assertSame('success', $saveRes['status']);
        $this->assertFalse($saveRes['order_status_changed']);
        $this->assertTrue($saveRes['requires_alina_status_question']);
        $this->assertSame('processing', $saveRes['checkin']['suggested_status']);
        $this->assertStringContainsString('In Bearbeitung', $saveRes['instruction_for_funkira']);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('Ich lasere gerade die Vorderseite am xTool', $order->fresh()->workshop_note);

        // 3. Attempting order_update_status WITHOUT confirmed_by_alina=true is strictly blocked!
        $blockedFalse = AIFunctionsRegistry::execute('order_update_status', [
            'order_number' => 'ORD-AI-300',
            'status' => 'processing',
            'confirmed_by_alina' => false,
        ]);
        $this->assertSame('confirmation_required', $blockedFalse['status']);
        $this->assertFalse($blockedFalse['changed']);
        $this->assertSame('pending', $order->fresh()->status);

        $blockedMissing = AiOrderFuncs::executeOrderUpdateStatus([
            'order_number' => 'ORD-AI-300',
            'status' => 'processing',
        ]);
        $this->assertSame('confirmation_required', $blockedMissing['status']);
        $this->assertSame('pending', $order->fresh()->status);

        // 4. ONLY after Alina explicitly confirms (confirmed_by_alina=true) does the status change!
        $confirmed = AIFunctionsRegistry::execute('order_update_status', [
            'order_number' => 'ORD-AI-300',
            'status' => 'processing',
            'confirmed_by_alina' => true,
        ]);
        $this->assertSame('success', $confirmed['status']);
        $this->assertSame('processing', $order->fresh()->status);

        // 5. External customer MUST NEVER be able to call order_workshop_checkin
        auth('admin')->logout();
        $this->actingAs($this->customer, 'customer');
        $denied = AiOrderFuncs::executeOrderWorkshopCheckin([
            'action' => 'get_priority_checkin',
        ]);
        $this->assertSame('error', $denied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $denied['message']);
    }

    public function test_monitor_pulse_exposes_zero_pii_priority_order_checkin(): void
    {
        $order = $this->createOrderWithItem('ORD-PULSE-400', [
            'status' => 'pending',
            'workshop_note' => 'Rohling vorbereitet am Laser',
            'workshop_note_updated_at' => now()->subHours(3),
            'workshop_next_check_at' => now()->subMinutes(10),
        ]);

        $token = $this->admin->createToken('minipc-pulse-test', ['monitor:pulse'])->plainTextToken;

        $resp = $this->withToken($token)->getJson('/api/shop/monitor-pulse');
        $resp->assertOk();

        $poc = $resp->json('priority_order_checkin');
        $this->assertIsArray($poc);
        $this->assertSame('ORD-PULSE-400', $poc['order_number']);
        $this->assertSame('Rohling vorbereitet am Laser', $poc['workshop_note']);
        $this->assertTrue($poc['is_checkin_due']);
        $this->assertTrue($poc['has_discrepancy']);
        $this->assertSame('processing', $poc['suggested_status']);

        // Zero-PII verification: Customer last name, email, and street address MUST NOT appear in priority_order_checkin
        $rawPocJson = json_encode($poc);
        $this->assertStringNotContainsString('KundenGeheimname', $rawPocJson);
        $this->assertStringNotContainsString('geheim.kunde@example.de', $rawPocJson);
        $this->assertStringNotContainsString('Geheimweg', $rawPocJson);
        $this->assertNotEmpty($order->id);
    }
}
