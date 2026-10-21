<?php

namespace Tests\Feature\Auth;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoogleAuthFillableTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_google_id_on_customer_creation_and_update()
    {
        $customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => bcrypt('secret123'),
            'google_id' => 'google_sub_123456',
            'email_verified_at' => now(),
        ]);

        $this->assertEquals('google_sub_123456', $customer->google_id);
        $this->assertNotNull($customer->email_verified_at);

        $customer->update(['google_id' => 'google_sub_updated']);
        $this->assertEquals('google_sub_updated', $customer->fresh()->google_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_persists_google_id_on_admin_and_employee_update()
    {
        $admin = Admin::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret123'),
            'google_id' => 'google_admin_999',
        ]);
        $this->assertEquals('google_admin_999', $admin->google_id);

        $employee = Employee::create([
            'first_name' => 'Employee',
            'last_name' => 'User',
            'email' => 'employee@example.com',
            'password' => bcrypt('secret123'),
            'google_id' => 'google_emp_888',
        ]);
        $this->assertEquals('google_emp_888', $employee->google_id);
    }
}
