<?php

namespace App\Livewire\Shop\Management;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeePayslip;
use App\Services\Employee\EmployeeHrService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.backend_layout')]
class ManagementEmployees extends Component
{
    use WithDepartmentTheming;
    use WithFileUploads;

    public string $themingDepartment = 'Leitung';

    public string $search = '';
    public string $filterStatus = 'active'; // active, pending, archived
    public int $selectedYear;
    public int $selectedMonth;

    // 1-Klick Einladungs-Formular
    public bool $showInviteModal = false;
    public string $inviteLabel = '';
    public string $inviteJobTitle = 'Manufaktur- & Versand-Assistent/in';
    public string $inviteDepartment = 'Produktion & Versand';
    public string $inviteEmploymentType = 'minijob';
    public string $inviteCompensationType = 'hourly';
    public string $inviteHourlyRateEur = '14,00';
    public string $inviteMonthlyGrossEur = '540,00';
    public string $inviteWeeklyHours = '9,0';
    public int $inviteVacationDays = 20;
    public string $inviteStartDate = '';
    public bool $inviteIsFixedTerm = false;
    public string $inviteEndDate = '';

    #[Locked]
    public ?string $activeInviteId = null;

    // Inline Lohnabrechnungs- & SEPA-Panel
    #[Locked]
    public ?string $activePayrollContractId = null;
    public string $payrollGrossEur = '';
    public string $payrollNetEur = '';
    public string $payrollEmployerChargesEur = '';
    public string $payrollNotes = '';
    public $externalPayslipUpload = null;

    // Inline Vertrags-Bearbeitung
    #[Locked]
    public ?string $editingContractId = null;
    public string $editJobTitle = '';
    public string $editEmploymentType = 'minijob';
    public string $editCompensationType = 'hourly';
    public string $editHourlyRateEur = '14,00';
    public string $editMonthlyGrossEur = '540,00';
    public string $editWeeklyHours = '9,0';
    public int $editVacationDays = 20;
    public int $editVacationTaken = 0;

    public bool $showExplanation = false;

    public function boot(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(): void
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Zugriff verweigert.');
        }

        app(EmployeeHrService::class)->ensureTablesExist();

        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;
        $this->inviteStartDate = now()->startOfMonth()->format('Y-m-d');
    }

    public function openInviteModal(): void
    {
        $this->resetErrorBag();
        $this->inviteLabel = '';
        $this->inviteJobTitle = 'Manufaktur- & Versand-Assistent/in';
        $this->inviteDepartment = 'Produktion & Versand';
        $this->inviteEmploymentType = 'minijob';
        $this->inviteCompensationType = 'hourly';
        $this->inviteHourlyRateEur = '14,00';
        $this->inviteMonthlyGrossEur = '540,00';
        $this->inviteWeeklyHours = '9,0';
        $this->inviteVacationDays = 20;
        $this->inviteStartDate = now()->startOfMonth()->format('Y-m-d');
        $this->inviteIsFixedTerm = false;
        $this->inviteEndDate = '';
        $this->showInviteModal = true;
    }

    public function createInvitation(EmployeeHrService $service): void
    {
        $hourlyCents = $this->parseEurToCents($this->inviteHourlyRateEur);
        $monthlyCents = $this->parseEurToCents($this->inviteMonthlyGrossEur);
        $weeklyHours = (float) str_replace(',', '.', $this->inviteWeeklyHours);

        if ($hourlyCents < 1282 && $this->inviteCompensationType === 'hourly') {
            $this->addError('inviteHourlyRateEur', 'Der Stundenlohn darf den gesetzlichen Mindestlohn (12,82 €) nicht unterschreiten.');
            return;
        }

        if ($this->inviteIsFixedTerm && empty($this->inviteEndDate)) {
            $this->addError('inviteEndDate', 'Bitte gib bei einem befristeten Vertrag das Enddatum an.');
            return;
        }

        $slot = $service->createInvitationSlot([
            'invitation_label' => $this->inviteLabel ?: ('Mitarbeiter #' . strtoupper(Str::random(4))),
            'job_title' => $this->inviteJobTitle,
            'department' => $this->inviteDepartment,
            'employment_type' => $this->inviteEmploymentType,
            'compensation_type' => $this->inviteCompensationType,
            'hourly_rate_cents' => $hourlyCents,
            'monthly_gross_cents' => $monthlyCents,
            'weekly_hours' => $weeklyHours,
            'vacation_days_per_year' => $this->inviteVacationDays,
            'start_date' => $this->inviteStartDate ?: now()->toDateString(),
            'is_fixed_term' => $this->inviteIsFixedTerm,
            'end_date' => $this->inviteIsFixedTerm ? $this->inviteEndDate : null,
        ]);

        $this->showInviteModal = false;
        $this->activeInviteId = $slot->id;
        session()->flash('success', 'Einladungslink & 6-stelliger Sicherheitscode wurden erfolgreich generiert.');
    }

    public function showInvitationCredentials(string $id): void
    {
        $slot = EmployeeContract::findOrFail($id);
        $this->activeInviteId = $this->activeInviteId === $slot->id ? null : $slot->id;
    }

    public function regenerateInvite(string $id, EmployeeHrService $service): void
    {
        $slot = EmployeeContract::findOrFail($id);
        $service->regenerateInvitationToken($slot);
        $this->activeInviteId = $slot->id;
        session()->flash('success', 'Neuer Einladungslink & Sicherheitscode wurden generiert (14 Tage gültig).');
    }

    public function startEditContract(string $id): void
    {
        $contract = EmployeeContract::findOrFail($id);
        $this->editingContractId = $contract->id;
        $this->editJobTitle = (string) $contract->job_title;
        $this->editEmploymentType = (string) $contract->employment_type;
        $this->editCompensationType = (string) $contract->compensation_type;
        $this->editHourlyRateEur = number_format($contract->hourly_rate_cents / 100, 2, ',', '');
        $this->editMonthlyGrossEur = number_format($contract->monthly_gross_cents / 100, 2, ',', '');
        $this->editWeeklyHours = number_format((float) $contract->weekly_hours, 1, ',', '');
        $this->editVacationDays = (int) $contract->vacation_days_per_year;
        $this->editVacationTaken = (int) $contract->vacation_days_taken;
    }

    public function saveEditContract(EmployeeHrService $service): void
    {
        if (!$this->editingContractId) {
            return;
        }

        $contract = EmployeeContract::findOrFail($this->editingContractId);
        $hourlyCents = max(1282, $this->parseEurToCents($this->editHourlyRateEur));
        $monthlyCents = max(0, $this->parseEurToCents($this->editMonthlyGrossEur));
        $weeklyHours = max(1.0, min(48.0, (float) str_replace(',', '.', $this->editWeeklyHours)));

        $contract->update([
            'job_title' => trim($this->editJobTitle) ?: $contract->job_title,
            'employment_type' => in_array($this->editEmploymentType, ['minijob', 'part_time', 'full_time', 'working_student', 'short_term'], true)
                ? $this->editEmploymentType
                : $contract->employment_type,
            'compensation_type' => in_array($this->editCompensationType, ['hourly', 'fixed_salary'], true)
                ? $this->editCompensationType
                : $contract->compensation_type,
            'hourly_rate_cents' => $hourlyCents,
            'monthly_gross_cents' => $monthlyCents,
            'weekly_hours' => $weeklyHours,
            'vacation_days_per_year' => max(0, min(60, $this->editVacationDays)),
            'vacation_days_taken' => max(0, min(60, $this->editVacationTaken)),
        ]);

        if ($contract->signed_at) {
            $service->generateAndStoreContractPdf($contract->fresh('employee.profile'));
            $service->generateAndStoreQuestionnairePdf($contract->fresh('employee.profile'));
        }

        $this->editingContractId = null;
        session()->flash('success', 'Vertragskonditionen & PDF-Dokumente wurden aktualisiert.');
    }

    public function cancelEditContract(): void
    {
        $this->editingContractId = null;
    }

    public function openPayrollPanel(string $contractId, EmployeeHrService $service): void
    {
        if ($this->activePayrollContractId === $contractId) {
            $this->activePayrollContractId = null;
            return;
        }

        $contract = EmployeeContract::with('employee.profile')->findOrFail($contractId);
        $this->activePayrollContractId = $contract->id;

        $existing = EmployeePayslip::where('contract_id', $contract->id)
            ->where('period_year', $this->selectedYear)
            ->where('period_month', $this->selectedMonth)
            ->first();

        if ($existing) {
            $this->payrollGrossEur = number_format($existing->gross_amount_cents / 100, 2, ',', '');
            $this->payrollNetEur = number_format($existing->net_payout_cents / 100, 2, ',', '');
            $this->payrollEmployerChargesEur = number_format($existing->employer_charges_cents / 100, 2, ',', '');
            $this->payrollNotes = (string) ($existing->notes ?? '');
        } else {
            $stats = $service->getMonthlyStats($contract, $this->selectedYear, $this->selectedMonth);
            $this->payrollGrossEur = number_format($stats['estimated_gross_cents'] / 100, 2, ',', '');
            $this->payrollNetEur = number_format($stats['estimated_net_cents'] / 100, 2, ',', '');
            $this->payrollEmployerChargesEur = number_format($stats['employer_charges_cents'] / 100, 2, ',', '');
            $this->payrollNotes = '';
        }
        $this->externalPayslipUpload = null;
    }

    public function generateMonthlyPayslip(EmployeeHrService $service): void
    {
        if (!$this->activePayrollContractId) {
            return;
        }

        $contract = EmployeeContract::with('employee.profile')->findOrFail($this->activePayrollContractId);

        $uploadedPath = null;
        if ($this->externalPayslipUpload) {
            $this->validate([
                'externalPayslipUpload' => 'file|mimes:pdf|max:10240',
            ]);
            $safeNo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) ($contract->contract_number ?: $contract->id));
            $monthPadded = str_pad((string) $this->selectedMonth, 2, '0', STR_PAD_LEFT);
            $uploadedPath = $this->externalPayslipUpload->storeAs(
                'buchhaltung/receipts',
                "DATEV_Lohnzettel_{$safeNo}_{$this->selectedYear}_{$monthPadded}.pdf",
                'local'
            );
        }

        $overrides = [
            'gross_amount_cents' => $this->parseEurToCents($this->payrollGrossEur),
            'net_payout_cents' => $this->parseEurToCents($this->payrollNetEur),
            'employer_charges_cents' => $this->parseEurToCents($this->payrollEmployerChargesEur),
            'notes' => trim($this->payrollNotes) ?: null,
        ];
        if ($uploadedPath) {
            $overrides['uploaded_external_pdf_path'] = $uploadedPath;
        }

        $payslip = $service->createOrUpdatePayslip($contract, $this->selectedYear, $this->selectedMonth, $overrides);

        $this->externalPayslipUpload = null;
        session()->flash('success', "Lohnabrechnung {$payslip->payslip_number} & Stundenzettel wurden für {$payslip->period_label} bereitgestellt.");
    }

    public function markPayslipPaid(string $payslipId, EmployeeHrService $service): void
    {
        $payslip = EmployeePayslip::findOrFail($payslipId);
        $adminId = Auth::guard('admin')->id();
        $service->markPayslipAsPaid($payslip, $adminId ? (string) $adminId : null);

        session()->flash('success', "Lohnabrechnung {$payslip->payslip_number} als überwiesen markiert und automatisch in der Buchhaltung (Personal & Löhne) verbucht.");
    }

    public function toggleActive(string $id): void
    {
        $contract = EmployeeContract::findOrFail($id);
        $contract->update(['is_active' => !$contract->is_active]);
        session()->flash('success', $contract->is_active ? 'Mitarbeitervertrag aktiviert.' : 'Mitarbeitervertrag pausiert.');
    }

    public function toggleArchive(string $id): void
    {
        $contract = EmployeeContract::findOrFail($id);
        $newArchived = !$contract->is_archived;
        $contract->update([
            'is_archived' => $newArchived,
            'is_active' => $newArchived ? false : $contract->is_active,
        ]);
        session()->flash('success', $newArchived ? 'Personalakte archiviert.' : 'Personalakte wiederhergestellt.');
    }

    protected function parseEurToCents(string $value): int
    {
        $clean = str_replace(['€', ' '], '', trim($value));
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        return max(0, (int) round(((float) $clean) * 100));
    }

    public function render(EmployeeHrService $service)
    {
        $query = EmployeeContract::with(['employee.profile', 'payslips' => function ($q) {
            $q->where('period_year', $this->selectedYear)
                ->where('period_month', $this->selectedMonth);
        }]);

        if ($this->filterStatus === 'archived') {
            $query->where('is_archived', true);
        } elseif ($this->filterStatus === 'pending') {
            $query->where('is_archived', false)->whereNull('invitation_accepted_at');
        } else {
            $query->where('is_archived', false);
        }

        if (trim($this->search) !== '') {
            $s = '%' . trim($this->search) . '%';
            $query->where(function ($q) use ($s) {
                $q->where('invitation_label', 'like', $s)
                    ->orWhere('contract_number', 'like', $s)
                    ->orWhere('job_title', 'like', $s)
                    ->orWhereHas('employee', function ($eq) use ($s) {
                        $eq->where('first_name', 'like', $s)
                            ->orWhere('last_name', 'like', $s)
                            ->orWhere('email', 'like', $s);
                    });
            });
        }

        $contracts = $query->orderByDesc('created_at')->get();

        $rows = $contracts->map(function (EmployeeContract $contract) use ($service) {
            $stats = $service->getMonthlyStats($contract, $this->selectedYear, $this->selectedMonth);
            $currentPayslip = $contract->payslips->first();
            $epcQrSvg = null;

            if ($currentPayslip && $currentPayslip->status === 'open') {
                $payload = $contract->buildEpcGiroCodePayload(
                    (int) $currentPayslip->net_payout_cents,
                    "Lohn {$currentPayslip->payslip_number} {$currentPayslip->period_label}"
                );
                if ($payload) {
                    $epcQrSvg = $service->generateQrCodeSvg($payload, 150);
                }
            }

            return [
                'contract' => $contract,
                'stats' => $stats,
                'payslip' => $currentPayslip,
                'epc_qr_svg' => $epcQrSvg,
            ];
        });

        $activeCount = EmployeeContract::where('is_archived', false)->whereNotNull('invitation_accepted_at')->count();
        $pendingInvitesCount = EmployeeContract::where('is_archived', false)->whereNull('invitation_accepted_at')->count();
        $totalWorkedMinutesMonth = $rows->sum(fn ($r) => $r['stats']['worked_minutes']);
        $openPayslipsQuery = EmployeePayslip::where('status', 'open');
        $openPayslipsCount = (clone $openPayslipsQuery)->count();
        $openPayslipsNetCents = (int) (clone $openPayslipsQuery)->sum('net_payout_cents');
        $monthTotalEmployerCostCents = $rows->sum(fn ($r) => $r['payslip'] ? $r['payslip']->total_employer_cost_cents : $r['stats']['total_employer_cost_cents']);

        $activeInviteSlot = $this->activeInviteId ? EmployeeContract::find($this->activeInviteId) : null;

        return view('livewire.shop.management.management-employees', [
            'rows' => $rows,
            'activeCount' => $activeCount,
            'pendingInvitesCount' => $pendingInvitesCount,
            'totalWorkedHoursMonth' => round($totalWorkedMinutesMonth / 60, 1),
            'openPayslipsCount' => $openPayslipsCount,
            'openPayslipsNetCents' => $openPayslipsNetCents,
            'monthTotalEmployerCostCents' => $monthTotalEmployerCostCents,
            'activeInviteSlot' => $activeInviteSlot,
        ]);
    }
}
