<?php

namespace Tests\Feature\Livewire\Frontend;

use App\Livewire\Frontend\Emergency\EmergencyProtocol;
use App\Models\System\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmergencyProtocolTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_renders_login_screen_initially()
    {
        SystemSetting::create([
            'key' => 'emergency_master_password',
            'value' => Hash::make('secret-emergency-pass'),
        ]);

        Livewire::test(EmergencyProtocol::class)
            ->assertSet('isAuthenticated', false)
            ->assertSee('Digitales Todesfall-Protokoll')
            ->assertSee('Master-Passwort eingeben');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_unauthenticated_actions_with_403()
    {
        SystemSetting::create([
            'key' => 'emergency_master_password',
            'value' => Hash::make('secret-emergency-pass'),
        ]);

        Livewire::test(EmergencyProtocol::class)
            ->call('downloadEmergencyPdf')
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_unauthenticated_set_maintenance_mode_with_403()
    {
        SystemSetting::create([
            'key' => 'emergency_master_password',
            'value' => Hash::make('secret-emergency-pass'),
        ]);

        Livewire::test(EmergencyProtocol::class)
            ->call('setMaintenanceMode')
            ->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_authenticates_with_valid_password_and_allows_actions()
    {
        SystemSetting::create([
            'key' => 'emergency_master_password',
            'value' => Hash::make('secret-emergency-pass'),
        ]);

        Livewire::test(EmergencyProtocol::class)
            ->set('passwordInput', 'wrong-pass')
            ->call('authenticate')
            ->assertSet('isAuthenticated', false)
            ->assertSee('Das eingegebene Master-Passwort ist inkorrekt')
            ->set('passwordInput', 'secret-emergency-pass')
            ->call('authenticate')
            ->assertSet('isAuthenticated', true)
            ->call('setStep', 2)
            ->assertSet('currentStep', 2)
            ->call('toggleTodo', 'versicherungen')
            ->assertSet('todoStates.versicherungen', true);

        $this->assertDatabaseHas('shop_settings', [
            'key' => 'emergency_todo_states',
        ]);
    }
}
