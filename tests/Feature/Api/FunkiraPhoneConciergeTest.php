<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Ai\AiUniverseTicket;
use App\Models\Ai\AiUniverseTicketMessage;
use App\Models\Customer\Customer;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\SupportTelephonyCall;
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

        // 9. Smartphone-Sekretärin speichert vollständiges Gesprächsprotokoll (inkl. Transkript & Rahmenbedingungen)
        $androidCallId = 'android-uuid-test-9001';
        $androidComplete = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => $androidCallId,
                'contact_id' => $papa->id,
                'caller_name' => 'TestJan SteinhauerTest',
                'greeting_name' => 'Papa',
                'caller_phone' => '+4917699887766',
                'handled_by' => 'android_secretary',
                'summary' => 'Papa bringt morgen um 14:00 Uhr neue Kartons vorbei.',
                'rahmenbedingungen' => ['Morgen, 14:00 Uhr', 'Lieferung Kartons'],
                'action_required' => 'Kurz bestätigen, dass jemand da ist.',
                'urgency' => 'hoch',
                'callback_requested' => true,
                'duration_seconds' => 42,
                'transcript' => [
                    'Funkira: Hallo Papa, hier ist Funkira!',
                    'Anrufer: Sag Alina, ich bringe morgen um 14 Uhr Kartons vorbei.',
                ],
            ]);

        $androidComplete->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.handled_by', 'android_secretary');

        $dbCallId = $androidComplete->json('data.db_call_id');
        $this->assertNotEmpty($dbCallId);

        $cfgAfter = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->getJson('/api/system/phone-concierge/config');
        $cfgAfter->assertOk();
        $recent = collect($cfgAfter->json('data.recent_calls'))->firstWhere('client_call_id', $androidCallId);
        $this->assertNotNull($recent);
        $this->assertSame('android_secretary', $recent['handled_by']);
        $this->assertSame(42, $recent['duration_seconds']);
        $this->assertCount(2, $recent['rahmenbedingungen']);
        $this->assertCount(2, $recent['transcript']);
        $this->assertSame('Kurz bestätigen, dass jemand da ist.', $recent['action_required']);
    }

    public function test_phone_normalization_handles_german_trunk_zero_and_voip_sip_uris(): void
    {
        $this->assertSame(
            '4917657793016',
            \App\Services\System\FunkiraPhoneConciergeService::normalizePhoneDigits('+49 (0) 176 57793016')
        );
        $this->assertSame(
            '4917657793016',
            \App\Services\System\FunkiraPhoneConciergeService::normalizePhoneDigits('0176 / 57793016')
        );
        $this->assertSame(
            '4917657793016',
            \App\Services\System\FunkiraPhoneConciergeService::normalizePhoneDigits('sip:+4917657793016@ims.mnc001.mcc262.3gppnetwork.org;user=phone')
        );
    }

    public function test_completed_call_creates_structured_universe_ticket_upgrades_stub_and_blocks_post_cache_replay_duplicates(): void
    {
        Cache::forget('funkira_phone_concierge_active_call_v1');
        Cache::forget('funkira_phone_concierge_last_summary_v1');

        $admin = Admin::factory()->create();
        $androidToken = $admin->createToken('AndroidApp', ['*'])->plainTextToken;

        $customerContact = ManagementContact::create([
            'first_name' => 'Lisa',
            'last_name' => 'Kundin',
            'relation_type' => 'Kundin',
            'phone' => '+49 176 44556677',
            'funkira_call_whitelist' => true,
        ]);

        $privateContact = ManagementContact::create([
            'first_name' => 'Jan',
            'last_name' => 'Privat',
            'nickname' => 'Papa',
            'relation_type' => 'Vater',
            'phone' => '+49 176 88990011',
            'funkira_call_whitelist' => true,
        ]);

        // 1. Erst Fallback-Stub (z. B. kurzes Auflegen), dann Upgrade durch echtes KI-Protokoll -> genau 1 Ticket + 1 Task!
        $callId = 'android-ticket-upgrade-7701';
        $stubResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => $callId,
                'contact_id' => $customerContact->id,
                'caller_name' => 'Lisa Kundin',
                'greeting_name' => 'Lisa',
                'caller_phone' => '+4917644556677',
                'handled_by' => 'android_secretary',
                'is_fallback_stub' => true,
                'summary' => 'Lisa hat angerufen, Zusammenfassung wird nachgereicht.',
                'action_required' => 'Lisa kurz zurückrufen.',
                'urgency' => 'mittel',
                'callback_requested' => true,
                'duration_seconds' => 18,
            ])->assertOk();

        $stubTaskId = $stubResp->json('data.task_id');
        $stubTicketId = $stubResp->json('data.ticket_id');
        $this->assertNotEmpty($stubTaskId);
        $this->assertNotEmpty($stubTicketId);

        // Cache gezielt leeren, um DB-Idempotenz > 30 Min. zu testen
        Cache::forget('funkira_concierge_completed_' . md5($callId));

        $fullResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => $callId,
                'contact_id' => $customerContact->id,
                'caller_name' => 'Lisa Kundin',
                'greeting_name' => 'Lisa',
                'caller_phone' => '+4917644556677',
                'handled_by' => 'android_secretary',
                'is_fallback_stub' => false,
                'summary' => 'Möchte 25 personalisierte Schieferplatten bis nächsten Freitag bestellen.',
                'rahmenbedingungen' => ['Menge: 25 Stück', 'Wunschtermin: nächster Freitag'],
                'action_required' => 'Angebot erstellen und Lisa heute zurückrufen.',
                'urgency' => 'hoch',
                'callback_requested' => true,
                'duration_seconds' => 64,
                'transcript' => [
                    'Funkira: Hallo Lisa, hier ist Funkira!',
                    'Anrufer: Ich brauche 25 gravierte Schieferplatten bis nächsten Freitag.',
                ],
            ])->assertOk();

        $this->assertSame($stubTaskId, $fullResp->json('data.task_id'));
        $this->assertSame($stubTicketId, $fullResp->json('data.ticket_id'));

        $ticket = AiUniverseTicket::findOrFail($stubTicketId);
        $this->assertSame('phone_call', $ticket->ticket_type);
        $this->assertSame(AiUniverseTicket::STATUS_WAITING_FOR_ALINA, $ticket->status);
        $this->assertSame('high', $ticket->priority);
        $this->assertFalse((bool) $ticket->assigned_to_antigravity);
        $this->assertTrue((bool) $ticket->is_business);
        $this->assertStringContainsString('Schieferplatten', $ticket->title);
        $this->assertSame((int) $fullResp->json('data.db_call_id'), (int) ($ticket->action_payload['db_call_id'] ?? 0));

        $messages = AiUniverseTicketMessage::where('ticket_id', $ticket->id)->get();
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Menge: 25 Stück', $messages[0]->content);
        $this->assertStringContainsString('Angebot erstellen', $messages[0]->content);
        $this->assertStringNotContainsString(' – ', $messages[0]->content);

        // 2. Späteres Offline-Queue-Replay nach erneutem Cache-Ablauf -> 0 Dubletten!
        Cache::forget('funkira_concierge_completed_' . md5($callId));
        $taskCountBefore = ManagementTask::count();
        $ticketCountBefore = AiUniverseTicket::count();

        $replayResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => $callId,
                'contact_id' => $customerContact->id,
                'caller_name' => 'Lisa Kundin',
                'greeting_name' => 'Lisa',
                'summary' => 'Möchte 25 personalisierte Schieferplatten bis nächsten Freitag bestellen.',
                'is_fallback_stub' => false,
            ])->assertOk();

        $this->assertSame($stubTaskId, $replayResp->json('data.task_id'));
        $this->assertSame($stubTicketId, $replayResp->json('data.ticket_id'));
        $this->assertSame($taskCountBefore, ManagementTask::count());
        $this->assertSame($ticketCountBefore, AiUniverseTicket::count());

        // 3. Familiärer Anruf (Vater/Papa) -> is_business = false (Mitarbeiter-Isolation!) & Verknüpfung in recent_calls
        $privCallId = 'android-ticket-private-7702';
        $privResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => $privCallId,
                'contact_id' => $privateContact->id,
                'caller_name' => 'Jan Privat',
                'greeting_name' => 'Papa',
                'caller_phone' => '+4917688990011',
                'handled_by' => 'android_secretary',
                'summary' => 'Fragt nach dem Abendessen am Sonntag.',
                'action_required' => 'Papa kurz zurückrufen.',
                'urgency' => 'mittel',
                'callback_requested' => true,
                'duration_seconds' => 30,
            ])->assertOk();

        $privTicket = AiUniverseTicket::findOrFail($privResp->json('data.ticket_id'));
        $this->assertFalse((bool) $privTicket->is_business);

        $cfgResp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->getJson('/api/system/phone-concierge/config')->assertOk();
        $recentItem = collect($cfgResp->json('data.recent_calls'))->firstWhere('client_call_id', $privCallId);
        $this->assertNotNull($recentItem);
        $this->assertSame($privTicket->id, $recentItem['ticket_id']);
        $this->assertSame($privTicket->ticket_number, $recentItem['ticket_number']);
        $this->assertSame($privResp->json('data.task_id'), $recentItem['task_id']);
    }
}
