<?php

namespace App\Livewire\Employee;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Employee\EmployeeContract;
use App\Models\Employee\EmployeePayslip;
use App\Models\Employee\EmployeeTimeEntry;
use App\Services\Employee\EmployeeHrService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.backend_layout', ['guard' => 'employee'])]
class EmployeeDashboard extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Leitung';

    public int $selectedYear;
    public int $selectedMonth;
    public string $mainTab = 'timesheets'; // timesheets, payslips
    public bool $showAddEntryForm = false;
    public bool $showExplanation = false;

    // Formular: Schicht eintragen (§ 17 MiLoG / § 4 ArbZG)
    public string $entryDate = '';
    public string $entryStartTime = '09:00';
    public string $entryEndTime = '13:30';
    public int $entryBreakMinutes = 0;
    public string $entryActivityNote = 'Manufaktur & Versand';

    public function boot(): void
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->isInternalEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }
    }

    public function mount(): void
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee || !$employee->isInternalEmployee()) {
            abort(403, 'Zugriff verweigert.');
        }

        $this->selectedYear = (int) now()->year;
        $this->selectedMonth = (int) now()->month;
        $this->entryDate = now()->toDateString();

        if (request()->routeIs('employee.payslips')) {
            $this->mainTab = 'payslips';
        }
    }

    public function clockInNow(EmployeeHrService $service): void
    {
        $contract = $this->resolveContract();
        if (!$contract) {
            return;
        }

        $service->clockIn($contract, 'Aktive Schicht (Stempeluhr)');
        session()->flash('success', 'Einstempeln erfolgreich. Deine Arbeitszeit läuft.');
    }

    public function clockOutNow(EmployeeHrService $service): void
    {
        $contract = $this->resolveContract();
        if (!$contract) {
            return;
        }

        $running = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('status', 'running')
            ->first();

        if ($running) {
            $entry = $service->clockOut($running);
            session()->flash('success', "Ausgestempelt: {$entry->formatted_hours} Netto-Arbeitszeit (inkl. {$entry->break_minutes} Min. Pause nach § 4 ArbZG) gespeichert.");
        }
    }

    public function saveTimeEntry(EmployeeHrService $service): void
    {
        $contract = $this->resolveContract();
        if (!$contract) {
            return;
        }

        $this->validate([
            'entryDate' => 'required|date',
            'entryStartTime' => 'required|string|max:10',
            'entryEndTime' => 'required|string|max:10',
            'entryBreakMinutes' => 'required|integer|min:0|max:240',
            'entryActivityNote' => 'nullable|string|max:200',
        ]);

        $entry = $service->recordTimeEntry($contract, [
            'work_date' => $this->entryDate,
            'start_time' => $this->entryStartTime,
            'end_time' => $this->entryEndTime,
            'break_minutes' => $this->entryBreakMinutes,
            'activity_note' => $this->entryActivityNote,
            'auto_enforce_arbzg' => true,
        ]);

        $this->showAddEntryForm = false;
        session()->flash('success', "Arbeitszeit am {$entry->work_date->format('d.m.Y')} ({$entry->formatted_hours} Netto) wurde gespeichert.");
    }

    public function deleteTimeEntry(string $entryId): void
    {
        $contract = $this->resolveContract();
        if (!$contract) {
            return;
        }

        $entry = EmployeeTimeEntry::where('contract_id', $contract->id)
            ->where('id', $entryId)
            ->where('status', '!=', 'approved')
            ->first();

        if ($entry) {
            $entry->delete();
            session()->flash('success', 'Zeiteintrag wurde entfernt.');
        }
    }

    protected function resolveContract(): ?EmployeeContract
    {
        $employee = Auth::guard('employee')->user();
        if (!$employee) {
            return null;
        }

        return EmployeeContract::where('employee_id', $employee->id)->first();
    }

    public function render(EmployeeHrService $service)
    {
        $employee = Auth::guard('employee')->user();
        $contract = $this->resolveContract();

        $stats = $contract
            ? $service->getMonthlyStats($contract, $this->selectedYear, $this->selectedMonth)
            : [
                'entries' => collect(),
                'entries_count' => 0,
                'worked_minutes' => 0,
                'break_minutes' => 0,
                'worked_hours_decimal' => 0.0,
                'worked_hours_formatted' => '0:00 Std.',
                'target_hours_decimal' => 0.0,
                'estimated_gross_cents' => 0,
                'estimated_net_cents' => 0,
                'is_minijob' => true,
                'minijob_limit_cents' => EmployeeHrService::MINIJOB_LIMIT_CENTS,
                'minijob_usage_percent' => 0,
                'minijob_warning' => false,
                'arbzg_all_compliant' => true,
            ];

        $runningEntry = $contract
            ? EmployeeTimeEntry::where('contract_id', $contract->id)->where('status', 'running')->first()
            : null;

        $payslips = $contract
            ? EmployeePayslip::where('contract_id', $contract->id)
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->get()
            : collect();

        return view('livewire.employee.employee-dashboard', [
            'employee' => $employee,
            'contract' => $contract,
            'stats' => $stats,
            'runningEntry' => $runningEntry,
            'payslips' => $payslips,
        ]);
    }
}
