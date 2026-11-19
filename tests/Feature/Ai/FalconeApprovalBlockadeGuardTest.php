<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Services\AI\AIFunctionsRegistry;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\System\AntigravityDirectInboxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class FalconeApprovalBlockadeGuardTest extends TestCase
{
    use DatabaseTransactions;

    private string $isolatedPrivateRoot;

    private AntigravityDirectInboxService $inboxService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolatedPrivateRoot = storage_path('app/private/testing_falcone_blockade_' . Str::lower(Str::random(10)));
        $this->inboxService = new AntigravityDirectInboxService($this->isolatedPrivateRoot);
        $this->app->instance(AntigravityDirectInboxService::class, $this->inboxService);
    }

    protected function tearDown(): void
    {
        if (isset($this->isolatedPrivateRoot) && File::isDirectory($this->isolatedPrivateRoot)) {
            File::deleteDirectory($this->isolatedPrivateRoot);
        }

        parent::tearDown();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-blockade-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_complete_item_on_concept_or_question_summary_sets_awaiting_go_and_increments_counts(): void
    {
        // 1. Konzept-Ticket -> completeItem() setzt zwingend status = awaiting_go statt completed
        $conceptCreated = $this->inboxService->createItem([
            'channel' => 'shop',
            'title' => 'Konzept Magma-Parkour Erweiterung',
            'body' => 'Bitte Architektur fuer die neuen Magma-Raeume ausarbeiten.',
            'execution_mode' => 'concept',
        ]);
        $conceptId = (string) $conceptCreated['item']['id'];

        $afterConceptComplete = $this->inboxService->completeItem(
            $conceptId,
            'Konzept fertiggestellt. Sollen wir mit Magma-Raum 6 starten?'
        );

        $this->assertNotNull($afterConceptComplete);
        $this->assertSame('awaiting_go', $afterConceptComplete['status']);
        $this->assertSame(
            'Konzept fertiggestellt. Sollen wir mit Magma-Raum 6 starten?',
            $afterConceptComplete['pending_question']
        );
        $this->assertNotEmpty($afterConceptComplete['awaiting_go_at']);
        $this->assertNull($afterConceptComplete['completed_at']);

        // 2. Direkt-Ticket mit offener Rueckfrage ("wartet auf dein Go") -> wird ebenfalls vor Untergehen geschuetzt
        $questionDirect = $this->inboxService->createItem([
            'channel' => 'buchhaltung',
            'title' => 'DHL Geschaeftskunden-Vertrag anlegen',
            'body' => 'Tarif im Seeder hinterlegen.',
            'execution_mode' => 'direct',
        ]);
        $questionDirectId = (string) $questionDirect['item']['id'];

        $afterQuestionComplete = $this->inboxService->completeItem(
            $questionDirectId,
            'Vorbereitung fertig, wartet auf dein Go bezueglich Startdatum.'
        );
        $this->assertNotNull($afterQuestionComplete);
        $this->assertSame('awaiting_go', $afterQuestionComplete['status']);

        // 3. Normales Direkt-Ticket ohne Rueckfrage -> wird regulaer completed
        $normalDirect = $this->inboxService->createItem([
            'channel' => 'android',
            'title' => 'Button-Abstand korrigieren',
            'execution_mode' => 'direct',
        ]);
        $normalCompleted = $this->inboxService->completeItem(
            (string) $normalDirect['item']['id'],
            'Abstand auf 12dp angepasst und getestet.'
        );
        $this->assertNotNull($normalCompleted);
        $this->assertSame('completed', $normalCompleted['status']);

        $snapshot = $this->inboxService->listInbox();
        $this->assertSame(2, $snapshot['counts']['awaiting_go']);
        $this->assertSame(2, $snapshot['counts']['open']);
        $this->assertSame(1, $snapshot['counts']['completed']);

        $blockades = $this->inboxService->getPendingBlockades();
        $this->assertCount(2, $blockades);
        $this->assertSame($conceptId, $blockades[0]['id']);
        $this->assertSame($questionDirectId, $blockades[1]['id']);
    }

    public function test_live_credentials_for_falcone_injects_persistent_blockade_directive_and_declares_resolver_tool(): void
    {
        config([
            'services.gemini.key' => 'AIzaSyBlockadeGuardVoiceKey',
            'services.vertex.enabled' => false,
        ]);

        $created = $this->inboxService->createItem([
            'channel' => 'shop',
            'title' => '3D Showroom Beleuchtung',
            'body' => 'Konzept fuer Lichtstimmung im Showroom',
            'execution_mode' => 'concept',
        ]);
        $blockadeId = (string) $created['item']['id'];
        $questionText = 'Wartet auf deine Entscheidung: Warmgold 3000K oder Galerie-Neutralweiss 4000K?';
        $this->inboxService->completeItem($blockadeId, $questionText);

        $falcone = AiAgent::where('name', 'Falcone')->first() ?: AiAgent::create([
            'name' => 'Falcone',
            'wake_word' => 'falcone',
            'role_description' => 'Chef-Dirigent',
            'system_prompt' => 'Du bist Falcone.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);

        $admin = $this->createAdmin();
        $token = $admin->createToken('android-falcone-voice', ['*'])->plainTextToken;

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/ai/live-credentials?agent_id=' . $falcone->id)
            ->assertStatus(200);

        $sysInstruction = (string) $res->json('system_instruction');
        $this->assertStringContainsString('=== AKTIVER FREIGABE- & RÜCKFRAGE-STAU BEI ANTIGRAVITY (HÖCHSTE PRIORITÄT) ===', $sysInstruction);
        $this->assertStringContainsString('BEHARRLICH AUF ANTWORT BESTEHEN', $sysInstruction);
        $this->assertStringContainsString($blockadeId, $sysInstruction);
        $this->assertStringContainsString($questionText, $sysInstruction);
        $this->assertStringContainsString('ag_resolve_pending_blockade', $sysInstruction);
        $this->assertStringNotContainsString(" \u{2013} ", $sysInstruction);

        $pendingBlockades = $res->json('pending_ag_blockades');
        $this->assertIsArray($pendingBlockades);
        $this->assertCount(1, $pendingBlockades);
        $this->assertSame($blockadeId, $pendingBlockades[0]['id']);
        $this->assertSame($questionText, $pendingBlockades[0]['pending_question']);
        $this->assertNotEmpty($res->json('proactive_kick_prompt'));

        $liveToken = (string) $res->json('token');
        $cached = Cache::get('gemini_live_token_' . $liveToken);
        $declaredTools = collect($cached['tools'][0]['functionDeclarations'] ?? [])
            ->map(fn ($fn) => is_object($fn) ? ($fn->name ?? null) : ($fn['name'] ?? null))
            ->filter()
            ->values()
            ->all();

        $this->assertContains('ag_resolve_pending_blockade', $declaredTools);
        $this->assertContains('ag_list_pending_blockades', $declaredTools);
        $this->assertContains('ag_resolve_pending_blockade', FunkiraSkillPoolService::getCoreToolIdentifiers());
    }

    public function test_execute_ag_resolve_pending_blockade_and_rest_endpoints_close_stalled_items_and_enqueue_direct_followup(): void
    {
        $falcone = AiAgent::where('name', 'Falcone')->first() ?: AiAgent::create([
            'name' => 'Falcone',
            'wake_word' => 'falcone',
            'role_description' => 'Chef-Dirigent',
            'system_prompt' => 'Du bist Falcone.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);

        $concept = $this->inboxService->createItem([
            'channel' => 'shop',
            'title' => 'Magma-Parkour Raum-Architektur',
            'body' => 'Konzept fuer neue Parkour-Stufen',
            'execution_mode' => 'concept',
        ]);
        $conceptId = (string) $concept['item']['id'];
        $this->inboxService->completeItem(
            $conceptId,
            'Konzept bereit. Mit welchem Raum soll gestartet werden?'
        );

        $admin = $this->createAdmin();
        $adminToken = $admin->createToken('android-falcone-exec', ['*'])->plainTextToken;

        // 1. Aufloesung ueber KI-Tool POST /api/ai/execute (ag_resolve_pending_blockade)
        $execRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->postJson('/api/ai/execute', [
                'agent_id' => $falcone->id,
                'function' => 'ag_resolve_pending_blockade',
                'args' => [
                    'item_id' => $conceptId,
                    'decision' => 'approved',
                    'answer_text' => 'Starte mit Magma-Raum 6',
                ],
            ])
            ->assertStatus(200);

        $this->assertSame('success', $execRes->json('result.status'));
        $this->assertSame('approved', $execRes->json('result.decision'));
        $this->assertSame('completed', $execRes->json('result.resolved_item.status'));
        $this->assertSame(0, $execRes->json('result.remaining_blockades'));

        $followup = $execRes->json('result.followup_item');
        $this->assertIsArray($followup);
        $this->assertSame('shop', $followup['channel']);
        $this->assertSame('direct', $followup['execution_mode']);
        $this->assertSame('pending', $followup['status']);
        $this->assertStringContainsString('GO & Antwort zu: Magma-Parkour Raum-Architektur', $followup['title']);
        $this->assertStringContainsString('Starte mit Magma-Raum 6', $followup['body']);
        $this->assertStringContainsString($conceptId, $followup['body']);

        // 2. Direkter 1-Tap-Endpunkt POST /api/funkenwerk/ag-inbox/{id}/resolve (dismissed)
        $concept2 = $this->inboxService->createItem([
            'channel' => 'minipc',
            'title' => 'Experimenteller Kamera-Filter',
            'execution_mode' => 'concept',
        ]);
        $concept2Id = (string) $concept2['item']['id'];
        $this->inboxService->completeItem($concept2Id, 'Wartet auf dein Go.');

        $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->postJson("/api/funkenwerk/ag-inbox/{$concept2Id}/resolve", [
                'decision' => 'dismissed',
                'answer_text' => 'Brauchen wir aktuell nicht.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('resolved_item.status', 'completed')
            ->assertJsonPath('counts.awaiting_go', 0);

        // 3. Manuelles Sammel-Go ueber POST /api/funkenwerk/ag-inbox schliesst offene awaiting_go-Tickets automatisch
        $c3 = $this->inboxService->createItem(['channel' => 'leitung', 'title' => 'Konzept A', 'execution_mode' => 'concept']);
        $c4 = $this->inboxService->createItem(['channel' => 'android', 'title' => 'Konzept B', 'execution_mode' => 'concept']);
        $this->inboxService->completeItem((string) $c3['item']['id'], 'Wartet auf Go.');
        $this->inboxService->completeItem((string) $c4['item']['id'], 'Wartet auf Go.');
        $this->assertCount(2, $this->inboxService->getPendingBlockades());

        $bulkRes = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->postJson('/api/funkenwerk/ag-inbox', [
                'channel' => 'leitung',
                'title' => 'Go fuer alle offenen Konzepte',
                'body' => 'Bitte alle offenen Konzepte direkt umsetzen.',
                'execution_mode' => 'direct',
            ])
            ->assertStatus(201);

        $this->assertCount(2, $bulkRes->json('auto_resolved_ids'));
        $this->assertCount(0, $this->inboxService->getPendingBlockades());
    }

    public function test_strict_security_isolation_blocks_funki_non_executive_agents_partners_customers_and_guests(): void
    {
        config(['services.gemini.key' => 'AIzaSyIsolationKey']);

        $stalled = $this->inboxService->createItem([
            'channel' => 'leitung',
            'title' => 'Vertrauliches Leitungs-Konzept',
            'execution_mode' => 'concept',
        ]);
        $stalledId = (string) $stalled['item']['id'];
        $this->inboxService->completeItem($stalledId, 'Geheime Leitungs-Rueckfrage?');

        $funki = AiAgent::where('name', 'Funki')->first() ?: AiAgent::create([
            'name' => 'Funki',
            'wake_word' => 'funki',
            'role_description' => 'Support-KI',
            'system_prompt' => 'Du bist Funki.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);

        $nonExecAgent = AiAgent::create([
            'name' => 'SubAgentTest',
            'wake_word' => 'subagent',
            'role_description' => 'Fach-Agent',
            'system_prompt' => 'Du bist ein Fach-Agent.',
            'model' => 'gemini-3.5-flash',
            'is_active' => true,
        ]);

        $admin = $this->createAdmin();
        $adminToken = $admin->createToken('admin-check', ['*'])->plainTextToken;

        // 1. Air-Gapped Funki erhaelt selbst bei Admin-Token KEINE Blockade-Daten und KEIN Aufloesungs-Tool
        $funkiLive = $this->withHeaders(['Authorization' => 'Bearer ' . $adminToken])
            ->getJson('/api/ai/live-credentials?agent_id=' . $funki->id)
            ->assertStatus(200);
        $this->assertNull($funkiLive->json('pending_ag_blockades'));
        $this->assertStringNotContainsString('Geheime Leitungs-Rueckfrage', (string) $funkiLive->json('system_instruction'));

        $this->actingAs($admin, 'admin');
        $funkiSandbox = AIFunctionsRegistry::execute('ag_resolve_pending_blockade', [
            'item_id' => $stalledId,
            'decision' => 'approved',
            'answer_text' => 'Versuch ueber Funki',
        ], $funki);
        $this->assertTrue((bool) ($funkiSandbox['error'] ?? false));
        $this->assertStringContainsString('SANDBOX VIOLATION', (string) ($funkiSandbox['message'] ?? ''));

        // Nicht-Executive-Agent -> ROLE VIOLATION
        $nonExecRes = AIFunctionsRegistry::execute('ag_resolve_pending_blockade', [
            'item_id' => $stalledId,
            'decision' => 'approved',
        ], $nonExecAgent);
        $this->assertTrue((bool) ($nonExecRes['error'] ?? false));
        $this->assertStringContainsString('ROLE VIOLATION', (string) ($nonExecRes['message'] ?? ''));

        // 2. Gast -> 401/403
        auth()->forgetGuards();
        $this->flushHeaders();

        $this->postJson("/api/funkenwerk/ag-inbox/{$stalledId}/resolve", ['decision' => 'approved'])
            ->assertStatus(401);
        $guestAiStatus = $this->postJson('/api/ai/execute', [
            'function' => 'ag_resolve_pending_blockade',
            'args' => ['item_id' => $stalledId, 'decision' => 'approved'],
        ])->status();
        $this->assertContains($guestAiStatus, [401, 403]);

        // 3. Interner Mitarbeiter, Influencer & Steuerberater -> 403
        foreach (['internal', 'influencer', 'tax_advisor'] as $role) {
            auth()->forgetGuards();
            $this->flushHeaders();

            $emp = Employee::create([
                'first_name' => 'Role',
                'last_name' => ucfirst($role),
                'email' => $role . '-blockade-' . Str::lower(Str::random(6)) . '@example.com',
                'role' => $role,
                'password' => bcrypt('Secret123!'),
            ]);
            $empToken = $emp->createToken('emp-tok', ['*'])->plainTextToken;

            $this->withHeaders(['Authorization' => 'Bearer ' . $empToken])
                ->postJson("/api/funkenwerk/ag-inbox/{$stalledId}/resolve", ['decision' => 'approved'])
                ->assertStatus(403);

            $this->withHeaders(['Authorization' => 'Bearer ' . $empToken])
                ->postJson('/api/ai/execute', [
                    'agent_id' => $funki->id,
                    'function' => 'ag_resolve_pending_blockade',
                    'args' => ['item_id' => $stalledId, 'decision' => 'approved'],
                ])
                ->assertStatus(403);
        }

        // 4. Kunde -> 401/403
        auth()->forgetGuards();
        $this->flushHeaders();
        $customer = Customer::create([
            'first_name' => 'Kunde',
            'last_name' => 'Test',
            'email' => 'kunde-blockade-' . Str::lower(Str::random(6)) . '@example.com',
            'password' => bcrypt('Secret123!'),
        ]);
        $custToken = $customer->createToken('cust-tok', ['*'])->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer ' . $custToken])
            ->postJson("/api/funkenwerk/ag-inbox/{$stalledId}/resolve", ['decision' => 'approved'])
            ->assertStatus(403);

        $this->withHeaders(['Authorization' => 'Bearer ' . $custToken])
            ->postJson('/api/ai/execute', [
                'function' => 'ag_resolve_pending_blockade',
                'args' => ['item_id' => $stalledId, 'decision' => 'approved'],
            ])
            ->assertStatus(403);

        // Blockade muss nach allen unautorisierten Angriffsversuchen unveraendert auf awaiting_go stehen!
        $this->assertCount(1, $this->inboxService->getPendingBlockades());
    }
}
