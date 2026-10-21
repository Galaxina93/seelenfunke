<?php

namespace Tests\Feature\Support;

use App\Models\SupportTelephonyCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwilioCallControllerSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_call_log_accepts_valid_payload()
    {
        $response = $this->postJson('/api/twilio/call-log', [
            'twilio_sid' => 'CA1234567890abcdef',
            'contact_name' => 'Max Mustermann',
            'objective' => 'Kundensupport Termin',
            'transcript' => ['Hallo', 'Guten Tag'],
            'duration_seconds' => 45,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('support_telephony_calls', [
            'twilio_sid' => 'CA1234567890abcdef',
            'contact_name' => 'Max Mustermann',
            'duration_seconds' => 45,
        ]);
    }

    public function test_call_log_rejects_oversized_transcript_payload()
    {
        // 101 items exceeds max:100 limit
        $oversizedTranscript = array_fill(0, 101, 'Spam line');

        $response = $this->postJson('/api/twilio/call-log', [
            'twilio_sid' => 'CA1234567890abcdef',
            'transcript' => $oversizedTranscript,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['transcript']);
    }

    public function test_call_log_rejects_excessive_single_line_length()
    {
        // Single line exceeding max:2000 chars
        $hugeLine = str_repeat('A', 2500);

        $response = $this->postJson('/api/twilio/call-log', [
            'twilio_sid' => 'CA1234567890abcdef',
            'transcript' => [$hugeLine],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['transcript.0']);
    }
}
