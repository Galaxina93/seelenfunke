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
        $chatSession = \App\Models\Ai\AiChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Test Session'
        ]);

        // Seed some AiChatMemory
        AiChatMemory::create([
            'session_id' => $chatSession->id,
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

        $chatSessionId = $component->get('currentChatSessionId');

        // Check if state is reset
        $component->assertSet('input', '')
                  ->assertSet('attachments', []);

        // Assert memory DB persistence
        $this->assertDatabaseHas('ai_chat_memories', [
            'session_id' => $chatSessionId,
            'role' => 'user',
            'content' => 'Test Message 123'
        ]);
        
        $mem = AiChatMemory::where('session_id', $chatSessionId)->orderBy('id', 'desc')->first();
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

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->set('activeAgentIds', [$agent->id])
            ->set('input', 'Look at this picture')
            ->set('uploadedFiles', [$file])
            ->call('sendMessage')
            ->assertSet('uploadedFiles', []);

        $chatSessionId = $component->get('currentChatSessionId');

        // Validate DB structure
        $mem = AiChatMemory::where('session_id', $chatSessionId)->orderBy('id', 'desc')->first();
        
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

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->set('activeAgentIds', [])
            ->set('input', 'Agent should not respond')
            ->call('sendMessage')
            ->call('processAutoRouting')
            ->assertSee('FEHLER: Kein Agent für Verarbeitung ausgewählt');
            
        $chatSessionId = $component->get('currentChatSessionId');

        // Memory holds the error message
        $this->assertDatabaseHas('ai_chat_memories', [
            'session_id' => $chatSessionId,
            'role' => 'assistant',
        ]);
    }

    /** @test */
    public function test_computes_artifacts_from_storage()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        
        $chatSession = \App\Models\Ai\AiChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Test Session'
        ]);

        \App\Models\Ai\AiArtifact::create([
            'session_id' => $chatSession->id,
            'user_id' => $admin->id,
            'name' => 'implementation_plan.md',
            'content' => '# Test Plan',
            'summary' => 'Test Summary',
            'type' => 'implementation_plan'
        ]);
        
        Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class)
            ->assertCount('artifacts', 1)
            ->assertSet('artifacts.0.name', 'implementation_plan')
            ->assertSet('artifacts.0.content', '# Test Plan');

        @unlink(storage_path('app/agenten/workspace/Berufsleben/Projekte/implementation_plan.md'));
    }

    /** @test */
    public function test_computes_global_files_from_chat_memory()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $chatSession = \App\Models\Ai\AiChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Test Session'
        ]);

        AiChatMemory::create([
            'session_id' => $chatSession->id,
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

    /** @test */
    public function test_workspace_disk_points_directly_to_app_agenten()
    {
        $resolvedPath = Storage::disk('workspace')->path('agenten/workspace');
        $expectedPath = storage_path('app/agenten/workspace');

        $this->assertEquals($expectedPath, $resolvedPath);
        $this->assertTrue(is_dir($expectedPath), 'storage/app/agenten/workspace directory must exist');
    }

    /** @test */
    public function test_workspace_files_are_stored_under_app_agenten_and_not_private_or_ai_workspace()
    {
        $testRelativePath = 'agenten/workspace/Dokumente/Berichte/unit_test_probe.txt';
        Storage::disk('workspace')->put($testRelativePath, 'Seelenfunke Probe');

        // Must exist physically in storage/app/agenten/workspace
        $this->assertFileExists(storage_path('app/' . $testRelativePath));

        // Must NOT exist in old legacy folders
        $this->assertDirectoryDoesNotExist(storage_path('app/private/agenten'));
        $this->assertDirectoryDoesNotExist(storage_path('app/ai_workspace'));

        // Clean up test file
        Storage::disk('workspace')->delete($testRelativePath);
    }

    /** @test */
    public function test_workspace_file_route_is_private_and_requires_admin_auth()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        // 1. Unauthenticated guest request must be blocked (private)
        $guestResponse = $this->get('/admin/ai/workspace/file?path=agenten/workspace/Dokumente/Allgemein/alina.jpg');
        $guestResponse->assertRedirect('/login');

        // 2. Authenticated admin can view valid workspace file
        $testRelativePath = 'agenten/workspace/Dokumente/Berichte/auth_test_probe.txt';
        Storage::disk('workspace')->put($testRelativePath, 'Test file content');

        $adminResponse = $this->actingAs($admin, 'admin')
            ->get('/admin/ai/workspace/file?path=' . $testRelativePath);
        $adminResponse->assertStatus(200);

        Storage::disk('workspace')->delete($testRelativePath);

        // 3. Traversal attempt with .. must return 404
        $traversalResponse = $this->actingAs($admin, 'admin')
            ->get('/admin/ai/workspace/file?path=../app/Http/Kernel.php');
        $traversalResponse->assertStatus(404);

        // 4. Nonexistent file must return 404
        $notFoundResponse = $this->actingAs($admin, 'admin')
            ->get('/admin/ai/workspace/file?path=agenten/workspace/nonexistent_file_xyz.pdf');
        $notFoundResponse->assertStatus(404);
    }

    /** @test */
    public function test_can_switch_to_workspace_workflows_and_cronjobs_tabs()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiWorkspace::class);

        foreach (['workspace', 'chat', 'files', 'health', 'workflows', 'cronjobs'] as $tab) {
            $component->set('activeTab', $tab)
                ->assertSet('activeTab', $tab);

            $this->assertDatabaseHas('ai_user_workspace_settings', [
                'user_id' => $admin->id,
                'active_tab' => $tab,
            ]);
        }

        // Invalid tab falls back to chat
        $component->set('activeTab', 'invalid-tab')
            ->assertSet('activeTab', 'chat');
    }
}
