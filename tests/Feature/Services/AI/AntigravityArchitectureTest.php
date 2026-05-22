<?php

namespace Tests\Feature\Services\AI;

use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiSystemFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AntigravityArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear session before each test
        Session::flush();
        Cache::flush();
    }

    public function test_guardrail_blocks_destructive_tool_without_plan()
    {
        // "system_multi_replace_file" is a destructive tool.
        // It requires an implementation_plan in the session.
        $result = AIFunctionsRegistry::execute('system_multi_replace_file', [
            'file_path' => 'dummy',
            'chunks' => []
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('error', $result['status'] ?? '');
        $this->assertStringContainsString('SYSTEM GUARDRAIL BLOCK', $result['message']);
    }

    public function test_writing_implementation_plan_unlocks_guardrail()
    {
        // First, write the implementation plan artifact.
        // We use AIFunctionsRegistry to test the integration.
        Session::start();
        $sessionId = Session::getId();
        
        $writeResult = AIFunctionsRegistry::execute('system_write_artifact', [
            'artifact_name' => 'implementation_plan',
            'content' => '# My Plan'
        ]);

        $this->assertEquals('success', $writeResult['status']);
        $this->assertTrue(Session::get('has_ai_implementation_plan'), 'Flag was not set in session');

        // Now test that a destructive tool goes through to execution.
        // It should fail with "file_path fehlt" rather than the Guardrail block.
        $destructiveResult = AIFunctionsRegistry::execute('system_multi_replace_file', [
            'file_path' => '', // INTENTIONALLY EMPTY to trigger validation error instead of guardrail
            'chunks' => []
        ]);

        $this->assertEquals('error', $destructiveResult['status']);
        $this->assertStringNotContainsString('SYSTEM GUARDRAIL BLOCK', $destructiveResult['message']);
        $this->assertStringContainsString('file_path fehlt', $destructiveResult['message']);
    }

    public function test_multi_replace_file_executes_correctly()
    {
        $testFile = storage_path('app/test_dummy_file.php');
        file_put_contents($testFile, "<?php\n\nfunction old() {\n    return 1;\n}\n");

        // Need the plan to bypass guardrail if we invoked via AIFunctionsRegistry
        // But we can call the direct method AiSystemFuncs::executeMultiReplaceFile to just unit-test the replace logic
        $result = AiSystemFuncs::executeMultiReplaceFile([
            'file_path' => 'storage/app/test_dummy_file.php',
            'chunks' => [
                [
                    'search_content' => "function old() {\n    return 1;\n}",
                    'replace_content' => "function newFunc() {\n    return 2;\n}"
                ]
            ]
        ]);

        $this->assertEquals('success', $result['status'], "Failed chunks: " . json_encode($result));
        
        $newContent = file_get_contents($testFile);
        $this->assertStringContainsString('function newFunc', $newContent);
        $this->assertStringNotContainsString('function old', $newContent);
        
        @unlink($testFile);
    }

    public function test_system_run_command_creates_async_job()
    {
        $result = AiSystemFuncs::executeRunCommand([
            'command' => 'echo "hello antigravity"'
        ]);

        $this->assertEquals('success', $result['status']);
        $this->assertArrayHasKey('job_id', $result);
        
        $jobId = $result['job_id'];
        
        // Cache must have the PID
        $pid = Cache::get('ai_cmd_pid_' . $jobId);
        $this->assertNotNull($pid);
        
        // Let it "run" for half a second
        usleep(500000);
        
        // Check status
        $statusResult = AiSystemFuncs::executeCommandStatus([
            'job_id' => $jobId
        ]);
        
        $this->assertEquals('success', $statusResult['status']);
        // output might have a newline, let's just assert containing
        $this->assertStringContainsString('hello antigravity', $statusResult['output']);
    }

    public function test_system_generate_docx_report()
    {
        $result = AiSystemFuncs::executeGenerateDocxReport([
            'title' => 'Test-Report-DOCX',
            'content_markdown' => "# Test Titel\nDies ist ein *kursiver* und **fetter** Absatz.\n\n> Dies ist ein Callout.\n\n| Spalte 1 | Spalte 2 |\n| --- | --- |\n| Wert 1 | Wert 2 |",
            'design' => 'generic',
            'target_action' => 'download'
        ]);

        $this->assertEquals('success', $result['status'], "Failed to generate report: " . json_encode($result));
        $this->assertArrayHasKey('_event', $result);
        $this->assertEquals('download-file', $result['_event']['name']);
        
        $downloadUrl = $result['_event']['detail']['url'];
        $this->assertNotNull($downloadUrl);
        
        // Clean up generated file
        $fileName = $result['_event']['detail']['filename'];
        $filePath = 'public/reports/' . $fileName;
        Storage::disk('local')->delete($filePath);
    }
}

