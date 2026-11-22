<?php

namespace Tests\Feature\Accounting;

use App\Livewire\Shop\Accounting\AccountingAnalytics;
use App\Livewire\Shop\Accounting\AccountingLiquidity;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvestmentPlan;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Services\FinancialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountingSalaryAndReinvestmentTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $adminId = (string) Str::uuid();
        DB::table('admins')->insert([
            'id' => $adminId,
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'alina-cfo-' . uniqid() . '@mein-seelenfunke.de',
            'password' => bcrypt('password123'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->admin = Admin::findOrFail($adminId);
    }

    #[Test]
    public function it_separates_business_and_private_scopes_and_stops_gruendungszuschuss_after_end_date(): void
    {
        $incomeGroup = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Einnahmen',
            'type' => 'income',
            'position' => 10,
        ]);

        $expenseGroup = AccountingGroup::create([
            'admin_id' => $this->admin->id,
            'name' => 'Verträge',
            'type' => 'expense',
            'position' => 20,
        ]);

        // Gründungszuschuss Phase 1: 01.11.2026 bis 30.04.2027
        AccountingCostItem::create([
            'accounting_group_id' => $incomeGroup->id,
            'name' => 'ALG 1 / Gründerzuschuss',
            'amount' => 2106.30,
            'interval_months' => 1,
            'first_payment_date' => '2026-11-01',
            'last_payment_date' => '2027-04-30',
            'contract_end_date' => '2027-04-30',
            'is_business' => false,
            'tax_rate' => 0,
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Mittwald Hosting',
            'amount' => -48.79,
            'interval_months' => 1,
            'first_payment_date' => '2026-04-15',
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        AccountingCostItem::create([
            'accounting_group_id' => $expenseGroup->id,
            'name' => 'Hauskredit',
            'amount' => -546.00,
            'interval_months' => 1,
            'first_payment_date' => '2021-09-14',
            'is_business' => false,
            'tax_rate' => 0,
        ]);

        $service = app(FinancialService::class);

        // Dezember 2026: Gewerbe darf KEINEN Gründungszuschuss und KEINEN Hauskredit enthalten!
        $bizDec = $service->getMonthlyStats($this->admin->id, 12, 2026, false, 'business');
        $this->assertEquals(0.0, $bizDec['fixed_income']);
        $this->assertEquals(-48.79, $bizDec['fixed_expenses']);

        // Dezember 2026 Privat: Gründungszuschuss aktiv
        $privDec = $service->getMonthlyStats($this->admin->id, 12, 2026, false, 'private');
        $this->assertEquals(2106.30, $privDec['fixed_income']);
        $this->assertEquals(-546.00, $privDec['fixed_expenses']);

        // Mai 2027 Privat: Gründungszuschuss MUSS beendet sein (0,00 €)!
        $privMay27 = $service->getMonthlyStats($this->admin->id, 5, 2027, false, 'private');
        $this->assertEquals(0.0, $privMay27['fixed_income']);
    }

    #[Test]
    public function it_calculates_german_income_tax_with_section_35_trade_tax_credit_and_detects_afa_assets(): void
    {
        $service = app(FinancialService::class);

        // Unter Grundfreibetrag 2026 (12.348 €) -> 0 € Steuer
        $zeroTax = $service->calculateGermanIncomeTax(12000.0, 2026, 12000.0, 380.0);
        $this->assertEquals(0.0, $zeroTax['total_tax']);

        // Bei 35.000 € Gewinn: Gewerbesteuer (10.500 * 3,5 % = 367,50 € Messbetrag * 3,8 = 1.396,50 €)
        // wird nach § 35 EStG (bis 4,0 * Messbetrag = 1.470 €) zu 100 % auf die ESt angerechnet!
        $withGewSt = $service->calculateGermanIncomeTax(35000.0, 2026, 35000.0, 380.0);
        $withoutGewSt = $service->calculateGermanIncomeTax(35000.0, 2026, 0.0, 380.0);
        $this->assertGreaterThan(0.0, $withGewSt['gewerbesteuer']);
        $this->assertEquals($withGewSt['gewerbesteuer'], $withGewSt['est_anrechnung_35']);
        $this->assertEqualsWithDelta($withoutGewSt['total_tax'], $withGewSt['total_tax'], 1.0);

        // Anlagegut > 800 € Netto (Laser 4.907,51 € brutto = 4.123,96 € netto) vs. GWG (Bambu Lab 281,25 € brutto = 236,34 € netto)
        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'xTool F2 Ultra UV Laser',
            'category' => 'Arbeitsmaterial',
            'amount' => -4907.51,
            'execution_date' => '2026-11-01',
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        AccountingSpecialIssue::create([
            'admin_id' => $this->admin->id,
            'title' => 'Bambu Lab A1 3D-Drucker',
            'category' => 'Arbeitsmaterial',
            'amount' => -281.25,
            'execution_date' => '2026-11-17',
            'is_business' => true,
            'tax_rate' => 19,
        ]);

        $depr = $service->getFixedAssetsAndDepreciation($this->admin->id, 2026, 11);
        $this->assertCount(1, $depr['assets']);
        $this->assertCount(1, $depr['gwg_items']);
        $this->assertEquals(4123.96, $depr['assets'][0]['net_cost']);
        $this->assertEquals(68.73, $depr['assets'][0]['monthly_afa']);
        $this->assertEquals(137.47, $depr['annual_afa']); // Nov + Dez 2026 (2/60)
    }

    #[Test]
    public function it_calculates_dynamic_salary_and_reinvestment_completion_dates_and_manages_plans_in_livewire(): void
    {
        $this->actingAs($this->admin, 'admin');

        Livewire::test(AccountingAnalytics::class)
            ->assertStatus(200)
            ->call('openCreatePlanModal')
            ->set('planTitle', 'Neuer MOPA Faserlaser')
            ->set('planTargetGross', '3570') // 3.000,00 € Netto
            ->set('planTaxRate', 19)
            ->set('planSavedInitial', '500')
            ->set('planAllocationMode', 'fixed_monthly')
            ->set('planMonthlyRate', '250')
            ->set('planStartDate', '2027-01-01')
            ->set('planTargetDate', '2028-06-30')
            ->set('planAfaYears', 5)
            ->set('planUseIab', true)
            ->call('savePlan')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounting_investment_plans', [
            'admin_id' => $this->admin->id,
            'title' => 'Neuer MOPA Faserlaser',
            'use_iab' => true,
        ]);

        $service = app(FinancialService::class);
        $result = $service->getDynamicSalaryAndReinvestmentData($this->admin->id, 6, 2027, [
            'sim_enabled' => true,
            'sim_monthly_gross_revenue' => 4760.00, // 4.000 € Netto
            'sim_cogs_percent' => 25.0,             // 1.000 € Wareneinsatz
            'reserve_afa_replacement' => false,
        ]);

        $this->assertGreaterThan(0.0, $result['safe_monthly_salary']);
        $this->assertEquals(1500.00, $result['tax_projection']['iab_deduction_total']); // 50 % von 3.000 € Netto
        $this->assertCount(1, $result['investment_plans']);

        $planInfo = $result['investment_plans'][0];
        $this->assertEquals(3000.00, $planInfo['target_net']);
        // Start 500 € + 6 Monate (Jan-Jun 2027) * 250 € = 2.000 € angespart -> Rest 1.000 € = 4 Monate (Okt 2027)
        $this->assertEquals(2000.00, $planInfo['accumulated_saved']);
        $this->assertEquals(1000.00, $planInfo['remaining_net']);
        $this->assertEquals(4, $planInfo['months_remaining']);
        $this->assertTrue($planInfo['on_track']);

        // Auch Liquiditätsplanung rendert fehlerfrei im Live- & Demo-Modus
        Livewire::test(AccountingLiquidity::class)
            ->assertStatus(200)
            ->set('configLoadDemoData', false)
            ->assertStatus(200);
    }
}
