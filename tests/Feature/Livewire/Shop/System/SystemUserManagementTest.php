<?php

namespace Tests\Feature\Livewire\Shop\System;

use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Admin\AdminProfile;
use App\Models\Customer\Customer;
use App\Models\System\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'System',
            'last_name' => 'Admin',
            'email' => 'admin-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->admin = Admin::find($adminId);
        AdminProfile::create([
            'admin_id' => $adminId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->admin, 'admin');
    }

    public function test_component_renders_user_with_2fa_badge()
    {
        $customer = Customer::create([
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'max@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer->profile->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => 'SECRETKEY123',
            'two_factor_recovery_codes' => encrypt(json_encode(['CODE1', 'CODE2'])),
            'email_verified_at' => now(),
        ]);

        Livewire::test(SystemUserManagement::class)
            ->assertSee('2FA Aktiv')
            ->assertSee('max@example.com');
    }

    public function test_admin_can_disable_2fa_via_inline_edit()
    {
        $customer = Customer::create([
            'first_name' => 'Erika',
            'last_name' => 'Mustermann',
            'email' => 'erika@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer->profile->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => 'SECRETKEY456',
            'two_factor_recovery_codes' => encrypt(json_encode(['REC1', 'REC2'])),
            'email_verified_at' => now(),
        ]);

        Livewire::test(SystemUserManagement::class)
            ->call('startEdit', $customer->id, 'customer')
            ->assertSet('editing2faActive', true)
            ->call('disable2Fa')
            ->assertSet('editing2faActive', false)
            ->assertSee('erfolgreich deaktiviert');

        $customer->profile->refresh();
        $this->assertFalse((bool) $customer->profile->two_factor_is_active);
        $this->assertNull($customer->profile->two_factor_secret);
        $this->assertNull($customer->profile->two_factor_recovery_codes);

        $this->assertDatabaseHas('system_logs', [
            'action_id' => 'user:2fa_disable',
            'status' => 'warning',
        ]);
    }

    public function test_admin_can_disable_2fa_via_table_quick_action()
    {
        $customer = Customer::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $customer->profile->update([
            'two_factor_is_active' => true,
            'two_factor_secret' => 'SECRETKEY789',
            'two_factor_recovery_codes' => encrypt(json_encode(['REC9', 'REC8'])),
            'email_verified_at' => now(),
        ]);

        Livewire::test(SystemUserManagement::class)
            ->call('disable2FaForUser', $customer->id, 'customer')
            ->assertSee('erfolgreich deaktiviert');

        $customer->profile->refresh();
        $this->assertFalse((bool) $customer->profile->two_factor_is_active);
        $this->assertNull($customer->profile->two_factor_secret);
        $this->assertNull($customer->profile->two_factor_recovery_codes);

        $this->assertDatabaseHas('system_logs', [
            'action_id' => 'user:2fa_disable',
            'status' => 'warning',
        ]);
    }
}
