<?php

namespace Tests\Feature\System;

use App\Livewire\Auth\AuthLoginDropdown;
use App\Livewire\Shop\System\SystemUserManagement;
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Models\Order\OrderOrder;
use App\Models\System\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUserManagementGamificationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => Hash::make('SecretAdmin123!'),
        ]);
    }

    public function test_auth_login_dropdown_refreshes_immediately_on_auth_status_changed_event(): void
    {
        $customer = Customer::create([
            'first_name' => 'Clara',
            'last_name' => 'Stern',
            'email' => 'clara@example.com',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $desktop = Livewire::test(AuthLoginDropdown::class, ['isMobile' => false])
            ->assertSee('Anmelden')
            ->assertDontSee('clara@example.com');

        $mobile = Livewire::test(AuthLoginDropdown::class, ['isMobile' => true])
            ->assertSee('Login / Kundenbereich')
            ->assertDontSee('clara@example.com');

        Auth::guard('customer')->login($customer);

        $desktop->dispatch('auth-status-changed')
            ->assertSee('Hallo, Clara Stern!')
            ->assertSee('clara@example.com')
            ->assertSee('Mein Dashboard');

        $mobile->dispatch('auth-status-changed')
            ->assertSee('Clara Stern')
            ->assertSee('clara@example.com')
            ->assertSee('Mein Dashboard');
    }

    public function test_blade_has_single_gamification_active_checkbox_and_theme_color_styling_without_hardcoded_amber(): void
    {
        $customer = Customer::create([
            'first_name' => 'Lukas',
            'last_name' => 'Berg',
            'email' => 'lukas@example.com',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer', 'profile');

        $html = $component->html();

        $this->assertSame(1, substr_count($html, 'wire:model="formData.gamification_active"'));
        $this->assertStringNotContainsString('Magie aktiv', $html);
        $this->assertStringContainsString('Seelenfunken-Treueprogramm aktiv', $html);
        $this->assertStringContainsString('Magma-Freischaltung (1-10)', $html);
        $this->assertStringContainsString('Magma-Herzen (1-6)', $html);
        $this->assertStringContainsString('Heiltränke im Rucksack (0-30)', $html);

        $sectionPos = strpos($html, 'Seelenfunken, Funki');
        $this->assertNotFalse($sectionPos);
        $sectionHtml = substr($html, $sectionPos);
        $this->assertStringNotContainsString('text-amber-500', $sectionHtml);
        $this->assertStringNotContainsString('focus:ring-amber-500', $sectionHtml);
        $this->assertStringNotContainsString('hover:border-amber-500/40', $sectionHtml);

        foreach ([
            app_path('Livewire/Auth/AuthLoginDropdown.php'),
            app_path('Livewire/Shop/System/SystemUserManagement.php'),
            resource_path('views/livewire/shop/system/system-user-management.blade.php'),
        ] as $checkedFile) {
            $this->assertStringNotContainsString(" \u{2013} ", (string) file_get_contents($checkedFile));
        }
    }

    public function test_create_customer_initializes_gamification_keys_and_validates_player_name(): void
    {
        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startCreate')
            ->assertSet('formData.ranking_opt_in', false)
            ->assertSet('formData.use_real_name', false)
            ->assertSet('formData.player_name', '')
            ->assertSet('formData.ai_data_consent', false)
            ->assertSet('formData.magma_unlocked_level', 1)
            ->assertSet('formData.magma_hearts', 6)
            ->assertSet('formData.healing_potions', 0);

        $component
            ->set('formData.first_name', 'Nora')
            ->set('formData.last_name', 'Glanz')
            ->set('formData.email', 'nora@example.com')
            ->set('formData.password', 'Secret123!')
            ->set('formData.player_name', 'AdminChef')
            ->call('saveNewUser')
            ->assertHasErrors(['formData.player_name']);

        $this->assertNull(Customer::where('email', 'nora@example.com')->first());

        $component
            ->set('formData.player_name', 'Sternenheldin')
            ->set('formData.magma_unlocked_level', 4)
            ->set('formData.magma_hearts', 5)
            ->set('formData.healing_potions', 3)
            ->call('saveNewUser')
            ->assertHasNoErrors();

        $created = Customer::where('email', 'nora@example.com')->firstOrFail();
        $gamification = CustomerGamification::where('customer_id', $created->id)->firstOrFail();
        $this->assertSame('Sternenheldin', $gamification->player_name);
        $this->assertSame(4, (int) $gamification->magma_unlocked_level);
        $this->assertSame(5, (int) $gamification->magma_hearts);
    }

    public function test_inline_edit_loads_potions_updates_magma_validates_player_name_and_logs_exact_deltas(): void
    {
        $otherCustomer = Customer::create([
            'first_name' => 'Erik',
            'last_name' => 'Fels',
            'email' => 'erik@example.com',
            'password' => Hash::make('Secret123!'),
        ]);
        CustomerGamification::create([
            'customer_id' => $otherCustomer->id,
            'is_active' => true,
            'level' => 2,
            'player_name' => 'VergebenerHeld',
        ]);

        $customer = Customer::create([
            'first_name' => 'Mila',
            'last_name' => 'Gold',
            'email' => 'mila@example.com',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);
        $customer->profile()->firstOrCreate([]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'funken_balance' => 120,
            'funken_total_earned' => 300,
            'level' => 2,
            'magma_unlocked_level' => 2,
            'magma_hearts' => 6,
            'magma_active_run_id' => 'run-old-123',
            'magma_run_committed' => true,
            'inventory_items' => [
                ['id' => 'item-1', 'item_key' => 'healing_potion'],
                ['id' => 'item-2', 'item_key' => 'healing_potion', 'quantity' => 2],
                ['id' => 'item-3', 'item_key' => 'other_relic'],
            ],
        ]);

        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer', 'profile')
            ->assertSet('formData.funken_balance', 120)
            ->assertSet('formData.funken_total_earned', 300)
            ->assertSet('formData.funki_level', 2)
            ->assertSet('formData.magma_unlocked_level', 2)
            ->assertSet('formData.magma_hearts', 6)
            ->assertSet('formData.healing_potions', 3);

        // 1. Out-of-range validation
        $component
            ->set('formData.magma_unlocked_level', 11)
            ->set('formData.magma_hearts', 0)
            ->set('formData.healing_potions', 31)
            ->call('saveInline')
            ->assertHasErrors([
                'formData.magma_unlocked_level',
                'formData.magma_hearts',
                'formData.healing_potions',
            ]);

        // 2. Duplicate / blocked player_name validation via PlayerNameValidator
        $component
            ->set('formData.magma_unlocked_level', 7)
            ->set('formData.magma_hearts', 4)
            ->set('formData.healing_potions', 5)
            ->set('formData.player_name', 'VergebenerHeld')
            ->call('saveInline')
            ->assertHasErrors(['formData.player_name']);

        // 3. Valid save + SystemLog delta check
        $component
            ->set('formData.funken_balance', 450)
            ->set('formData.funken_total_earned', 700)
            ->set('formData.funki_level', 4)
            ->set('formData.player_name', 'Lichtwanderin')
            ->call('saveInline')
            ->assertHasNoErrors();

        $freshGamification = CustomerGamification::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame(450, (int) $freshGamification->funken_balance);
        $this->assertSame(700, (int) $freshGamification->funken_total_earned);
        $this->assertSame(4, (int) $freshGamification->level);
        $this->assertSame(7, (int) $freshGamification->magma_unlocked_level);
        $this->assertSame(4, (int) $freshGamification->magma_hearts);
        $this->assertSame('Lichtwanderin', $freshGamification->player_name);

        $potionSum = 0;
        $hasOtherRelic = false;
        $inv = is_array($freshGamification->inventory_items) ? $freshGamification->inventory_items : [];
        if (isset($inv['bag_slots']) && is_array($inv['bag_slots'])) {
            foreach ($inv['bag_slots'] as $slot) {
                if (is_array($slot) && ($slot['item_key'] ?? null) === 'healing_potion') {
                    $potionSum += (int) ($slot['qty'] ?? $slot['quantity'] ?? 0);
                }
            }
        }
        foreach ($inv as $invKey => $entry) {
            if (!is_int($invKey)) {
                continue;
            }
            if (!isset($inv['bag_slots']) && is_array($entry) && ($entry['item_key'] ?? null) === 'healing_potion') {
                $potionSum += (int) ($entry['quantity'] ?? $entry['qty'] ?? $entry['count'] ?? 1);
            }
            if (is_array($entry) && ($entry['item_key'] ?? null) === 'other_relic') {
                $hasOtherRelic = true;
            }
        }
        $this->assertSame(5, $potionSum);
        $this->assertTrue($hasOtherRelic);

        Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', (string) $customer->id, 'customer', 'profile')
            ->assertSet('formData.healing_potions', 5);

        $log = SystemLog::where('action_id', 'user:update')->latest('id')->firstOrFail();
        $changes = $log->payload['changes'] ?? [];

        $this->assertSame(['old' => 120, 'new' => 450], $changes['funken_balance'] ?? null);
        $this->assertSame(['old' => 300, 'new' => 700], $changes['funken_total_earned'] ?? null);
        $this->assertSame(['old' => 2, 'new' => 4], $changes['funki_level'] ?? null);
        $this->assertSame(['old' => 2, 'new' => 7], $changes['magma_unlocked_level'] ?? null);
        $this->assertSame(['old' => 6, 'new' => 4], $changes['magma_hearts'] ?? null);
        $this->assertSame(['old' => 3, 'new' => 5], $changes['healing_potions'] ?? null);
        $this->assertSame(['old' => '', 'new' => 'Lichtwanderin'], $changes['player_name'] ?? null);
    }

    public function test_status_pills_display_master_key_cleared_rooms_and_one_click_3d_chat_unmute(): void
    {
        $customer = Customer::create([
            'first_name' => 'Theo',
            'last_name' => 'Funke',
            'email' => 'theo@example.com',
            'password' => Hash::make('Secret123!'),
            'email_verified_at' => now(),
        ]);

        CustomerGamification::create([
            'customer_id' => $customer->id,
            'is_active' => true,
            'level' => 3,
            'titles_progress' => [
                'lava_cleared_levels' => [1, 2, 3],
            ],
        ]);

        $cid = (string) $customer->id;
        $hash = md5($cid);
        Cache::put('sf_lobby_mute_v1_' . $cid, time() + 900, 900);
        Cache::put('sf_lobby_reports_v1_' . $cid, ['r1' => time()], 900);
        Cache::put('sf_showroom_mute_' . $cid, time() + 900, 900);
        Cache::put('sf_showroom_mute_' . $hash, time() + 900, 900);
        Cache::put('sf_showroom_reports_' . $hash, ['r1' => time()], 900);

        $component = Livewire::actingAs($this->admin, 'admin')
            ->test(SystemUserManagement::class)
            ->call('startEdit', $cid, 'customer')
            ->assertSee('Meisterschlüssel: Offen')
            ->assertSee('Gemeisterte Räume: 3 / 10')
            ->assertSee('3D-Chat: Stummgeschaltet')
            ->assertSee('Freischalten');

        $component->call('resetCustomer3dChatMute')
            ->assertSee('3D-Chat: Frei')
            ->assertDontSee('3D-Chat: Stummgeschaltet');

        $this->assertNull(Cache::get('sf_lobby_mute_v1_' . $cid));
        $this->assertNull(Cache::get('sf_lobby_reports_v1_' . $cid));
        $this->assertNull(Cache::get('sf_showroom_mute_' . $cid));
        $this->assertNull(Cache::get('sf_showroom_mute_' . $hash));
        $this->assertNull(Cache::get('sf_showroom_reports_' . $hash));

        OrderOrder::create([
            'order_number' => 'SF-TEST-MASTERKEY-01',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal_price' => 2990,
            'tax_amount' => 477,
            'total_price' => 2990,
            'billing_address' => ['first_name' => 'Theo', 'last_name' => 'Funke'],
        ]);

        $component->call('startEdit', $cid, 'customer')
            ->assertSee('Meisterschlüssel: Aktiv ✓');
    }
}
