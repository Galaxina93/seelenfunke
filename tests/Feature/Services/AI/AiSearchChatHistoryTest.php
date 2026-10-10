<?php

namespace Tests\Feature\Services\AI;

use App\Models\Admin\Admin;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiChatSession;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\Functions\AiSystemFuncs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AiSearchChatHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected Admin $admin;
    protected AiChatSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::first() ?? Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');

        $this->session = AiChatSession::create([
            'user_id' => $this->admin->id,
            'title' => 'Test Recherche Session ' . uniqid(),
            'is_archived' => false,
        ]);
    }

    public function test_search_chat_history_finds_recent_messages(): void
    {
        $uniquePhrase = 'Sonnenschein-Code-12345';
        AiChatMemory::create([
            'session_id' => $this->session->id,
            'role' => 'user',
            'content' => 'Hier ist das geheime Codewort ' . $uniquePhrase,
            'context_data' => ['name' => 'Alina']
        ]);

        AiChatMemory::create([
            'session_id' => $this->session->id,
            'role' => 'assistant',
            'content' => 'Ich habe das Codewort ' . $uniquePhrase . ' verstanden.',
            'context_data' => ['name' => 'Funkira']
        ]);

        $result = AIFunctionsRegistry::execute('system_search_chat_history', [
            'time_filter' => 'today',
            'chat_session_id' => $this->session->id,
        ]);

        $this->assertEquals('success', $result['status'] ?? null);
        $this->assertStringContainsString($uniquePhrase, $result['logs']);
        $this->assertStringContainsString('Alina (user)', $result['logs']);
        $this->assertStringContainsString('Funkira (assistant)', $result['logs']);
    }

    public function test_search_chat_history_with_keyword(): void
    {
        $keyword = 'QuantensprungXYZ';
        AiChatMemory::create([
            'session_id' => $this->session->id,
            'role' => 'user',
            'content' => 'Das Projekt hat einen ' . $keyword . ' gemacht.',
            'context_data' => ['name' => 'Alina']
        ]);

        AiChatMemory::create([
            'session_id' => $this->session->id,
            'role' => 'user',
            'content' => 'Eine völlig andere unbedeutende Nachricht.',
            'context_data' => ['name' => 'Alina']
        ]);

        $result = AIFunctionsRegistry::execute('system_search_chat_history', [
            'time_filter' => 'today',
            'keyword' => $keyword,
            'chat_session_id' => $this->session->id,
        ]);

        $this->assertEquals('success', $result['status'] ?? null);
        $this->assertStringContainsString($keyword, $result['logs']);
        $this->assertStringNotContainsString('Eine völlig andere unbedeutende Nachricht', $result['logs']);
    }

    public function test_search_chat_history_falls_back_to_user_sessions_when_no_session_id_provided(): void
    {
        $uniqueWord = 'FallbackTestWord9988';
        AiChatMemory::create([
            'session_id' => $this->session->id,
            'role' => 'user',
            'content' => 'Test mit Wort: ' . $uniqueWord,
            'context_data' => ['name' => 'Alina']
        ]);

        // Call without passing chat_session_id
        $result = AIFunctionsRegistry::execute('system_search_chat_history', [
            'time_filter' => 'today',
        ]);

        $this->assertEquals('success', $result['status'] ?? null);
        $this->assertStringContainsString($uniqueWord, $result['logs']);
    }
}
