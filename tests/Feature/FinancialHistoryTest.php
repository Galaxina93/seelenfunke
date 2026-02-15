<?php

namespace Tests\Feature;

use App\Models\Order\Order;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_check_stats_calculation()
    {
        $admin = Admin::factory()->create();

        // Erstelle eine bezahlte Bestellung
        Order::factory()->create([
            'total_price' => 10000,
            'payment_status' => 'paid',
            'created_at' => now()
        ]);

        // Erstelle eine Ausgabe
        FinanceSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Material',
            'amount' => -50.00,
            'execution_date' => now(),
            'is_business' => true,
            'category' => 'Rohmaterial'
        ]);

        $this->actingAs($admin, 'admin');

        // Teste ob die Livewire-Komponente die Daten korrekt lädt
        $component = \Livewire\Livewire::test(\App\Livewire\Global\Widgets\SystemCheck::class);
        $stats = $component->get('stats');

        $this->assertGreaterThan(0, $stats['total_revenue']);
        $this->assertNotNull($stats['total_profit']);
    }
}
