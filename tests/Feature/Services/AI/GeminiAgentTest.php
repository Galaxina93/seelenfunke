<?php

namespace Tests\Feature\Services\AI;

use Tests\TestCase;
use App\Services\AI\GeminiAgent;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CurlMockRegistry {
    public static $writeCallbacks = [];
    public static $responseStrings = [];
}

class TestableGeminiAgent extends GeminiAgent {
    public array $capturedMessages = [];

    protected function chatLoop(array &$messages, array &$contextData = [], array &$usageData = [], array &$eventsData = [], int $depth = 0, array &$calledTools = [], \Closure $streamCallback = null): string {
        $result = parent::chatLoop($messages, $contextData, $usageData, $eventsData, $depth, $calledTools, $streamCallback);
        $this->capturedMessages = $messages;
        return $result;
    }
}

class GeminiAgentTest extends TestCase {
    use DatabaseTransactions;

    protected function setUp(): void {
        parent::setUp();
        TestCase::$useCurlMock = true;
        CurlMockRegistry::$writeCallbacks = [];
        CurlMockRegistry::$responseStrings = [];
    }

    protected function tearDown(): void {
        TestCase::$useCurlMock = false;
        parent::tearDown();
    }

    /** @test */
    public function test_handles_arguments_as_array_in_stream_correctly() {
        // Create role and tool
        $tool = AiTool::firstOrCreate([
            'identifier' => 'calendar_get_events'
        ], [
            'name' => 'Calendar Get Events',
            'description' => 'Gets calendar events'
        ]);

        $role = AiRole::create([
            'name' => 'Calendar Role',
            'description' => 'Role for calendar'
        ]);
        $role->tools()->attach($tool->id);

        $agent = AiAgent::create([
            'name' => 'Calendar Bot',
            'ai_role_id' => $role->id,
            'system_prompt' => 'You are a calendar assistant.',
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.6
        ]);

        // Prepare a mock response where Gemini returns the arguments as an actual array in tool_calls delta chunk
        $mockResponse = "data: " . json_encode([
            'choices' => [
                [
                    'delta' => [
                        'tool_calls' => [
                            [
                                'index' => 0,
                                'id' => 'call_abc123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'calendar_get_events',
                                    'arguments' => [
                                        'date_from' => '2026-05-25 00:00:00',
                                        'date_to' => '2026-05-25 23:59:59'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]) . "\n" . "data: [DONE]";

        // Store in mock registry
        CurlMockRegistry::$responseStrings = [];
        for ($i = 0; $i < 40; $i++) {
            CurlMockRegistry::$responseStrings[$i] = $mockResponse;
        }

        $geminiAgent = new TestableGeminiAgent($agent);
        
        // Execute the ask method which initiates the chatLoop
        $response = $geminiAgent->ask([
            ['role' => 'user', 'content' => 'Show me my events for today']
        ]);

        $this->assertIsArray($response);
        $captured = $geminiAgent->capturedMessages;
        $toolCallExists = false;
        foreach ($captured as $msg) {
            if ($msg['role'] === 'tool') {
                $toolCallExists = true;
                $this->assertStringNotContainsString('Dein JSON-Format für die Argumente ist ungültig', $msg['content']);
            }
        }
        $this->assertTrue($toolCallExists, "Tool call was not executed/added to captured messages");
    }

    /** @test */
    public function test_correct_json_error_message_is_logged_and_returned_when_parsing_fails() {
        // Create role and tool
        $tool = AiTool::firstOrCreate([
            'identifier' => 'calendar_get_events'
        ], [
            'name' => 'Calendar Get Events',
            'description' => 'Gets calendar events'
        ]);

        $role = AiRole::create([
            'name' => 'Calendar Role',
            'description' => 'Role for calendar'
        ]);
        $role->tools()->attach($tool->id);

        $agent = AiAgent::create([
            'name' => 'Calendar Bot',
            'ai_role_id' => $role->id,
            'system_prompt' => 'You are a calendar assistant.',
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.6
        ]);

        // Prepare a mock response where Gemini returns an invalid JSON string
        $mockResponse = "data: " . json_encode([
            'choices' => [
                [
                    'delta' => [
                        'tool_calls' => [
                            [
                                'index' => 0,
                                'id' => 'call_xyz789',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'calendar_get_events',
                                    'arguments' => '{"date_from": "2026-05-25 00:00:00",}' // trailing comma is invalid in JSON
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]) . "\n" . "data: [DONE]";

        CurlMockRegistry::$responseStrings = [];
        for ($i = 0; $i < 40; $i++) {
            CurlMockRegistry::$responseStrings[$i] = $mockResponse;
        }

        $geminiAgent = new TestableGeminiAgent($agent);
        
        $response = $geminiAgent->ask([
            ['role' => 'user', 'content' => 'Show me my events for today']
        ]);

        $this->assertIsArray($response);
        $captured = $geminiAgent->capturedMessages;
        $toolErrorFound = false;
        foreach ($captured as $msg) {
            if ($msg['role'] === 'tool' && str_contains($msg['content'], 'Dein JSON-Format für die Argumente ist ungültig')) {
                $toolErrorFound = true;
                $this->assertStringNotContainsString('Parse Error: No error', $msg['content']);
                $this->assertStringContainsString('Syntax error', $msg['content']);
            }
        }
        $this->assertTrue($toolErrorFound, "Tool error was not registered in captured messages");
    }
}
