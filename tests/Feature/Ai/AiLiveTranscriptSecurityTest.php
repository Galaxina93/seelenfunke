<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiChatMemory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiLiveTranscriptSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_live_transcript_rejects_payloads_exceeding_character_limits()
    {
        $hugeContent = str_repeat('A', 50001);

        $response = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $hugeContent,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_save_live_transcript_sanitizes_role_and_persists_valid_chunk()
    {
        $sessionId = (string) Str::uuid();

        $response = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $sessionId,
            'role' => 'malicious_injected_role',
            'content' => 'Hallo, dies ist ein legitimer Live-Chunk.',
        ]);

        $response->assertStatus(200);

        $memory = AiChatMemory::where('session_id', $sessionId)->first();
        $this->assertNotNull($memory);
        $this->assertEquals('assistant', $memory->role);
    }

    public function test_camera_snapshot_generates_randomized_filename_preventing_collision()
    {
        Storage::fake('public');
        Storage::fake('workspace');

        $admin = Admin::create([
            'email' => 'admin-camera@example.com',
            'password' => bcrypt('password123'),
            'first_name' => 'Admin',
            'last_name' => 'Camera',
        ]);

        $file1 = UploadedFile::fake()->image('snap1.jpg', 640, 480);
        $file2 = UploadedFile::fake()->image('snap2.jpg', 640, 480);

        $res1 = $this->actingAs($admin, 'admin')->postJson('/api/ai/camera/snapshot', [
            'image' => $file1,
        ]);
        $res1->assertStatus(200);
        $path1 = $res1->json('file_path');

        $res2 = $this->actingAs($admin, 'admin')->postJson('/api/ai/camera/snapshot', [
            'image' => $file2,
        ]);
        $res2->assertStatus(200);
        $path2 = $res2->json('file_path');

        // Both snapshots must have unique randomized filenames even if created within the same second
        $this->assertNotEquals($path1, $path2);
    }
}
