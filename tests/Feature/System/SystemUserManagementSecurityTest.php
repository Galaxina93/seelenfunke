<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUserManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin1;
    private Admin $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        Admin::withTrashed()->forceDelete();

        $this->admin1 = Admin::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin1@seelenfunke.de',
            'password' => Hash::make('password123'),
        ]);

        $this->admin2 = Admin::create([
            'first_name' => 'Second',
            'last_name' => 'Admin',
            'email' => 'admin2@seelenfunke.de',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_admin_cannot_archive_self(): void
    {
        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->call('archiveUser', (string) $this->admin1->id, 'admin')
            ->assertSee('Sie können Ihr eigenes Administrator-Konto nicht archivieren.');

        $this->assertNull($this->admin1->fresh()->deleted_at);
    }

    public function test_cannot_archive_last_remaining_active_admin(): void
    {
        // Delete admin2 first so admin1 is the only active admin left
        $this->admin2->delete();

        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->call('archiveUser', (string) $this->admin2->id, 'admin')
            ->assertSee('Der letzte verbleibende aktive Administrator kann nicht archiviert werden.');
    }

    public function test_admin_can_archive_other_admin_when_multiple_exist(): void
    {
        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->call('archiveUser', (string) $this->admin2->id, 'admin');

        $this->assertNotNull($this->admin2->fresh()->deleted_at);
    }

    public function test_creating_verified_customer_sets_customer_email_verified_at(): void
    {
        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->set('createType', 'customer')
            ->set('formData.first_name', 'Verified')
            ->set('formData.last_name', 'Customer')
            ->set('formData.email', 'verified@kunde.de')
            ->set('formData.password', 'password123')
            ->set('formData.is_verified', true)
            ->call('saveNewUser');

        $customer = Customer::where('email', 'verified@kunde.de')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertNotNull($customer->profile->email_verified_at);
    }

    public function test_inline_edit_updates_customer_email_verified_at(): void
    {
        $customer = Customer::create([
            'first_name' => 'Unverified',
            'last_name' => 'Customer',
            'email' => 'unverified@kunde.de',
            'password' => Hash::make('password123'),
            'email_verified_at' => null,
        ]);

        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer')
            ->set('formData.is_verified', true)
            ->call('saveInline');

        $this->assertNotNull($customer->fresh()->email_verified_at);

        // Now toggle it back to unverified
        Livewire::actingAs($this->admin1, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer')
            ->set('formData.is_verified', false)
            ->call('saveInline');

        $this->assertNull($customer->fresh()->email_verified_at);
    }
}
