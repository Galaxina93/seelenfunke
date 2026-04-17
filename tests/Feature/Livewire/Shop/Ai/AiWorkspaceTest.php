<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use App\Livewire\Shop\Ai\AiWorkspace;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiWorkspaceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Session ID since Livewire uses it
        session()->setId(Str::uuid()->toString());
        session()->start();
    }

    /** @test */
    public function test_renders_ai_chat_component_and_loads_history()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        // Seed some AiChatMemory
        AiChatMemory::create([
            'session_id' => session()->getId(),
            'role' => 'user',
            'content' => 'Hello Funkira',
            'context_data' => [
                'name' => 'Admin',
                'attachments' => ['app/Models/User.php'],
                'local_uploads' => [
                    ['name' => 'test.jpg', 'path' => 'ai-chat-uploads/test.jpg', 'mime' => 'image/jpeg']
                ]
            ]
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->assertStatus(200)
            ->assertViewHas('messages')
            ->assertSee('Hello Funkira')
            ->assertSee('User.php')
            ->assertSet('messages.0.attachments.0', 'app/Models/User.php')
            ->assertSet('messages.0.local_uploads.0.name', 'test.jpg');
    }

    /** @test */
    public function test_can_send_a_message_that_persists_to_db()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        // Seed Default Agent so it doesn't fail on "no agent selected"
        $agent = AiAgent::firstOrCreate(
            ['name' => 'Funkira'],
            ['id' => Str::uuid()->toString(), 'provider' => 'openai', 'model' => 'gemini-1.5-flash', 'is_active' => true]
        );

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            // Selecting agent happens in mount() via activeAgentIds[]
            ->set('activeAgentIds', [$agent->id])
            ->set('input', 'Test Message 123')
            ->set('attachments', ['routes/web.php'])
            ->call('sendMessage');

        // Check if state is reset
        $component->assertSet('input', '')
                  ->assertSet('attachments', []);

        // Assert memory DB persistence
        $this->assertDatabaseHas('ai_chat_memories', [
            'session_id' => session()->getId(),
            'role' => 'user',
            'content' => 'Test Message 123'
        ]);
        
        $mem = AiChatMemory::where('session_id', session()->getId())->orderBy('id', 'desc')->first();
        $this->assertEquals(['routes/web.php'], $mem->context_data['attachments']);
    }

    /** @test */
    public function test_handles_local_file_uploads_correctly()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        Storage::fake('public');

        // Mock a file upload
        $file = UploadedFile::fake()->image('avatar.jpg');

        $agent = AiAgent::firstOrCreate(
            ['name' => 'Funkira'],
            ['id' => Str::uuid()->toString(), 'provider' => 'openai', 'model' => 'gemini-1.5-flash', 'is_active' => true]
        );

        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->set('activeAgentIds', [$agent->id])
            ->set('input', 'Look at this picture')
            ->set('uploadedFiles', [$file])
            ->call('sendMessage')
            ->assertSet('uploadedFiles', []);

        // Validate DB structure
        $mem = AiChatMemory::where('session_id', session()->getId())->orderBy('id', 'desc')->first();
        
        $this->assertNotNull($mem);
        $this->assertArrayHasKey('local_uploads', $mem->context_data);
        $this->assertEquals('avatar.jpg', $mem->context_data['local_uploads'][0]['name']);
        
        // Assert storage path exists on public disk (where AiWorkspace saves it)
        Storage::disk('public')->assertExists($mem->context_data['local_uploads'][0]['path']);
    }

    /** @test */
    public function test_blocks_message_sending_if_no_agent_is_selected()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->set('activeAgentIds', [])
            ->set('input', 'Agent should not respond')
            ->call('sendMessage')
            ->assertSee('FEHLER: Kein Agent für Verarbeitung ausgewählt');
            
        // Memory holds the error message
        $this->assertDatabaseHas('ai_chat_memories', [
            'session_id' => session()->getId(),
            'role' => 'assistant',
        ]);
    }

    /** @test */
    public function test_computes_artifacts_from_storage()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        Storage::fake('local');
        
        $sessionId = session()->getId();
        Storage::disk('local')->put("ai-artifacts/{$sessionId}/implementation_plan.md", "# Test Plan");
        
        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->assertCount('artifacts', 1)
            ->assertSet('artifacts.0.name', 'implementation_plan')
            ->assertSet('artifacts.0.content', '# Test Plan');
    }

    /** @test */
    public function test_computes_global_files_from_chat_memory()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        AiChatMemory::create([
            'session_id' => session()->getId(),
            'role' => 'user',
            'content' => 'Hello',
            'context_data' => [
                'attachments' => ['app/Models/User.php'],
                'local_uploads' => [
                    ['name' => 'test.jpg', 'path' => 'ai-chat-uploads/test.jpg', 'mime' => 'image/jpeg']
                ]
            ]
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            // Should contain 2 files total (1 attachment, 1 upload)
            ->assertCount('globalFiles', 2)
            ->assertSet('globalFiles.0.name', 'User.php')
            ->assertSet('globalFiles.1.name', 'test.jpg');
    }
}
