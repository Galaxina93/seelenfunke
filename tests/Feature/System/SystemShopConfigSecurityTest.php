<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemShopConfig;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SystemShopConfigSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_system_shop_config(): void
    {
        Livewire::test(SystemShopConfig::class)->assertStatus(403);
    }

    public function test_employees_and_customers_cannot_mount_system_shop_config(): void
    {
        $customer = Customer::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Emma',
            'last_name' => 'Mitarbeiterin',
            'email' => 'emma-sys@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($customer, 'customer')
            ->test(SystemShopConfig::class)
            ->assertStatus(403);

        Livewire::actingAs($employee, 'employee')
            ->test(SystemShopConfig::class)
            ->assertStatus(403);
    }

    public function test_admin_can_mount_system_shop_config(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->assertStatus(200);
    }

    public function test_delete_report_blocks_path_traversal(): void
    {
        Storage::fake('local');
        $admin = Admin::factory()->create();

        // Create a sensitive file outside the updates directory
        Storage::disk('local')->put('sensitive/secrets.txt', 'SUPER_SECRET_DATA');

        Livewire::actingAs($admin, 'admin')
            ->test(SystemShopConfig::class)
            ->call('deleteReport', '../../sensitive/secrets.txt');

        // File must NOT be deleted!
        Storage::disk('local')->assertExists('sensitive/secrets.txt');
    }
}
