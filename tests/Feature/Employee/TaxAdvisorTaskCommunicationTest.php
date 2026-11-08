<?php

namespace Tests\Feature\Employee;

use App\Livewire\Employee\TaxAdvisor\TaxAdvisorDashboard;
use App\Livewire\Shop\Management\ManagementTask;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Management\ManagementTask as TaskModel;
use App\Models\Management\ManagementTaskList;
use App\Models\System\SystemLog;
use App\Models\System\SystemSetting;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxAdvisorTaskCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected Employee $taxAdvisor;
    protected Employee $internalEmployee;
    protected Employee $influencer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);

        $this->admin = Admin::where('email', 'kontakt@mein-seelenfunke.de')->firstOrFail();
        $this->taxAdvisor = Employee::where('email', 'steuerberater@mein-seelenfunke.de')->firstOrFail();
        $this->internalEmployee = Employee::where('email', 'mitarbeiter@mein-seelenfunke.de')->firstOrFail();
        $this->influencer = Employee::where('email', 'influencer@mein-seelenfunke.de')->firstOrFail();

        SystemSetting::updateOrCreate(['key' => 'owner_tax_id'], ['value' => '19/123/45678']);
        SystemSetting::updateOrCreate(['key' => 'owner_proprietor'], ['value' => 'Alina Steinhauer']);
        SystemSetting::updateOrCreate(['key' => 'default_tax_rate'], ['value' => '19']);
    }

    #[Test]
    public function tax_advisor_can_view_tasks_strictly_isolated_to_kanzlei_list(): void
    {
        // 1. Create dedicated "Kanzlei & Steuern" list
        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        // 2. Create another internal management list (e.g. Laser-Werkstatt)
        $internalList = ManagementTaskList::create([
            'name' => 'Laser-Werkstatt Produktion',
            'color' => '#3B82F6',
            'icon' => 'wrench',
            'is_system' => false,
        ]);

        // 3. Create task in Tax list
        $taxTask = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Belegnachweis FYRST Bankgebühren fehlt',
            'priority' => 'hoch',
            'is_completed' => false,
            'ai_plan' => "[07.11.2026 10:00 - Kanzlei (Dr. Thomas Steuerberater)]:\nBitte den PDF-Kontoauszug nachreichen.",
        ]);

        // 4. Create task in Internal list
        $internalTask = TaskModel::create([
            'task_list_id' => $internalList->id,
            'title' => 'UV-Laser Linse reinigen und kalibrieren',
            'priority' => 'niedrig',
            'is_completed' => false,
        ]);

        // 5. Test Livewire component as Tax Advisor
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->assertSee('Kanzlei-Rückfragen & Aufgaben')
            ->assertSee('Belegnachweis FYRST Bankgebühren fehlt')
            ->assertSee('Dringend')
            ->assertDontSee('UV-Laser Linse reinigen und kalibrieren');
    }

    #[Test]
    public function tax_advisor_can_create_a_task_with_attachments_and_priority(): void
    {
        Storage::fake('local');

        $fakePdf = UploadedFile::fake()->create('Fragebogen_UStVA_Korr.pdf', 150, 'application/pdf');

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('toggleNewTaskForm')
            ->assertSet('showNewTaskForm', true)
            ->set('newTaskTitle', 'Umsatzsteuer-Voranmeldung Q3 Vorsteuerberichtigung')
            ->set('newTaskPriority', 'hoch')
            ->set('newTaskRelevantFrom', '2026-11-15')
            ->set('newTaskNote', 'Bitte prüfen Sie die Rechnung vom Lieferanten Glücksfieber.')
            ->set('newTaskUploads', [$fakePdf])
            ->call('createTaxTask')
            ->assertSet('showNewTaskForm', false)
            ->assertSee('Rückfrage / Aufgabe erfolgreich an Mandanten übermittelt');

        $createdTask = TaskModel::where('title', 'Umsatzsteuer-Voranmeldung Q3 Vorsteuerberichtigung')->first();
        $this->assertNotNull($createdTask);
        $this->assertSame('hoch', $createdTask->priority);
        $this->assertFalse($createdTask->is_completed);
        $this->assertStringContainsString('Kanzlei (Dr. Thomas Steuerberater)', $createdTask->ai_plan);
        $this->assertStringContainsString('Bitte prüfen Sie die Rechnung vom Lieferanten Glücksfieber', $createdTask->ai_plan);

        $files = is_array($createdTask->file_paths) ? $createdTask->file_paths : json_decode($createdTask->file_paths, true);
        $this->assertCount(1, $files);
        $this->assertTrue(Storage::disk('local')->exists($files[0]));
    }

    #[Test]
    public function tax_advisor_can_toggle_task_completion(): void
    {
        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Elster Übermittlungsprotokoll bestätigen',
            'priority' => 'niedrig',
            'is_completed' => false,
        ]);

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('toggleTaskComplete', (string) $task->id);

        $this->assertTrue($task->fresh()->is_completed);

        // Toggle back to open
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('toggleTaskComplete', (string) $task->id);

        $this->assertFalse($task->fresh()->is_completed);
    }

    #[Test]
    public function tax_advisor_can_submit_inline_dialogue_response_with_files(): void
    {
        Storage::fake('local');

        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Rückfrage zu Westpack Verpackung',
            'priority' => 'niedrig',
            'is_completed' => false,
            'ai_plan' => "[01.11.2026 12:00 - Mandantin Alina]:\nIst der Beleg so ausreichend?",
        ]);

        $responseDoc = UploadedFile::fake()->create('Pruefbericht_Kanzlei.pdf', 200, 'application/pdf');

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('openTaskResponse', (string) $task->id)
            ->assertSet('activeResponseTaskId', (string) $task->id)
            ->set('taskResponseText', 'Ja, der Beleg ist buchhalterisch vollständig.')
            ->set('taskResponseUploads', [$responseDoc])
            ->call('submitTaskResponse', (string) $task->id)
            ->assertSet('activeResponseTaskId', null)
            ->assertSee('Antwort / Dokumente erfolgreich gespeichert');

        $task->refresh();
        $this->assertStringContainsString('Mandantin Alina', $task->ai_plan);
        $this->assertStringContainsString('Kanzlei (Dr. Thomas Steuerberater)', $task->ai_plan);
        $this->assertStringContainsString('Ja, der Beleg ist buchhalterisch vollständig.', $task->ai_plan);

        $files = is_array($task->file_paths) ? $task->file_paths : json_decode($task->file_paths, true);
        $this->assertCount(1, $files);
        $this->assertTrue(Storage::disk('local')->exists($files[0]));
    }

    #[Test]
    public function tax_advisor_can_download_attached_task_file(): void
    {
        Storage::fake('local');

        $filePath = 'leitung/tasks/attachments/steuer_beleg_123.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 Fake Tax Attachment');

        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Geprüfter Steuerbescheid',
            'priority' => 'niedrig',
            'is_completed' => false,
            'file_paths' => [$filePath],
        ]);

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('downloadTaskFile', (string) $task->id, $filePath)
            ->assertFileDownloaded('steuer_beleg_123.pdf');
    }

    #[Test]
    public function path_traversal_and_unattached_file_downloads_are_blocked_with_403(): void
    {
        Storage::fake('local');

        $secretPath = 'private_salaries.csv';
        Storage::disk('local')->put($secretPath, 'secret data');

        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Sicherheits-Check Download',
            'priority' => 'niedrig',
            'is_completed' => false,
            'file_paths' => ['leitung/tasks/attachments/legit.pdf'],
        ]);

        // Attempting to download unattached file results in 403
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('downloadTaskFile', (string) $task->id, $secretPath)
            ->assertForbidden();

        // Attempting path traversal results in 403
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('downloadTaskFile', (string) $task->id, '../../.env')
            ->assertForbidden();
    }

    #[Test]
    public function internal_employee_and_influencer_are_isolated_from_tax_advisor_dashboard(): void
    {
        // Internal employee cannot mount TaxAdvisorDashboard
        Livewire::actingAs($this->internalEmployee, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->assertForbidden();

        // Influencer cannot mount TaxAdvisorDashboard
        Livewire::actingAs($this->influencer, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->assertForbidden();
    }

    #[Test]
    public function admin_can_see_tax_advisor_tasks_in_management_tasks_seamlessly(): void
    {
        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Belegnachweis Sonderausgabe UV-Laser',
            'priority' => 'hoch',
            'is_completed' => false,
            'ai_plan' => "[07.11.2026 - Steuerberater]: Bitte Quittung hochladen.",
        ]);

        // Admin (Alina) accesses ManagementTask Livewire component
        Livewire::actingAs($this->admin, 'admin')
            ->test(ManagementTask::class)
            ->call('selectList', (string) $taxList->id)
            ->assertSee('Belegnachweis Sonderausgabe UV-Laser')
            ->assertSee('Kanzlei & Steuern');
    }

    #[Test]
    public function tax_advisor_can_prefill_new_task_for_missing_receipt_and_filter_by_receipt_status(): void
    {
        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('prefillNewTaskForMissingReceipt', 'Cloud Server Hosting', '119,00', 'Fixvertrag')
            ->assertSet('showNewTaskForm', true)
            ->assertSet('newTaskTitle', 'Beleg fehlt: Cloud Server Hosting')
            ->assertSet('newTaskPriority', 'hoch')
            ->assertDispatched('switch-tab', tab: 'tasks')
            ->call('setReceiptStatusFilter', 'missing_receipt')
            ->assertSet('receiptStatusFilter', 'missing_receipt');
    }

    #[Test]
    public function tax_advisor_downloads_are_recorded_in_gobd_audit_log(): void
    {
        Storage::fake('local');

        $filePath = 'leitung/tasks/attachments/audit_test_file.pdf';
        Storage::disk('local')->put($filePath, '%PDF-1.4 Fake Tax Attachment');

        $taxList = ManagementTaskList::firstOrCreate(
            ['name' => 'Kanzlei & Steuern'],
            ['color' => '#C5A059', 'icon' => 'calculator', 'is_system' => true]
        );

        $task = TaskModel::create([
            'task_list_id' => $taxList->id,
            'title' => 'Audit Test Task',
            'priority' => 'niedrig',
            'is_completed' => false,
            'file_paths' => [$filePath],
        ]);

        Livewire::actingAs($this->taxAdvisor, 'employee')
            ->test(TaxAdvisorDashboard::class)
            ->call('downloadTaskFile', (string) $task->id, $filePath);

        $this->assertDatabaseHas('system_logs', [
            'type' => 'gobd_audit',
            'title' => 'Steuerberater Export Zugriff',
        ]);
    }
}
