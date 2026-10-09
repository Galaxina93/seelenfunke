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
}
