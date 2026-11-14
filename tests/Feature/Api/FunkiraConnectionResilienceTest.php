<?php

namespace Tests\Feature\Api;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementContact;
use App\Models\Management\ManagementTask;
use App\Models\System\SystemLog;
use App\Services\System\SystemErrorLogger;
use App\Services\System\SystemSecurityService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FunkiraConnectionResilienceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_bridge_secret_verification_works_under_cached_config_and_logs_403_diagnostics(): void
    {
        config([
            'services.gemini.key' => 'AIzaSyTestServerGeminiKey1234567890',
            'services.gemini.bridge_secret' => null,
            'services.ai.proxy_secret' => 'stage_proxy_secret_test_99',
            'services.twilio.bridge_secret' => null,
        ]);

        $reqOk = Request::create('/api/ai/verify-token', 'POST');
        $reqOk->headers->set('X-AI-Proxy-Secret', 'stage_proxy_secret_test_99');
        $this->assertTrue(SystemSecurityService::isTrustedBridgeRequest($reqOk));

        $reqBad = Request::create('/api/ai/verify-token', 'POST');
        $reqBad->headers->set('X-AI-Proxy-Secret', 'wrong_secret');
        $this->assertFalse(SystemSecurityService::isTrustedBridgeRequest($reqBad));

        // Unauthorized bridge secret returns 403 and writes detailed Code-4003 diagnostic log
        $unauthResp = $this->withHeader('X-AI-Proxy-Secret', 'wrong_secret')
            ->postJson('/api/ai/verify-token', [
                'token' => 'any_token_abc',
            ]);

        $unauthResp->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden');

        $diagLog = SystemLog::where('action_id', 'like', 'portal:ai_bridge_verify:%')
            ->latest('id')
            ->first();
        $this->assertNotNull($diagLog);
        $this->assertSame('gemini_bridge_token_verification_failed', $diagLog->payload['diagnosis']['category'] ?? null);

        // Expired/missing live token returns 403 and logs warning event
        $expiredResp = $this->withHeader('X-AI-Proxy-Secret', 'stage_proxy_secret_test_99')
            ->postJson('/api/ai/verify-token', [
                'token' => 'expired_or_missing_live_token_abc123456',
            ]);

        $expiredResp->assertStatus(403)
            ->assertJsonPath('error', 'Invalid or expired token');

        // Valid live token in Cache returns 200 + Gemini key (pulled once)
        Cache::put('gemini_live_token_valid_test_tok_777', [
            'api_key' => 'AIzaSyTestServerGeminiKey1234567890',
            'user_id' => 'admin-1',
            'agent_name' => 'Funkira',
            'chat_session_id' => 'sess-1',
        ], now()->addMinutes(5));

        $validResp = $this->withHeader('X-AI-Proxy-Secret', 'stage_proxy_secret_test_99')
            ->postJson('/api/ai/verify-token', [
                'token' => 'valid_test_tok_777',
            ]);

        $validResp->assertOk()
            ->assertJsonPath('api_key', 'AIzaSyTestServerGeminiKey1234567890')
            ->assertJsonPath('agent_name', 'Funkira');
    }

    public function test_live_credentials_provides_admin_direct_failover_url_with_no_store_headers_and_isolates_employees(): void
    {
        config([
            'services.gemini.key' => 'AIzaSyTestDirectFallbackKey987654321',
            'services.gemini.ws_url' => 'wss://api-live-bridge.mein-seelenfunke.de',
        ]);

        if (!AiAgent::where('is_active', true)->exists()) {
            AiAgent::create([
                'name' => 'Funkira',
                'wake_word' => 'Funkira',
                'system_prompt' => 'Du bist Funkira.',
                'model' => 'gemini-2.5-flash',
                'is_active' => true,
                'tts_enabled' => true,
                'tts_voice' => 'Kore',
            ]);
        }

        $admin = Admin::factory()->create();
        $adminToken = $admin->createToken('AndroidAdmin', ['*'])->plainTextToken;

        $adminResp = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/ai/live-credentials?client=android_secretary&fallback=direct');

        $adminResp->assertOk();
        $this->assertStringContainsString('no-store', (string) $adminResp->headers->get('Cache-Control'));
        $this->assertNotEmpty($adminResp->json('token'));
        $this->assertStringContainsString(
            'generativelanguage.googleapis.com',
            (string) $adminResp->json('direct_ws_url')
        );
        $this->assertNotEmpty($adminResp->json('origin_url'));

        Auth::forgetGuards();

        // Non-admin employee must NEVER receive direct_ws_url even if ?fallback=direct is requested
        $employee = Employee::create([
            'first_name' => 'Support',
            'last_name' => 'Mitarbeiter',
            'email' => 'support-resilience-' . uniqid() . '@mein-seelenfunke.de',
            'role' => 'support',
            'password' => bcrypt('SecretTest123!'),
        ]);
        $empToken = $employee->createToken('SupportStaff', ['*'])->plainTextToken;

        $empResp = $this->withHeader('Authorization', 'Bearer ' . $empToken)
            ->getJson('/api/ai/live-credentials?client=android_secretary&fallback=direct');

        if ($empResp->status() === 200) {
            $this->assertNull($empResp->json('direct_ws_url'));
        } else {
            $this->assertContains($empResp->status(), [401, 403]);
        }
    }

    public function test_client_error_log_and_eagain_diagnosis_scrub_sensitive_keys(): void
    {
        $admin = Admin::factory()->create();
        $token = $admin->createToken('AdminWeb', ['*'])->plainTextToken;

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/ai/client-error-log', [
                'platform' => 'web',
                'component' => 'funkira_live_ws',
                'severity' => 'error',
                'title' => 'Verbindungsfehler: Resource temporarily unavailable (stage.mein-seelenfunke.de:443)',
                'message' => 'EAI_AGAIN SocketError TryAgain url=wss://generativelanguage.googleapis.com/ws?key=AIzaSySecretLeak999999999',
                'context' => [
                    'direct_ws_url' => 'wss://generativelanguage.googleapis.com/ws?key=AIzaSySecretLeak999999999',
                    'gemini_api_key' => 'AIzaSySecretLeak999999999',
                    'ws_mode' => 'bridge',
                ],
            ]);

        $resp->assertOk()
            ->assertJsonPath('status', 'ok');

        $log = SystemLog::findOrFail($resp->json('log_id'));
        $this->assertSame('network_dns_or_socket_eagain', $log->payload['diagnosis']['category'] ?? null);
        $this->assertStringNotContainsString('AIzaSySecretLeak999999999', (string) $log->message);
        $this->assertSame('[REDACTED]', $log->payload['context']['direct_ws_url'] ?? null);
        $this->assertSame('[REDACTED]', $log->payload['context']['gemini_api_key'] ?? null);

        // Verify phone secretary diagnosis rule too
        $secLog = SystemErrorLogger::recordCrossPlatformEvent([
            'platform' => 'android',
            'component' => 'phone_secretary_telecom',
            'severity' => 'error',
            'title' => 'TelecomManager Anrufannahme fehlgeschlagen',
            'message' => 'TelecomManager acceptRingingCall SecurityException',
        ]);
        $this->assertNotNull($secLog);
        $this->assertSame('funkira_phone_secretary_error', $secLog->payload['diagnosis']['category'] ?? null);
    }

    public function test_standalone_android_secretary_completion_matches_caller_by_phone_without_minipc(): void
    {
        Cache::forget('funkira_phone_concierge_active_call_v1');
        Cache::forget('funkira_phone_concierge_last_summary_v1');

        $admin = Admin::factory()->create();
        $androidToken = $admin->createToken('AndroidStandalone', ['*'])->plainTextToken;

        $this->assertSame(
            '4917657793016',
            \App\Services\System\FunkiraPhoneConciergeService::normalizePhoneDigits('0176 / 57793016')
        );
        $this->assertSame(
            '57793016',
            \App\Services\System\FunkiraPhoneConciergeService::extractMatchSuffix('0176 / 57793016')
        );
        $this->assertSame(
            '491761112223',
            \App\Services\System\FunkiraPhoneConciergeService::normalizePhoneDigits('01761112223 / 05371998877')
        );

        $papa = ManagementContact::create([
            'first_name' => 'StandaloneJan',
            'last_name' => 'Steinhauer',
            'nickname' => 'Papa',
            'relation_type' => 'Vater',
            'phone' => '+49 176 44556677',
            'funkira_call_whitelist' => true,
        ]);

        // Smartphone completes call directly WITHOUT prior Mini-PC /signal and WITHOUT contact_id UUID
        $resp = $this->withHeader('Authorization', 'Bearer ' . $androidToken)
            ->postJson('/api/system/phone-concierge/complete', [
                'call_id' => 'android-standalone-call-42',
                'caller_name' => 'StandaloneJan Steinhauer',
                'greeting_name' => 'Papa',
                'caller_phone' => '0176 44556677',
                'handled_by' => 'android_secretary',
                'summary' => 'Hat sein Paket abgeholt und wollte kurz Bescheid geben, alles bestens.',
                'urgency' => 'niedrig',
                'callback_requested' => false,
            ]);

        $resp->assertOk()
            ->assertJsonPath('success', true);

        $task = ManagementTask::findOrFail($resp->json('data.task_id'));
        $this->assertStringContainsString('Papa', $task->title);
        $this->assertStringContainsString('Smartphone-Sekretärin', (string) $task->ai_plan);
        $this->assertSame('niedrig', $task->priority);

        $papa->refresh();
        $this->assertStringContainsString('Paket abgeholt', (string) $papa->ai_learned_facts);
    }
}
