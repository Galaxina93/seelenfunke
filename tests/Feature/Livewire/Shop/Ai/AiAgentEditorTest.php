<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use App\Livewire\Shop\Ai\AiAgentEditor;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
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
}
