<?php

namespace Tests\Feature\Api;

use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\SupportTelephonyCall;
use App\Services\System\FunkiraPhoneConciergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TwilioWhitelistSecretaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.twilio.sid', 'AC_TEST_SEELENFUNKE_SID_1234567890');
        Config::set('services.twilio.wss_url', 'wss://api-live-bridge.mein-seelenfunke.de/twilio-stream');
        Config::set('services.twilio.phone_number', '+4953717269023');
        FunkiraPhoneConciergeService::updateConfig(true, 0);
    }

    public function test_unknown_caller_is_rejected_free_of_charge_without_creating_logs_or_tasks(): void
    {
        $callsBefore = SupportTelephonyCall::count();
        $tasksBefore = ManagementTask::count();

        $res = $this->post('/api/twilio/inbound', [
            'AccountSid' => 'AC_TEST_SEELENFUNKE_SID_1234567890',
            'CallSid' => 'CA11112222333344445555666677778888',
            'From' => '+4917699998888',
            'To' => '+4953717269023',
        ]);

        $res->assertStatus(200);
        $xml = $res->getContent();
        $this->assertStringContainsString('<Reject reason="busy"', (string) $xml);
        $this->assertStringNotContainsString('<Stream', (string) $xml);
        $this->assertSame($callsBefore, SupportTelephonyCall::count());
        $this->assertSame($tasksBefore, ManagementTask::count());
    }

    public function test_whitelisted_philip_goik_is_greeted_as_philip_and_bridged_and_creates_dashboard_todo(): void
    {
        $philip = ManagementContact::create([
            'first_name' => 'Philip',
            'last_name' => 'Goik',
            'nickname' => 'Liebster Mensch',
            'relation_type' => 'Partner',
            'phone' => '+49 176 52784439',
            'funkira_call_whitelist' => true,
            'is_favorite' => true,
        ]);

        // Fake cached Aoede WAV so <Play> token endpoint is exercised without hitting live Gemini TTS in testing
        $fakeWav = base64_encode("RIFF\x24\x00\x00\x00WAVEfmt ");
        Cache::put(
            'gemini_tts_wav_v1_' . sha1('Aoede|' . FunkiraPhoneConciergeService::buildOpeningGreetingText('Philip')),
            $fakeWav,
            now()->addMinutes(5)
        );

        $res = $this->post('/api/twilio/inbound', [
            'AccountSid' => 'AC_TEST_SEELENFUNKE_SID_1234567890',
            'CallSid' => 'CAaaaa2222333344445555666677778888',
            'From' => '+4917652784439',
            'ForwardedFrom' => '+4915901966864',
            'To' => '+4953717269023',
        ]);

        $res->assertStatus(200);
        $xml = (string) $res->getContent();
        $this->assertStringNotContainsString('<Reject', $xml);
        $this->assertStringNotContainsString('both_tracks', $xml);
        $this->assertStringContainsString('<Play>', $xml);
        $this->assertStringContainsString('wss://api-live-bridge.mein-seelenfunke.de/twilio-stream', $xml);
        $this->assertStringContainsString('name="greeting_name" value="Philip"', $xml);
        $this->assertStringContainsString('name="call_mode" value="inbound_secretary"', $xml);

        // Verify <Play> WAV URL returns 200 audio/wav
        $this->assertMatchesRegularExpression('#/api/twilio/greeting/([a-f0-9]+)\.wav#', $xml);
        preg_match('#(/api/twilio/greeting/[a-f0-9]+\.wav)#', $xml, $playMatch);
        $wavRes = $this->get($playMatch[1]);
        $wavRes->assertStatus(200);
        $this->assertStringContainsString('audio/wav', (string) $wavRes->headers->get('Content-Type'));

        // Extract one-time {callRecordId}:{nonce} planned_call_id and verify callLog succeeds even without X-Bridge-Secret
        preg_match('#name="planned_call_id" value="(\d+:[a-f0-9]{64})"#', $xml, $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? '');

        $logRes = $this->postJson('/api/twilio/call-log', [
            'planned_call_id' => $tokenMatch[1],
            'twilio_sid' => 'CAaaaa2222333344445555666677778888',
            'contact_name' => 'Philip (Philip Goik)',
            'objective' => 'Funkira Secretary',
            'duration_seconds' => 24,
            'transcript' => [
                'Anrufer: Hey Funkira, sag Alina bitte, dass ich heute Abend Pizza mitbringe und sie kurz zurückrufen soll.',
                'KI: Alles klar lieber Philip, ich richte Alina sofort aus, dass du heute Abend Pizza mitbringst!',
            ],
        ]);

        $logRes->assertStatus(200)->assertJson(['status' => 'success']);

        $callId = (int) explode(':', $tokenMatch[1])[0];
        $callRecord = SupportTelephonyCall::findOrFail($callId);
        $this->assertSame('completed', $callRecord->status);
        $this->assertStringContainsString('Pizza', (string) $callRecord->summary);

        $latestTask = ManagementTask::latest('id')->first();
        $this->assertNotNull($latestTask);
        $this->assertStringContainsString('📞 Anruf von Philip (Philip Goik)', (string) $latestTask->title);
        $this->assertStringContainsString('Pizza', (string) $latestTask->title);

        $philip->refresh();
        $this->assertStringContainsString('Pizza', (string) $philip->ai_learned_facts);
    }

    public function test_carrier_redirecting_number_fallback_matches_only_when_pixel_signaled_whitelist_diversion(): void
    {
        $papa = ManagementContact::create([
            'first_name' => 'Jan',
            'last_name' => 'Steinhauer',
            'nickname' => 'Papa',
            'relation_type' => 'Vater',
            'phone' => '+49 176 57793016',
            'funkira_call_whitelist' => true,
        ]);

        // Without Pixel signal, a call presenting only Alina's own number as From (without whitelisted ForwardedFrom) is rejected
        $rejected = $this->post('/api/twilio/inbound', [
            'AccountSid' => 'AC_TEST_SEELENFUNKE_SID_1234567890',
            'CallSid' => 'CAbbbb2222333344445555666677778888',
            'From' => '+4915901966864',
            'To' => '+4953717269023',
        ]);
        $this->assertStringContainsString('<Reject reason="busy"', (string) $rejected->getContent());

        // Now Pixel screens Papa's call and signals diversion
        $signal = FunkiraPhoneConciergeService::signalCallState([
            'state' => 'ringing',
            'contact_id' => $papa->id,
            'caller_phone' => 'sip:+4917657793016@ims.mnc001.mcc262.3gppnetwork.org',
        ]);
        $this->assertEmpty($signal['rejected'] ?? false);

        $accepted = $this->post('/api/twilio/inbound', [
            'AccountSid' => 'AC_TEST_SEELENFUNKE_SID_1234567890',
            'CallSid' => 'CAcccc2222333344445555666677778888',
            'From' => '+4915901966864',
            'To' => '+4953717269023',
        ]);
        $xml = (string) $accepted->getContent();
        $this->assertStringNotContainsString('<Reject', $xml);
        $this->assertStringContainsString('name="greeting_name" value="Papa"', $xml);
    }
}
