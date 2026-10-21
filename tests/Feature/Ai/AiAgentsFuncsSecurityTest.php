<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiWorkspaceDocument;
use App\Services\AI\Functions\AiAgentsFuncs;

class AiAgentsFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private $mockClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClass = new class {
            use AiAgentsFuncs;

            public static function callSecureWorkspacePath(string $path): string
            {
                $method = new \ReflectionMethod(static::class, 'secureWorkspacePath');
                $method->setAccessible(true);
                return $method->invoke(null, $path);
            }
        };
    }

    public function test_secure_workspace_path_prevents_directory_traversal(): void
    {
        // Test standard traversal
        $path1 = $this->mockClass::callSecureWorkspacePath('../../etc/passwd');
        $this->assertEquals('agenten/workspace/etc/passwd', $path1);

        // Test nested trick traversal
        $path2 = $this->mockClass::callSecureWorkspacePath('....//....//secret');
        $this->assertEquals('agenten/workspace/secret', $path2);

        // Test backslashes and null bytes
        $path3 = $this->mockClass::callSecureWorkspacePath("..\\..\\config\0/database.php");
        $this->assertEquals('agenten/workspace/config/database.php', $path3);
    }

    public function test_move_agent_to_department_escapes_like_wildcards(): void
    {
        $dept = AiDepartment::create([
            'name' => 'Marketing',
            'order_index' => 1
        ]);

        $agent1 = AiAgent::create([
            'name' => 'Marketi',
            'system_prompt' => 'prompt',
            'is_active' => true
        ]);

        $agent2 = AiAgent::create([
            'name' => 'Buchi',
            'system_prompt' => 'prompt',
            'is_active' => true
        ]);

        // Wildcard '%' should not match random agent if search is literal '%'
        $result = $this->mockClass::executeMoveAgentToDepartment([
            'agent_name' => '%',
            'department_name' => 'Marketing'
        ]);

        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('nicht gefunden', $result['message']);
    }

    public function test_workspace_find_documents_escapes_category_wildcards(): void
    {
        AiWorkspaceDocument::create([
            'filename' => 'doc1.pdf',
            'file_path' => 'agenten/workspace/Dokumente/doc1.pdf',
            'title' => 'Test Doc',
            'category' => 'Finance',
            'file_size' => 1024
        ]);

        // Querying for category '%' with wildcard escaping shouldn't crash
        $res = $this->mockClass::executeWorkspaceFindDocuments([
            'category' => '%',
            'limit' => 10
        ]);

        $this->assertEquals('success', $res['status']);
        $this->assertEquals(0, $res['count']);
    }
}
