<?php

namespace Tests\Feature\Ai;

use App\Livewire\Shop\Ai\AiUniverseTickets;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiUniverseTicket;
use App\Services\AI\FunkiraTicketRouter;
use App\Services\System\AntigravityDirectInboxService;
use Database\Seeders\AiAgentSeeder;
use Database\Seeders\AiCompanyStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class UniverseTicketChatMentionsAndMultilineTest extends TestCase
{
    use RefreshDatabase;

    private string $tempInboxRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempInboxRoot = storage_path('framework/testing/ag_inbox_mentions_' . Str::random(8));
        File::makeDirectory($this->tempInboxRoot, 0775, true, true);
        $this->app->singleton(
            AntigravityDirectInboxService::class,
            fn () => new AntigravityDirectInboxService($this->tempInboxRoot)
        );
    }

    protected function tearDown(): void
    {
        if (!empty($this->tempInboxRoot) && File::isDirectory($this->tempInboxRoot)) {
            File::deleteDirectory($this->tempInboxRoot);
        }

        parent::tearDown();
    }

    private function createAdmin(): Admin
    {
        return Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-mentions-' . Str::lower(Str::random(6)) . '@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
    }

    public function test_mentionable_agents_include_antigravity_and_specialists_but_strictly_exclude_funki(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();

        $component = Livewire::actingAs($admin, 'admin')->test(AiUniverseTickets::class);
        $mentionable = $component->instance()->mentionableAgents;
        $names = array_column($mentionable, 'name');

        $this->assertContains('Funkira', $names);
        $this->assertContains('Antigravity', $names);
        $this->assertContains('Buchi', $names);
        $this->assertContains('Systemi', $names);
        $this->assertContains('Dr. Funki', $names);
        $this->assertNotContains('Funki', $names, 'Frontend-Support Funki darf niemals im internen @-Picker auftauchen.');

        // Auch der Parser ignoriert @Funki strikt, erkennt aber @Funkira, @Dr. Funki und @Antigravity
        $extractedFunki = FunkiraTicketRouter::extractMentionedAgents('Hallo @Funki kannst du hier helfen?');
        $this->assertCount(0, $extractedFunki);

        $extractedMulti = FunkiraTicketRouter::extractMentionedAgents("Bitte @Systemi prüfen\nund @Antigravity übernehmen!");
        $this->assertSame(['Systemi', 'Antigravity'], $extractedMulti->pluck('name')->all());
    }

    public function test_multiline_messages_preserve_line_breaks_and_format_mentions_xss_safely(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();

        $multilineText = "Zeile 1: Übersicht prüfen\nZeile 2: @Buchi bitte gegenrechnen\nZeile 3: @Antigravity umsetzen <script>alert(1)</script>";

        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->set('commandInput', $multilineText)
            ->call('submitCommand')
            ->assertSet('commandInput', '');

        $ticket = AiUniverseTicket::with('messages')->firstOrFail();
        $alinaMsg = $ticket->messages->firstWhere('sender_role', 'alina');
        $this->assertNotNull($alinaMsg);
        $this->assertSame($multilineText, $alinaMsg->content);

        $renderedHtml = AiUniverseTickets::formatMessageHtml($alinaMsg->content);
        $this->assertStringContainsString("Zeile 1: Übersicht prüfen\nZeile 2:", $renderedHtml);
        $this->assertStringContainsString('>@Buchi</span>', $renderedHtml);
        $this->assertStringContainsString('>@Antigravity</span>', $renderedHtml);
        $this->assertStringNotContainsString('<script>', $renderedHtml);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $renderedHtml);
    }

    public function test_existing_ticket_reply_supports_switching_specialists_and_bringing_in_antigravity_via_at_mentions(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $systemi = AiAgent::where('name', 'Systemi')->firstOrFail();
        $buchi = AiAgent::where('name', 'Buchi')->firstOrFail();
        $antigravity = AiAgent::where('name', 'Antigravity')->firstOrFail();

        // 1. Neues Ticket startet allgemein bei Funkira
        $initial = FunkiraTicketRouter::dispatchMessage("Kurze Rückfrage zur internen Abstimmung\nPunkt A\nPunkt B", [
            'channel' => 'web_portal',
        ]);

        /** @var AiUniverseTicket $ticket */
        $ticket = $initial['ticket'];
        $this->assertNotNull($ticket);

        // 2. Im bestehenden Ticket wird @Systemi per @-Mention einbezogen -> Systemi antwortet und übernimmt
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('selectTicket', $ticket->id)
            ->set('ticketReplyInput', "@Systemi kannst du kurz die Logs dazu prüfen?\nDanke dir!")
            ->call('submitTicketReply')
            ->assertSet('ticketReplyInput', '');

        $ticket->refresh();
        $this->assertSame($systemi->id, $ticket->assigned_agent_id);
        $this->assertFalse((bool) $ticket->assigned_to_antigravity);
        $this->assertTrue(
            $ticket->messages()->where('sender_role', 'agent')->where('sender_name', 'Systemi')->exists(),
            'Systemi muss chronologisch im Ticket geantwortet haben.'
        );

        // 3. Im selben Ticket wird @Antigravity per @-Mention einbezogen -> Handoff + Inbox-Eintrag angelegt
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('selectTicket', $ticket->id)
            ->set('ticketReplyInput', "@Antigravity bitte passe die Datei jetzt direkt sauber im Code an.")
            ->call('submitTicketReply');

        $ticket->refresh();
        $this->assertTrue((bool) $ticket->assigned_to_antigravity);
        $this->assertSame($antigravity->id, $ticket->assigned_agent_id);
        $firstAgItemId = (string) ($ticket->action_payload['ag_item_id'] ?? '');
        $this->assertNotSame('', $firstAgItemId);

        $inbox = app(AntigravityDirectInboxService::class)->listInbox();
        $this->assertGreaterThanOrEqual(1, count($inbox['items']));
        $this->assertStringContainsString('@Antigravity bitte passe die Datei', (string) ($inbox['items'][0]['body'] ?? ''));

        // 4. Sogar auf einem Antigravity-Ticket kann Alina zwischendurch @Buchi hinzuziehen, ohne dass Buchi verschluckt wird!
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('selectTicket', $ticket->id)
            ->set('ticketReplyInput', '@Buchi stimmt der Nettobetrag dafür?')
            ->call('submitTicketReply');

        $ticket->refresh();
        $this->assertSame($buchi->id, $ticket->assigned_agent_id);
        $this->assertFalse((bool) $ticket->assigned_to_antigravity);
        $this->assertTrue(
            $ticket->messages()->where('sender_role', 'agent')->where('sender_name', 'Buchi')->exists(),
            'Buchi muss nach @Buchi direkt im Ticket antworten.'
        );

        // 5. Erneutes @Antigravity legt ein frisches Follow-Up Inbox-Item an (statt die alte ag_item_id stumm zu überspringen)
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->call('selectTicket', $ticket->id)
            ->set('ticketReplyInput', '@Antigravity perfekt, Betrag passt, bitte final umsetzen.')
            ->call('submitTicketReply');

        $ticket->refresh();
        $secondAgItemId = (string) ($ticket->action_payload['ag_item_id'] ?? '');
        $this->assertNotSame('', $secondAgItemId);
        $this->assertNotSame($firstAgItemId, $secondAgItemId);
        $this->assertContains($firstAgItemId, $ticket->action_payload['ag_history_ids'] ?? []);
    }

    public function test_mobile_master_detail_navigation_and_long_path_chat_formatting(): void
    {
        $this->seed(AiCompanyStructureSeeder::class);
        $this->seed(AiAgentSeeder::class);

        $admin = $this->createAdmin();
        $this->actingAs($admin, 'admin');

        $initial = FunkiraTicketRouter::dispatchMessage('Systemdiagnose StreamFactory prüfen', [
            'channel' => 'web_portal',
        ]);
        /** @var AiUniverseTicket $ticket */
        $ticket = $initial['ticket'];

        // Standard-Einstieg auf Mobile zeigt zuerst die Ticket-Liste (mobileTicketOpen = false), Auswahl öffnet Detail
        Livewire::actingAs($admin, 'admin')
            ->test(AiUniverseTickets::class)
            ->assertSet('mobileTicketOpen', false)
            ->call('selectTicket', $ticket->id)
            ->assertSet('mobileTicketOpen', true)
            ->call('closeMobileTicket')
            ->assertSet('mobileTicketOpen', false);

        // Lange Linux-Pfade, Listenpunkte und mehrzeilige Codeblöcke brechen mobil sauber um
        $diagMessage = "**0-Token System-Diagnose (`Systemi`)**\n"
            . "- **Umgebung:** `STAGE`\n"
            . "- **Ort:** `/home/p-g27wim/html/seelenfunke-stage/vendor/guzzlehttp/psr7/src/StreamFactory.php:241`\n"
            . "```php\nthrow new RuntimeException('php_network_getaddresses');\n```";

        $html = AiUniverseTickets::formatMessageHtml($diagMessage);
        $this->assertStringContainsString('break-all sm:break-words [overflow-wrap:anywhere]', $html);
        $this->assertStringContainsString('<pre class="', $html);
        $this->assertStringContainsString('StreamFactory.php:241', $html);
        $this->assertStringContainsString('•</span>', $html);
    }
}
