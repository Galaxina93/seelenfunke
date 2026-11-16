<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderOrderItem;
use App\Models\Product\Product;
use App\Models\Product\ProductSupplier;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AiPromptService;
use App\Services\AI\Functions\AiMasterFuncs;
use App\Services\AI\Functions\AiOrderFuncs;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\AI\MasterNextBestActionService;
use Carbon\Carbon;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MasterNextBestActionTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina.kompass@mein-seelenfunke.de',
            'password' => bcrypt('secret1234'),
        ]);

        $this->customer = Customer::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Klara',
            'last_name' => 'Kundin',
            'email' => 'klara.kundin@example.de',
            'password' => bcrypt('secret1234'),
        ]);
    }

    public function test_core_pool_exposes_next_best_actions_to_funkira_only_and_blocks_customers(): void
    {
        $this->seed(AiAgentSeeder::class);

        $coreTools = FunkiraSkillPoolService::getCoreToolIdentifiers();
        $this->assertContains('master_get_next_best_actions', $coreTools);

        // Customer MUST NEVER execute master_get_next_best_actions
        $this->actingAs($this->customer, 'customer');
        $denied = AiMasterFuncs::executeGetNextBestActions([]);
        $this->assertSame('error', $denied['status']);
        $this->assertStringContainsString('Zugriff verweigert', $denied['message']);

        // Switch to Admin: Funki external support bot MUST STILL NEVER execute master_get_next_best_actions (Air-Gap Sandbox)
        auth('customer')->logout();
        $this->actingAs($this->admin, 'admin');
        $funki = AiAgent::where('name', 'Funki')->firstOrFail();
        $funkiSandbox = AIFunctionsRegistry::execute('master_get_next_best_actions', [], $funki);
        $this->assertTrue($funkiSandbox['error'] ?? false);
        $this->assertStringContainsString('SANDBOX VIOLATION', $funkiSandbox['message'] ?? '');

        // Funkira prompt enforces strict ON-DEMAND usage & warm-cool tactical brevity (1-3 short sentences)
        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $prompt = AiPromptService::getRichPrompt($funkira);
        $this->assertStringContainsString('master_get_next_best_actions', $prompt);
        $this->assertStringContainsString('STRIKT NUR AUF NACHFRAGE', $prompt);
        $this->assertStringContainsString('TAKTISCH-KNACKIGER, HERZLICH-COOLER SPRACHSTIL', $prompt);
        $this->assertStringContainsString('1 bis maximal 3 kurze Sätze', $prompt);
    }

    public function test_urgent_tasks_and_compass_sort_priority_hoch_before_mittel_before_niedrig(): void
    {
        $this->actingAs($this->admin, 'admin');

        $list = ManagementTaskList::create([
            'name' => 'Manufaktur Fokus',
            'color' => '#C5A059',
            'icon' => 'star',
        ]);

        ManagementTask::create([
            'task_list_id' => $list->id,
            'title' => 'Niedrige Fleißaufgabe',
            'priority' => 'niedrig',
            'is_completed' => false,
            'is_archived' => false,
        ]);

        ManagementTask::create([
            'task_list_id' => $list->id,
            'title' => 'Mittlere Aufgabe',
            'priority' => 'mittel',
            'is_completed' => false,
            'is_archived' => false,
        ]);

        ManagementTask::create([
            'task_list_id' => $list->id,
            'title' => 'Wichtige Prio-1 Aufgabe',
            'priority' => 'hoch',
            'is_completed' => false,
            'is_archived' => false,
        ]);

        // 1. Fixed bug in AiOrderFuncs::executeGetUrgentTasks
        $urgentRes = AiOrderFuncs::executeGetUrgentTasks(['limit' => 3]);
        $this->assertSame('success', $urgentRes['status']);
        $this->assertSame('Wichtige Prio-1 Aufgabe', $urgentRes['tasks'][0]['title']);
        $this->assertSame('Mittlere Aufgabe', $urgentRes['tasks'][1]['title']);
        $this->assertSame('Niedrige Fleißaufgabe', $urgentRes['tasks'][2]['title']);

        // 2. Same priority order in MasterNextBestActionService
        $compass = app(MasterNextBestActionService::class)->evaluate(null, Carbon::parse('2026-11-17 10:45:00', 'Europe/Berlin'));
        $this->assertSame('success', $compass['status']);
        $this->assertSame('on_demand_only', $compass['mode']);
        $this->assertSame('Wichtige Prio-1 Aufgabe', $compass['top_open_tasks'][0]['title']);
        $this->assertSame('Mittlere Aufgabe', $compass['top_open_tasks'][1]['title']);
        $this->assertSame('Niedrige Fleißaufgabe', $compass['top_open_tasks'][2]['title']);
    }

    public function test_workshop_objections_detect_supplier_lead_time_and_batch_bundling(): void
    {
        $this->actingAs($this->admin, 'admin');

        $supplier = ProductSupplier::create([
            'name' => 'Glücksfieber',
        ]);

        $outOfStockProd = Product::create([
            'name' => 'Vergoldeter Herz-Anhänger',
            'slug' => 'vergoldeter-herz-anhaenger-' . Str::lower(Str::random(5)),
            'sku' => 'SKU-LEAD-1',
            'type' => 'physical',
            'status' => 'active',
            'price' => 3990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 0,
            'continue_selling_when_out_of_stock' => true,
            'product_supplier_id' => $supplier->id,
        ]);

        $rotaryGlass = Product::create([
            'name' => 'Weizenglas Seelenfunke',
            'slug' => 'weizenglas-seelenfunke-' . Str::lower(Str::random(5)),
            'sku' => 'GLASS-RA3-1',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2990,
            'is_personalizable' => true,
            'track_quantity' => true,
            'quantity' => 10,
        ]);

        // Order 1: Needs supplier procurement (60s lead-time trigger)
        $ordSupplier = $this->makePaidOrder('ORD-SUP-101');
        OrderOrderItem::create([
            'order_id' => $ordSupplier->id,
            'product_id' => $outOfStockProd->id,
            'product_name' => $outOfStockProd->name,
            'quantity' => 1,
            'completed_quantity' => 0,
            'is_completed' => false,
            'configuration' => [
                'supplier_procurement' => [
                    'required' => true,
                    'ordered_from_supplier' => false,
                    'missing_quantity' => 1,
                    'supplier_name' => 'Glücksfieber',
                ],
            ],
            'unit_price' => 3990,
            'total_price' => 3990,
        ]);

        // Orders 2 & 3: Two identical RA3 rotary glasses -> should trigger Ruestzeit-Hebel bundling tip!
        foreach (['ORD-RA3-201', 'ORD-RA3-202'] as $num) {
            $o = $this->makePaidOrder($num);
            OrderOrderItem::create([
                'order_id' => $o->id,
                'product_id' => $rotaryGlass->id,
                'product_name' => $rotaryGlass->name,
                'quantity' => 1,
                'completed_quantity' => 0,
                'is_completed' => false,
                'configuration' => ['text' => 'Gravur'],
                'unit_price' => 2990,
                'total_price' => 2990,
            ]);
        }

        $res = app(MasterNextBestActionService::class)->evaluate(null, Carbon::parse('2026-11-17 10:50:00', 'Europe/Berlin'));
        $tipsJoined = implode(' | ', $res['smart_objections_and_tips']);

        $this->assertStringContainsString('Vorlauf-Einwand', $tipsJoined);
        $this->assertStringContainsString('1× Vergoldeter Herz-Anhänger', $tipsJoined);
        $this->assertStringNotContainsString('#ORD-SUP-101', $tipsJoined);
        $this->assertStringContainsString('Rüstzeit-Hebel', $tipsJoined);
        $this->assertStringContainsString('Weizenglas Seelenfunke', $tipsJoined);
    }

    public function test_short_time_window_before_calendar_appointment_pivots_to_quick_win_and_raises_objection(): void
    {
        $this->actingAs($this->admin, 'admin');

        $now = Carbon::parse('2026-11-17 11:12:00', 'Europe/Berlin');

        // Appointment in 18 minutes (11:30)
        ManagementCalendarEvent::create([
            'title' => 'Steuerberater Rücksprache',
            'start_date' => Carbon::parse('2026-11-17 11:30:00', 'Europe/Berlin'),
            'end_date' => Carbon::parse('2026-11-17 12:00:00', 'Europe/Berlin'),
            'is_all_day' => false,
            'send_email' => false,
        ]);

        $list = ManagementTaskList::create([
            'name' => 'Projekte',
            'color' => '#C5A059',
            'icon' => 'star',
        ]);

        // Big 45-minute task (4 open subtasks)
        $bigTask = ManagementTask::create([
            'task_list_id' => $list->id,
            'title' => 'Großer Konfigurator-Umbau mit 4 Modulen',
            'priority' => 'hoch',
            'is_completed' => false,
            'is_archived' => false,
        ]);
        for ($i = 1; $i <= 4; $i++) {
            ManagementTask::create([
                'parent_id' => $bigTask->id,
                'task_list_id' => $list->id,
                'title' => "Teilmodul {$i}",
                'priority' => 'mittel',
                'is_completed' => false,
                'is_archived' => false,
            ]);
        }

        // Quick-win (< 15m): Pending product review
        $prod = Product::create([
            'name' => 'Schieferherz',
            'slug' => 'schieferherz-' . Str::lower(Str::random(5)),
            'sku' => 'SKU-REV-1',
            'type' => 'physical',
            'status' => 'active',
            'price' => 2490,
            'track_quantity' => false,
            'quantity' => 5,
        ]);
        \App\Models\Product\ProductReview::create([
            'product_id' => $prod->id,
            'customer_id' => $this->customer->id,
            'rating' => 5,
            'title' => 'Wunderschön',
            'content' => 'Tolle Gravur!',
            'status' => 'pending',
        ]);

        $res = app(MasterNextBestActionService::class)->evaluate(null, $now);

        $tipsJoined = implode(' | ', $res['smart_objections_and_tips']);
        $this->assertStringContainsString('Zeitfenster-Einwand', $tipsJoined);
        $this->assertStringContainsString('Steuerberater Rücksprache', $tipsJoined);

        // Focus pivoted away from 45m task to a quick win (<= 15m)!
        $this->assertLessThanOrEqual(15, $res['recommended_focus_now']['estimated_minutes']);
        $this->assertStringContainsString('Bewertungen', $res['recommended_focus_now']['title']);
    }

    public function test_calm_day_surfaces_14_day_seasonal_foresight_when_no_active_voucher_exists(): void
    {
        $this->actingAs($this->admin, 'admin');

        // 14.11.2026 -> 10 days before Advent start (24.11.2026), 0 open orders, 0 active vouchers
        $now = Carbon::parse('2026-11-14 11:00:00', 'Europe/Berlin');
        $res = app(MasterNextBestActionService::class)->evaluate(null, $now);

        $tipsJoined = implode(' | ', $res['smart_objections_and_tips']);
        $this->assertStringContainsString('Advents-', $tipsJoined);
        $this->assertStringContainsString('Saison', $res['recommended_focus_now']['title']);
    }

    private function makePaidOrder(string $orderNumber): OrderOrder
    {
        return OrderOrder::create([
            'order_number' => $orderNumber,
            'customer_id' => $this->customer->id,
            'email' => $this->customer->email,
            'status' => 'pending',
            'payment_status' => 'paid',
            'is_express' => false,
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'shipping_price' => 0,
            'total_price' => 2990,
            'billing_address' => [
                'first_name' => 'Klara',
                'last_name' => 'Kundin',
                'address' => 'Teststr. 1',
                'postal_code' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
            'shipping_address' => [
                'first_name' => 'Klara',
                'last_name' => 'Kundin',
                'street' => 'Teststr. 1',
                'zip' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
            ],
        ]);
    }
}
