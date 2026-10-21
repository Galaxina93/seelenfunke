<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Services\AI\Workspace\DateitrichterService;

class DateitrichterServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dateitrichter_blocks_path_traversal_attempts(): void
    {
        $service = new DateitrichterService();

        $res1 = $service->processSingleFile('../../etc/passwd');
        $this->assertNull($res1);

        $res2 = $service->processSingleFile("agenten/workspace/../../../config\0/database.php");
        $this->assertNull($res2);
    }

    public function test_dateitrichter_sanitizes_filenames_and_enforces_directory_structure(): void
    {
        Storage::fake('workspace');
        $workspace = Storage::disk('workspace');

        // Create a test file in root
        $workspace->put('agenten/workspace/2026-05-15_Kontoauszug_Sparkasse.txt', "Saldo: 1.500,00 EUR\nSparkasse Kontoauszug Mai 2026");

        $service = new DateitrichterService();
        $result = $service->processSingleFile('agenten/workspace/2026-05-15_Kontoauszug_Sparkasse.txt');

        $this->assertNotNull($result);
        $this->assertEquals('Dokumente', $result['main_folder']);
        $this->assertEquals('Bank', $result['sub_folder']);
        $this->assertTrue($result['moved']);
        $this->assertDatabaseHas('ai_workspace_documents', [
            'file_path' => 'agenten/workspace/Dokumente/Bank/2026-05-15_Kontoauszug_Sparkasse.txt'
        ]);
    }
}
