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

    public function test_it_calculates_gemini_3_flash_and_pro_costs_accurately_and_formats_context_load(): void
    {
        $admin = Admin::first() ?? Admin::create([
            'id' => Str::uuid()->toString(),
            'first_name' => 'Test',
            'last_name' => 'Admin',
            'email' => 'admin_cost_' . Str::random(5) . '@seelenfunke.test',
            'password' => bcrypt('password123'),
        ]);

        // Entferne bestehende Metriken im aktuellen Monat innerhalb der Transaktion für deterministischen Kosten-Check
        AiMetric::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->delete();
        if (class_exists(\App\Models\System\SystemAiHostingPlan::class)) {
            \App\Models\System\SystemAiHostingPlan::query()->update(['is_active' => false]);
        }

        $flashAgent = AiAgent::create([
            'name' => 'Test Flash 3.5',
            'provider' => 'google',
            'model' => 'gemini-3.5-flash',
            'color' => 'orange-500',
            'system_prompt' => 'Flash Prompt',
        ]);

        $proAgent = AiAgent::create([
            'name' => 'Test Pro 3.1',
            'provider' => 'google',
            'model' => 'gemini-3.1-pro-preview',
            'color' => 'indigo-500',
            'system_prompt' => 'Pro Prompt',
        ]);

        // 1M in + 1M out auf gemini-3.5-flash => 0.15 + 0.60 = 0.75 (nicht 6.25 Pro!)
        AiMetric::create([
            'id' => Str::uuid()->toString(),
            'ai_agent_id' => $flashAgent->id,
            'type' => 'inference',
            'input_tokens' => 1000000,
            'output_tokens' => 1000000,
            'total_time_ms' => 800,
            'is_success' => true,
        ]);

        // 1M in + 1M out auf gemini-3.1-pro-preview => 1.25 + 5.00 = 6.25
        AiMetric::create([
            'id' => Str::uuid()->toString(),
            'ai_agent_id' => $proAgent->id,
            'type' => 'inference',
            'input_tokens' => 1000000,
            'output_tokens' => 1000000,
            'total_time_ms' => 1600,
            'is_success' => true,
        ]);

        $component = Livewire::actingAs($admin, 'admin')
            ->test(AiAnalytics::class)
            ->assertStatus(200)
            ->assertSee('Vertex AI Postpay')
            ->assertSee('50,00 € Budget')
            ->assertDontSee('Unbekanntes Paket')
            ->assertDontSee('bg-orange-500-500')
            ->assertDontSee('/ 32k')
            ->assertSee('1 Mio.')
            ->assertSee('2 Mio.');

        $this->assertEqualsWithDelta(7.00, (float) $component->viewData('estimatedCostThisMonth'), 0.001);
        $this->assertSame(2000000, (int) $component->viewData('inputTokensThisMonth'));
        $this->assertSame(2000000, (int) $component->viewData('outputTokensThisMonth'));
    }

    public function test_company_structure_seeder_places_globi_in_leitung_with_funkira(): void
    {
        $this->seed(\Database\Seeders\AiAgentSeeder::class);
        $this->seed(\Database\Seeders\AiCompanyStructureSeeder::class);

        $globi = AiAgent::where('name', 'Globi')->with('department')->firstOrFail();
        $funkira = AiAgent::where('name', 'Funkira')->with('department')->firstOrFail();

        $this->assertSame('Leitung', $globi->department?->name);
        $this->assertSame('019d0000-0000-0000-0000-000000000000', $globi->ai_department_id);
        $this->assertSame('Leitung', $funkira->department?->name);
    }
}
