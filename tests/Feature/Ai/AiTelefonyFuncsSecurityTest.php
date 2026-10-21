<?php

namespace Tests\Feature\Ai;

use App\Models\SupportTelephonyCall;
use App\Services\AI\Functions\AiTelefonyFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiTelefonyFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_status_enforces_whitelist()
    {
        $dummy = new class {
            use AiTelefonyFuncs;
            public static function findPersonProfile($name) { return null; }
        };

        $call = SupportTelephonyCall::create([
            'contact_name' => 'Max Mustermann',
            'phone' => '+49123456789',
            'status' => 'planned',
            'objective' => 'Projektstatus erfragen',
        ]);

        // Attempt invalid status
        $resInvalid = $dummy::executeTelephonyCallUpdateStatus([
            'call_id' => $call->id,
            'status' => 'hacked_status',
        ]);

        $this->assertEquals('error', $resInvalid['status']);
        $this->assertStringContainsString('Ungültiger Status', $resInvalid['message']);
        $call->refresh();
        $this->assertEquals('planned', $call->status);

        // Valid status
        $resValid = $dummy::executeTelephonyCallUpdateStatus([
            'call_id' => $call->id,
            'status' => 'COMPLETED',
        ]);

        $this->assertEquals('success', $resValid['status']);
        $call->refresh();
        $this->assertEquals('completed', $call->status);
    }

    public function test_get_history_clamps_limit_and_escapes_search()
    {
        $dummy = new class {
            use AiTelefonyFuncs;
            public static function findPersonProfile($name) { return null; }
        };

        SupportTelephonyCall::create([
            'contact_name' => 'Max_Mustermann',
            'phone' => '+491111111',
            'status' => 'completed',
        ]);

        SupportTelephonyCall::create([
            'contact_name' => 'MaxXMustermann',
            'phone' => '+492222222',
            'status' => 'completed',
        ]);

        // Search with '_' should only match exact 'Max_Mustermann' due to escaping
        $res = $dummy::executeTelephonyCallGetHistory([
            'first_name' => 'Max_Mustermann',
            'limit' => 1000, // Should clamp to 50
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertCount(1, $res['data']);
        $this->assertEquals('Max_Mustermann', $res['data'][0]['contact']);
    }
}
