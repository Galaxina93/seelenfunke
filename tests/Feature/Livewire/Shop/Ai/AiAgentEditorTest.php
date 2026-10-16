<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use App\Livewire\Shop\Ai\AiAgentEditor;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiRole;
use App\Models\Ai\AiTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AiAgentEditorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        
        session()->setId(Str::uuid()->toString());
        session()->start();
    }

    /** @test */
    public function test_editor_renders_successfully()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::first() ?? AiAgent::create([
            'name' => 'Test Agent',
            'wake_word' => 'Test',
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Aoede'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->assertStatus(200)
            ->assertSee($agent->name);
    }

    /** @test */
    public function test_saving_agent_persists_changes_and_does_not_redirect()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::first() ?? AiAgent::create([
            'name' => 'Initial Name',
            'wake_word' => 'Initial',
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->set('name', 'Updated Agent Name')
            ->call('save')
            ->assertNoRedirect()
            ->assertSet('savedMessage', 'Gespeichert')
            ->assertDispatched('agent-saved', message: 'Gespeichert');

        $this->assertEquals('Updated Agent Name', $agent->fresh()->name);
    }

    /** @test */
    public function test_changing_model_autosaves_and_dispatches_active_voice_notice()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::create([
            'name' => 'Autosave Agent',
            'wake_word' => 'Autosave',
            'model' => 'gemini-3.8-flash',
            'color' => 'emerald-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Aoede'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->set('model', 'gemini-3.1-pro')
            ->assertDispatched('agent-saved', function ($event, $params) {
                return str_contains($params['message'], 'Gespeichert') && 
                       str_contains($params['message'], 'Aoede');
            });

        $this->assertEquals('gemini-3.1-pro', $agent->fresh()->model);
    }

    /** @test */
    public function test_changing_tts_voice_autosaves_and_dispatches_notice()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::create([
            'name' => 'Voice Agent',
            'wake_word' => 'Voice',
            'model' => 'gemini-3.8-flash',
            'color' => 'emerald-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Aoede'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->set('tts_voice', 'Kore')
            ->assertDispatched('agent-saved', function ($event, $params) {
                return str_contains($params['message'], 'Gespeichert') && 
                       str_contains($params['message'], 'Kore');
            });

        $this->assertEquals('Kore', $agent->fresh()->tts_voice);
    }

    /** @test */
    public function test_saving_new_agent_sets_agent_id_and_does_not_redirect()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => 'new'])
            ->set('name', 'Brand New Agent')
            ->set('color', 'cyan-500')
            ->set('icon', 'sparkles')
            ->call('save')
            ->assertNoRedirect()
            ->assertSet('savedMessage', 'Gespeichert')
            ->assertDispatched('agent-saved', message: 'Gespeichert');

        $createdAgent = AiAgent::where('name', 'Brand New Agent')->first();
        $this->assertNotNull($createdAgent);
        $this->assertEquals($createdAgent->id, $component->get('agentId'));
    }

    /** @test */
    public function test_changing_tts_voice_triggers_sample_playback_dispatch()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::create([
            'name' => 'Audio Preview Agent',
            'wake_word' => 'Audio',
            'model' => 'gemini-3.8-flash',
            'color' => 'indigo-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Aoede'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->set('tts_voice', 'Puck')
            ->assertDispatched('play-voice-sample');
    }

    /** @test */
    public function test_play_voice_sample_button_dispatches_audio_event()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::create([
            'name' => 'Audio Button Agent',
            'wake_word' => 'Audio',
            'model' => 'gemini-3.8-flash',
            'color' => 'indigo-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Fenrir'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->call('playVoiceSample')
            ->assertDispatched('play-voice-sample');
    }

    /** @test */
    public function test_voice_selection_persists_across_multiple_changes_and_does_not_revert_to_aoede()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::create([
            'name' => 'Persistence Agent',
            'wake_word' => 'Persist',
            'model' => 'gemini-3.8-flash',
            'color' => 'indigo-500',
            'icon' => 'sparkles',
            'tts_enabled' => true,
            'tts_provider' => 'gemini_native',
            'tts_voice' => 'Aoede'
        ]);

        $test = Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id]);

        // Change to Fenrir
        $test->set('tts_voice', 'Fenrir');
        $this->assertEquals('Fenrir', $agent->fresh()->tts_voice);
        $test->assertSet('tts_voice', 'Fenrir');

        // Change model - ensure voice remains Fenrir
        $test->set('model', 'gemini-3.1-pro');
        $this->assertEquals('Fenrir', $agent->fresh()->tts_voice);
        $test->assertSet('tts_voice', 'Fenrir');

        // Change to Kore
        $test->set('tts_voice', 'Kore');
        $this->assertEquals('Kore', $agent->fresh()->tts_voice);
        $test->assertSet('tts_voice', 'Kore');
    }

    /** @test */
    public function test_editor_renders_three_pillars_and_role_management_actions()
    {
        $admin = Admin::first() ?? Admin::factory()->create();
        $agent = AiAgent::first() ?? AiAgent::create([
            'name' => 'Structure Agent',
            'wake_word' => 'Structure',
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->assertStatus(200)
            ->assertSee('1. Identität & Rolle', false)
            ->assertSee('2. Neuronales Netz (Modell)', false)
            ->assertSee('3. Skills (Fähigkeiten)', false)
            ->assertSee('Rolle verwalten', false)
            ->assertSee('section1Open: false, section2Open: false, section3Open: false', false);
    }

    /** @test */
    public function test_agent_displays_active_skills_from_role_in_section_three()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $role = AiRole::create([
            'name' => 'Support Genius ' . Str::random(5),
            'description' => 'Dedicated Customer Support'
        ]);

        $tool1 = AiTool::firstOrCreate(
            ['identifier' => 'test_skill_alpha_' . Str::random(4)],
            ['name' => 'Skill Alpha', 'description' => 'Alpha tool desc']
        );
        $tool2 = AiTool::firstOrCreate(
            ['identifier' => 'test_skill_beta_' . Str::random(4)],
            ['name' => 'Skill Beta', 'description' => 'Beta tool desc']
        );

        $role->tools()->sync([$tool1->id, $tool2->id]);

        $agent = AiAgent::create([
            'name' => 'Support Bot ' . Str::random(5),
            'wake_word' => 'SupportBot',
            'ai_role_id' => $role->id,
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->assertStatus(200)
            ->assertSee('2 Fähigkeiten aktiv')
            ->assertSee($tool1->name)
            ->assertSee($tool2->name);
    }

    /** @test */
    public function test_toggling_skill_syncs_with_role_and_dispatches_saved()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $role = AiRole::create([
            'name' => 'Toggle Role ' . Str::random(5),
            'description' => 'Testing Toggle'
        ]);

        $tool = AiTool::firstOrCreate(
            ['identifier' => 'test_skill_toggle_' . Str::random(4)],
            ['name' => 'Skill Toggle Test', 'description' => 'Toggle description']
        );

        $agent = AiAgent::create([
            'name' => 'Toggle Agent ' . Str::random(5),
            'wake_word' => 'ToggleAgent',
            'ai_role_id' => $role->id,
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        $this->assertFalse($role->fresh()->tools->contains($tool->id));

        // Toggle skill ON
        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->call('toggleSkill', $tool->id)
            ->assertDispatched('agent-saved', message: 'Fähigkeiten aktualisiert');

        $this->assertTrue($role->fresh()->tools->contains($tool->id));

        // Toggle skill OFF
        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->call('toggleSkill', $tool->id)
            ->assertDispatched('agent-saved', message: 'Fähigkeiten aktualisiert');

        $this->assertFalse($role->fresh()->tools->contains($tool->id));
    }

    /** @test */
    public function test_searching_skills_filters_tool_list()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $role = AiRole::create([
            'name' => 'Search Role ' . Str::random(5),
            'description' => 'Testing Search'
        ]);

        $uniqueTool = AiTool::firstOrCreate(
            ['identifier' => 'unique_secret_xyz_skill'],
            ['name' => 'SecretXYZSkill', 'description' => 'Super secretive tool']
        );

        $agent = AiAgent::create([
            'name' => 'Search Agent ' . Str::random(5),
            'wake_word' => 'SearchAgent',
            'ai_role_id' => $role->id,
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->set('searchSkill', 'SecretXYZSkill')
            ->assertSee('SecretXYZSkill')
            ->set('searchSkill', 'NonExistentZ99999Tool')
            ->assertSee('Keine Fähigkeiten gefunden');
    }

    /** @test */
    public function test_role_saved_event_refreshes_role_description()
    {
        $admin = Admin::first() ?? Admin::factory()->create();

        $role = AiRole::create([
            'name' => 'Reactive Role ' . Str::random(5),
            'description' => 'Old Description'
        ]);

        $agent = AiAgent::create([
            'name' => 'Reactive Agent ' . Str::random(5),
            'wake_word' => 'ReactiveAgent',
            'ai_role_id' => $role->id,
            'role_description' => 'Old Description',
            'model' => 'gemini-3.8-flash',
            'color' => 'cyan-500',
            'icon' => 'sparkles'
        ]);

        $role->update(['description' => 'Brand New Description']);

        Livewire::actingAs($admin, 'admin')
            ->test(AiAgentEditor::class, ['id' => $agent->id])
            ->dispatch('role-saved', roleId: $role->id)
            ->assertSet('role_description', 'Brand New Description');
    }

    /** @test */
    public function test_teamleiter_role_does_not_contain_persona_tools()
    {
        $role = AiRole::where('name', 'Teamleiter')->first();
        if ($role) {
            $personaToolsCount = $role->tools()->where('identifier', 'like', 'persona_%')->count();
            $this->assertEquals(0, $personaToolsCount, 'Teamleiter should not have any persona tools.');
        } else {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function test_video_capabilities_are_completely_removed()
    {
        // 1. Verify schema in AIFunctionsRegistry
        $schema = \App\Services\AI\AIFunctionsRegistry::getSchema();
        $videoFunctions = array_filter($schema, function ($fn) {
            $name = $fn['function']['name'] ?? '';
            return str_starts_with($name, 'video_');
        });
        $this->assertEmpty($videoFunctions, 'AIFunctionsRegistry should not contain any video_ functions.');

        // 2. Verify AiTool database table
        $videoToolsCount = AiTool::where('identifier', 'like', 'video_%')->count();
        $this->assertEquals(0, $videoToolsCount, 'AiTool database table should not contain any video_ tools.');

        // 3. Verify Marketing role does not have any video_ tools
        $marketingRole = AiRole::where('name', 'Marketing')->first();
        if ($marketingRole) {
            $marketingVideoTools = $marketingRole->tools()->where('identifier', 'like', 'video_%')->count();
            $this->assertEquals(0, $marketingVideoTools, 'Marketing role should not contain any video_ tools.');
        }
    }
}
