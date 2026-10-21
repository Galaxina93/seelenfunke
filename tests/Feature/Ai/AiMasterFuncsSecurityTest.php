<?php

namespace Tests\Feature\Ai;

use App\Services\AI\Functions\AiMasterFuncs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AiMasterFuncsSecurityTest extends TestCase
{
    public function test_toggle_maintenance_requires_passphrase_and_validates_action()
    {
        $dummy = new class {
            use AiMasterFuncs;
        };

        // Wrong passphrase
        $resWrongPass = $dummy::executeToggleMaintenance([
            'action' => 'down',
            'secret_passphrase' => 'wrong_pass',
        ]);
        $this->assertEquals('error', $resWrongPass['status']);
        $this->assertStringContainsString('Autorisierung fehlgeschlagen', $resWrongPass['message']);

        // Invalid action with correct passphrase
        $resInvalidAction = $dummy::executeToggleMaintenance([
            'action' => 'restart_server',
            'secret_passphrase' => 'CONFIRM_CEO_OVERRIDE',
        ]);
        $this->assertEquals('error', $resInvalidAction['status']);
        $this->assertStringContainsString('Ungültige Aktion', $resInvalidAction['message']);
    }

    public function test_toggle_maintenance_uses_secure_bypass_token()
    {
        $dummy = new class {
            use AiMasterFuncs;
        };

        Config::set('app.maintenance_secret', 'custom-safe-maintenance-token-99');

        Artisan::shouldReceive('call')
            ->once()
            ->with('down', ['--secret' => 'custom-safe-maintenance-token-99'])
            ->andReturn(0);

        $res = $dummy::executeToggleMaintenance([
            'action' => 'down',
            'secret_passphrase' => 'CONFIRM_CEO_OVERRIDE',
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertStringContainsString('custom-safe-maintenance-token-99', $res['message']);
    }
}
