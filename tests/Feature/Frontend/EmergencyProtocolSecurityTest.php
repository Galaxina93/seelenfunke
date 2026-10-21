<?php

namespace Tests\Feature\Frontend;

use App\Livewire\Frontend\Emergency\EmergencyProtocol;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmergencyProtocolSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_actions_are_blocked()
    {
        Livewire::test(EmergencyProtocol::class)
            ->call('toggleTodo', 'offline_mode')
            ->assertForbidden();
    }

    public function test_authenticated_toggle_todo_whitelists_valid_keys_and_clamps_step()
    {
        // Set master password in SystemSetting
        SystemSetting::create([
            'key' => 'emergency_master_password',
            'value' => bcrypt('SuperSecureEmergency2026!'),
        ]);

        $component = Livewire::test(EmergencyProtocol::class)
            ->set('passwordInput', 'SuperSecureEmergency2026!')
            ->call('authenticate')
            ->assertSet('isAuthenticated', true);

        // Step clamping
        $component->call('setStep', 99);
        $this->assertEquals(5, $component->get('currentStep'));

        $component->call('setStep', -5);
        $this->assertEquals(1, $component->get('currentStep'));

        // Arbitrary key injection is ignored
        $component->call('toggleTodo', 'malicious_arbitrary_key_injection');
        $setting = SystemSetting::where('key', 'emergency_todo_states')->first();
        $this->assertNull($setting);

        // Valid key is toggled
        $component->call('toggleTodo', 'versicherungen');

        $savedSetting = SystemSetting::where('key', 'emergency_todo_states')->first();
        $this->assertNotNull($savedSetting);
        $decoded = json_decode($savedSetting->value, true);
        $this->assertTrue($decoded['versicherungen']);
    }
}
