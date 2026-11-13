<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FunkiraPhoneConciergeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_phone_concierge_enforces_whitelist_and_creates_dashboard_task_on_completion(): void
    {
        Cache::forget('funkira_phone_concierge_active_call_v1');
        Cache::forget('funkira_phone_concierge_last_summary_v1');

        $admin = Admin::factory()->create();
        $androidToken = $admin->createToken('AndroidApp', ['*'])->plainTextToken;
        $miniPcToken = $admin->createToken('FunkenwerkMiniPc', ['monitor:pulse', 'ai:funkira'])->plainTextToken;

        $customer = Customer::factory()->create();
        $customerToken = $customer->createToken('CustomerWeb', ['*'])->plainTextToken;

        // 1. Customer token must be strictly blocked (403)
        $this->withHeader('Authorization', 'Bearer ' . $customerToken)
            ->getJson('/api/system/phone-concierge/config')
            ->assertStatus(403);

        Auth::forgetGuards();

        // 2. Create whitelisted contact (Papa) + non-whitelisted contact
        $papa = ManagementContact::create([
            'first_name' => 'TestJan',
            'last_name' => 'SteinhauerTest',
            'nickname' => 'Papa',
            'relation_type' => 'Vater',
            'phone' => '+49 176 99887766',
            'funkira_call_whitelist' => true,
            'system_instructions' => 'Vater von Alina.',
        ]);

        ManagementContact::create([
            'first_name' => 'Fremder',
            'last_name' => 'Anrufer',
            'phone' => '+49 176 11112222',
            'funkira_call_whitelist' => false,
        ]);

        // 3. Config endpoint accessible by both Android (*) and Mini-PC (monitor:pulse)
        $cfgResp = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
            ->getJson('/api/system/phone-concierge/config');

        $cfgResp->assertOk()
            ->assertJsonPath('success', true);

        $whitelistIds = collect($cfgResp->json('data.whitelist_contacts'))->pluck('id')->all();
        $this->assertContains($papa->id, $whitelistIds);

        // 4. Non-whitelisted caller signal must be rejected
        $rejectResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/signal', [
                'state' => 'accepted',
                'caller_phone' => '0176 11112222',
            ]);

        $rejectResp->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.reason', 'not_whitelisted');

        // 5. Whitelisted caller (formatted nationally as 0176-99887766) is matched & accepted
        $acceptResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/signal', [
                'call_id' => 'call-test-papa-01',
                'state' => 'accepted',
                'caller_phone' => '0176-99887766',
                'audio_mode' => 'bluetooth_hfp',
            ]);

        $acceptResp->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.caller_name', 'TestJan SteinhauerTest')
            ->assertJsonPath('data.greeting_name', 'Papa')
            ->assertJsonPath('data.hangup_requested', false);

        // 6. Mini-PC polls and sees active call
        Auth::forgetGuards();
        $pollResp = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
            ->getJson('/api/system/phone-concierge/poll');

        $pollResp->assertOk()
            ->assertJsonPath('data.active_call.call_id', 'call-test-papa-01')
            ->assertJsonPath('data.active_call.state', 'accepted');

        // 7. Mini-PC completes call -> creates Dashboard ManagementTask + sets hangup_requested = true
        $completeResp = $this->withHeader('Authorization', 'Bearer ' . $miniPcToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => 'call-test-papa-01',
                'contact_id' => $papa->id,
                'caller_name' => 'TestJan SteinhauerTest',
                'greeting_name' => 'Papa',
                'summary' => 'Fragt, ob du Sonntag um 15 Uhr zum Kaffee vorbeikommst.',
                'urgency' => 'mittel',
                'callback_requested' => true,
            ]);

        $completeResp->assertOk()
            ->assertJsonPath('success', true);

        $taskId = $completeResp->json('data.task_id');
        $this->assertNotEmpty($taskId);

        $task = ManagementTask::findOrFail($taskId);
        $this->assertStringContainsString('Papa', $task->title);
        $this->assertStringContainsString('Kaffee', $task->title);
        $this->assertSame('hoch', $task->priority);

        // 8. Android polls and sees hangup_requested = true + last_completed_call
        Auth::forgetGuards();
        $afterPoll = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->getJson('/api/system/phone-concierge/poll');

        $afterPoll->assertOk()
            ->assertJsonPath('data.hangup_requested', true)
            ->assertJsonPath('data.last_completed_call.summary', 'Fragt, ob du Sonntag um 15 Uhr zum Kaffee vorbeikommst.');
    }
}
