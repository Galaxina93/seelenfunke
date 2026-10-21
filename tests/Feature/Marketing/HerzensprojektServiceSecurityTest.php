<?php

namespace Tests\Feature\Marketing;

use App\Models\Marketing\MarketingHerzensprojektAction;
use App\Models\Marketing\MarketingHerzensprojektTransaction;
use App\Models\Order\OrderOrder;
use App\Services\HerzensprojektService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HerzensprojektServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_donation_is_idempotent()
    {
        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-HERZ-001',
            'email' => 'herz@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Anna'],
            'subtotal_price' => 3000,
            'tax_amount' => 570,
            'shipping_price' => 490,
            'total_price' => 3490,
        ]);

        $tx1 = HerzensprojektService::recordDonationForOrder($order, 200);
        $this->assertNotNull($tx1);
        $this->assertEquals(200, $tx1->amount_cents);

        // Attempt second booking
        $tx2 = HerzensprojektService::recordDonationForOrder($order, 200);
        $this->assertEquals($tx1->id, $tx2->id);

        $this->assertEquals(1, MarketingHerzensprojektTransaction::where('order_id', $order->id)->count());
    }

    public function test_reverse_donation_is_robust_against_different_reasons()
    {
        $order = OrderOrder::create([
            'id' => Str::uuid(),
            'order_number' => 'ORD-HERZ-002',
            'email' => 'herz2@example.com',
            'status' => 'completed',
            'payment_status' => 'paid',
            'billing_address' => ['first_name' => 'Ben'],
            'subtotal_price' => 4000,
            'tax_amount' => 760,
            'shipping_price' => 490,
            'total_price' => 4490,
        ]);

        HerzensprojektService::recordDonationForOrder($order, 250);

        // Reverse with reason "Kundenreklamation"
        $rev1 = HerzensprojektService::reverseDonationForOrder($order, 'Kundenreklamation');
        $this->assertNotNull($rev1);
        $this->assertEquals(-250, $rev1->amount_cents);

        // Second reverse attempt with different reason "Storno" should be blocked
        $rev2 = HerzensprojektService::reverseDonationForOrder($order, 'Storno');
        $this->assertNull($rev2);

        $this->assertEquals(2, MarketingHerzensprojektTransaction::where('order_id', $order->id)->count());
    }

    public function test_get_active_action_and_stats()
    {
        MarketingHerzensprojektAction::create([
            'title' => 'Alte Aktion',
            'target_recipient' => 'Kinderhospiz',
            'description' => 'Bereits abgeschlossen',
            'allocated_amount_cents' => 50000,
            'status' => 'completed',
        ]);

        $activeAction = MarketingHerzensprojektAction::create([
            'title' => 'Laufende Aktion Baumspende',
            'target_recipient' => 'Bergwaldprojekt e.V.',
            'description' => 'Wir pflanzen Bäume',
            'allocated_amount_cents' => 100000,
            'status' => 'in_progress',
        ]);

        $found = HerzensprojektService::getActiveAction();
        $this->assertNotNull($found);
        $this->assertEquals($activeAction->id, $found->id);

        $stats = HerzensprojektService::getDashboardStats();
        $this->assertEquals(2, $stats['actions_count']);
        $this->assertEquals(1, $stats['completed_actions_count']);
    }
}
