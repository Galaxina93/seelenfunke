<?php

namespace Tests\Feature\Product;

use App\Livewire\Shop\Product\ProductControlReviews;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductControlReviewsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_mount_product_control_reviews(): void
    {
        Livewire::test(ProductControlReviews::class)->assertStatus(403);
    }

    public function test_customers_cannot_mount_product_control_reviews(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(ProductControlReviews::class)
            ->assertStatus(403);
    }

    public function test_admins_and_employees_can_mount_product_control_reviews(): void
    {
        $admin = Admin::factory()->create();
        $employee = Employee::create([
            'first_name' => 'Review',
            'last_name' => 'Moderator',
            'email' => 'employee.mod@seelenfunke.de',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(ProductControlReviews::class)
            ->assertStatus(200);

        Livewire::actingAs($employee, 'employee')
            ->test(ProductControlReviews::class)
            ->assertStatus(200);
    }
}
