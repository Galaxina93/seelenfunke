<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiAnalytics;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiAnalyticsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_ai_analytics(): void
    {
        Livewire::test(AiAnalytics::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_ai_analytics(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(AiAnalytics::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_ai_analytics(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Metrics',
            'last_name' => 'Agent',
            'email' => 'employee.metrics@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAnalytics::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(AiAnalytics::class)
            ->assertStatus(200);
    }
}
