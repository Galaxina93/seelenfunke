<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\AuthProfileDropdown;
use App\Models\Admin\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAccountProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function the_sole_administrator_account_cannot_be_deleted(): void
    {
        $admin = Admin::factory()->create();

        $this->assertEquals(1, Admin::count());

        Livewire::actingAs($admin, 'admin')
            ->test(AuthProfileDropdown::class)
            ->call('deleteAccount');

        // Sole admin MUST still exist
        $this->assertEquals(1, Admin::count());
        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function an_admin_can_be_deleted_if_other_admins_exist(): void
    {
        $admin1 = Admin::factory()->create();
        $admin2 = Admin::factory()->create();

        $this->assertEquals(2, Admin::count());

        Livewire::actingAs($admin1, 'admin')
            ->test(AuthProfileDropdown::class)
            ->call('deleteAccount');

        // Admin 1 deleted (soft delete)
        $this->assertSoftDeleted('admins', ['id' => $admin1->id]);
        // Admin 2 still active
        $this->assertEquals(1, Admin::count());
    }
}
