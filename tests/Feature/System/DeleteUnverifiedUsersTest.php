<?php

namespace Tests\Feature\System;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Order\OrderOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeleteUnverifiedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_and_employees_are_never_deleted_by_unverified_cronjob(): void
    {
        $admin = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
        DB::table('admins')->where('id', $admin->id)->update(['created_at' => now()->subDays(5)]);

        $employee = Employee::create([
            'first_name' => 'Mitarbeiter',
            'last_name' => 'Eins',
            'email' => 'employee@seelenfunke.de',
            'password' => bcrypt('secret123'),
        ]);
        DB::table('employees')->where('id', $employee->id)->update(['created_at' => now()->subDays(5)]);

        $this->artisan('system:delete-unverified-users')
            ->assertExitCode(0);

        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
    }

    public function test_customer_with_orders_is_never_deleted(): void
    {
        $customer = Customer::create([
            'first_name' => 'Buyer',
            'last_name' => 'Customer',
            'email' => 'buyer@example.com',
            'password' => bcrypt('secret123'),
        ]);
        DB::table('customers')->where('id', $customer->id)->update(['created_at' => now()->subDays(3)]);

        OrderOrder::create([
            'order_number' => 'ORD-PROTECTED-001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'subtotal_price' => 2000,
            'tax_amount' => 380,
            'total_price' => 2000,
            'billing_address' => ['name' => 'Buyer Customer'],
            'shipping_address' => ['name' => 'Buyer Customer'],
        ]);

        $this->artisan('system:delete-unverified-users')
            ->assertExitCode(0);

        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_unverified_customer_without_orders_older_than_24_hours_is_deleted(): void
    {
        $customer = Customer::create([
            'first_name' => 'Spam',
            'last_name' => 'Bot',
            'email' => 'spam@example.com',
            'password' => bcrypt('secret123'),
        ]);
        DB::table('customers')->where('id', $customer->id)->update(['created_at' => now()->subHours(25)]);

        $this->artisan('system:delete-unverified-users')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('customers', ['id' => $customer->id]);
    }
}
