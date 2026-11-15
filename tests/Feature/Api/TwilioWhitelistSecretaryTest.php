<?php

namespace Tests\Feature\Api;

use App\Models\Management\ManagementCalendarEvent;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\SupportTelephonyCall;
use App\Services\System\AudioMulawCodec;
use App\Services\System\FunkiraPhoneConciergeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioWhitelistSecretaryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.twilio.sid', 'AC_TEST_SEELENFUNKE_SID_1234567890');
        Config::set('services.twilio.wss_url', 'wss://ws.mein-seelenfunke.de/twilio-stream');
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

    public function test_whitelisted_philip_goik_is_bridged_live_without_static_play_and_creates_structured_secretary_summary(): void
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

        // Privater Kalendereintrag: Nur das Zeitfenster ("Belegt") darf an die Sekretärin fließen, niemals der Titel!
        ManagementCalendarEvent::create([
            'title' => 'Geheimer Arzttermin Privat',
            'start_date' => now()->addHours(2),
            'end_date' => now()->addHours(3),
            'is_all_day' => false,
            'category' => 'Privat',
        ]);

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
        // 100% Live-Gespräch ab Sekunde 0.0: KEIN <Play>-Monolog und KEIN <Say> vor dem bidirektionalen Stream!
        $this->assertStringNotContainsString('<Play', $xml);
        $this->assertStringNotContainsString('<Say', $xml);
        $this->assertStringContainsString('wss://ws.mein-seelenfunke.de/twilio-stream', $xml);
        $this->assertStringContainsString('name="greeting_name" value="Philip"', $xml);
        $this->assertStringContainsString('name="relation_type" value="Partner"', $xml);
        $this->assertStringContainsString('name="call_mode" value="inbound_secretary"', $xml);
        $this->assertStringContainsString('name="calendar_events"', $xml);
        $this->assertStringNotContainsString('Geheimer Arzttermin', $xml);

        // Extrahiere einmaliges {callRecordId}:{nonce} planned_call_id und prüfe strukturierte Sekretärinnen-Auswertung
        preg_match('#name="planned_call_id" value="(\d+:[a-f0-9]{64})"#', $xml, $tokenMatch);
        $this->assertNotEmpty($tokenMatch[1] ?? '');

        Config::set('services.gemini.key', 'test-gemini-key');
        Config::set('services.gemini.allow_in_tests', true);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'summary' => 'Philip möchte heute Abend gegen 19:00 Uhr Pizza vom Italiener mitbringen.',
                                'rahmenbedingungen' => [
                                    'Heute Abend gegen 19:00 Uhr',
                                    'Italiener in Gifhorn (Salami & Rucola)',
                                    'Abholung auf dem Heimweg',
                                ],
                                'action_required' => 'Philip bis 18:00 Uhr kurz schreiben, ob 19:00 Uhr passt.',
                                'urgency' => 'hoch',
                                'callback_requested' => true,
                            ], JSON_UNESCAPED_UNICODE),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $logRes = $this->postJson('/api/twilio/call-log', [
            'planned_call_id' => $tokenMatch[1],
            'twilio_sid' => 'CAaaaa2222333344445555666677778888',
            'contact_name' => 'Philip (Philip Goik)',
            'objective' => 'Funkira Secretary',
            'duration_seconds' => 38,
            'transcript' => [
                'KI: Hallo Philip, hier ist Funkira, Alinas persönliche Assistentin! Alina kann gerade kurz nicht selbst ans Telefon gehen, aber ich kläre das gern direkt mit dir. Worum geht es denn?',
                'Anrufer: Hey Funkira, ich wollte heute Abend gegen 19 Uhr Pizza vom Italiener in Gifhorn auf dem Heimweg mitbringen. Sag Alina bitte, sie soll mir bis 18 Uhr kurz schreiben, ob 19 Uhr passt.',
                'KI: Super lieb von dir! Ich halte für Alina fest: Heute Abend gegen 19 Uhr Pizza vom Italiener auf dem Heimweg, und sie gibt dir bis 18 Uhr kurz Bescheid. Bis später, tschüss!',
            ],
        ]);

        $logRes->assertStatus(200)->assertJson(['status' => 'success']);

        $callId = (int) explode(':', $tokenMatch[1])[0];
        $callRecord = SupportTelephonyCall::findOrFail($callId);
        $this->assertSame('completed', $callRecord->status);
        $this->assertStringContainsString('19:00 Uhr Pizza', (string) $callRecord->summary);

        $decodedNext = json_decode((string) $callRecord->next_steps, true);
        $this->assertIsArray($decodedNext);
        $this->assertCount(3, $decodedNext['rahmenbedingungen'] ?? []);
        $this->assertSame('Philip bis 18:00 Uhr kurz schreiben, ob 19:00 Uhr passt.', $decodedNext['action_required'] ?? '');

        // Prüfe Hauptaufgabe + abhakbare Unteraufgabe auf Alinas Dashboard
        $parentTask = ManagementTask::whereNull('parent_id')->latest('id')->first();
        $this->assertNotNull($parentTask);
        $this->assertStringContainsString('📞 Anruf von Philip (Philip Goik)', (string) $parentTask->title);
        $this->assertStringContainsString('Besprochen:', (string) $parentTask->ai_plan);
        $this->assertStringContainsString('Rahmenbedingungen:', (string) $parentTask->ai_plan);
        $this->assertStringContainsString('✓ Heute Abend gegen 19:00 Uhr', (string) $parentTask->ai_plan);
        $this->assertStringContainsString('Für dich zu tun:', (string) $parentTask->ai_plan);

        $subTask = ManagementTask::where('parent_id', $parentTask->id)->first();
        $this->assertNotNull($subTask);
        $this->assertSame('Philip bis 18:00 Uhr kurz schreiben, ob 19:00 Uhr passt.', $subTask->title);

        // Prüfe, dass die strukturierte Sekretärinnen-Zusammenfassung auch im App-Payload (recent_calls) erscheint
        $configPayload = FunkiraPhoneConciergeService::getConfigPayload();
        $this->assertNotEmpty($configPayload['recent_calls']);
        $firstRecent = $configPayload['recent_calls'][0];
        $this->assertSame((string) $callId, $firstRecent['id']);
        $this->assertStringContainsString('19:00 Uhr Pizza', $firstRecent['summary']);
        $this->assertCount(3, $firstRecent['rahmenbedingungen']);
        $this->assertSame('Philip bis 18:00 Uhr kurz schreiben, ob 19:00 Uhr passt.', $firstRecent['action_required']);
        $this->assertCount(3, $firstRecent['transcript']);

        $philip->refresh();
        $this->assertStringContainsString('Pizza', (string) $philip->ai_learned_facts);
        $this->assertStringContainsString('Zu tun:', (string) $philip->ai_learned_facts);
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

        // Ohne Pixel-Signal wird ein Anruf mit Alinas eigener Nummer als From gebührenfrei abgewiesen
        $rejected = $this->post('/api/twilio/inbound', [
            'AccountSid' => 'AC_TEST_SEELENFUNKE_SID_1234567890',
            'CallSid' => 'CAbbbb2222333344445555666677778888',
            'From' => '+4915901966864',
            'To' => '+4953717269023',
        ]);
        $this->assertStringContainsString('<Reject reason="busy"', (string) $rejected->getContent());

        // Jetzt filtert das Pixel Papas Anruf und signalisiert die Umleitung
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
        $this->assertStringNotContainsString('<Play', $xml);
        $this->assertStringContainsString('name="greeting_name" value="Papa"', $xml);
    }

    public function test_audio_mulaw_codec_roundtrip_and_resampling_precision(): void
    {
        $this->assertSame(0xFF, AudioMulawCodec::encodePcmSample(0));
        $this->assertSame(0, AudioMulawCodec::decodeMulawByte(0xFF));

        $posDecoded = AudioMulawCodec::decodeMulawByte(AudioMulawCodec::encodePcmSample(16000));
        $negDecoded = AudioMulawCodec::decodeMulawByte(AudioMulawCodec::encodePcmSample(-16000));
        $this->assertGreaterThan(15000, $posDecoded);
        $this->assertSame(-$posDecoded, $negDecoded);

        // 20 ms Twilio G.711 Frame (160 Bytes @ 8 kHz) -> 640 Bytes 16-Bit PCM @ 16 kHz
        $prev = 0;
        $mulawFrame = str_repeat("\xFF", 160);
        $pcm16k = AudioMulawCodec::mulaw8kToPcm16k($mulawFrame, $prev);
        $this->assertSame(640, strlen($pcm16k));

        // 20 ms Gemini Output (960 Bytes 16-Bit PCM @ 24 kHz) -> exakt 160 Bytes G.711 @ 8 kHz (auch über ungerade Chunk-Grenzen!)
        $pcm24k = '';
        for ($i = 0; $i < 480; $i++) {
            $sample = (int) round(12000 * sin(2 * M_PI * 440 * ($i / 24000)));
            $pcm24k .= pack('v', $sample & 0xFFFF);
        }
        $carry = '';
        $part1 = AudioMulawCodec::pcm24kToMulaw8k(substr($pcm24k, 0, 333), $carry);
        $part2 = AudioMulawCodec::pcm24kToMulaw8k(substr($pcm24k, 333), $carry);
        $this->assertSame(160, strlen($part1 . $part2));
        $this->assertSame('', $carry);
    }
}
