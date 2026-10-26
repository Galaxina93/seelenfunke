<?php

namespace Tests\Feature\Employee;

use App\Livewire\Employee\EmployeeDashboard;
use App\Livewire\Frontend\Employee\EmployeeInvitationAccept;
use App\Livewire\Shop\Management\ManagementEmployees;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Models\Admin\Admin;
use App\Models\Employee\Employee;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeePayslip;
use App\Services\AnalyticsService;
use App\Services\Employee\EmployeeHrService;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeHrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_seeder_creates_employee_with_signed_contract_time_entries_and_payslip(): void
    {
        Storage::fake('local');

        $this->seed(UserSeeder::class);

        $employee = Employee::where('email', 'mitarbeiter@mein-seelenfunke.de')->first();
        $this->assertNotNull($employee);
        $this->assertTrue($employee->isInternalEmployee());

        $contract = $employee->employmentContract;
        $this->assertNotNull($contract);
        $this->assertNotNull($contract->signed_at);
        $this->assertNotEmpty($contract->contract_pdf_path);
        $this->assertNotEmpty($contract->questionnaire_pdf_path);

        Storage::disk('local')->assertExists($contract->contract_pdf_path);
        Storage::disk('local')->assertExists($contract->questionnaire_pdf_path);

        $this->assertGreaterThanOrEqual(2, $contract->timeEntries()->count());
        $this->assertEquals(1, $contract->payslips()->count());
    }

    #[Test]
    public function full_onboarding_time_tracking_and_payroll_accounting_sync_works(): void
    {
        Storage::fake('local');

        $admin = Admin::create([
            'first_name' => 'Alina',
            'last_name' => 'Steinhauer',
            'email' => 'admin-hr@mein-seelenfunke.de',
            'password' => Hash::make('SeeleSeele1993+++'),
        ]);

        // 1. Admin erstellt unter Leitung -> Mitarbeiter (/admin/employees) eine neue Einladung
        $this->actingAs($admin, 'admin');

        Livewire::test(ManagementEmployees::class)
            ->call('openInviteModal')
            ->set('inviteLabel', 'Sophie Versand')
            ->set('inviteJobTitle', 'Versand & Manufaktur')
            ->set('inviteEmploymentType', 'minijob')
            ->set('inviteHourlyRateEur', '15,00')
            ->set('inviteWeeklyHours', '8,5')
            ->call('createInvitation')
            ->assertHasNoErrors();

        $contract = EmployeeContract::where('invitation_label', 'Sophie Versand')->first();
        $this->assertNotNull($contract);
        $this->assertTrue($contract->is_pending_invitation);

        $rawToken = (string) $contract->invitation_token_encrypted;
        $rawCode = (string) $contract->verification_code;
        $this->assertNotEmpty($rawToken);
        $this->assertEquals(6, strlen($rawCode));

        // 2. Neuer Mitarbeiter öffnet den Einladungslink (/mitarbeiter/einladung/{token}) und unterschreibt digital
        auth('admin')->logout();

        Livewire::test(EmployeeInvitationAccept::class, ['token' => $rawToken])
            ->assertSet('isValidToken', true)
            ->set('verificationCode', $rawCode)
            ->call('verifyCode')
            ->assertSet('isCodeVerified', true)
            ->assertSet('step', 2)
            ->set('firstName', 'Sophie')
            ->set('lastName', 'Versand')
            ->set('email', 'sophie.versand@mein-seelenfunke.de')
            ->set('password', 'SicherPass123!')
            ->set('password_confirmation', 'SicherPass123!')
            ->set('birthDate', '1999-03-21')
            ->set('street', 'Hauptstraße')
            ->set('houseNumber', '10')
            ->set('postal', '38518')
            ->set('city', 'Gifhorn')
            ->call('nextToStepThree')
            ->assertSet('step', 3)
            ->set('taxId', '98765432109')
            ->set('taxClass', 'I')
            ->set('socialSecurityNumber', '65210399V012')
            ->set('healthInsuranceName', 'AOK Niedersachsen')
            ->set('accountHolder', 'Sophie Versand')
            ->set('iban', 'DE89370400440532013000')
            ->set('signatureName', 'Sophie Versand')
            ->set('contractAccepted', true)
            ->set('truthConfirmed', true)
            ->call('completeOnboarding')
            ->assertRedirect(route('employee.dashboard'));

        $contract->refresh();
        $this->assertNotNull($contract->employee_id);
        $this->assertNotNull($contract->signed_at);
        $this->assertEquals('Sophie Versand', $contract->signature_name);
        Storage::disk('local')->assertExists($contract->contract_pdf_path);
        Storage::disk('local')->assertExists($contract->questionnaire_pdf_path);

        $employee = $contract->employee;
        $this->assertAuthenticatedAs($employee, 'employee');

        // 3. Mitarbeiter trägt im /employee/dashboard eine 7-Stunden-Schicht ein -> § 4 ArbZG erzwingt mind. 30 Min. Pause
        Livewire::actingAs($employee, 'employee')
            ->test(EmployeeDashboard::class)
            ->set('entryDate', now()->toDateString())
            ->set('entryStartTime', '08:00')
            ->set('entryEndTime', '15:00') // 7 Std. (420 Min.) Brutto
            ->set('entryBreakMinutes', 0)   // 0 angegeben -> System muss automatisch 30 Min. nach § 4 ArbZG abziehen!
            ->set('entryActivityNote', 'Gravur & Verpackung')
            ->call('saveTimeEntry')
            ->assertHasNoErrors();

        $entry = $contract->timeEntries()->first();
        $this->assertNotNull($entry);
        $this->assertEquals(30, $entry->break_minutes);
        $this->assertEquals(390, $entry->net_minutes); // 6,5 Std. Netto
        $this->assertTrue($entry->arbzg_compliant);

        // Mitarbeiter kann seinen Vertrag, Personalbogen und Stundenzettel als PDF herunterladen
        $this->actingAs($employee, 'employee')
            ->get(route('employee.contract.pdf'))
            ->assertOk();
        $this->actingAs($employee, 'employee')
            ->get(route('employee.questionnaire.pdf'))
            ->assertOk();
        $this->actingAs($employee, 'employee')
            ->get(route('employee.timesheet.pdf', ['year' => now()->year, 'month' => now()->month]))
            ->assertOk();

        // 4. Admin erstellt Lohnabrechnung -> erscheint in Master Analytics To-Do Liste -> Als überwiesen markieren -> Buchhaltung Sync
        auth('employee')->logout();
        $this->actingAs($admin, 'admin');

        $hrService = app(EmployeeHrService::class);
        $payslip = $hrService->createOrUpdatePayslip($contract, (int) now()->year, (int) now()->month);
        $this->assertEquals('open', $payslip->status);
        $this->assertEquals(9750, $payslip->gross_amount_cents); // 6,5 Std. * 15,00 € = 97,50 €

        // Prüfe Master Analytics To-Do Liste
        $checks = app(AnalyticsService::class)->getHealthChecks();
        $this->assertArrayHasKey('employee_payroll', $checks);
        $this->assertEquals('warning', $checks['employee_payroll']['status']);
        $this->assertGreaterThanOrEqual(1, $checks['employee_payroll']['count']);

        // Admin markiert Lohnabrechnung als überwiesen
        Livewire::actingAs($admin, 'admin')
            ->test(ManagementEmployees::class)
            ->call('markPayslipPaid', $payslip->id);

        $payslip->refresh();
        $this->assertEquals('paid', $payslip->status);
        $this->assertNotNull($payslip->accounting_special_issue_id);

        $specialIssue = AccountingSpecialIssue::find($payslip->accounting_special_issue_id);
        $this->assertNotNull($specialIssue);
        $this->assertEquals('Personal & Löhne', $specialIssue->category);
        $this->assertTrue((bool) $specialIssue->is_business);
    }

    #[Test]
    public function strict_role_isolation_between_internal_employees_influencers_and_tax_advisors(): void
    {
        $this->seed(UserSeeder::class);

        $internalEmployee = Employee::where('email', 'mitarbeiter@mein-seelenfunke.de')->firstOrFail();
        $influencer = Employee::where('email', 'influencer@mein-seelenfunke.de')->firstOrFail();
        $taxAdvisor = Employee::where('email', 'steuerberater@mein-seelenfunke.de')->firstOrFail();

        // Interner Mitarbeiter darf auf /employee/dashboard, aber nicht auf /admin/employees oder externe Portale
        $this->actingAs($internalEmployee, 'employee')
            ->get('/employee/dashboard')
            ->assertOk();
        $this->actingAs($internalEmployee, 'employee')
            ->get('/employee/influencer/dashboard')
            ->assertRedirect(route('employee.dashboard'));
        $this->actingAs($internalEmployee, 'employee')
            ->get('/employee/tax-advisor/dashboard')
            ->assertRedirect(route('employee.dashboard'));

        // Influencer darf nicht auf /employee/dashboard
        $this->actingAs($influencer, 'employee')
            ->get('/employee/dashboard')
            ->assertRedirect(route('employee.influencer.dashboard'));

        // Steuerberater darf nicht auf /employee/dashboard
        $this->actingAs($taxAdvisor, 'employee')
            ->get('/employee/dashboard')
            ->assertRedirect(route('employee.tax-advisor.dashboard'));
    }
}
