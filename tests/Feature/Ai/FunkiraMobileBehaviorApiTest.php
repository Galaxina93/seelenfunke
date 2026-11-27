<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiBehaviorFeedback;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Models\Employee\Employee;
use App\Services\AI\AiBehaviorAnalysisService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FunkiraMobileBehaviorApiTest extends TestCase
{
    use DatabaseTransactions;

    private function createActorsAndAgents(): array
    {
        Cache::flush();
        AiBehaviorAnalysisService::clearDirectivesCache();

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Leitung',
            'email' => 'alina-mobile-behavior-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);

        $employee = Employee::create([
            'first_name' => 'Max',
            'last_name' => 'Mitarbeiter',
            'email' => 'emp-mobile-behavior-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);

        $dept = AiDepartment::firstOrCreate(
            ['name' => 'Agenten'],
            ['description' => 'KI-Agenten Abteilung', 'color' => '#C5A059']
        );

        $funkira = AiAgent::where('name', 'Funkira')->first() ?? AiAgent::create([
            'name' => 'Funkira',
            'ai_department_id' => $dept->id,
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
            'is_in_chat' => true,
        ]);

        $funki = AiAgent::where('name', 'Funki')->first() ?? AiAgent::create([
            'name' => 'Funki',
            'ai_department_id' => $dept->id,
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
            'is_in_chat' => false,
        ]);

        return [$admin, $employee, $funkira, $funki];
    }

    public function test_mobile_behavior_endpoints_require_admin_authentication_and_block_employees(): void
    {
        [$admin, $employee] = $this->createActorsAndAgents();

        // 1. Unauthentifiziert: 401
        $this->getJson('/api/ai/funkira/behavior/summary')->assertStatus(401);
        $this->postJson('/api/ai/funkira/behavior/feedback', [
            'polarity' => 'praise',
            'channel' => 'android_app',
        ])->assertStatus(401);

        // 2. Mitarbeiter (kein Admin): 403 auf allen 4 Endpunkten
        Sanctum::actingAs($employee, ['*']);
        $this->getJson('/api/ai/funkira/behavior/summary')->assertStatus(403);
        $this->postJson('/api/ai/funkira/behavior/feedback', [
            'polarity' => 'praise',
            'channel' => 'android_app',
        ])->assertStatus(403);
        $this->postJson('/api/ai/funkira/behavior/rules/approve', [
            'channel' => 'android_live_call',
            'category' => 'tone_length',
        ])->assertStatus(403);
        $this->postJson('/api/ai/funkira/behavior/rules/dismiss', [
            'channel' => 'android_live_call',
            'category' => 'tone_length',
        ])->assertStatus(403);

        // 3. Eingeschraenktes Monitor-Token ohne ai:funkira: 403
        Sanctum::actingAs($admin, ['monitor:pulse']);
        $this->getJson('/api/ai/funkira/behavior/summary')->assertStatus(403);
    }

    public function test_explicit_praise_and_criticism_chips_deduplicate_and_show_in_summary(): void
    {
        [$admin, , $funkira] = $this->createActorsAndAgents();
        Sanctum::actingAs($admin, ['ai:funkira']);

        $memoryId = (string) Str::uuid();

        // Explizites Lob in android_app
        $praiseRes = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'explicit_reaction',
            'channel' => 'android_app',
            'polarity' => 'praise',
            'agent_id' => $funkira->id,
            'ai_chat_memory_id' => $memoryId,
            'agent_excerpt' => 'Deine heutigen Bestellungen sind alle geprueft.',
        ]);
        $praiseRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('source_type', 'explicit_reaction')
            ->assertJsonPath('channel', 'android_app')
            ->assertJsonPath('polarity', 'praise');

        // Wechsel auf Daumen-runter + Schnell-Chip auf derselben Nachricht -> aktualisiert statt Dublette
        $critRes = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'explicit_reaction',
            'channel' => 'android_app',
            'polarity' => 'criticism',
            'reason_code' => 'too_long',
            'agent_id' => $funkira->id,
            'ai_chat_memory_id' => $memoryId,
            'agent_excerpt' => 'Deine heutigen Bestellungen sind alle geprueft.',
        ]);
        $critRes->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('polarity', 'criticism')
            ->assertJsonPath('category', 'tone_length')
            ->assertJsonPath('reason_note', 'Zu lang / umständlich');

        $this->assertSame(1, AiBehaviorFeedback::where('ai_chat_memory_id', $memoryId)->count());

        // Summary pruefen: enthaelt 4 Schnell-Chips & aktuelle Zahlen
        $summary = $this->getJson('/api/ai/funkira/behavior/summary?channel=android_app&days=30');
        $summary->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('agent.name', 'Funkira')
            ->assertJsonPath('window_days', 30)
            ->assertJsonCount(4, 'quick_chips')
            ->assertJsonPath('quick_chips.0.code', 'too_long')
            ->assertJsonPath('quick_chips.0.short_label', 'Zu lang')
            ->assertJsonPath('quick_chips.1.code', 'wrong_tool_or_fact')
            ->assertJsonPath('quick_chips.2.code', 'unnecessary_question')
            ->assertJsonPath('quick_chips.3.code', 'wrong_tone');
    }

    public function test_implicit_voice_signals_detect_criticism_negated_praise_and_ignore_neutral(): void
    {
        [$admin, , $funkira] = $this->createActorsAndAgents();
        Sanctum::actingAs($admin, ['*']);

        $sessionId = 'android_live_' . Str::uuid();

        // 1. Klares Sprach-Lob
        $praise = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'implicit_text_voice',
            'channel' => 'android_live_call',
            'user_message' => 'Perfekt, genau so meinte ich das!',
            'session_id' => $sessionId,
            'agent_id' => $funkira->id,
        ]);
        $praise->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('detected', true)
            ->assertJsonPath('polarity', 'praise')
            ->assertJsonPath('channel', 'android_live_call');

        // 2. Negiertes Lob muss als Kritik statt Lob erkannt werden
        $negated = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'implicit_text_voice',
            'channel' => 'android_live_call',
            'user_message' => 'Das war gar nicht gut und nicht perfekt.',
            'session_id' => $sessionId,
            'agent_id' => $funkira->id,
        ]);
        $negated->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('detected', true)
            ->assertJsonPath('polarity', 'criticism');

        // 3. Sprach-Kritik zur Laenge
        $tooLong = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'implicit_text_voice',
            'channel' => 'android_live_call',
            'user_message' => 'Viel zu lang, fass dich bitte kürzer!',
            'session_id' => $sessionId,
            'agent_id' => $funkira->id,
            'agent_excerpt' => 'Sehr langer Monolog von Funkira...',
        ]);
        $tooLong->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('detected', true)
            ->assertJsonPath('polarity', 'criticism')
            ->assertJsonPath('category', 'tone_length');

        // 4. Neutraler Satz erzeugt kein Rauschen
        $neutral = $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'implicit_text_voice',
            'channel' => 'android_live_call',
            'user_message' => 'Wie viele Bestellungen stehen heute offen?',
            'session_id' => $sessionId,
            'agent_id' => $funkira->id,
        ]);
        $neutral->assertOk()
            ->assertJsonPath('status', 'ignored_neutral')
            ->assertJsonPath('detected', false)
            ->assertJsonPath('feedback_id', null);
    }

    public function test_one_click_rule_approval_injects_live_call_prompt_and_dismiss_clears_cluster(): void
    {
        [$admin, , $funkira] = $this->createActorsAndAgents();
        Sanctum::actingAs($admin, ['*']);

        config(['services.gemini.key' => 'test-live-api-key-123456']);

        // 2 hochintensive Kritiken im 3D Live-Call -> triggern offenen Regelvorschlag
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/ai/funkira/behavior/feedback', [
                'source_type' => 'explicit_reaction',
                'channel' => 'android_live_call',
                'polarity' => 'criticism',
                'reason_code' => 'too_long',
                'agent_id' => $funkira->id,
                'ai_chat_memory_id' => (string) Str::uuid(),
                'agent_excerpt' => "Langer Live-Turn #{$i}",
            ])->assertOk();
        }

        $beforeSummary = $this->getJson('/api/ai/funkira/behavior/summary?channel=android_live_call');
        $beforeSummary->assertOk();
        $proposals = $beforeSummary->json('pending_proposals');
        $this->assertNotEmpty($proposals);
        $this->assertSame('android_live_call', $proposals[0]['channel']);
        $this->assertSame('tone_length', $proposals[0]['category']);

        // 1-Klick-Freigabe ueber Mobile-API
        $approve = $this->postJson('/api/ai/funkira/behavior/rules/approve', [
            'channel' => 'android_live_call',
            'category' => 'tone_length',
            'directive' => 'Im 3D Live-Call: Maximal 2 kurze, direkte Saetze sprechen.',
            'agent_id' => $funkira->id,
        ]);
        $approve->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('rule.scope_key', 'behavior:android_live_call:tone_length')
            ->assertJsonPath('rule.is_active', true);
        $this->assertStringContainsString('AKTIVE VERHALTENSREGELN VON ALINA', (string) $approve->json('prompt_block'));
        $this->assertStringContainsString('Maximal 2 kurze, direkte Saetze sprechen.', (string) $approve->json('prompt_block'));

        // Summary & live-credentials enthalten die aktive Regel sofort fuer Funkira
        $afterSummary = $this->getJson('/api/ai/funkira/behavior/summary?channel=android_live_call');
        $afterSummary->assertOk();
        $this->assertStringContainsString('Maximal 2 kurze, direkte Saetze sprechen.', (string) $afterSummary->json('prompt_block'));

        $liveCreds = $this->getJson('/api/ai/live-credentials?client=android_native&channel=android_live_call&agent_id=' . $funkira->id);
        $liveCreds->assertOk();
        $this->assertStringContainsString('AKTIVE VERHALTENSREGELN VON ALINA', (string) $liveCreds->json('system_instruction'));
        $this->assertStringContainsString('Maximal 2 kurze, direkte Saetze sprechen.', (string) $liveCreds->json('behavior_prompt_block'));

        // Zweites Cluster in android_app verwerfen (dismiss)
        for ($i = 1; $i <= 2; $i++) {
            $this->postJson('/api/ai/funkira/behavior/feedback', [
                'source_type' => 'explicit_reaction',
                'channel' => 'android_app',
                'polarity' => 'criticism',
                'reason_code' => 'unnecessary_question',
                'agent_id' => $funkira->id,
                'ai_chat_memory_id' => (string) Str::uuid(),
            ])->assertOk();
        }

        $dismiss = $this->postJson('/api/ai/funkira/behavior/rules/dismiss', [
            'channel' => 'android_app',
            'category' => 'proactivity',
            'agent_id' => $funkira->id,
        ]);
        $dismiss->assertOk()
            ->assertJsonPath('status', 'success');
        $this->assertGreaterThanOrEqual(2, (int) $dismiss->json('dismissed_count'));
    }

    public function test_frozen_support_agent_funki_is_strictly_excluded_across_all_endpoints_and_live_credentials(): void
    {
        [$admin, , $funkira, $funki] = $this->createActorsAndAgents();
        Sanctum::actingAs($admin, ['*']);

        config(['services.gemini.key' => 'test-live-api-key-123456']);

        // Aktive Regel fuer Funkira anlegen, um 0-Leak an Funki zu beweisen
        AiBehaviorAnalysisService::approveProposalAsRule(
            'android_live_call',
            'tone_length',
            'Im 3D Live-Call: Kurz und direkt antworten.',
            $funkira->id
        );

        $beforeFeedbackCount = AiBehaviorFeedback::where('ai_agent_id', $funki->id)->count();
        $beforePrefCount = AiLearnedPreference::where('scope_key', 'behavior:android_app:wrong_tone')->count();

        // 1. Summary fuer Funki -> 422 blockiert
        $this->getJson('/api/ai/funkira/behavior/summary?agent_id=' . $funki->id)
            ->assertStatus(422)
            ->assertJsonPath('status', 'ignored_immutable_agent')
            ->assertJsonPath('excluded', true);

        // 2. Explizites & implizites Feedback fuer Funki -> 422 blockiert, 0 DB-Eintraege
        $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'explicit_reaction',
            'channel' => 'android_app',
            'polarity' => 'criticism',
            'reason_code' => 'too_long',
            'agent_id' => $funki->id,
            'agent_name' => 'Funki',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'ignored_immutable_agent');

        $this->postJson('/api/ai/funkira/behavior/feedback', [
            'source_type' => 'implicit_text_voice',
            'channel' => 'android_live_call',
            'user_message' => 'Viel zu lang!',
            'agent_name' => 'funki',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'ignored_immutable_agent');

        // 3. Approve & Dismiss fuer Funki -> 422 blockiert
        $this->postJson('/api/ai/funkira/behavior/rules/approve', [
            'channel' => 'android_app',
            'category' => 'tone_length',
            'directive' => 'Darf niemals gespeichert werden',
            'agent_id' => $funki->id,
        ])->assertStatus(422)
            ->assertJsonPath('status', 'ignored_immutable_agent');

        $this->postJson('/api/ai/funkira/behavior/rules/dismiss', [
            'channel' => 'android_app',
            'category' => 'tone_length',
            'agent_name' => 'Funki',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'ignored_immutable_agent');

        $this->assertSame($beforeFeedbackCount, AiBehaviorFeedback::where('ai_agent_id', $funki->id)->count());
        $this->assertSame($beforePrefCount, AiLearnedPreference::where('scope_key', 'behavior:android_app:wrong_tone')->count());

        // 4. live-credentials fuer Funki darf NIEMALS interne Verhaltensregeln injizieren!
        $funkiLiveCreds = $this->getJson('/api/ai/live-credentials?channel=android_live_call&agent_id=' . $funki->id);
        $funkiLiveCreds->assertOk();
        $this->assertStringNotContainsString('AKTIVE VERHALTENSREGELN VON ALINA', (string) $funkiLiveCreds->json('system_instruction'));
        $this->assertNull($funkiLiveCreds->json('behavior_prompt_block'));
    }
}
