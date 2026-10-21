<?php

namespace Tests\Feature\Master;

use App\Livewire\Shop\Master\MasterShopCapacity;
use App\Livewire\Shop\Master\MasterStorageCapacity;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterCapacityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_mount_master_shop_capacity(): void
    {
        Livewire::test(MasterShopCapacity::class)
            ->assertStatus(403);
    }

    public function test_customer_cannot_mount_master_shop_capacity(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(MasterShopCapacity::class)
            ->assertStatus(403);
    }

    public function test_guest_cannot_mount_master_storage_capacity(): void
    {
        Livewire::test(MasterStorageCapacity::class)
            ->assertStatus(403);
    }

    public function test_customer_cannot_mount_master_storage_capacity(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(MasterStorageCapacity::class)
            ->assertStatus(403);
    }

    public function test_admin_can_mount_master_shop_and_storage_capacity(): void
    {
        $admin = Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(MasterShopCapacity::class)
            ->assertStatus(200);

        Livewire::actingAs($admin, 'admin')
            ->test(MasterStorageCapacity::class)
            ->assertStatus(200);
    }
}
