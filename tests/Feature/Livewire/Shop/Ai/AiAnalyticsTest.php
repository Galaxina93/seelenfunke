<?php

namespace Tests\Feature\Livewire\Shop\Ai;

use App\Livewire\Shop\Ai\AiAnalytics;
use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiMetric;
use App\Models\Ai\AiToolUsage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Support\Str;

class AiAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_renders_analytics_dashboard_correctly()
    {
        // Hole oder erstelle einen Admin, da Livewire Auth Guard benötigt
        $admin = Admin::first() ?? Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin_test_'.Str::random(5).'@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);

        // Erstelle einen Test Agent
        $agent = AiAgent::create([
            'name' => 'Test Agent X',
            'provider' => 'openai',
            'model' => 'test-model',
            'system_prompt' => 'Test Prompt'
        ]);
        $agentId = $agent->id;

        // Simuliere Traffic Usage Metriken
        AiMetric::create([
            'id' => Str::uuid()->toString(),
            'ai_agent_id' => $agentId,
            'type' => 'inference',
            'input_tokens' => 500,
            'output_tokens' => 250,
            'total_time_ms' => 1200,
            'is_success' => true
        ]);

        // Simuliere Ein Tool Usage
        AiToolUsage::create([
            'ai_agent_id' => $agentId,
            'tool_name' => 'demo_tool_xyz',
            'used_at' => now(),
            'is_error' => false
        ]);

        // Teste Rendering und Daten-Aggregierung
        Livewire::actingAs($admin, 'admin')
            ->test(AiAnalytics::class)
            ->assertStatus(200)
            ->assertViewHas('tokensToday')
            ->assertViewHas('avgLatency')
            ->assertViewHas('successRate')
            ->assertSee('Test Agent X')
            ->assertSee('demo_tool_xyz');
    }

    public function test_it_renders_ai_agent_manager_correctly()
    {
        $admin = Admin::first() ?? Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin_test_'.Str::random(5).'@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\AiCompanyStructure::class)
            ->assertStatus(200)
            ->assertViewHas('departments');
    }

    public function test_it_renders_ai_role_manager_correctly()
    {
        $admin = Admin::first() ?? Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin_test_'.Str::random(5).'@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Ai\AiRoleManager::class)
            ->assertStatus(200)
            ->assertViewHas('roles');
    }

    public function test_it_renders_support_chat_analytics_correctly()
    {
        $admin = Admin::first() ?? Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin_test_'.Str::random(5).'@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);

        Livewire::actingAs($admin, 'admin')
            ->test(\App\Livewire\Shop\Support\SupportChats::class)
            ->assertStatus(200)
            ->assertViewHas('openCount')
            ->assertViewHas('resolvedCount');
    }
}
