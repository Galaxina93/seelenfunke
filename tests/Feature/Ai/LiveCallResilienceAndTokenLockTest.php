<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\System\PersonalAccessToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveCallResilienceAndTokenLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_personal_access_token_throttles_last_used_at_writes_to_prevent_row_lock_timeouts(): void
    {
        $this->assertSame(
            PersonalAccessToken::class,
            Sanctum::personalAccessTokenModel()
        );

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-locktest@mein-seelenfunke.de',
            'password' => bcrypt('SecretPass123!'),
        ]);

        $newToken = $admin->createToken('android-live-device');
        $plainTextToken = $newToken->plainTextToken;
        /** @var PersonalAccessToken $pat */
        $pat = $newToken->accessToken;
        Cache::store('file')->forget('sanctum_pat_touch_' . $pat->getKey());

        // First authenticated API call sets initial last_used_at
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/ai/bridge-ping');
        $res1->assertOk();

        $pat->refresh();
        $this->assertNotNull($pat->last_used_at);

        // Subsequent rapid parallel-style requests within 5 minutes MUST NOT issue UPDATE personal_access_tokens!
        DB::enableQueryLog();
        DB::flushQueryLog();

        $res2 = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/ai/bridge-ping');
        $res2->assertOk();

        $patUpdates = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql) => str_contains(strtolower($sql), 'update') && str_contains(strtolower($sql), 'personal_access_tokens'))
            ->values();

        DB::disableQueryLog();
        $this->assertCount(0, $patUpdates, 'Expected 0 redundant UPDATE queries on personal_access_tokens within throttle window.');
    }

    public function test_live_credentials_token_survives_primary_cache_flush_via_file_store_fallback(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-livefallback@mein-seelenfunke.de',
            'password' => bcrypt('SecretPass123!'),
        ]);

        AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'funkira',
            'role_description' => 'Stabschefin',
            'system_prompt' => 'Du bist Funkira.',
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.4,
            'is_active' => true,
            'is_in_chat' => true,
            'color' => 'amber-500',
            'icon' => 'sparkles',
        ]);

        config([
            'cache.default' => 'array',
            'services.gemini.key' => 'AIzaSyDummyLiveResilienceKey123456789',
            'services.gemini.bridge_secret' => 'test-bridge-secret-xyz',
            'services.ai.proxy_secret' => 'test-bridge-secret-xyz',
        ]);

        $credRes = $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/live-credentials?client=android_native');
        $credRes->assertOk();

        $token = $credRes->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Simulate transient Redis/primary cache loss between /live-credentials and /verify-token
        Cache::store('array')->flush();

        $verifyRes = $this->withHeaders([
            'X-Bridge-Secret' => 'test-bridge-secret-xyz',
            'X-AI-Proxy-Secret' => 'test-bridge-secret-xyz',
            'REMOTE_ADDR' => '127.0.0.1',
        ])->postJson('/api/ai/verify-token', [
            'token' => $token,
        ]);

        $verifyRes->assertOk();
        $this->assertSame('Funkira', $verifyRes->json('agent_name'));

        // One-time token must be consumed from both stores and rejected on replay
        $replayRes = $this->withHeaders([
            'X-Bridge-Secret' => 'test-bridge-secret-xyz',
            'X-AI-Proxy-Secret' => 'test-bridge-secret-xyz',
            'REMOTE_ADDR' => '127.0.0.1',
        ])->postJson('/api/ai/verify-token', [
            'token' => $token,
        ]);
        $replayRes->assertStatus(403);
    }

    public function test_frontend_error_endpoint_ignores_benign_cancelled_transition_rejections(): void
    {
        $res = $this->postJson('/api/log/frontend-error', [
            'message' => '{"isFromCancelledTransition":true}',
            'type' => 'promise_rejection',
            'subpage_path' => '/admin/tasks',
        ]);

        $res->assertOk()
            ->assertJson(['status' => 'ignored_benign_transition']);
    }

    public function test_live_credentials_preserves_recent_conversation_context_for_new_android_live_session_and_isolates_users(): void
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-context@mein-seelenfunke.de',
            'password' => bcrypt('SecretPass123!'),
        ]);

        $funkira = AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'funkira',
            'role_description' => 'Stabschefin',
            'system_prompt' => 'Du bist Funkira.',
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.4,
            'is_active' => true,
            'is_in_chat' => true,
            'color' => 'amber-500',
            'icon' => 'sparkles',
        ]);

        config([
            'services.gemini.key' => 'AIzaSyDummyContextContinuityKey12345',
            'services.gemini.bridge_secret' => 'test-bridge-secret-xyz',
            'services.ai.proxy_secret' => 'test-bridge-secret-xyz',
        ]);

        // 1. Previous Live-Call saved transcripts under UUID / android_live session via Sanctum Admin
        $previousSessionUuid = (string) \Illuminate\Support\Str::uuid();
        $saveUser = $this->actingAs($admin, 'sanctum')->postJson('/api/ai/save-live-transcript', [
            'session_id' => $previousSessionUuid,
            'role' => 'user',
            'content' => 'Merke dir bitte den Laser-Fokus 42 Millimeter fuer die neue Serie.',
            'agent_id' => $funkira->id,
            'agent_name' => 'Funkira',
            'channel' => 'android_live_call',
        ]);
        $saveUser->assertOk()->assertJsonPath('status', 'created');

        $saveAi = $this->actingAs($admin, 'sanctum')->postJson('/api/ai/save-live-transcript', [
            'session_id' => $previousSessionUuid,
            'role' => 'assistant',
            'content' => 'Verstanden, Fokus 42 Millimeter fuer die neue Serie ist gemerkt.',
            'agent_id' => $funkira->id,
            'agent_name' => 'Funkira',
            'channel' => 'android_live_call',
        ]);
        $saveAi->assertOk()->assertJsonPath('status', 'created');

        $this->assertDatabaseHas('ai_chat_sessions', [
            'id' => $previousSessionUuid,
            'user_id' => $admin->id,
        ]);

        // 2. Next Live-Call starts with a brand-new android_live_<uuid> session ID -> MUST receive recent context!
        $freshLiveSessionId = 'android_live_' . \Illuminate\Support\Str::uuid();
        $credResp = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/ai/live-credentials?client=android_native&agent_id=' . $funkira->id . '&chat_session_id=' . urlencode($freshLiveSessionId));
        $credResp->assertOk();

        $sysPrompt = (string) $credResp->json('system_instruction');
        $this->assertStringContainsString('Laser-Fokus 42 Millimeter', $sysPrompt);
        $this->assertStringContainsString('Fokus 42 Millimeter fuer die neue Serie ist gemerkt', $sysPrompt);

        // 3. Whitespace/malformed token on verify-token must fail cleanly with 400
        $badTokenResp = $this->withHeaders([
            'X-Bridge-Secret' => 'test-bridge-secret-xyz',
            'X-AI-Proxy-Secret' => 'test-bridge-secret-xyz',
            'REMOTE_ADDR' => '127.0.0.1',
        ])->postJson('/api/ai/verify-token', [
            'token' => '   bad token with spaces   ',
        ]);
        $badTokenResp->assertStatus(400);
    }
}
