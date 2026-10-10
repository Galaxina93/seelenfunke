<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use Tests\TestCase;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatSession;
use App\Models\Ai\AiChatMemory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use App\Livewire\Shop\Ai\AiWidget;

class AiWidgetLiveModeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        session()->setId(Str::uuid()->toString());
        session()->start();
    }

    /**
     * Test that the live-credentials endpoint is protected by authentication.
     */
    public function test_live_credentials_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/ai/live-credentials');

        $response->assertStatus(401);
    }

    /**
     * Test that the live-credentials endpoint returns data when authenticated as an admin.
     */
    public function test_live_credentials_endpoint_returns_data_for_authenticated_admins(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $response = $this->actingAs($admin, 'admin')->getJson('/api/ai/live-credentials');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'token',
                     'ws_url',
                     'system_instruction',
                     'agent_id',
                     'agent_name',
                 ]);
    }

    /**
     * Test that saveUserLiveMessage saves spoken words to AiChatMemory.
     */
    public function test_save_user_live_message_persists_to_ai_chat_memory(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $testText = 'Test User Spracheingabe Live Modus ' . uniqid();

        Livewire::actingAs($admin, 'admin')
            ->test(AiWidget::class)
            ->call('saveUserLiveMessage', $testText);

        $this->assertDatabaseHas('ai_chat_memories', [
            'role' => 'user',
            'content' => $testText,
        ]);
    }

    /**
     * Test that saveAssistantLiveMessage saves agent response to AiChatMemory.
     */
    public function test_save_assistant_live_message_persists_to_ai_chat_memory(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::first() ?? AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'Funkira',
            'model' => 'gemini-3.8-flash',
            'color' => 'purple-500',
            'icon' => 'sparkles',
            'is_in_chat' => true,
            'is_active' => true,
        ]);

        $testText = 'Test KI Antwort Live Modus ' . uniqid();

        Livewire::actingAs($admin, 'admin')
            ->test(AiWidget::class)
            ->call('saveAssistantLiveMessage', $testText, $agent->id);

        $this->assertDatabaseHas('ai_chat_memories', [
            'role' => 'assistant',
            'content' => $testText,
        ]);
    }

    /**
     * Test that the saveLiveTranscript endpoint saves the spoken agent response directly.
     */
    public function test_save_live_transcript_endpoint_persists_assistant_message(): void
    {
        $sessionId = (string) Str::uuid();
        $testText = 'Vollständige gesprochene Antwort vom Gemini Live Agenten ' . uniqid();

        $response = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $testText,
            'agent_name' => 'Funkira',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'created',
                     'content' => $testText,
                 ]);

        $this->assertDatabaseHas('ai_chat_memories', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $testText,
        ]);
    }

    /**
     * Test that saveLiveTranscript cleanly skips duplicate messages within 20 seconds.
     */
    public function test_save_live_transcript_endpoint_skips_duplicates(): void
    {
        $sessionId = (string) Str::uuid();
        $testText = 'Test Einmalige Nachricht ' . uniqid();

        // First call creates
        $res1 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $testText,
        ]);
        $res1->assertStatus(200)->assertJson(['status' => 'created']);

        // Second duplicate call skips
        $res2 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $testText,
        ]);
        $res2->assertStatus(200)->assertJson(['status' => 'skipped_duplicate']);

        // Ensure only 1 record exists in DB
        $count = AiChatMemory::where('session_id', $sessionId)->where('role', 'assistant')->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test that saveLiveTranscript updates and extends a partial snippet into the complete sentence.
     */
    public function test_save_live_transcript_endpoint_extends_partial_transcript(): void
    {
        $sessionId = (string) Str::uuid();
        $partial = 'Ja, Alina,';
        $full = 'Ja, Alina, wie kann ich dir heute weiterhelfen?';

        // 1. Partial insert
        $res1 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $partial,
        ]);
        $res1->assertStatus(200)->assertJson(['status' => 'created']);

        // 2. Full extended text update
        $res2 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $full,
        ]);
        $res2->assertStatus(200)->assertJson(['status' => 'updated_extended']);

        // Ensure still only 1 record exists and it contains the full text
        $memories = AiChatMemory::where('session_id', $sessionId)->where('role', 'assistant')->get();
        $this->assertCount(1, $memories);
        $this->assertEquals($full, $memories->first()->content);
    }

    /**
     * Test that live-credentials generates a token containing session_id and agent_id,
     * which can be verified by the internal bridge via verify-token.
     */
    public function test_live_credentials_caches_session_and_agent_id_for_bridge_verification(): void
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::first() ?? AiAgent::create([
            'name' => 'Funkira',
            'wake_word' => 'Funkira',
            'model' => 'gemini-3.8-flash',
            'color' => 'purple-500',
            'icon' => 'sparkles',
            'is_in_chat' => true,
            'is_active' => true,
        ]);

        $customSessionId = (string) Str::uuid();

        // 1. Request credentials as admin
        $resCreds = $this->actingAs($admin, 'admin')->getJson('/api/ai/live-credentials?agent_id=' . $agent->id . '&chat_session_id=' . $customSessionId);
        $resCreds->assertStatus(200);
        $token = $resCreds->json('token');
        $this->assertNotEmpty($token);

        // 2. Internal bridge verifies the token
        $resVerify = $this->postJson('/api/ai/verify-token', ['token' => $token]);
        $resVerify->assertStatus(200)
                  ->assertJson([
                      'chat_session_id' => $customSessionId,
                      'agent_id' => $agent->id,
                      'agent_name' => $agent->name,
                  ]);
        $this->assertNotEmpty($resVerify->json('api_key'));
    }
}
