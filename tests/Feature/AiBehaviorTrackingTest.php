<?php

namespace Tests\Feature;

use App\Livewire\Shop\Ai\AiAnalytics;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiBehaviorFeedback;
use App\Models\Ai\AiBehaviorProfile;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiChatSession;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiLearnedPreference;
use App\Services\AI\AiBehaviorAnalysisService;
use App\Services\AI\AiOutputQualityGate;
use App\Services\AI\FunkiraPresenceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class AiBehaviorTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndAgents(): array
    {
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-behavior-test@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);

        $dept = AiDepartment::create([
            'name' => 'Agenten',
            'description' => 'KI-Agenten Abteilung',
            'color' => '#C5A059',
        ]);

        $funkira = AiAgent::create([
            'name' => 'Funkira',
            'ai_department_id' => $dept->id,
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
            'is_in_chat' => true,
        ]);

        $funki = AiAgent::create([
            'name' => 'Funki',
            'ai_department_id' => $dept->id,
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
            'is_in_chat' => false,
        ]);

        return [$admin, $dept, $funkira, $funki];
    }

    public function test_implicit_zero_token_detector_classifies_praise_criticism_correction_and_negations(): void
    {
        $praise = AiBehaviorAnalysisService::detectImplicitSignal('Perfekt, genau so habe ich mir das vorgestellt!');
        $this->assertNotNull($praise);
        $this->assertSame('praise', $praise['polarity']);

        // Negiertes Lob darf niemals als Lob gezählt werden!
        $negated = AiBehaviorAnalysisService::detectImplicitSignal('Das war leider nicht perfekt und gar nicht gut.');
        $this->assertNotNull($negated);
        $this->assertSame('criticism', $negated['polarity']);

        $tooLong = AiBehaviorAnalysisService::detectImplicitSignal('Das ist viel zu lang, fass dich bitte kürzer.');
        $this->assertNotNull($tooLong);
        $this->assertSame('criticism', $tooLong['polarity']);
        $this->assertSame('tone_length', $tooLong['category']);

        $bluff = AiBehaviorAnalysisService::detectImplicitSignal('Das stimmt nicht, das hast du dir erfunden!');
        $this->assertNotNull($bluff);
        $this->assertSame('correction', $bluff['polarity']);
        $this->assertSame('hallucination_bluff', $bluff['category']);

        $pacing = AiBehaviorAnalysisService::detectImplicitSignal('Unterbrich mich nicht und sprich bitte langsamer.');
        $this->assertNotNull($pacing);
        $this->assertSame('voice_pacing', $pacing['category']);

        // Normale neutrale Sätze lösen kein Rauschen aus
        $this->assertNull(AiBehaviorAnalysisService::detectImplicitSignal('Zeige mir bitte die offenen Bestellungen von heute.'));
    }

    public function test_explicit_reaction_api_deduplicates_toggles_and_blocks_funki_and_minipc_visitor_sandbox(): void
    {
        [$admin, , $funkira, $funki] = $this->createAdminAndAgents();
        Sanctum::actingAs($admin, ['*']);

        $memoryId = (string) Str::uuid();

        // 1. Erst Lob, dann Korrektur derselben Nachricht -> aktualisiert statt Dublette!
        $res1 = $this->postJson('/api/ai/behavior-feedback', [
            'polarity' => 'praise',
            'channel' => 'live_widget',
            'agent_id' => $funkira->id,
            'ai_chat_memory_id' => $memoryId,
            'agent_excerpt' => 'Bestellstatus wurde geprüft.',
        ]);
        $res1->assertOk()->assertJsonPath('polarity', 'praise');

        $res2 = $this->postJson('/api/ai/behavior-feedback', [
            'polarity' => 'criticism',
            'channel' => 'live_widget',
            'reason_key' => 'too_long',
            'agent_id' => $funkira->id,
            'ai_chat_memory_id' => $memoryId,
            'agent_excerpt' => 'Bestellstatus wurde geprüft.',
        ]);
        $res2->assertOk()
            ->assertJsonPath('polarity', 'criticism')
            ->assertJsonPath('category', 'tone_length');

        $this->assertSame(1, AiBehaviorFeedback::where('ai_chat_memory_id', $memoryId)->count());

        // 2. Funki bleibt 100 % immun & getrennt
        $resFunki = $this->postJson('/api/ai/behavior-feedback', [
            'polarity' => 'criticism',
            'channel' => 'web_portal',
            'agent_id' => $funki->id,
            'agent_name' => 'Funki',
        ]);
        $resFunki->assertOk()->assertJsonPath('status', 'ignored_immutable_agent');
        $this->assertSame(0, AiBehaviorFeedback::where('ai_agent_id', $funki->id)->count());

        // 3. Mini-PC Besuchermodus isoliert unverified Besucher
        $resVisitor = $this->postJson('/api/ai/behavior-feedback', [
            'polarity' => 'criticism',
            'channel' => 'workshop_minipc',
            'agent_id' => $funkira->id,
            'camera_alina_verified' => false,
            'voice_alina_verified' => false,
        ]);
        $resVisitor->assertOk()->assertJsonPath('status', 'ignored_visitor_sandbox');
        $this->assertSame(0, AiBehaviorFeedback::where('channel', 'workshop_minipc')->count());
    }

    public function test_live_transcript_and_quality_gate_capture_signals_across_channels(): void
    {
        [$admin, , $funkira] = $this->createAdminAndAgents();
        Sanctum::actingAs($admin, ['*']);

        $session = AiChatSession::create([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'title' => '3D Live-Call',
        ]);

        AiChatMemory::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Hier ist eine sehr ausführliche Erklärung mit vielen Sätzen.',
        ]);

        // Android 3D Live-Call Transkript (inkl. Streaming-Erweiterung ohne Dublette!)
        $t1 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $session->id,
            'role' => 'user',
            'channel' => 'android_live_call',
            'agent_id' => $funkira->id,
            'content' => 'Das war viel zu lang',
        ]);
        $t1->assertOk()->assertJsonPath('status', 'created');

        $t2 = $this->postJson('/api/ai/save-live-transcript', [
            'session_id' => $session->id,
            'role' => 'user',
            'channel' => 'android_live_call',
            'agent_id' => $funkira->id,
            'content' => 'Das war viel zu lang, fass dich bitte kürzer!',
        ]);
        $t2->assertOk()->assertJsonPath('status', 'updated_extended');

        $this->assertSame(1, AiBehaviorFeedback::where('channel', 'android_live_call')->where('category', 'tone_length')->count());

        // Quality-Gate Bluff & Tool-Fehler-Signale
        $gate = AiOutputQualityGate::inspectAndClean('Ich nehme das sofort für dich in Arbeit und habe es bereits eingetragen.', []);
        $this->assertTrue($gate['bluff_detected']);

        $qgFeedback = AiBehaviorAnalysisService::recordQualityGateSignal($gate, $funkira, 'android_app', [
            'session_id' => $session->id,
            'user_excerpt' => 'Trage die Rechnung ein',
        ]);
        $this->assertNotNull($qgFeedback);
        $this->assertSame('quality_gate', $qgFeedback->source_type);
        $this->assertSame('hallucination_bluff', $qgFeedback->category);

        $toolErr = AiBehaviorAnalysisService::recordToolErrorSignal('order_update_status', 'Timeout', $funkira, 'workshop_minipc', [
            'session_id' => $session->id,
        ]);
        $this->assertNotNull($toolErr);
        $this->assertSame('tool_error', $toolErr->source_type);
    }

    public function test_closed_loop_proposal_activation_prompt_injection_and_before_after_verification(): void
    {
        [$admin, , $funkira, $funki] = $this->createAdminAndAgents();

        // 3 gleichartige Kritiken im Kanal android_live_call erzeugen einen Regelvorschlag
        for ($i = 1; $i <= 3; $i++) {
            AiBehaviorFeedback::create([
                'ai_agent_id' => $funkira->id,
                'ai_department_id' => $funkira->ai_department_id,
                'channel' => 'android_live_call',
                'source_type' => 'implicit_text_voice',
                'polarity' => 'criticism',
                'category' => 'tone_length',
                'intensity' => 4,
                'user_excerpt' => "Antwort #{$i} war zu lang",
                'status' => 'new',
                'created_at' => now()->subMinutes(10 + $i),
            ]);
        }

        $profile = AiBehaviorAnalysisService::rebuildProfile(null, 'android_live_call', 30);
        $this->assertNotEmpty($profile->pending_rule_proposals);
        $proposal = $profile->pending_rule_proposals[0];
        $this->assertSame('android_live_call', $proposal['channel']);
        $this->assertSame('tone_length', $proposal['category']);

        // 1-Klick-Aktivierung durch Alina -> AiLearnedPreference
        $pref = AiBehaviorAnalysisService::activateRuleProposal(
            'android_live_call',
            'tone_length',
            'Im 3D Live-Call: Maximal 2 kurze Sätze ohne Füllfloskeln antworten.',
            $funkira->id
        );
        $this->assertInstanceOf(AiLearnedPreference::class, $pref);
        $this->assertSame(0, AiBehaviorFeedback::where('channel', 'android_live_call')->where('status', 'new')->count());
        $this->assertSame(3, AiBehaviorFeedback::where('learned_preference_id', $pref->id)->where('status', 'applied')->count());

        // Direktive fließt in FunkiraPresenceContext für android_live_call ein, aber NICHT für Funki und NICHT im Besuchermodus!
        $livePresence = FunkiraPresenceContext::build(['channel' => 'android_live_call']);
        $liveHeader = FunkiraPresenceContext::toPromptHeader($livePresence, $funkira);
        $this->assertStringContainsString('AKTIVE VERHALTENSREGELN VON ALINA', $liveHeader);
        $this->assertStringContainsString('Maximal 2 kurze Sätze', $liveHeader);

        $funkiHeader = FunkiraPresenceContext::toPromptHeader($livePresence, $funki);
        $this->assertStringNotContainsString('AKTIVE VERHALTENSREGELN VON ALINA', $funkiHeader);

        $visitorPresence = FunkiraPresenceContext::build([
            'channel' => 'workshop_minipc',
            'camera_alina_verified' => false,
            'voice_alina_verified' => false,
        ]);
        $this->assertTrue($visitorPresence['visitor_sandbox_locked']);
        $visitorHeader = FunkiraPresenceContext::toPromptHeader($visitorPresence, $funkira);
        $this->assertStringNotContainsString('AKTIVE VERHALTENSREGELN VON ALINA', $visitorHeader);

        // Before/After-Verifikation: Nach Aktivierung kommen positive Signale -> Kritik-Rate sinkt!
        AiBehaviorFeedback::create([
            'ai_agent_id' => $funkira->id,
            'channel' => 'android_live_call',
            'source_type' => 'implicit_text_voice',
            'polarity' => 'praise',
            'category' => 'domain_competence',
            'intensity' => 5,
            'status' => 'new',
            'created_at' => now()->addSeconds(5),
        ]);

        $afterProfile = AiBehaviorAnalysisService::rebuildProfile(null, 'android_live_call', 30);
        $impacts = $afterProfile->rule_impact_metrics;
        $this->assertNotEmpty($impacts);
        $this->assertSame(100.0, (float) $impacts[0]['baseline_criticism_rate']);
        $this->assertSame(1, $impacts[0]['after_sample_size']);
        $this->assertNotNull($impacts[0]['after_criticism_rate']);
        $this->assertSame(0.0, (float) $impacts[0]['after_criticism_rate']);
        $this->assertTrue($impacts[0]['is_improved']);

        // Blueprint-Konformität beider neuer Models
        $this->assertSame('KI-Verhaltens-Feedback (Lob & Kritik)', AiBehaviorFeedback::blueprint()->label);
        $this->assertSame('Aggregiertes KI-Verhaltensprofil', AiBehaviorProfile::blueprint()->label);
    }

    public function test_admin_can_render_behavior_cockpit_and_trigger_closed_loop_actions(): void
    {
        [$admin, , $funkira] = $this->createAdminAndAgents();
        $this->actingAs($admin, 'admin');

        for ($i = 0; $i < 3; $i++) {
            AiBehaviorFeedback::create([
                'ai_agent_id' => $funkira->id,
                'channel' => 'web_portal',
                'source_type' => 'explicit_reaction',
                'polarity' => 'criticism',
                'category' => 'proactivity',
                'intensity' => 4,
                'reason_note' => 'Unnötige Rückfrage',
                'status' => 'new',
            ]);
        }

        // Route /admin/ai-analytics?tab=behavior lädt sauber
        $this->get('/admin/ai-analytics?tab=behavior')
            ->assertOk()
            ->assertSee('Verhaltensprofil')
            ->assertSee('Zufriedenheits-Index')
            ->assertSee('Kanal-Matrix');

        // AiAgentEditor zeigt kompakte Verhaltens-Score-Kachel
        $this->get('/admin/ki-agenten/' . $funkira->id)
            ->assertOk()
            ->assertSee('Verhaltens-Score');

        // Livewire Aktion: Ticket eskalieren & Profil prüfen
        Livewire::test(AiAnalytics::class, ['activeTab' => 'behavior'])
            ->call('escalateProposal', 'web_portal', 'proactivity', 'Bei Statusabfragen sofort handeln.', $funkira->id)
            ->assertSee('Entwicklungs-Ticket');

        $this->assertSame(0, AiBehaviorFeedback::where('channel', 'web_portal')->where('status', 'new')->count());
        $this->assertSame(3, AiBehaviorFeedback::where('channel', 'web_portal')->where('status', 'reviewed')->count());
    }

    public function test_funkira_presence_header_enforces_crisp_style_and_bundling_while_isolating_funki_and_visitor_mode(): void
    {
        [, , $funkira, $funki] = $this->createAdminAndAgents();

        $livePresence = FunkiraPresenceContext::build(['channel' => 'android_live_call']);
        $funkiraHeader = FunkiraPresenceContext::toPromptHeader($livePresence, $funkira);
        $this->assertStringContainsString('[STIL & BÜNDELUNG:', $funkiraHeader);
        $this->assertStringContainsString('1 bis 3 kurzen Sätzen', $funkiraHeader);
        $this->assertStringContainsString('Sammelpaket', $funkiraHeader);

        // Funki und Besuchermodus erhalten diese internen Direktiven niemals
        $funkiHeader = FunkiraPresenceContext::toPromptHeader($livePresence, $funki);
        $this->assertStringNotContainsString('[STIL & BÜNDELUNG:', $funkiHeader);

        $visitorPresence = FunkiraPresenceContext::build([
            'channel' => 'workshop_minipc',
            'camera_alina_verified' => false,
            'voice_alina_verified' => false,
        ]);
        $visitorHeader = FunkiraPresenceContext::toPromptHeader($visitorPresence, $funkira);
        $this->assertStringNotContainsString('[STIL & BÜNDELUNG:', $visitorHeader);
    }
}
