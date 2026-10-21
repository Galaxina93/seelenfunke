<?php

namespace Tests\Feature\Management;

use App\Livewire\Shop\Management\ManagementCalender;
use App\Livewire\Shop\Management\ManagementContacts;
use App\Livewire\Shop\Management\ManagementEMails;
use App\Livewire\Shop\Management\ManagementRoutine;
use App\Livewire\Shop\Management\ManagementShoppingList;
use App\Livewire\Shop\Management\ManagementTask;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManagementAccessSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_management_components(): void
    {
        Livewire::test(ManagementEMails::class)->assertStatus(403);
        Livewire::test(ManagementContacts::class)->assertStatus(403);
        Livewire::test(ManagementTask::class)->assertStatus(403);
        Livewire::test(ManagementCalender::class)->assertStatus(403);
        Livewire::test(ManagementRoutine::class)->assertStatus(403);
        Livewire::test(ManagementShoppingList::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_management_components(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')->test(ManagementEMails::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ManagementContacts::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ManagementTask::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ManagementCalender::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ManagementRoutine::class)->assertStatus(403);
        Livewire::actingAs($customer, 'customer')->test(ManagementShoppingList::class)->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_management_components(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'employee@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        // Admin
        Livewire::actingAs($admin, 'admin')->test(ManagementContacts::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ManagementTask::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ManagementCalender::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ManagementRoutine::class)->assertStatus(200);
        Livewire::actingAs($admin, 'admin')->test(ManagementShoppingList::class)->assertStatus(200);

        // Employee
        Livewire::actingAs($employee, 'employee')->test(ManagementContacts::class)->assertStatus(200);
        Livewire::actingAs($employee, 'employee')->test(ManagementTask::class)->assertStatus(200);
    }
}
