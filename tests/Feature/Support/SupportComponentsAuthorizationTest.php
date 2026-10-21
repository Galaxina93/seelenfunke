<?php

namespace Tests\Feature\Support;

use App\Livewire\Shop\Support\SupportChats;
use App\Livewire\Shop\Support\SupportTicket;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupportComponentsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_support_backend_components(): void
    {
        Livewire::test(SupportTicket::class)->assertStatus(403);
        Livewire::test(SupportChats::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_support_backend_components(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')->test(SupportTicket::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(SupportChats::class)->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_support_backend_components(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'employee-support@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        // Admin
        Livewire::actingAs($admin, 'admin')->test(SupportTicket::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(SupportChats::class)->assertStatus(200);

        // Employee
        Livewire::actingAs($employee, 'employee')->test(SupportTicket::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(SupportChats::class)->assertStatus(200);
    }
}
