<?php

namespace Tests\Feature\System;

use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Employee\Employee;
use App\Models\System\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MmorpgAccessAndSsoGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-mmorpg@mein-seelenfunke.de',
            'password' => Hash::make('secret1234'),
        ]);
    }

    public function test_only_admin_can_toggle_mmorpg_access_and_system_log_is_written(): void
    {
        $customer = Customer::create([
            'first_name' => 'Klara',
            'last_name' => 'Funken',
            'email' => 'klara@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => now(),
            'mmorpg_access_enabled' => false,
        ]);

        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Werkstatt',
            'email' => 'max@mein-seelenfunke.de',
            'password' => Hash::make('secret1234'),
        ]);

        // Guest -> 403
        Livewire::test(SystemUserManagement::class)
            ->assertForbidden();

        // Employee -> 403
        Livewire::actingAs($employee, 'employee')
            ->test(SystemUserManagement::class)
            ->assertForbidden();

        // Customer -> 403
        Livewire::actingAs($customer, 'customer')
            ->test(SystemUserManagement::class)
            ->assertForbidden();

        // Admin toggles ON via 1-click method
        Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('toggleMmorpgAccess', (string) $customer->id)
            ->assertHasNoErrors();

        $this->assertTrue((bool) $customer->fresh()->mmorpg_access_enabled);

        $grantLog = SystemLog::where('action_id', 'user:mmorpg_access_granted')->latest()->first();
        $this->assertNotNull($grantLog);
        $this->assertSame('success', $grantLog->status);
        $this->assertSame((string) $customer->id, $grantLog->payload['user_id'] ?? null);
        $this->assertTrue((bool) ($grantLog->payload['mmorpg_access_enabled'] ?? false));

        // Admin toggles OFF via 1-click method
        Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('toggleMmorpgAccess', (string) $customer->id)
            ->assertHasNoErrors();

        $this->assertFalse((bool) $customer->fresh()->mmorpg_access_enabled);

        $revokeLog = SystemLog::where('action_id', 'user:mmorpg_access_revoked')->latest()->first();
        $this->assertNotNull($revokeLog);
        $this->assertSame('warning', $revokeLog->status);
        $this->assertFalse((bool) ($revokeLog->payload['mmorpg_access_enabled'] ?? true));

        // Admin can also enable via startEdit + saveInline
        Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer')
            ->assertSet('formData.mmorpg_access_enabled', false)
            ->set('formData.mmorpg_access_enabled', true)
            ->call('saveInline')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $customer->fresh()->mmorpg_access_enabled);
    }

    public function test_funkenreich_enter_enforces_closed_alpha_gate_and_redirects_authorized_users_with_signed_token(): void
    {
        $secret = bin2hex(random_bytes(32));
        config([
            'services.funkenreich.url' => 'https://game.mein-seelenfunke.de',
            'services.funkenreich.sso_secret' => $secret,
        ]);

        // 1. Guest -> 403
        $this->get('/funkenreich/enter')->assertStatus(403);

        // 2. Normal verified customer (mmorpg_access_enabled = false) -> 403
        $normalCustomer = Customer::create([
            'first_name' => 'Normal',
            'last_name' => 'Kunde',
            'email' => 'normal@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => now(),
            'mmorpg_access_enabled' => false,
        ]);

        $this->actingAs($normalCustomer, 'customer')
            ->get('/funkenreich/enter')
            ->assertStatus(403);

        // 3. Unverified customer even with flag true -> 403
        $unverifiedCustomer = Customer::create([
            'first_name' => 'Unverifiziert',
            'last_name' => 'Tester',
            'email' => 'unverified-tester@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => null,
            'mmorpg_access_enabled' => true,
        ]);

        $this->actingAs($unverifiedCustomer, 'customer')
            ->get('/funkenreich/enter')
            ->assertStatus(403);

        // 4. Customer with pending password change -> 403
        $pwChangeCustomer = Customer::create([
            'first_name' => 'Reset',
            'last_name' => 'Tester',
            'email' => 'reset-tester@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => now(),
            'needs_password_change' => true,
            'mmorpg_access_enabled' => true,
        ]);

        $this->actingAs($pwChangeCustomer, 'customer')
            ->get('/funkenreich/enter')
            ->assertStatus(403);

        // 5. Enabled & verified customer -> 302 Redirect with valid HMAC token
        $alphaCustomer = Customer::create([
            'first_name' => 'Aria',
            'last_name' => 'Licht',
            'email' => 'aria@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => now(),
            'needs_password_change' => false,
            'mmorpg_access_enabled' => true,
        ]);

        CustomerGamification::create([
            'customer_id' => $alphaCustomer->id,
            'is_active' => true,
            'level' => 7,
            'funken_balance' => 450,
            'funken_total_earned' => 1280,
            'player_name' => 'Sternenwaechterin',
            'magma_unlocked_level' => 5,
            'magma_hearts' => 4,
            'inventory_items' => [
                ['id' => 'hp-1', 'item_key' => 'healing_potion'],
                ['id' => 'hp-2', 'item_key' => 'healing_potion'],
            ],
        ]);

        $resp = $this->actingAs($alphaCustomer->fresh(), 'customer')
            ->get('/funkenreich/enter');

        $resp->assertStatus(302);
        $location = (string) $resp->headers->get('Location');
        $this->assertStringStartsWith('https://game.mein-seelenfunke.de/sso/callback?token=', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('token', $query);
        $parts = explode('.', (string) $query['token']);
        $this->assertCount(2, $parts);
        [$b64, $sig] = $parts;

        $this->assertSame(hash_hmac('sha256', $b64, $secret), $sig);

        $padded = strtr($b64, '-_', '+/');
        $mod = strlen($padded) % 4;
        if ($mod > 0) {
            $padded .= str_repeat('=', 4 - $mod);
        }
        $payload = json_decode((string) base64_decode($padded, true), true);

        $this->assertIsArray($payload);
        $this->assertSame('mein-seelenfunke', $payload['iss']);
        $this->assertSame('customer', $payload['sub_type']);
        $this->assertSame((string) $alphaCustomer->id, $payload['sub_id']);
        $this->assertSame('Sternenwaechterin', $payload['display_name']);
        $this->assertSame(7, $payload['funki_level']);
        $this->assertSame(450, $payload['funken_balance']);
        $this->assertSame(1280, $payload['funken_total_earned']);
        $this->assertSame(5, $payload['magma_unlocked_level']);
        $this->assertSame(4, $payload['magma_hearts']);
        $this->assertSame(2, $payload['healing_potions']);
        $this->assertTrue($payload['mmorpg_access']);
        $this->assertSame($payload['iat'] + 60, $payload['exp']);
        $this->assertSame(32, strlen((string) $payload['nonce']));

        // 6. Admin -> 302 Redirect without needing customer account
        \Illuminate\Support\Facades\Auth::guard('customer')->logout();

        $adminResp = $this->actingAs($this->admin, 'admin')
            ->get('/funkenreich/enter');

        $adminResp->assertStatus(302);
        $adminLoc = (string) $adminResp->headers->get('Location');
        $this->assertStringStartsWith('https://game.mein-seelenfunke.de/sso/callback?token=', $adminLoc);

        parse_str((string) parse_url($adminLoc, PHP_URL_QUERY), $adminQuery);
        [$adminB64, $adminSig] = explode('.', (string) $adminQuery['token']);
        $this->assertSame(hash_hmac('sha256', $adminB64, $secret), $adminSig);

        $adminPad = strtr($adminB64, '-_', '+/');
        if (strlen($adminPad) % 4 > 0) {
            $adminPad .= str_repeat('=', 4 - (strlen($adminPad) % 4));
        }
        $adminPayload = json_decode((string) base64_decode($adminPad, true), true);
        $this->assertSame('admin', $adminPayload['sub_type']);
        $this->assertSame((string) $this->admin->id, $adminPayload['sub_id']);
        $this->assertSame(10, $adminPayload['funki_level']);
        $this->assertTrue($adminPayload['mmorpg_access']);
    }

    public function test_funkenreich_access_status_api_validates_hmac_and_reflects_live_revocation(): void
    {
        $secret = bin2hex(random_bytes(32));
        config([
            'services.funkenreich.sso_secret' => $secret,
        ]);

        $customer = Customer::create([
            'first_name' => 'Lukas',
            'last_name' => 'Tester',
            'email' => 'lukas@example.com',
            'password' => Hash::make('secret1234'),
            'email_verified_at' => now(),
            'mmorpg_access_enabled' => true,
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => 6,
            'funken_balance' => 320,
        ]);

        $cid = (string) $customer->id;
        $ts = (string) time();

        // 1. Invalid signature -> 401
        $this->getJson("/api/funkenreich/access-status?sub_type=customer&sub_id={$cid}", [
            'X-Funkenreich-Timestamp' => $ts,
            'X-Funkenreich-Signature' => str_repeat('0', 64),
        ])->assertStatus(401)->assertJson(['allowed' => false]);

        // 2. Expired timestamp (> 120s) -> 401
        $oldTs = (string) (time() - 300);
        $oldSig = hash_hmac('sha256', "customer|{$cid}|{$oldTs}", $secret);
        $this->getJson("/api/funkenreich/access-status?sub_type=customer&sub_id={$cid}", [
            'X-Funkenreich-Timestamp' => $oldTs,
            'X-Funkenreich-Signature' => $oldSig,
        ])->assertStatus(401)->assertJson(['allowed' => false]);

        // 3. Valid signature while enabled -> allowed: true
        $validSig = hash_hmac('sha256', "customer|{$cid}|{$ts}", $secret);
        $this->getJson("/api/funkenreich/access-status?sub_type=customer&sub_id={$cid}", [
            'X-Funkenreich-Timestamp' => $ts,
            'X-Funkenreich-Signature' => $validSig,
        ])->assertOk()->assertExactJson([
            'allowed' => true,
            'funki_level' => 6,
            'funken_balance' => 320,
        ]);

        // 4. Revoke access -> immediate allowed: false
        $customer->update(['mmorpg_access_enabled' => false]);

        $ts2 = (string) time();
        $sig2 = hash_hmac('sha256', "customer|{$cid}|{$ts2}", $secret);
        $this->getJson("/api/funkenreich/access-status?sub_type=customer&sub_id={$cid}", [
            'X-Funkenreich-Timestamp' => $ts2,
            'X-Funkenreich-Signature' => $sig2,
        ])->assertOk()->assertExactJson([
            'allowed' => false,
            'funki_level' => 6,
            'funken_balance' => 320,
        ]);

        // 5. Admin live verification -> allowed: true
        $aid = (string) $this->admin->id;
        $adminSig = hash_hmac('sha256', "admin|{$aid}|{$ts2}", $secret);
        $this->getJson("/api/funkenreich/access-status?sub_type=admin&sub_id={$aid}", [
            'X-Funkenreich-Timestamp' => $ts2,
            'X-Funkenreich-Signature' => $adminSig,
        ])->assertOk()->assertExactJson([
            'allowed' => true,
            'funki_level' => 10,
            'funken_balance' => 0,
        ]);
    }
}
