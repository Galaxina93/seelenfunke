<?php

namespace Tests\Feature\Livewire\Global\Ai;

use App\Livewire\Shop\Management\ManagementHealth as AiCeoHealth;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiChatMemory;
use App\Models\Ai\AiHealthMedication;
use App\Models\Ai\AiHealthProtocol;
use App\Models\Ai\AiHealthTreatmentItem;
use App\Models\Ai\AiHealthTreatmentPlan;
use App\Models\System\SystemUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiCeoHealthTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = \App\Models\Admin\Admin::withoutEvents(function () {
            return \App\Models\Admin\Admin::forceCreate([
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'email' => 'test@admin.local',
                'password' => bcrypt('password'),
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);
        });
        $this->actingAs($this->user, 'admin');

        // Create the Dr. Funki Agent
        $this->agent = AiAgent::create([
            'name' => 'Dr. Funki',
            'role' => 'system',
            'prompt' => 'You are a doctor.',
            'base_model' => 'gpt-4',
            'color' => 'teal-500',
            'icon' => 'user-plus',
            'is_active' => true,
        ]);

        Storage::fake('public');
    }

    public function test_component_mounts_correctly_and_creates_initial_welcome_message()
    {
        // Act & Assert
        Livewire::test(AiCeoHealth::class)
            ->assertSet('agentId', $this->agent->id)
            ->assertSet('activeTab', 'chat')
            ->assertCount('messages', 1) // Initial welcome message
            ->assertSee('Guten Tag! Ich bin Dr. Funki');
            
        // Assert DB
        $this->assertDatabaseHas('ai_chat_memories', [
            'role' => 'assistant',
            'session_id' => session()->getId() . '_health',
        ]);
    }

    public function test_component_loads_existing_chat_history()
    {
        AiChatMemory::create([
            'session_id' => session()->getId() . '_health',
            'role' => 'user',
            'content' => 'Mir geht es nicht gut.',
            'context_data' => ['name' => 'John', 'color' => 'gray-400', 'icon' => 'user']
        ]);

        AiChatMemory::create([
            'session_id' => session()->getId() . '_health',
            'role' => 'assistant',
            'content' => 'Ohje, was fehlt Ihnen?',
            'context_data' => ['name' => 'Dr. Funki', 'color' => 'teal-500', 'icon' => 'user-plus']
        ]);

        Livewire::test(AiCeoHealth::class)
            ->assertCount('messages', 2)
            ->assertSee('Mir geht es nicht gut.')
            ->assertSee('Ohje, was fehlt Ihnen?');
    }

    public function test_can_send_message_and_trigger_inference()
    {
        Livewire::test(AiCeoHealth::class)
            ->set('input', 'Ich habe Kopfschmerzen.')
            ->call('sendMessage')
            ->assertSet('input', '')
            ->assertSet('typing', true)
            ->assertCount('messages', 2) // Welcome + New user message
            ->assertSee('Ich habe Kopfschmerzen.')
            ->assertDispatched('start-health-ai-inference');

        $this->assertDatabaseHas('ai_chat_memories', [
            'role' => 'user',
            'content' => 'Ich habe Kopfschmerzen.'
        ]);
    }

    public function test_can_switch_tabs()
    {
        Livewire::test(AiCeoHealth::class)
            ->assertSet('activeTab', 'chat')
            ->call('selectTab', 'medications')
            ->assertSet('activeTab', 'medications')
            ->call('selectTab', 'plans')
            ->assertSet('activeTab', 'plans')
            ->call('selectTab', 'protocols')
            ->assertSet('activeTab', 'protocols')
            ->call('selectTab', 'files')
            ->assertSet('activeTab', 'files');
    }

    public function test_can_create_and_manage_medications()
    {
        $component = Livewire::test(AiCeoHealth::class)
            ->call('editMedication')
            ->assertSet('showMedicationModal', true)
            ->set('medicationForm.name', 'Ibuprofen 400')
            ->set('medicationForm.dosage', '400mg')
            ->set('medicationForm.frequency', 'Bei Bedarf')
            ->call('saveMedication')
            ->assertSet('showMedicationModal', false);

        $this->assertDatabaseHas('ai_health_medications', [
            'name' => 'Ibuprofen 400',
            'dosage' => '400mg',
        ]);

        $medication = AiHealthMedication::first();

        // View Medication
        $component->call('selectTab', 'medications')
            ->call('viewMedication', $medication->id)
            ->assertSet('viewingMedicationId', $medication->id)
            ->assertSee('Ibuprofen 400');

        // Close View
        $component->call('closeMedicationView')
            ->assertSet('viewingMedicationId', null);

        // Edit Existing Medication
        $component->call('editMedication', $medication->id)
            ->assertSet('medicationForm.id', $medication->id)
            ->set('medicationForm.dosage', '600mg')
            ->call('saveMedication');

        $this->assertDatabaseHas('ai_health_medications', [
            'id' => $medication->id,
            'dosage' => '600mg',
        ]);

        // Delete Medication
        $component->call('deleteMedication', $medication->id);
        $this->assertDatabaseMissing('ai_health_medications', ['id' => $medication->id]);
    }

    public function test_file_management_creates_folders_and_navigates()
    {
        Livewire::test(AiCeoHealth::class)
            ->call('createFolder', 'Laborberichte')
            ->assertSet('uploadedHealthFiles.0.name', 'Laborberichte')
            ->assertSet('uploadedHealthFiles.0.type', 'folder');

        Storage::disk('public')->assertExists('wiki/health/Laborberichte');

        Livewire::test(AiCeoHealth::class)
            ->call('openFolder', 'Laborberichte')
            ->assertSet('currentPath', 'wiki/health/Laborberichte')
            ->call('goUp')
            ->assertSet('currentPath', 'wiki/health');
    }

    public function test_can_upload_files()
    {
        $file1 = UploadedFile::fake()->create('blutbild.pdf', 100);
        $file2 = UploadedFile::fake()->create('röntgen.png', 200);

        Livewire::test(AiCeoHealth::class)
            ->set('healthFiles', [$file1, $file2])
            ->assertDispatched('health-files-updated')
            ->assertDispatched('docs-uploaded');

        Storage::disk('public')->assertExists('wiki/health/blutbild.pdf');
        Storage::disk('public')->assertExists('wiki/health/röntgen.png');
    }

    public function test_can_delete_files_and_folders()
    {
        Storage::disk('public')->put('wiki/health/test.txt', 'Content');
        Storage::disk('public')->makeDirectory('wiki/health/TestFolder');

        Livewire::test(AiCeoHealth::class)
            ->call('deleteItem', 'wiki/health/test.txt')
            ->call('deleteItem', 'wiki/health/TestFolder');

        Storage::disk('public')->assertMissing('wiki/health/test.txt');
        $this->assertFalse(Storage::disk('public')->exists('wiki/health/TestFolder'));
    }

    public function test_treatment_plan_item_toggle_and_auto_complete()
    {
        $plan = AiHealthTreatmentPlan::create([
            'title' => 'Erkältung',
            'status' => 'active',
            'user_id' => $this->user->id,
            'ai_agent_id' => $this->agent->id,
        ]);

        $item1 = AiHealthTreatmentItem::create([
            'plan_id' => $plan->id,
            'name' => 'Tee trinken',
            'dosage' => '1 Tasse',
            'is_completed' => false,
        ]);

        $item2 = AiHealthTreatmentItem::create([
            'plan_id' => $plan->id,
            'name' => 'Schlafen',
            'dosage' => '8 Stunden',
            'is_completed' => true, // Already done
        ]);

        Livewire::test(AiCeoHealth::class)
            ->call('togglePlanItem', $item1->id);

        $item1->refresh();
        $plan->refresh();

        $this->assertTrue((bool)$item1->is_completed);
        $this->assertEquals('completed', $plan->status);
        $this->assertEquals('Plan wurde automatisch durch Erledigung aller Schritte abgeschlossen.', $plan->result_evaluation);
    }
    
    public function test_clear_chat_removes_history()
    {
        AiChatMemory::create([
            'session_id' => session()->getId() . '_health',
            'role' => 'user',
            'content' => 'To be deleted',
        ]);
        
        Livewire::test(AiCeoHealth::class)
            ->call('clearChat')
            ->assertCount('messages', 1) // Only initial welcome remaining after remount
            ->assertSee('Guten Tag! Ich bin Dr. Funki');
            
        $this->assertDatabaseMissing('ai_chat_memories', ['content' => 'To be deleted']);
    }
}
