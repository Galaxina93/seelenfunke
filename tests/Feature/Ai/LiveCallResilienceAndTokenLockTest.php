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
}
