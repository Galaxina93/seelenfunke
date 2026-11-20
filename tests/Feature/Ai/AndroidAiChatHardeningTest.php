<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\System\SystemLog;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\VertexAiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AndroidAiChatHardeningTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-aichat-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_vertex_model_formatter_avoids_ai_studio_flash_stall(): void
    {
        $this->assertSame('google/gemini-3.5-flash', VertexAiService::formatOpenAiModel('gemini-3.5-flash', true));
        $this->assertSame('google/gemini-3.5-flash', VertexAiService::formatOpenAiModel('gemini-2.5-flash', true));
        $this->assertSame('gemini-3-flash-preview', VertexAiService::formatOpenAiModel('gemini-3.5-flash', false));
        $this->assertSame('gemini-3-flash-preview', VertexAiService::formatOpenAiModel('gemini-3.5-flash-lite', false));
    }

    public function test_chat_endpoint_strips_legacy_error_bubbles_and_deduplicates_client_message_id(): void
    {
        Cache::flush();
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $agent = AiAgent::create([
            'name' => 'FunkiraTest_' . Str::random(5),
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
            'tts_enabled' => false,
            'tts_provider' => 'none',
        ]);

        FakeAiChatProvider::$capturedHistories = [];
        FakeAiChatProvider::$callCount = 0;

        $this->app->bind(AiProviderInterface::class, function ($app, array $params) {
            return new FakeAiChatProvider($params['agent']);
        });

        $clientMsgId = 'android-msg-' . Str::uuid()->toString();
        $payload = [
            'prompt' => 'funkira ?',
            'agent_id' => $agent->id,
            'client_message_id' => $clientMsgId,
            'history' => [
                ['role' => 'user', 'content' => 'Erster Test'],
                ['role' => 'assistant', 'content' => '[Fehler beim Laden der Antwort]'],
                ['role' => 'user', 'content' => 'funkira ?'],
            ],
        ];

        $resp1 = $this->postJson('/api/ai/chat', $payload);
        $resp1->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('response', 'Ja, ich bin bereit, Alina!');

        $sessionId = $resp1->json('chat_session_id');
        $this->assertNotEmpty($sessionId);
        $this->assertSame(1, FakeAiChatProvider::$callCount);
        $this->assertCount(2, FakeAiChatProvider::$capturedHistories[0]);
        $this->assertSame('Erster Test', FakeAiChatProvider::$capturedHistories[0][0]['content']);
        $this->assertSame('funkira ?', FakeAiChatProvider::$capturedHistories[0][1]['content']);

        $memoryCountAfterFirst = AiChatMemory::where('session_id', $sessionId)->count();
        $this->assertSame(2, $memoryCountAfterFirst);

        // Idempotenter Zweitaufruf mit derselben client_message_id (z. B. nach WLAN/5G-Handover)
        $resp2 = $this->postJson('/api/ai/chat', $payload);
        $resp2->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('deduplicated', true)
            ->assertJsonPath('response', 'Ja, ich bin bereit, Alina!');

        $this->assertSame(1, FakeAiChatProvider::$callCount, 'Idempotenter Retry darf keinen zweiten LLM-Aufruf auslösen.');
        $this->assertSame(2, AiChatMemory::where('session_id', $sessionId)->count(), 'Keine doppelten AiChatMemory-Zeilen.');
    }

    public function test_error_ingest_maps_android_stacktrace_and_honors_unhealed_chat_errors(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin, ['*']);

        $fp = hash('sha256', 'android|airepo|test_' . Str::random(12));
        $resp = $this->postJson('/api/system/error-ingest', [
            'events' => [
                [
                    'platform' => 'android',
                    'severity' => 'error',
                    'component' => 'AiRepo',
                    'title' => 'AiRepo: SocketTimeoutException',
                    'message' => 'sendChatMessage failed: agentId=test (SocketTimeoutException)',
                    'error_class' => 'java.net.SocketTimeoutException',
                    'file' => 'AiRepository.kt',
                    'line' => 118,
                    'stacktrace' => "java.net.SocketTimeoutException: timeout\n\tat de.meinseelenfunke.app.data.repository.AiRepository.sendChatMessage(AiRepository.kt:118)",
                    'fingerprint' => $fp,
                    'auto_healed' => false,
                ],
            ],
        ]);

        $resp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('ingested', 1);

        $logId = $resp->json('log_ids.0');
        $log = SystemLog::findOrFail($logId);
        $this->assertSame('error', $log->status);
        $this->assertFalse((bool) ($log->payload['auto_healed'] ?? true));
        $this->assertSame('java.net.SocketTimeoutException', $log->payload['exception_class'] ?? null);
        $this->assertStringContainsString('AiRepository.kt:118', (string) ($log->payload['stack_excerpt'] ?? ''));
    }
}

class FakeAiChatProvider implements AiProviderInterface
{
    public static array $capturedHistories = [];
    public static int $callCount = 0;

    public function __construct(AiAgent $agent)
    {
    }

    public static function processDirectPrompt(AiAgent $agent, string $prompt): string
    {
        return 'OK';
    }

    public function ask(array $incomingMessages, ?\Closure $streamCallback = null): array
    {
        self::$callCount++;
        self::$capturedHistories[] = $incomingMessages;
        return [
            'response' => 'Ja, ich bin bereit, Alina!',
            'response_raw' => '<speak>Ja, ich bin bereit, Alina!</speak>',
            'history' => $incomingMessages,
            'context_data' => [],
            'events' => [],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 8, 'total_tokens' => 128],
        ];
    }
}
