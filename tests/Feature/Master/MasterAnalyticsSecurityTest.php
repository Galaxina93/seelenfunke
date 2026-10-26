<?php

namespace Tests\Feature\Master;

use App\Livewire\Shop\Master\MasterAnalytics;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterAnalyticsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_master_analytics(): void
    {
        Livewire::test(MasterAnalytics::class)->assertStatus(403);
    }

    public function test_customers_and_employees_cannot_mount_master_analytics(): void
    {
        $customer = Customer::factory()->create();
        Livewire::actingAs($customer, 'customer')
            ->test(MasterAnalytics::class)
            ->assertStatus(403);

        $employee = Employee::create([
            'first_name' => 'Agent',
            'last_name' => 'Support',
            'email' => 'employee.master@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($employee, 'employee')
            ->test(MasterAnalytics::class)
            ->assertStatus(403);
    }

    public function test_admins_can_mount_master_analytics(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MasterAnalytics::class)
            ->assertStatus(200);
    }

    public function test_routine_health_check_in_master_analytics_operative_todos(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $r1 = \App\Models\Management\ManagementDayRoutine::create([
            'start_time' => '08:00',
            'title' => 'Morgen-Fokus',
            'message' => 'Mails & Shop prüfen',
            'icon' => 'sun',
            'type' => 'work',
            'duration_minutes' => 30,
            'days_of_week' => 'weekdays',
            'is_active' => true,
        ]);

        $r2 = \App\Models\Management\ManagementDayRoutine::create([
            'start_time' => '14:00',
            'title' => 'Versand-Runde',
            'message' => 'Pakete packen',
            'icon' => 'truck',
            'type' => 'work',
            'duration_minutes' => 45,
            'days_of_week' => 'weekdays',
            'is_active' => true,
        ]);

        $service = app(\App\Services\AnalyticsService::class);

        // 1. Werktag (Mittwoch) ohne heutigen Check-in -> count = 1, title = 'Routine', status = 'todo'
        //    (auch wenn gestern nichts abgehakt wurde, gibt es nur 1 Tages-To-do für heute, keine Altlast)
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-28 11:00:00')); // Mittwoch
        $checks = $service->getHealthChecks();

        $this->assertArrayHasKey('routine', $checks);
        $this->assertSame('Routine', $checks['routine']['title']);
        $this->assertSame('todo', $checks['routine']['status']);
        $this->assertSame(1, $checks['routine']['count']);

        // 2. Teil-Erledigung heute (1 von 2 geschafft, das andere heute nicht geschafft)
        //    -> gilt als heute eingecheckt (count = 0, status = 'success'), bleibt NICHT als offenes To-do stehen!
        \App\Models\Management\ManagementDayRoutineLog::create([
            'day_routine_id' => $r1->id,
            'date' => '2026-10-28',
            'is_completed' => true,
            'is_sick' => false,
            'completed_steps' => [],
            'completed_at' => now(),
        ]);

        $checksAfterPartial = $service->getHealthChecks();
        $this->assertSame(0, $checksAfterPartial['routine']['count']);
        $this->assertSame('success', $checksAfterPartial['routine']['status']);
        $this->assertStringContainsString('1/2', $checksAfterPartial['routine']['message']);

        // 3. Nächster Tag (Donnerstag): Der gestrige Tag (nur 1/2 geschafft) hängt NICHT nach,
        //    Donnerstag startet frisch mit count = 1, bis heute eingecheckt oder Krank markiert wird.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-29 09:30:00')); // Donnerstag
        $checksThursday = $service->getHealthChecks();
        $this->assertSame(1, $checksThursday['routine']['count']);

        // Krankheitstag am Donnerstag -> sofort erledigt (count = 0)
        \App\Models\Management\ManagementDayRoutineLog::create([
            'day_routine_id' => $r1->id,
            'date' => '2026-10-29',
            'is_completed' => false,
            'is_sick' => true,
            'completed_steps' => [],
        ]);
        $checksSick = $service->getHealthChecks();
        $this->assertSame(0, $checksSick['routine']['count']);
        $this->assertSame('success', $checksSick['routine']['status']);

        // 4. Wochenende (Samstag) -> automatisch erledigt / frei (count = 0)
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-31 12:00:00')); // Samstag
        $checksWeekend = $service->getHealthChecks();
        $this->assertSame(0, $checksWeekend['routine']['count']);
        $this->assertSame('success', $checksWeekend['routine']['status']);

        \Carbon\Carbon::setTestNow();
    }
}
