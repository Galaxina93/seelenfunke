<?php

namespace Tests\Feature\Ai;

use App\Models\Admin\Admin;
use App\Models\Ai\AiHealthTreatmentPlan;
use App\Services\AI\Functions\AiHealthFuncs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AiHealthFuncsSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_treatment_plan_update_and_complete_prevent_idor()
    {
        $dummy = new class {
            use AiHealthFuncs;
        };

        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        // Create a plan owned by Admin A
        $plan = AiHealthTreatmentPlan::create([
            'user_id' => $adminA->id,
            'ai_agent_id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Rückentherapie Stufe 1',
            'diagnosis_summary' => 'LWS-Syndrom',
            'status' => 'active',
        ]);

        // 1. Admin B tries to maliciously update Admin A's plan
        Auth::login($adminB);

        $resUpdate = $dummy::executeUpdateTreatmentPlan([
            'plan_id' => $plan->id,
            'title' => 'Gehackter Titel',
        ]);

        $this->assertTrue($resUpdate['error'] ?? false);
        $plan->refresh();
        $this->assertEquals('Rückentherapie Stufe 1', $plan->title);

        // 2. Admin B tries to maliciously complete Admin A's plan
        $resComplete = $dummy::executeCompleteTreatmentPlan([
            'plan_id' => $plan->id,
            'result_evaluation' => 'Unbefugt abgeschlossen',
        ]);

        $this->assertTrue($resComplete['error'] ?? false);
        $plan->refresh();
        $this->assertEquals('active', $plan->status);

        // 3. Admin A updates and completes their own plan
        Auth::login($adminA);

        $resAuthUpdate = $dummy::executeUpdateTreatmentPlan([
            'plan_id' => $plan->id,
            'title' => 'Rückentherapie Phase 2',
        ]);
        $this->assertTrue($resAuthUpdate['success'] ?? false);

        $resAuthComplete = $dummy::executeCompleteTreatmentPlan([
            'plan_id' => $plan->id,
            'result_evaluation' => 'Erfolgreich therapiert',
        ]);
        $this->assertTrue($resAuthComplete['success'] ?? false);

        $plan->refresh();
        $this->assertEquals('Rückentherapie Phase 2', $plan->title);
        $this->assertEquals('completed', $plan->status);
    }

    public function test_export_treatment_plan_prevents_idor()
    {
        $dummy = new class {
            use AiHealthFuncs;
        };

        $adminA = Admin::factory()->create();
        $adminB = Admin::factory()->create();

        $plan = AiHealthTreatmentPlan::create([
            'user_id' => $adminA->id,
            'ai_agent_id' => (string) \Illuminate\Support\Str::uuid(),
            'title' => 'Kardiologie Check',
            'diagnosis_summary' => 'Belastungs-EKG unauffällig',
            'status' => 'active',
        ]);

        // Admin B attempts to export Admin A's medical report
        Auth::login($adminB);

        $resExport = $dummy::executeExportTreatmentPlan([
            'plan_id' => $plan->id,
            'send_email' => false,
        ]);

        $this->assertTrue($resExport['error'] ?? false);
        $this->assertStringContainsString('keine Zugriffsberechtigung', $resExport['message']);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $dummy = new class {
            use AiHealthFuncs;
        };

        Auth::logout();

        $res = $dummy::executeUpdateTreatmentPlan([
            'plan_id' => 'random-id',
            'title' => 'Test',
        ]);

        $this->assertTrue($res['error'] ?? false);
        $this->assertStringContainsString('Nicht authentifiziert', $res['message']);
    }
}
