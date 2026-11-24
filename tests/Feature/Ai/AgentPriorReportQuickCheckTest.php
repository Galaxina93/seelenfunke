<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiAgent;
use App\Models\Ai\AiDepartment;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiRole;
use App\Services\AI\AiPromptService;
use App\Services\AI\CentralBrainService;
use Database\Seeders\AiKnowledgeBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use ReflectionMethod;
use Tests\TestCase;

class AgentPriorReportQuickCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_knowledge_seeder_anchors_docker_report_and_token_lean_prior_check_works_with_air_gap(): void
    {
        $seeder = new AiKnowledgeBaseSeeder();
        $method = new ReflectionMethod($seeder, 'seedInfrastructureAndAgentBrainReports');
        $method->setAccessible(true);

        // Idempotenter Doppelaufruf darf keine Dubletten erzeugen
        $method->invoke($seeder);
        $method->invoke($seeder);

        $this->assertSame(
            1,
            AiKnowledgeBase::where(
                'source_path',
                'Abschlussberichte/System/Abschlussbericht_Docker_Desktop_WSL_Ubuntu_Integration_und_JobObject_Entkopplung.md'
            )->count()
        );
        $this->assertSame(
            1,
            AiKnowledgeBase::where(
                'source_path',
                'Abschlussberichte/Workflows/Workflow_Tokenarme_Vorabpruefung_Abschlussberichte.md'
            )->count()
        );

        // 1. Gäste / Kunden / öffentlicher Kontext sehen 0 Bytes aus admin_executive Abschlussberichten
        Auth::logout();
        $guestHit = CentralBrainService::findCompactPriorReportsContext(
            'Docker Desktop Ubuntu WSL /var/run/docker.sock stopped'
        );
        $this->assertSame('', $guestHit);

        // 2. Eingeloggte Administratorin Alina erhält sofort den kompakten, tokenarmen Treffer
        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'kontakt@mein-seelenfunke.de',
            'password' => bcrypt('Secret123!'),
        ]);
        $this->actingAs($admin, 'admin');

        $adminHit = CentralBrainService::findCompactPriorReportsContext(
            'Docker Desktop Ubuntu WSL /var/run/docker.sock unexpectedly stopped'
        );
        $this->assertStringContainsString('[BEREITS DOKUMENTIERTE ABSCHLUSSBERICHTE', $adminHit);
        $this->assertStringContainsString('Docker Desktop WSL-Ubuntu Integration', $adminHit);
        $this->assertLessThan(1400, mb_strlen($adminHit), 'Vorab-Treffer muss strikt tokenarm bleiben');

        // 3. Kurzkommandos wie "Go" erzeugen 0 Token-Overhead
        $this->assertSame('', CentralBrainService::findCompactPriorReportsContext('Go'));

        // 4. Interne Funkira erhält die Vorab-Prüfungs-Regel, öffentlicher Funki bleibt 100 % isoliert
        $dept = AiDepartment::create(['name' => 'Leitung', 'slug' => 'leitung']);
        $leaderRole = AiRole::create(['name' => 'Teamleiterin', 'description' => 'CEO-KI']);
        $funkira = AiAgent::create([
            'name' => 'Funkira',
            'ai_department_id' => $dept->id,
            'ai_role_id' => $leaderRole->id,
            'system_prompt' => 'Funkira Kern',
            'is_active' => true,
        ]);

        $funki = AiAgent::create([
            'name' => 'Funki',
            'system_prompt' => 'Öffentlicher Shop-Support',
            'is_active' => true,
        ]);

        $funkiraPrompt = AiPromptService::getRichPrompt($funkira);
        $this->assertStringContainsString('TOKEN-ARME VORAB-PRÜFUNG AUF ABSCHLUSSBERICHTE', $funkiraPrompt);

        Auth::guard('admin')->logout();
        $funkiPrompt = AiPromptService::getRichPrompt($funki);
        $this->assertStringNotContainsString('TOKEN-ARME VORAB-PRÜFUNG AUF ABSCHLUSSBERICHTE', $funkiPrompt);
    }
}
