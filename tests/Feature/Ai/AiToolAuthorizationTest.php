<?php

namespace Tests\Feature\Ai;

use Tests\TestCase;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use App\Services\AI\AIFunctionsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class AiToolAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_cannot_execute_unassigned_tool()
    {
        $role = AiRole::create([
            'id' => Str::uuid(),
            'name' => 'Support Assistant',
        ]);

        $allowedTool = AiTool::firstOrCreate(
            ['identifier' => 'system_get_current_time'],
            ['id' => Str::uuid(), 'name' => 'Current Time']
        );

        $role->tools()->attach($allowedTool->id);

        $agent = AiAgent::create([
            'id' => Str::uuid(),
            'name' => 'Funki Support',
            'ai_role_id' => $role->id,
            'is_active' => true,
        ]);

        // 1. Calling an unassigned tool (e.g. finance_get_monthly_stats) must be blocked
        $result = AIFunctionsRegistry::execute('finance_get_monthly_stats', ['year' => 2026], $agent);

        $this->assertTrue($result['error'] ?? false);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('PERMISSION DENIED', $result['message']);

        // 2. Calling an allowed tool must succeed
        $allowedResult = AIFunctionsRegistry::execute('system_get_current_time', [], $agent);
        $this->assertArrayNotHasKey('error', $allowedResult);
        $this->assertArrayHasKey('current_time', $allowedResult);
    }
}
