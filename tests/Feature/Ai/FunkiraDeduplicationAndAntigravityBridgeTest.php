<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiWidget;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiChatSession;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\AiPromptService;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\AI\GeminiAgent;
use App\Services\System\AntigravityDirectInboxService;
use Database\Seeders\AiAgentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class FunkiraDeduplicationAndAntigravityBridgeTest extends TestCase
{
    use DatabaseTransactions;

    private string $isolatedInboxDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolatedInboxDir = storage_path('framework/testing/ag_funkira_bridge_' . Str::random(10));
        File::makeDirectory($this->isolatedInboxDir, 0700, true, true);

        $this->app->instance(
            AntigravityDirectInboxService::class,
            new AntigravityDirectInboxService($this->isolatedInboxDir)
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->isolatedInboxDir) && File::isDirectory($this->isolatedInboxDir)) {
            File::deleteDirectory($this->isolatedInboxDir);
        }

        parent::tearDown();
    }

    public function test_funkira_has_direct_antigravity_tools_in_core_and_live_voice_like_falcone(): void
    {
        $this->seed(AiAgentSeeder::class);

        $admin = Admin::first() ?? Admin::factory()->create();
        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();

        $coreTools = FunkiraSkillPoolService::getCoreToolIdentifiers();
        $this->assertContains('ag_send_to_antigravity', $coreTools);
        $this->assertContains('ag_check_antigravity_status', $coreTools);
        $this->assertContains('ag_list_pending_blockades', $coreTools);
        $this->assertContains('ag_resolve_pending_blockade', $coreTools);

        $roleToolIdentifiers = $funkira->tools->pluck('identifier')->all();
        $this->assertContains('ag_send_to_antigravity', $roleToolIdentifiers);
        $this->assertContains('ag_check_antigravity_status', $roleToolIdentifiers);

        Auth::guard('admin')->login($admin);
        $richPrompt = AiPromptService::getRichPrompt($funkira);
        $this->assertStringContainsString('ag_send_to_antigravity', $richPrompt);
        $this->assertStringContainsString('ag_check_antigravity_status', $richPrompt);
        $this->assertStringContainsString('KEINE Vorab-Antwort vor dem Werkzeugaufruf', $richPrompt);
        $this->assertStringNotContainsString(' ' . "\u{2013}" . ' ', $richPrompt);

        $liveRes = $this->actingAs($admin, 'admin')
            ->getJson('/api/ai/live-credentials?agent_id=' . $funkira->id);
        $liveRes->assertStatus(200);

        $declaredNames = array_column($liveRes->json('tools.0.functionDeclarations') ?? [], 'name');
        $this->assertContains('ag_send_to_antigravity', $declaredNames);
        $this->assertContains('ag_check_antigravity_status', $declaredNames);

        $liveSys = (string) $liveRes->json('system_instruction');
        $this->assertStringContainsString('KEINE VORAB-SPRACHE VOR WERKZEUGAUFRUFEN', $liveSys);
        $this->assertStringContainsString('ag_send_to_antigravity', $liveSys);
    }

    public function test_funkira_can_send_problem_with_screenshot_and_check_status_while_non_admins_are_blocked(): void
    {
        $this->seed(AiAgentSeeder::class);

        $admin = Admin::first() ?? Admin::factory()->create();
        $funkira = AiAgent::where('name', 'Funkira')->with('role.tools')->firstOrFail();
        $funki = AiAgent::where('name', 'Funki')->with('role.tools')->firstOrFail();

        // 1. Unauthenticated / Customer / Employee / Funki -> strictly blocked
        $guestRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Unbefugter Test',
        ], $funkira);
        $this->assertTrue($guestRes['error'] ?? false);

        $customer = Customer::factory()->create();
        Auth::guard('customer')->login($customer);
        $custRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Kunden-Versuch',
        ], $funkira);
        $this->assertTrue($custRes['error'] ?? false);
        Auth::guard('customer')->logout();

        $employee = Employee::first() ?? Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Staff',
            'email' => 'staff_' . uniqid() . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
            'role' => 'internal',
            'is_active' => true,
        ]);
        Auth::guard('employee')->login($employee);
        $empRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Mitarbeiter-Versuch',
        ], $funkira);
        $this->assertTrue($empRes['error'] ?? false);
        Auth::guard('employee')->logout();

        Auth::guard('admin')->login($admin);
        $funkiAirGapRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Funki Sandbox Test',
        ], $funki);
        $this->assertTrue($funkiAirGapRes['error'] ?? false);
        $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($funkiAirGapRes['message'] ?? ''));

        // 2. Admin + Funkira sends problem including latest uploaded chat screenshot & secret scrubbing
        Storage::disk('public')->makeDirectory('agenten/workspace/chat-medien');
        $relImgPath = 'agenten/workspace/chat-medien/test_funkira_bug_' . uniqid() . '.png';
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        Storage::disk('public')->put($relImgPath, $tinyPng);

        $chatSession = AiChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Funkira Bug-Meldung',
        ]);
        config(['ai.current_session_id' => $chatSession->id]);

        AiChatMemory::create([
            'session_id' => $chatSession->id,
            'role' => 'user',
            'content' => 'Schick diesen Layout-Fehler bitte direkt an Antigravity.',
            'context_data' => [
                'name' => 'Alina',
                'local_uploads' => [
                    ['path' => $relImgPath, 'name' => 'layout_bug.png', 'mime' => 'image/png'],
                ],
            ],
        ]);

        $sendRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Doppelter Button im Konfigurator',
            'body' => 'Im mobilen Studio-Modus überlappt die Leiste (Key AIzaSySecretKey1234567890abcdef).',
            'channel' => 'konfigurator',
            'execution_mode' => 'direct',
            'checklist' => ['Padding unten prüfen', 'Mobile Test ausführen'],
            'attach_latest_upload' => true,
        ], $funkira);

        $this->assertSame('success', $sendRes['status'] ?? null);
        $this->assertSame('shop', $sendRes['channel'] ?? null);
        $this->assertSame('direct', $sendRes['execution_mode'] ?? null);
        $this->assertTrue($sendRes['has_attachment'] ?? false);
        $this->assertFalse($sendRes['idempotent_replay'] ?? true);

        // Immediate duplicate call within same minute is deduplicated idempotently
        $dupRes = AIFunctionsRegistry::execute('ag_send_to_antigravity', [
            'title' => 'Doppelter Button im Konfigurator',
            'body' => 'Im mobilen Studio-Modus überlappt die Leiste (Key AIzaSySecretKey1234567890abcdef).',
            'channel' => 'konfigurator',
            'execution_mode' => 'direct',
            'attach_latest_upload' => true,
        ], $funkira);
        $this->assertSame('success', $dupRes['status'] ?? null);
        $this->assertTrue($dupRes['idempotent_replay'] ?? false);
        $this->assertSame($sendRes['item_id'], $dupRes['item_id']);

        // Status check reads back the exact item with scrubbed secret
        $statusRes = AIFunctionsRegistry::execute('ag_check_antigravity_status', ['limit' => 5], $funkira);
        $this->assertSame('success', $statusRes['status'] ?? null);
        $this->assertCount(1, $statusRes['items'] ?? []);
        $this->assertSame('Doppelter Button im Konfigurator', $statusRes['items'][0]['title']);
        $this->assertSame('shop', $statusRes['items'][0]['channel']);

        $rawInbox = app(AntigravityDirectInboxService::class)->listInbox(5);
        $this->assertStringNotContainsString('AIzaSySecretKey1234567890abcdef', (string) ($rawInbox['items'][0]['body'] ?? ''));

        Storage::disk('public')->delete($relImgPath);
    }

    public function test_tool_loop_does_not_duplicate_preliminary_answer_and_widget_enforces_single_funkira_and_echo_dedup(): void
    {
        $this->seed(AiAgentSeeder::class);

        $admin = Admin::first() ?? Admin::factory()->create();
        Auth::guard('admin')->login($admin);

        $funkira = AiAgent::where('name', 'Funkira')->firstOrFail();
        $extraAgent = AiAgent::where('name', '!=', 'Funkira')
            ->whereNotIn('name', ['Funki', 'Falcon', 'Falcone'])
            ->first();
        if ($extraAgent) {
            $extraAgent->update(['is_active' => true, 'is_in_chat' => true]);
        }
        $funkira->update(['is_active' => true, 'is_in_chat' => true]);

        // 1. Single-Executive Guard & Echo Deduplication in AiWidget
        $widget = Livewire::actingAs($admin, 'admin')->test(AiWidget::class);
        $this->assertSame([$funkira->id], $widget->get('activeAgentIds'));

        $widget->call('handleSpeechEvent', ['text' => 'Einmaliger Hinweis'])
            ->assertDispatched('ai-speech-feedback', text: 'Einmaliger Hinweis');

        // 2. Simulated 2-turn tool loop: preliminary filler in turn 0 must NOT be prepended or double-saved in AiChatMemory
        $session = AiChatSession::create([
            'user_id' => $admin->id,
            'title' => 'Deduplizierungs-Test',
        ]);
        config(['ai.current_session_id' => $session->id]);

        $fakeAgent = new class($funkira) extends GeminiAgent {
            public array $simulatedTurns = [];

            protected function chatLoop(
                array &$messages,
                array &$contextData = [],
                array &$usageData = [],
                array &$eventsData = [],
                int $depth = 0,
                array &$calledTools = [],
                ?\Closure $streamCallback = null
            ): string {
                $turn = $this->simulatedTurns[$depth] ?? ['content' => ''];
                $content = (string) ($turn['content'] ?? '');

                if ($streamCallback && $content !== '') {
                    $streamCallback([
                        'type' => 'text_chunk',
                        'chunk' => $content,
                        'replace' => $depth > 0,
                    ]);
                }

                if (!empty($turn['tool_calls'])) {
                    $messages[] = [
                        'role' => 'assistant',
                        'content' => $content,
                        'tool_calls' => $turn['tool_calls'],
                    ];
                    foreach ($turn['tool_calls'] as $tc) {
                        $res = AIFunctionsRegistry::execute($tc['function']['name'], [], $this->agent);
                        AiChatMemory::create([
                            'session_id' => config('ai.current_session_id'),
                            'role' => 'tool',
                            'content' => 'Werkzeug: ' . $tc['function']['name'],
                            'context_data' => ['args' => [], 'result' => $res],
                        ]);
                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $tc['id'],
                            'content' => json_encode($res),
                        ];
                    }

                    $nextTurnResponse = $this->chatLoop($messages, $contextData, $usageData, $eventsData, $depth + 1, $calledTools, $streamCallback);
                    if (trim($nextTurnResponse) !== '') {
                        return $nextTurnResponse;
                    }
                    return trim($content);
                }

                return $content;
            }
        };

        $fakeAgent->simulatedTurns = [
            0 => [
                'content' => 'Moment Alina, ich prüfe sofort die Uhrzeit.',
                'tool_calls' => [
                    [
                        'id' => 'call_time_1',
                        'type' => 'function',
                        'function' => ['name' => 'system_get_current_time', 'arguments' => '{}'],
                    ],
                ],
            ],
            1 => [
                'content' => 'Hey Alina, es ist genau 17:40 Uhr.',
            ],
        ];

        $streamEvents = [];
        $askResult = $fakeAgent->ask(
            [['role' => 'user', 'content' => 'Wie spät ist es?']],
            function (array $ev) use (&$streamEvents) {
                $streamEvents[] = $ev;
            }
        );

        $this->assertSame('Hey Alina, es ist genau 17:40 Uhr.', $askResult['response']);
        $this->assertStringNotContainsString('Moment Alina', $askResult['response']);
        $this->assertCount(2, $streamEvents);
        $this->assertFalse($streamEvents[0]['replace']);
        $this->assertTrue($streamEvents[1]['replace']);
        $this->assertSame(
            0,
            AiChatMemory::where('session_id', $session->id)->where('role', 'assistant')->count(),
            'GeminiAgent must not insert duplicate intermediate assistant rows before caller persists final reply.'
        );
    }
}
