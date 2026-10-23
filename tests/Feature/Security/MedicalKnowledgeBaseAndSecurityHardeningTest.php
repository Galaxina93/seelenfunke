<?php

namespace Tests\Feature\Security;

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\Functions\AiBrainFuncs;
use App\Services\AI\Functions\AiMapControlFuncs;
use App\Services\AI\Functions\AiSystemFuncs;
use App\Services\AI\Functions\AiTaskFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MedicalKnowledgeBaseAndSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_medical_knowledge_base_contains_full_au_chronology_and_endokrinologikum_lab_findings(): void
    {
        $auEntry = AiKnowledgeBase::where('slug', 'krankschreibungen-au-eau-chronologie-aussteuerung-2016-2026')->first();
        $endoEntry = AiKnowledgeBase::where('slug', 'endokrinologikum-hannover-laborbefunde-humangenetik-hormonstatus-2024')->first();

        $this->assertNotNull($auEntry, 'AU Master-Dossier must exist in AiKnowledgeBase.');
        $this->assertNotNull($endoEntry, 'Endokrinologikum Master-Dossier must exist in AiKnowledgeBase.');

        // Check key details from 14_AU_2025-12-29_bis_2026-01-26_Praxis_Jung.jpg & chronology
        $this->assertStringContainsString('29.12.2025', $auEntry->content);
        $this->assertStringContainsString('26.01.2026', $auEntry->content);
        $this->assertStringContainsString('840902301', $auEntry->content);
        $this->assertStringContainsString('078354200', $auEntry->content);
        $this->assertStringContainsString('F43.9 G', $auEntry->content);
        $this->assertStringContainsString('F48.0 G', $auEntry->content);
        $this->assertStringContainsString('F64.0 G', $auEntry->content);
        $this->assertStringContainsString('29.01.2026', $auEntry->content);

        // Check key details from Endokrinologikum_Laborbefunde.pdf
        $this->assertStringContainsString('Susanne Krusche', $endoEntry->content);
        $this->assertStringContainsString('46,XY', $endoEntry->content);
        $this->assertStringContainsString('nuc ish(DXZ1,SRY)x1', $endoEntry->content);
        $this->assertStringContainsString('Faktor-V-Leiden', $endoEntry->content);
        $this->assertStringContainsString('Antithrombin III', $endoEntry->content);

        // Verify brain_search finds both dossiers via medical synonyms
        $auSearch = AiBrainFuncs::executeSearchBrain([
            'query' => 'Krankschreibung 29.12.2025 26.01.2026 Dorothea Jung Aussteuerung',
        ]);
        $this->assertEquals('success', $auSearch['status']);
        $this->assertNotEmpty($auSearch['knowledge_base']);

        $endoSearch = AiBrainFuncs::executeSearchBrain([
            'query' => 'Endokrinologikum Hannover Karyotyp Humangenetik Thrombophilie',
        ]);
        $this->assertEquals('success', $endoSearch['status']);
        $this->assertNotEmpty($endoSearch['knowledge_base']);
    }

    public function test_unused_scripts_and_archived_medical_folders_are_removed_from_project(): void
    {
        $this->assertFileExists(base_path('scripts/optimize-glb.mjs'));
        $this->assertFileDoesNotExist(base_path('scripts/download-vendor-assets.js'));
        $this->assertFileDoesNotExist(base_path('scripts/import_workspace_documents.php'));
        $this->assertFileDoesNotExist(base_path('scripts/migrate_funkira_knowledge.php'));
        $this->assertFileDoesNotExist(base_path('scripts/workspace_documents.json'));
        $this->assertFileDoesNotExist(base_path('scripts/playwright-scraper.js'));
        $this->assertFileDoesNotExist(base_path('scripts/stealth-scraper.js'));
        $this->assertFileDoesNotExist(base_path('scripts/svg-downloader.js'));

        $this->assertDirectoryDoesNotExist(storage_path('app/agenten/workspace/Gesundheit/Krankschreibungen'));
        $this->assertDirectoryDoesNotExist(storage_path('app/agenten/workspace/Gesundheit/Atteste'));
    }

    public function test_ai_system_and_task_tools_block_path_traversal_and_privilege_escalation(): void
    {
        // 1. executeAnalyzeNeuralError blocks .env and ..
        $envResult = AiSystemFuncs::executeAnalyzeNeuralError(['file_path' => '.env']);
        $this->assertEquals('error', $envResult['status']);

        $traversalResult = AiSystemFuncs::executeAnalyzeNeuralError(['file_path' => '../../etc/passwd']);
        $this->assertEquals('error', $traversalResult['status']);

        // 2. executeCommandStatus blocks non-cmd_ job_id traversal
        $cmdStatusResult = AiSystemFuncs::executeCommandStatus(['job_id' => '../../.env']);
        $this->assertEquals('error', $cmdStatusResult['status']);

        // 3. executeListDirectory blocks .. traversal and hides .env
        $listTraversal = AiSystemFuncs::executeListDirectory(['directory_path' => '../']);
        $this->assertEquals('error', $listTraversal['status']);

        $listRoot = AiSystemFuncs::executeListDirectory(['directory_path' => '']);
        $this->assertEquals('success', $listRoot['status']);
        $this->assertStringNotContainsString('.env', $listRoot['content']);

        // 4. executeOpenNavItem blocks javascript: and external URLs
        $xssNav = AiSystemFuncs::executeOpenNavItem(['url' => 'javascript:alert(1)']);
        $this->assertEquals('error', $xssNav['status']);

        $extNav = AiSystemFuncs::executeOpenNavItem(['url' => 'https://evil.example.com/phish']);
        $this->assertEquals('error', $extNav['status']);

        $safeNav = AiSystemFuncs::executeOpenNavItem(['url' => '/admin/dashboard']);
        $this->assertEquals('success', $safeNav['status']);

        // 5. executeDispatchBackgroundTask blocks destructive tools
        $bgResult = AiSystemFuncs::executeDispatchBackgroundTask([
            'task_description' => 'Run shell command',
            'tool_to_execute' => 'system_run_command',
        ]);
        $this->assertEquals('error', $bgResult['status']);

        // 6. executeAssignToolToRole blocks self-escalation of critical tools
        $role = AiRole::create(['name' => 'TestRole', 'description' => 'Test']);
        $agent = AiAgent::create([
            'name' => 'TestAgent',
            'ai_role_id' => $role->id,
            'is_active' => true,
        ]);
        AiTool::firstOrCreate(
            ['identifier' => 'system_run_command'],
            ['name' => 'Run Command', 'description' => 'Shell']
        );

        $assignResult = AiSystemFuncs::executeAssignToolToRole([
            'tool_identifier' => 'system_run_command',
        ], $agent);
        $this->assertIsString($assignResult);
        $this->assertStringContainsString('Sicherheitsrichtlinie', $assignResult);

        // 7. executeWriteArtifact & executePatchArtifact work safely and strip traversal
        Storage::fake('workspace');
        $writeArt = AiSystemFuncs::executeWriteArtifact([
            'artifact_name' => '../../my_test_plan',
            'content' => "- [ ] Step 1\n- [ ] Step 2",
        ]);
        $this->assertEquals('success', $writeArt['status']);

        $patchArt = AiSystemFuncs::executePatchArtifact([
            'artifact_name' => '../../my_test_plan',
            'search_text' => '- [ ] Step 1',
            'replace_text' => '- [x] Step 1',
        ]);
        $this->assertEquals('success', $patchArt['status']);

        // 8. AiTaskFuncs::executeReadFile & AiMapControlFuncs::executeCameraProcessSnapshot block .. traversal
        $taskFileResult = AiTaskFuncs::executeReadFile([
            'file_path' => 'leitung/tasks/attachments/../../../.env',
        ]);
        $this->assertStringContainsString('Zugriff verweigert', $taskFileResult['message']);

        $snapshotResult = AiMapControlFuncs::executeCameraProcessSnapshot([
            'file_path' => 'agenten/workspace/../../../.env',
        ]);
        $this->assertEquals('error', $snapshotResult['status']);
    }
}
