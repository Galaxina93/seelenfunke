<?php

namespace Tests\Feature\Management;

use App\Livewire\Shop\Management\ManagementCalender;
use App\Livewire\Shop\Management\ManagementTask;
use App\Models\Admin\Admin;
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList;
use App\Models\Management\ManagementTaskMessage;
use App\Models\Management\ManagementTaskOperatorProfile;
use App\Models\System\SystemCheckConfig;
use App\Services\AI\Functions\AiTaskFuncs;
use App\Services\Management\ManagementTaskTicketService;
use App\Support\Blueprint\Enums\AiAccessPolicy;
use App\Support\Blueprint\Exceptions\BlueprintSecurityException;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagementTaskTicketSystemTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private ManagementTaskList $list;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->admin = Admin::factory()->create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
        ]);

        $this->list = ManagementTaskList::create([
            'id' => (string) Str::uuid(),
            'name' => 'Fahrplan bis Livegang',
            'icon' => 'clipboard-document-list',
            'color' => '#C5A059',
        ]);

        $this->actingAs($this->admin, 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function new_tickets_start_in_open_table_support_inline_edit_and_drag_to_mine_without_changing_status(): void
    {
        $component = Livewire::test(ManagementTask::class)
            ->set('newTaskTitle', 'Produktfotos Seelen-Kristall')
            ->set('newTaskRelevantFrom', now()->addDays(2)->format('Y-m-d'))
            ->set('newTaskDueTime', '14:30')
            ->call('addTask')
            ->assertHasNoErrors();

        $task = TaskModel::where('title', 'Produktfotos Seelen-Kristall')->firstOrFail();
        $this->assertSame(TaskModel::STATUS_OPEN, $task->status);
        $this->assertFalse($task->is_mine);
        $this->assertSame('14:30', $task->due_time);

        $this->assertCount(1, $component->viewData('openPoolTickets'));
        $this->assertCount(0, $component->viewData('myTickets'));

        // Inline-Bearbeitung in der Offene-Tickets-Tabelle (Titel & Status Warten)
        $component->call('inlineUpdateTitle', $task->id, 'Produktfotos Seelen-Kristall K9');
        $component->call('inlineUpdateStatus', $task->id, TaskModel::STATUS_WAITING);

        $task->refresh();
        $this->assertSame('Produktfotos Seelen-Kristall K9', $task->title);
        $this->assertSame(TaskModel::STATUS_WAITING, $task->status);
        $this->assertFalse($task->is_mine);

        // Drag & Drop in "Meine Tickets": is_mine wird true, Status ("waiting") bleibt unveraendert!
        $component->call('dropTicketIntoPool', $task->id, true, [$task->id]);

        $task->refresh();
        $this->assertTrue($task->is_mine);
        $this->assertSame(TaskModel::STATUS_WAITING, $task->status);
        $this->assertCount(0, $component->viewData('openPoolTickets'));
        $this->assertCount(1, $component->viewData('myTickets'));

        // Drag & Drop zurueck in "Offene Tickets": Status bleibt weiter unveraendert!
        $component->call('dropTicketIntoPool', $task->id, false, [$task->id]);
        $task->refresh();
        $this->assertFalse($task->is_mine);
        $this->assertSame(TaskModel::STATUS_WAITING, $task->status);
    }

    #[Test]
    public function ticket_detail_opens_updates_status_via_top_right_dropdown_and_persists_splitters_and_order_in_config_db(): void
    {
        $first = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Erstes Ticket',
            'status' => TaskModel::STATUS_OPEN,
            'position' => 1,
        ]);
        $second = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Zweites Ticket',
            'status' => TaskModel::STATUS_OPEN,
            'position' => 2,
        ]);

        $component = Livewire::test(ManagementTask::class)
            ->call('selectTask', $first->id)
            ->assertSet('selectedTaskId', $first->id)
            ->assertDispatched('task-ticket-opened')
            ->set('detailStatus', TaskModel::STATUS_IN_PROGRESS)
            ->call('closeDetailPanel')
            ->assertSet('selectedTaskId', null)
            ->assertDispatched('task-ticket-closed');

        $first->refresh();
        $this->assertSame(TaskModel::STATUS_IN_PROGRESS, $first->status);

        // Splitter-Breite & -Hoehe in system_check_configs speichern
        $component->call('saveSplitterLayout', 390, 62);

        // Drag-&-Drop-Reihenfolge umkehren und in Config-DB persistieren
        $component->call('reorderTickets', [$second->id, $first->id], false);

        $cfgRow = SystemCheckConfig::where('user_id', (string) $this->admin->id)->firstOrFail();
        $uiCfg = $cfgRow->widgets['tasks_ui_config'] ?? [];
        $this->assertSame(390, $uiCfg['left_col_width_px']);
        $this->assertSame(62, $uiCfg['my_tickets_height_pct']);
        $this->assertSame([$second->id, $first->id], $uiCfg['open_tickets_order']['all']);

        $this->assertSame(1, $second->fresh()->position);
        $this->assertSame(2, $first->fresh()->position);
    }

    #[Test]
    public function auto_escalation_preserves_single_canonical_status_and_enforces_future_date_plus_chat_resolution(): void
    {
        $overdueTask = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Ueberfaelliges Ticket',
            'status' => TaskModel::STATUS_OPEN,
            'relevant_from' => now()->subDay()->startOfDay(),
        ]);

        $stagnantMyTicket = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Seit 4 Tagen ohne Chat-Update in Meine Tickets',
            'status' => TaskModel::STATUS_WAITING,
            'is_mine' => true,
            'last_activity_at' => now()->subHours(96),
        ]);
        TaskModel::where('id', $stagnantMyTicket->id)->update([
            'last_activity_at' => now()->subHours(96),
            'updated_at' => now()->subHours(96),
        ]);

        $freshMyTicket = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Frisches Ticket in Meine Tickets',
            'status' => TaskModel::STATUS_IN_PROGRESS,
            'is_mine' => true,
            'last_activity_at' => now()->subHours(12),
        ]);

        $count = app(ManagementTaskTicketService::class)->evaluateEscalations();
        $this->assertSame(2, $count);

        $overdueTask->refresh();
        $stagnantMyTicket->refresh();
        $freshMyTicket->refresh();

        // Kanonischer Einzel-Status bleibt erhalten (keine doppelte Statusanzeige!)
        $this->assertTrue($overdueTask->is_escalated);
        $this->assertSame(TaskModel::STATUS_OPEN, $overdueTask->status);
        $this->assertStringContainsString('Fälligkeit überschritten', (string) $overdueTask->escalation_reason);

        $this->assertTrue($stagnantMyTicket->is_escalated);
        $this->assertSame(TaskModel::STATUS_WAITING, $stagnantMyTicket->status);
        $this->assertStringContainsString('3 Tagen', (string) $stagnantMyTicket->escalation_reason);

        $this->assertFalse($freshMyTicket->is_escalated);

        // Stilles Umschalten auf anderen offenen Status wird blockiert, bis Datum + Chat-Grund gesetzt sind
        $futureDate = now()->addDays(4)->format('Y-m-d');
        $component = Livewire::test(ManagementTask::class)
            ->call('selectTask', $overdueTask->id)
            ->call('moveTicketToStatus', $overdueTask->id, TaskModel::STATUS_WAITING);

        $overdueTask->refresh();
        $this->assertTrue($overdueTask->is_escalated);
        $this->assertSame(TaskModel::STATUS_OPEN, $overdueTask->status);

        // Aufloesung mit neuem Datum + Uhrzeit + Status-Nachricht im Chat
        $component
            ->set('escalationNewDate', $futureDate)
            ->set('escalationNewTime', '10:15')
            ->set('escalationTargetStatus', TaskModel::STATUS_WAITING)
            ->set('escalationChatNote', 'Warte weiter auf Rueckmeldung vom Finanzamt Gifhorn.')
            ->call('resolveTicketEscalation');

        $overdueTask->refresh();
        $this->assertFalse($overdueTask->is_escalated);
        $this->assertSame(TaskModel::STATUS_WAITING, $overdueTask->status);
        $this->assertNull($overdueTask->escalation_reason);
        $this->assertSame($futureDate, $overdueTask->relevant_from->format('Y-m-d'));
        $this->assertSame('10:15', $overdueTask->due_time);
    }

    #[Test]
    public function ticket_chat_supports_multiple_uploads_and_5_minute_post_send_editing_window(): void
    {
        $baseNow = Carbon::parse('2026-11-24 12:00:00');
        Carbon::setTestNow($baseNow);

        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Design-Abnahme Verpackung',
            'status' => TaskModel::STATUS_IN_PROGRESS,
        ]);

        $fileOne = UploadedFile::fake()->image('entwurf-front.png', 400, 300);
        $fileTwo = UploadedFile::fake()->create('angebot-westpack.pdf', 120, 'application/pdf');

        $component = Livewire::test(ManagementTask::class)
            ->call('selectTask', $task->id)
            ->set('chatMessage', 'Erster Textentwurf im Chat.')
            ->set('chatUploads', [$fileOne, $fileTwo])
            ->call('sendChatMessage')
            ->assertSet('chatMessage', '');

        $msg = ManagementTaskMessage::where('management_task_id', $task->id)
            ->where('message_type', ManagementTaskMessage::TYPE_COMMENT)
            ->firstOrFail();

        $this->assertSame('Alina Steinhauer', $msg->sender_name);
        $this->assertCount(2, $msg->attachments);
        $this->assertTrue($msg->isEditable());

        // Innerhalb von 5 Minuten (z.B. nach 3 Minuten): Text anpassen & 2. Anhang entfernen
        Carbon::setTestNow($baseNow->copy()->addMinutes(3));
        $firstPath = $msg->attachments[0]['path'];
        $secondPath = $msg->attachments[1]['path'];

        $component
            ->call('startEditMessage', $msg->id)
            ->assertSet('editingMessageId', $msg->id)
            ->call('removeAttachmentFromEditingMessage', $secondPath)
            ->set('editingMessageContent', 'Korrigierter Textentwurf innerhalb von 5 Minuten.')
            ->call('saveEditedMessage')
            ->assertSet('editingMessageId', null);

        $msg->refresh();
        $this->assertSame('Korrigierter Textentwurf innerhalb von 5 Minuten.', $msg->content);
        $this->assertNotNull($msg->edited_at);
        $this->assertCount(1, $msg->attachments);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertMissing($secondPath);

        // Nach Ablauf der 5 Minuten (6 Minuten nach Erstellung): Bearbeitung wird sauber abgewiesen
        Carbon::setTestNow($baseNow->copy()->addMinutes(6));
        $this->assertFalse($msg->fresh()->isEditable());

        $component->call('startEditMessage', $msg->id)
            ->assertSet('editingMessageId', null);
        $this->assertNotEmpty($component->get('escalationError'));
    }

    #[Test]
    public function calendar_renders_tasks_with_due_time_as_timed_blocks_and_updates_time_on_drag(): void
    {
        $tomorrow = now()->addDay()->format('Y-m-d');

        $timedTask = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Laser-Kalibrierung F2 Ultra UV',
            'status' => TaskModel::STATUS_WAITING,
            'relevant_from' => $tomorrow,
            'due_time' => '13:30',
            'duration_minutes' => 90,
        ]);

        $cal = Livewire::test(ManagementCalender::class);
        $events = $cal->instance()->events;

        $virtualId = 'task_' . $timedTask->id;
        $timedEvent = collect($events)->firstWhere('id', $virtualId);

        $this->assertNotNull($timedEvent);
        $this->assertFalse($timedEvent['is_all_day']);
        $this->assertSame('Warten', $timedEvent['status_label']);

        $cal->call('moveEvent', $virtualId, $tomorrow, 16);
        $timedTask->refresh();
        $this->assertSame('16:00', $timedTask->due_time);
    }

    #[Test]
    public function rest_api_supports_mine_toggle_reorder_ui_config_waiting_status_and_5_min_chat_edit(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $futureDate = now()->addDays(3)->format('Y-m-d');

        // 1. Create Ticket via API
        $createRes = $this->postJson('/api/funki/tasks', [
            'title' => 'Desktop & Android Sync Ticket',
            'task_list_id' => $this->list->id,
            'priority' => 'hoch',
            'relevant_from' => $futureDate,
            'due_time' => '11:15',
            'duration_minutes' => 45,
        ])->assertOk();

        $taskId = $createRes->json('data.id');
        $this->assertSame('open', $createRes->json('data.status'));
        $this->assertFalse((bool) $createRes->json('data.is_mine'));

        // 2. Move into "Meine Tickets" via PUT /mine WITHOUT changing status!
        $this->putJson("/api/funki/tasks/{$taskId}/mine", [
            'is_mine' => true,
        ])->assertOk()
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.status', 'open');

        // 3. Change status to "waiting" (Gelb)
        $this->putJson("/api/funki/tasks/{$taskId}/status", [
            'status' => 'waiting',
        ])->assertOk()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.is_mine', true);

        // 4. Persist UI splitter config & reorder via REST API
        $this->putJson('/api/funki/tasks/ui-config', [
            'left_col_width_px' => 360,
            'my_tickets_height_pct' => 58,
        ])->assertOk()->assertJsonPath('data.left_col_width_px', 360);

        $this->postJson('/api/funki/tasks/reorder', [
            'ordered_ids' => [$taskId],
            'is_mine' => true,
        ])->assertOk();

        // 5. Post Chat Message & Edit within 5 minutes via REST API
        $upload = UploadedFile::fake()->create('notiz.txt', 4, 'text/plain');
        $msgRes = $this->post("/api/funki/tasks/{$taskId}/messages", [
            'content' => 'Status aus Funkenwerk gesendet',
            'files' => [$upload],
        ], ['Accept' => 'application/json'])->assertOk();

        $messageId = $msgRes->json('data.id');
        $this->assertTrue((bool) $msgRes->json('data.is_editable'));

        $this->putJson("/api/funki/tasks/{$taskId}/messages/{$messageId}", [
            'content' => 'Status aus Funkenwerk nachtraeglich angepasst',
        ])->assertOk()
            ->assertJsonPath('data.content', 'Status aus Funkenwerk nachtraeglich angepasst');
    }

    #[Test]
    public function funkira_ai_skills_manage_tickets_globally_move_to_mine_update_chat_and_read_attached_files(): void
    {
        // 1. Funkira erstellt ein Ticket per Skill
        $created = AiTaskFuncs::executeCreateTask([
            'title' => 'Verpackungsmuster Karton.eu pruefen',
            'list_name' => 'Fahrplan bis Livegang',
            'status' => 'open',
            'chat_note' => 'Musterpaket ist heute angekommen.',
        ]);
        $this->assertSame('success', $created['status']);
        $taskId = $created['task_id'];

        // 2. Funkira schiebt das Ticket in "Meine Tickets" (Status bleibt open!) und ergaenzt den Chat
        $moved = AiTaskFuncs::executeUpdateTask([
            'task_id' => 'Verpackungsmuster Karton.eu',
            'is_mine' => true,
            'chat_note' => 'Ich messe jetzt die Innenmasse nach.',
        ]);
        $this->assertSame('success', $moved['status']);

        $task = TaskModel::findOrFail($taskId);
        $this->assertTrue($task->is_mine);
        $this->assertSame(TaskModel::STATUS_OPEN, $task->status);

        // 3. Funkira aendert Status auf "waiting" und korrigiert ihren letzten Chat-Eintrag innerhalb von 5 Min
        $updated = AiTaskFuncs::executeUpdateTask([
            'task_id' => $taskId,
            'new_status' => 'waiting',
            'edit_last_chat_note' => 'Innenmasse geprueft, warte auf Staffelpreis von Karton.eu.',
        ]);
        $this->assertSame('success', $updated['status']);
        $this->assertSame(TaskModel::STATUS_WAITING, $task->fresh()->status);

        // 4. Datei im Ticket-Chat hinterlegen und durch Funkira per Dateiname + task_id auslesen lassen
        $storedPath = 'leitung/tasks/attachments/staffelpreise_karton.txt';
        Storage::disk('local')->put($storedPath, '100 Stueck = 0,48 EUR netto pro Karton');

        ManagementTaskMessage::create([
            'management_task_id' => $task->id,
            'admin_id' => $this->admin->id,
            'sender_name' => 'Alina Steinhauer',
            'message_type' => ManagementTaskMessage::TYPE_COMMENT,
            'content' => 'Angebot als TXT angehaengt.',
            'attachments' => [[
                'original_name' => 'staffelpreise_karton.txt',
                'path' => $storedPath,
                'mime' => 'text/plain',
                'size' => 38,
            ]],
        ]);

        $readTicket = AiTaskFuncs::executeGetTasks([
            'task_id' => 'Verpackungsmuster Karton.eu',
            'include_messages' => true,
        ]);
        $this->assertSame('success', $readTicket['status']);
        $this->assertSame(1, $readTicket['open_tasks_count']);
        $this->assertNotEmpty($readTicket['tasks'][0]['attachments']);

        $fileRes = AiTaskFuncs::executeReadFile([
            'file_path' => 'staffelpreise_karton.txt',
            'task_id' => $task->id,
        ]);
        $this->assertSame('success', $fileRes['status']);
        $this->assertStringContainsString('0,48 EUR netto', (string) $fileRes['content']);

        // Path-Traversal ausserhalb leitung/tasks/attachments/ wird strikt blockiert
        $blocked = AiTaskFuncs::executeReadFile([
            'file_path' => '../buchhaltung/geheim.txt',
        ]);
        $this->assertArrayNotHasKey('content', $blocked);
        $this->assertStringContainsString('Zugriff verweigert', (string) ($blocked['message'] ?? ''));
    }

    #[Test]
    public function setting_in_progress_auto_assigns_bearbeiter_moves_to_mine_and_fuzzy_search_excludes_external_roles(): void
    {
        $employee = \App\Models\Employee\Employee::create([
            'first_name' => 'Maximilian',
            'last_name' => 'Lasermeister',
            'email' => 'max@seelenfunke.test',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);
        \App\Models\Employee\EmployeeContract::create([
            'employee_id' => $employee->id,
            'job_title' => 'Werkstatt-Assistent',
            'department' => 'Laser & Versand',
            'is_active' => true,
            'is_archived' => false,
            'can_access_tasks' => true,
        ]);

        $influencer = \App\Models\Employee\Employee::create([
            'first_name' => 'Julia',
            'last_name' => 'Creator',
            'email' => 'julia@creator.test',
            'role' => 'influencer',
            'password' => bcrypt('Secret123!'),
        ]);

        $service = app(ManagementTaskTicketService::class);

        // Fuzzy-Suche findet Tippfehler ("Maximlian", "Alna", "Versnd") und schliesst Influencer strikt aus
        $allActors = $service->getAssignableActors();
        $actorEmailsOrNames = array_column($allActors, 'name');
        $this->assertContains('Alina Steinhauer', $actorEmailsOrNames);
        $this->assertContains('Maximilian Lasermeister', $actorEmailsOrNames);
        $this->assertNotContains('Julia Creator', $actorEmailsOrNames);

        $fuzzyTypo = $service->getAssignableActors('Maximlian');
        $this->assertNotEmpty($fuzzyTypo);
        $this->assertSame('Maximilian Lasermeister', $fuzzyTypo[0]['name']);

        $fuzzyAdminTypo = $service->getAssignableActors('Alna Steinh');
        $this->assertNotEmpty($fuzzyAdminTypo);
        $this->assertSame('Alina Steinhauer', $fuzzyAdminTypo[0]['name']);

        // Ticket mit Bearbeiter beim Anlegen erstellen, danach durch Admin auf "In Bearbeitung" setzen
        $component = Livewire::test(ManagementTask::class)
            ->set('newTaskTitle', 'Acryl-Zuschnitt prüfen')
            ->call('addTask');

        $task = TaskModel::where('title', 'Acryl-Zuschnitt prüfen')->firstOrFail();
        $this->assertNull($task->assignee_id);
        $this->assertFalse($task->is_mine);

        // Status "In Bearbeitung" setzt automatisch die aktive Person als Bearbeiter UND schiebt in Meine Tickets
        $component->call('inlineUpdateStatus', $task->id, TaskModel::STATUS_IN_PROGRESS);
        $task->refresh();

        $this->assertTrue($task->is_mine);
        $this->assertSame('admin', $task->assignee_type);
        $this->assertSame((string) $this->admin->id, (string) $task->assignee_id);
        $this->assertSame('Alina Steinhauer', $task->assignee_name);
    }

    #[Test]
    public function employee_dashboard_enforces_private_list_isolation_calendar_separation_and_team_ticket_workflow(): void
    {
        $employee = \App\Models\Employee\Employee::create([
            'first_name' => 'Lukas',
            'last_name' => 'Verpackung',
            'email' => 'lukas@seelenfunke.test',
            'role' => 'internal',
            'password' => bcrypt('Secret123!'),
        ]);
        \App\Models\Employee\EmployeeContract::create([
            'employee_id' => $employee->id,
            'job_title' => 'Versandhelfer',
            'department' => 'Versand',
            'is_active' => true,
            'is_archived' => false,
            'can_access_production' => false,
            'can_access_tasks' => true,
        ]);

        // Private Chef-Liste (is_team_visible = false) vs. Team-Liste (is_team_visible = true)
        $privateTask = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id, // is_team_visible = false
            'title' => 'STRENG GEHEIM: Steuerberater Bilanz',
            'status' => TaskModel::STATUS_OPEN,
        ]);

        $teamList = ManagementTaskList::create([
            'id' => (string) Str::uuid(),
            'name' => 'Werkstatt & Versand',
            'color' => '#C5A059',
            'is_team_visible' => true,
        ]);

        $tomorrow = now()->addDay()->format('Y-m-d');
        $teamTask = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $teamList->id,
            'title' => '100 Geschenkkartons vorfalten',
            'status' => TaskModel::STATUS_OPEN,
            'relevant_from' => $tomorrow,
            'due_time' => '10:00',
        ]);

        // 1. Mitarbeiter-Dashboard rendert sauber Datenkachel + Ticketkachel, ohne geheimes Chef-Ticket zu zeigen!
        Livewire::actingAs($employee, 'employee')
            ->test(\App\Livewire\Employee\EmployeeDashboard::class)
            ->assertSee('Lukas Verpackung')
            ->assertSee('Monatsstunden')
            ->assertSee('Score & Profil', false)
            ->assertDontSee('STRENG GEHEIM: Steuerberater Bilanz');

        $empBoard = Livewire::actingAs($employee, 'employee')
            ->test(ManagementTask::class, ['embeddedMode' => true])
            ->assertSee('100 Geschenkkartons vorfalten')
            ->assertDontSee('STRENG GEHEIM: Steuerberater Bilanz');

        // 2. Mitarbeiter nimmt das Team-Ticket "In Bearbeitung": wird automatisch Bearbeiter & landet in SEINEN Meine Tickets
        $empBoard->call('inlineUpdateStatus', $teamTask->id, TaskModel::STATUS_IN_PROGRESS);
        $teamTask->refresh();

        $this->assertSame('employee', $teamTask->assignee_type);
        $this->assertSame((string) $employee->id, (string) $teamTask->assignee_id);
        $this->assertSame('Lukas Verpackung', $teamTask->assignee_name);
        $this->assertTrue($teamTask->is_mine);

        // 3. Alinas persönlicher Kalender wird NICHT mit Mitarbeiter-Tickets gefüllt!
        $this->actingAs($this->admin, 'admin');
        $cal = Livewire::test(ManagementCalender::class);
        $this->assertNull(collect($cal->instance()->events)->firstWhere('id', 'task_' . $teamTask->id));

        // 4. Alina sieht das an Lukas delegierte Ticket trotzdem in ihrer Admin-Tabelle inklusive Bearbeiter!
        $adminBoard = Livewire::actingAs($this->admin, 'admin')->test(ManagementTask::class);
        $adminOpenIds = collect($adminBoard->viewData('openPoolTickets'))->pluck('id')->all();
        $this->assertContains($teamTask->id, $adminOpenIds);
    }

    #[Test]
    public function gamification_score_pauses_on_waiting_prevents_ep_farming_and_equips_titles_and_backgrounds_in_profile_modal(): void
    {
        $t0 = Carbon::parse('2026-11-24 09:00:00');
        Carbon::setTestNow($t0);

        $task = TaskModel::create([
            'id' => (string) Str::uuid(),
            'task_list_id' => $this->list->id,
            'title' => 'Gravur-Schablone kalibrieren',
            'ai_plan' => 'Detaillierte Kalibrierung für den xTool F2 Ultra UV.',
            'status' => TaskModel::STATUS_OPEN,
            'relevant_from' => $t0->copy()->addDays(5),
        ]);

        $service = app(ManagementTaskTicketService::class);

        // 1. In Bearbeitung nehmen (+10 EP)
        $task = $service->changeStatus($task, TaskModel::STATUS_IN_PROGRESS);

        // 2. Nach 1 Stunde aktiver Arbeit für 48 Stunden auf "Warten" stellen (Wartezeit pausiert Netto-Uhr!)
        Carbon::setTestNow($t0->copy()->addHour());
        $task = $service->changeStatus($task, TaskModel::STATUS_WAITING);

        Carbon::setTestNow($t0->copy()->addHours(49)); // 48h später
        $task = $service->changeStatus($task, TaskModel::STATUS_IN_PROGRESS);
        $this->assertEqualsWithDelta(48 * 3600, $task->waiting_paused_seconds, 5);

        // 3. Chat-Kommentar (+5 EP) und nach 1 weiteren Stunde pünktlich erledigen (+30 Basis + 15 Pünktlich = +45 EP -> Summe 60 EP = Stufe 2!)
        $service->postMessage($task, 'Kalibrierung erfolgreich dokumentiert.');
        Carbon::setTestNow($t0->copy()->addHours(50));
        $task = $service->changeStatus($task, TaskModel::STATUS_COMPLETED);

        $profile = \App\Models\Management\ManagementTaskOperatorProfile::where('actor_type', 'admin')
            ->where('actor_id', $this->admin->id)
            ->firstOrFail();

        $this->assertSame(60, $profile->ep_total);
        $this->assertSame(2, $profile->resolveLevel());
        // Netto-Lösungszeit ~ 2h (7200s) statt 50h Brutto!
        $this->assertLessThan(9000, $profile->total_net_resolution_seconds);

        // 4. Anti-Exploit: Status-Ping-Pong (wieder öffnen & nochmal erledigen) vergibt 0 Extra-EP!
        $task = $service->changeStatus($task, TaskModel::STATUS_OPEN);
        $task = $service->changeStatus($task, TaskModel::STATUS_IN_PROGRESS);
        $task = $service->changeStatus($task, TaskModel::STATUS_COMPLETED);
        $this->assertSame(60, $profile->fresh()->ep_total);

        // 5. Profil-Modal öffnen und auf Stufe 2 freigeschalteten Titel ("anpacker") & Background ("warm_sand") ausrüsten
        $comp = Livewire::test(ManagementTask::class)
            ->call('openOperatorProfileModal', 'admin', (string) $this->admin->id)
            ->assertSet('profileModalOpen', true)
            ->call('equipOperatorTitle', 'anpacker')
            ->call('equipOperatorBackground', 'warm_sand');

        $modalPayload = $comp->get('profileModalData');
        $this->assertSame('Anpacker', $modalPayload['title_label']);
        $this->assertSame('warm_sand', $modalPayload['equipped_background_key']);
        $this->assertSame('Kristall-Laser-Kammer', $modalPayload['background']['label']);
        $this->assertCount(11, $modalPayload['backgrounds']);

        // Jede Hintergrund-Stufe 1..10 besitzt ein reales WebP-Banner und steigende Seelenfunken-Dichte
        $prevSparks = 0;
        foreach (ManagementTaskOperatorProfile::backgroundCatalog() as $bgDef) {
            $relPath = ltrim(parse_url($bgDef['image_url'], PHP_URL_PATH) ?: '', '/');
            $this->assertFileExists(public_path($relPath));
            $this->assertGreaterThanOrEqual($prevSparks, $bgDef['sparks_count']);
            $prevSparks = $bgDef['sparks_count'];
        }
    }

    #[Test]
    public function operator_profile_blueprint_and_edge_case_guards_are_bulletproof(): void
    {
        $blueprint = ManagementTaskOperatorProfile::blueprint();
        $this->assertSame(AiAccessPolicy::ReadOnly, $blueprint->aiAccess);
        ManagementTaskOperatorProfile::assertAiReadAllowed();

        try {
            ManagementTaskOperatorProfile::assertAiWriteAllowed();
            $this->fail('Erwartete BlueprintSecurityException bei Schreibversuch auf ReadOnly-Operator-Profil.');
        } catch (BlueprintSecurityException $e) {
            $this->assertNotEmpty($e->getMessage());
        }

        // Unguardede / korrupte / Null-Werte im Model duerfen niemals Exceptions oder negative Raten werfen
        $profile = new ManagementTaskOperatorProfile([
            'actor_type' => '',
            'actor_id' => '00000000-0000-0000-0000-000000000001',
            'display_name' => null,
            'role_label' => null,
            'ep_total' => -50,
            'efficiency_score' => null,
            'tickets_claimed_count' => null,
            'tickets_completed_count' => null,
            'tickets_on_time_count' => null,
            'escalations_resolved_count' => null,
            'fast_escalations_count' => -5,
            'total_net_resolution_seconds' => -120,
            'equipped_title_key' => 'unbekannter_titel',
            'equipped_background_key' => 'seelen_gold', // erst ab Stufe 6 erlaubt
        ]);

        $this->assertSame(1, $profile->resolveLevel());
        $this->assertSame('KLASSE C', $profile->resolveRankClass());
        $this->assertFalse($profile->isTitleUnlocked(null));
        $this->assertFalse($profile->isTitleUnlocked(''));
        $this->assertFalse($profile->isTitleUnlocked('unbekannter_titel'));
        $this->assertFalse($profile->isBackgroundUnlocked(null));
        $this->assertFalse($profile->isBackgroundUnlocked('seelen_gold'));
        $this->assertSame('starter', $profile->resolveEquippedTitleKey());
        $this->assertSame('Starter', $profile->resolveEquippedTitleLabel());
        $this->assertSame('obsidian_slate', $profile->resolveEquippedBackground()['key']);
        $this->assertSame(100, $profile->recalculateEfficiencyScore());

        $payload = $profile->toModalPayload();
        $this->assertSame('Teammitglied', $payload['display_name']);
        $this->assertSame('T', $payload['initials']);
        $this->assertSame(0, $payload['ep_total']);
        $this->assertSame(100, $payload['efficiency_score']);
        $this->assertSame('starter', $payload['equipped_title_key']);
        $this->assertSame('obsidian_slate', $payload['equipped_background_key']);

        // Service-Haertung: Idempotenz, gesperrte Cosmetics & leerer Viewer/Actor-Guard
        $service = app(ManagementTaskTicketService::class);
        $created = $service->getOrCreateOperatorProfile('admin', (string) $this->admin->id);
        $again = $service->getOrCreateOperatorProfile('admin', (string) $this->admin->id);
        $this->assertSame($created->id, $again->id);

        $safeModal = $service->getOperatorProfileModalPayload('admin', (string) $this->admin->id, ['invalid' => true]);
        $this->assertFalse($safeModal['is_own_profile']);

        $ownModal = $service->getOperatorProfileModalPayload('admin', (string) $this->admin->id, []);
        $this->assertTrue($ownModal['is_own_profile']);

        $unchanged = $service->equipOperatorCosmetic('admin', (string) $this->admin->id, 'seelenfunke_legende', 'obsidian_master');
        $this->assertNull($unchanged->equipped_title_key);
        $this->assertSame('obsidian_slate', $unchanged->equipped_background_key);

        $this->expectException(InvalidArgumentException::class);
        $service->getOrCreateOperatorProfile('admin', '   ');
    }
}
