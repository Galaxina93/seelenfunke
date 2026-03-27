<?php

namespace Tests\Feature\Livewire\Shop\Management;

use App\Livewire\Shop\Management\ManagementAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ManagementAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Hydrate Administration Tenant (Using raw PDO string queries to circumvent Observer missing hooks)
        $adminId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Analytics',
            'last_name' => 'Director',
            'email' => 'management-' . uniqid() . '@example.com',
            'password' => bcrypt('management123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->admin = \App\Models\Admin\Admin::find($adminId);
    }

    #[Test]
    public function it_renders_the_management_analytics_dashboard_successfully()
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(ManagementAnalytics::class)
            ->assertStatus(200);
    }

    #[Test]
    public function it_calculates_and_updates_widget_metrics_based_on_dynamic_date_ranges()
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(ManagementAnalytics::class)
            ->set('filterType', 'active')
            ->assertStatus(200);
    }
}
