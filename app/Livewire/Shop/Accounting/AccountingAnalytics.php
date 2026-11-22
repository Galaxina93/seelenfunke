<?php

namespace App\Livewire\Shop\Accounting;

use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingInvestmentPlan;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.backend_layout')]
class AccountingAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    #[Url]
    public $selectedYear;

    #[Url]
    public $selectedMonth;

    #[Url]
    public string $scope = 'business';

    public $exportMonth;
    public $exportYear;

    // Chart Filter
    public $chartFilter = 'last_12_months';
    public $dateFrom;
    public $dateTo;

    // Toggle
    public bool $isNet = true;
    public $excludeSpecialExpenses = false;
    public $expandedCategories = [];

    // Analytics State
    public array $invoiceData = [];
    public array $specialIssueData = [];
    public array $costItemData = [];
    public array $groupData = [];

    // Dynamisches Gehalt & Simulator Konfiguration
    public bool $simEnabled = false;
    public $simMonthlyGrossRevenue = 3800.00;
    public $simCogsPercent = 25.0;
    public $configSafetyBuffer = 1500.00;
    public $configPrivateLivingAllowance = 600.00;
    public bool $configReserveAfaReplacement = true;
    public bool $configSmoothAnnualFixCosts = true;
    public bool $showPayoutConfigModal = false;

    // Reinvestitions-Planer & AfA UI State
    public string $investmentTab = 'plans';
    public bool $showPlanModal = false;

    #[Locked]
    public ?string $editingPlanId = null;

    public string $planTitle = '';
    public $planTargetGross = '';
    public int $planTaxRate = 19;
    public $planSavedInitial = '0';
    public string $planAllocationMode = 'fixed_monthly';
    public $planMonthlyRate = '125';
    public $planProfitPercent = '10';
    public string $planStartDate = '';
    public ?string $planTargetDate = null;
    public int $planAfaYears = 5;
    public bool $planUseIab = false;
    public bool $planIsActive = true;
    public string $planNote = '';

    public function boot(): void
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }
    }

    public function mount(FinancialService $service)
    {
        if (!Auth::guard('admin')->check() && !\App\Services\AI\AiAuthHelper::isAdmin()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }

        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?? date('Y'))));
        $this->selectedMonth = max(1, min(12, (int) ($this->selectedMonth ?? date('n'))));
        $this->scope = in_array($this->scope, ['business', 'private', 'all'], true) ? $this->scope : 'business';

        $this->exportYear = max(2000, min(2100, (int) ($this->exportYear ?? $this->selectedYear)));
        $this->exportMonth = max(1, min(12, (int) ($this->exportMonth ?? $this->selectedMonth)));
        $this->planStartDate = date('Y-m-01');

        $payoutCfg = $service->getPayoutPlannerConfig();
        $this->simEnabled = (bool) $payoutCfg['sim_enabled'];
        $this->simMonthlyGrossRevenue = (float) $payoutCfg['sim_monthly_gross_revenue'];
        $this->simCogsPercent = (float) $payoutCfg['sim_cogs_percent'];
        $this->configSafetyBuffer = (float) $payoutCfg['safety_buffer'];
        $this->configPrivateLivingAllowance = (float) $payoutCfg['private_living_allowance'];
        $this->configReserveAfaReplacement = (bool) $payoutCfg['reserve_afa_replacement'];
        $this->configSmoothAnnualFixCosts = (bool) $payoutCfg['smooth_annual_fix_costs'];

        $this->updateDateRange();
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id() ?: app(FinancialService::class)->resolveMandantAdminId();
    }

    public function setScope(string $scope): void
    {
        if (in_array($scope, ['business', 'private', 'all'], true)) {
            $this->scope = $scope;
        }
    }

    public function toggleSimulation(FinancialService $service): void
    {
        $this->simEnabled = !$this->simEnabled;
        $service->savePayoutPlannerConfig(['sim_enabled' => $this->simEnabled]);
    }

    public function applyQuickSimRevenue(float $amount, FinancialService $service): void
    {
        $this->simEnabled = true;
        $this->simMonthlyGrossRevenue = max(0.0, $amount);
        $service->savePayoutPlannerConfig([
            'sim_enabled' => true,
            'sim_monthly_gross_revenue' => $this->simMonthlyGrossRevenue,
        ]);
    }

    public function updatedSimMonthlyGrossRevenue(): void
    {
        $val = (float) str_replace(',', '.', (string) $this->simMonthlyGrossRevenue);
        $this->simMonthlyGrossRevenue = max(0.0, min(500000.0, $val));
        app(FinancialService::class)->savePayoutPlannerConfig([
            'sim_monthly_gross_revenue' => $this->simMonthlyGrossRevenue,
        ]);
    }

    public function updatedSimCogsPercent(): void
    {
        $val = (float) str_replace(',', '.', (string) $this->simCogsPercent);
        $this->simCogsPercent = max(0.0, min(95.0, $val));
        app(FinancialService::class)->savePayoutPlannerConfig([
            'sim_cogs_percent' => $this->simCogsPercent,
        ]);
    }

    public function savePayoutSettings(FinancialService $service): void
    {
        $saved = $service->savePayoutPlannerConfig([
            'safety_buffer' => (float) str_replace(',', '.', (string) $this->configSafetyBuffer),
            'private_living_allowance' => (float) str_replace(',', '.', (string) $this->configPrivateLivingAllowance),
            'reserve_afa_replacement' => (bool) $this->configReserveAfaReplacement,
            'smooth_annual_fix_costs' => (bool) $this->configSmoothAnnualFixCosts,
            'sim_enabled' => (bool) $this->simEnabled,
            'sim_monthly_gross_revenue' => (float) str_replace(',', '.', (string) $this->simMonthlyGrossRevenue),
            'sim_cogs_percent' => (float) str_replace(',', '.', (string) $this->simCogsPercent),
        ]);

        $this->configSafetyBuffer = $saved['safety_buffer'];
        $this->configPrivateLivingAllowance = $saved['private_living_allowance'];
        $this->configReserveAfaReplacement = $saved['reserve_afa_replacement'];
        $this->configSmoothAnnualFixCosts = $saved['smooth_annual_fix_costs'];
        $this->showPayoutConfigModal = false;
        session()->flash('success', 'Auszahlungs-Parameter gespeichert.');
    }

    public function openCreatePlanModal(): void
    {
        $this->resetValidation();
        $this->editingPlanId = null;
        $this->planTitle = '';
        $this->planTargetGross = '';
        $this->planTaxRate = app(\App\Services\Tax\TaxSettingService::class)->isCurrentSmallBusiness() ? 0 : 19;
        $this->planSavedInitial = '0';
        $this->planAllocationMode = 'fixed_monthly';
        $this->planMonthlyRate = '125';
        $this->planProfitPercent = '10';
        $this->planStartDate = sprintf('%04d-%02d-01', (int) $this->selectedYear, (int) $this->selectedMonth);
        $this->planTargetDate = Carbon::parse($this->planStartDate)->addYears(2)->endOfMonth()->format('Y-m-d');
        $this->planAfaYears = 5;
        $this->planUseIab = false;
        $this->planIsActive = true;
        $this->planNote = '';
        $this->showPlanModal = true;
    }

    public function openEditPlanModal(string $planId): void
    {
        $this->resetValidation();
        $plan = AccountingInvestmentPlan::where('admin_id', $this->getAdminId())->findOrFail($planId);

        $this->editingPlanId = $plan->id;
        $this->planTitle = (string) $plan->title;
        $this->planTargetGross = number_format((float) $plan->target_amount_gross, 2, '.', '');
        $this->planTaxRate = (int) $plan->tax_rate;
        $this->planSavedInitial = number_format((float) $plan->saved_initial, 2, '.', '');
        $this->planAllocationMode = (string) $plan->allocation_mode;
        $this->planMonthlyRate = number_format((float) $plan->monthly_rate, 2, '.', '');
        $this->planProfitPercent = number_format((float) $plan->profit_percent, 1, '.', '');
        $this->planStartDate = $plan->start_date->format('Y-m-d');
        $this->planTargetDate = $plan->target_date?->format('Y-m-d');
        $this->planAfaYears = (int) $plan->afa_years;
        $this->planUseIab = (bool) $plan->use_iab;
        $this->planIsActive = (bool) $plan->is_active;
        $this->planNote = (string) ($plan->note ?? '');
        $this->showPlanModal = true;
    }

    public function savePlan(): void
    {
        $this->planTargetGross = str_replace(',', '.', (string) $this->planTargetGross);
        $this->planSavedInitial = str_replace(',', '.', (string) ($this->planSavedInitial === '' ? '0' : $this->planSavedInitial));
        $this->planMonthlyRate = str_replace(',', '.', (string) ($this->planMonthlyRate === '' ? '0' : $this->planMonthlyRate));
        $this->planProfitPercent = str_replace(',', '.', (string) ($this->planProfitPercent === '' ? '0' : $this->planProfitPercent));

        $this->validate([
            'planTitle' => 'required|string|min:2|max:160',
            'planTargetGross' => 'required|numeric|min:1|max:500000',
            'planTaxRate' => 'required|integer|in:0,7,19',
            'planSavedInitial' => 'nullable|numeric|min:0|max:500000',
            'planAllocationMode' => 'required|in:fixed_monthly,profit_percent',
            'planMonthlyRate' => 'nullable|numeric|min:0|max:100000',
            'planProfitPercent' => 'nullable|numeric|min:0|max:100',
            'planStartDate' => 'required|date',
            'planTargetDate' => 'nullable|date|after_or_equal:planStartDate',
            'planAfaYears' => 'required|integer|min:0|max:20',
            'planNote' => 'nullable|string|max:1000',
        ], [
            'planTitle.required' => 'Bitte eine Bezeichnung eingeben.',
            'planTargetGross.required' => 'Bitte den Zielbetrag (Brutto) angeben.',
            'planTargetDate.after_or_equal' => 'Zieldatum muss nach dem Startdatum liegen.',
        ]);

        $adminId = $this->getAdminId();
        $payload = [
            'title' => trim($this->planTitle),
            'target_amount_gross' => round((float) $this->planTargetGross, 2),
            'tax_rate' => (int) $this->planTaxRate,
            'saved_initial' => round((float) $this->planSavedInitial, 2),
            'allocation_mode' => $this->planAllocationMode,
            'monthly_rate' => round((float) $this->planMonthlyRate, 2),
            'profit_percent' => round((float) $this->planProfitPercent, 2),
            'start_date' => $this->planStartDate,
            'target_date' => $this->planTargetDate ?: null,
            'afa_years' => (int) $this->planAfaYears,
            'use_iab' => (int) $this->planAfaYears > 0 ? (bool) $this->planUseIab : false,
            'is_active' => (bool) $this->planIsActive,
            'note' => trim($this->planNote) !== '' ? trim($this->planNote) : null,
        ];

        if ($this->editingPlanId) {
            $existing = AccountingInvestmentPlan::where('admin_id', $adminId)->findOrFail($this->editingPlanId);
            $existing->update($payload);
            session()->flash('success', 'Investitionsziel aktualisiert.');
        } else {
            $maxPrio = (int) AccountingInvestmentPlan::where('admin_id', $adminId)->max('priority');
            $payload['admin_id'] = $adminId;
            $payload['priority'] = $maxPrio + 1;
            AccountingInvestmentPlan::create($payload);
            session()->flash('success', 'Investitionsziel angelegt.');
        }

        $this->showPlanModal = false;
        $this->editingPlanId = null;
    }

    public function togglePlanActive(string $planId): void
    {
        $plan = AccountingInvestmentPlan::where('admin_id', $this->getAdminId())->findOrFail($planId);
        $plan->update(['is_active' => !$plan->is_active]);
    }

    public function deletePlan(string $planId): void
    {
        $plan = AccountingInvestmentPlan::where('admin_id', $this->getAdminId())->findOrFail($planId);
        $plan->delete();
        session()->flash('success', 'Investitionsziel entfernt.');
    }

    #[On('special-issue-created')]
    public function refreshStats()
    {
    }

    public function updatedChartFilter()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
        if ($this->chartFilter === 'last_12_months') {
            $this->dateFrom = Carbon::now()->startOfMonth()->subMonths(11)->format('Y-m-d');
            $this->dateTo = Carbon::now()->endOfMonth()->format('Y-m-d');
        } elseif ($this->chartFilter === 'this_year') {
            $this->dateFrom = Carbon::now()->startOfYear()->format('Y-m-d');
            $this->dateTo = Carbon::now()->endOfYear()->format('Y-m-d');
        }
    }

    public function toggleCategory($key)
    {
        if (in_array($key, $this->expandedCategories)) {
            $this->expandedCategories = array_diff($this->expandedCategories, [$key]);
        } else {
            $this->expandedCategories[] = $key;
        }
    }

    public function downloadTaxExport(FinancialService $service)
    {
        $path = $service->generateTaxExport($this->getAdminId(), $this->exportMonth, $this->exportYear, $this->scope);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    private function computeAnalytics()
    {
        // 1. Invoices
        if ($this->scope === 'private') {
            $this->invoiceData = ['labels' => [], 'data' => []];
        } else {
            $invoices = AccountingInvoice::whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
                ->whereIn('status', ['paid', 'cancelled'])
                ->whereNotNull('paid_at')
                ->whereIn('type', ['invoice', 'cancellation', 'credit_note'])
                ->get();

            $invGrouped = $invoices->groupBy(fn($i) => Carbon::parse($i->invoice_date)->format('Y-m'));
            $invLabels = [];
            $invData = [];
            foreach ($invGrouped->sortKeys() as $gDate => $items) {
                $invLabels[] = Carbon::createFromFormat('!Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4);
                $invData[] = $items->sum(function ($i) {
                    $sign = in_array($i->type, ['cancellation', 'credit_note'], true) ? -1 : 1;
                    $gross = $sign * abs((int) $i->total);
                    $tax = $sign * abs((int) $i->tax_amount);

                    return $this->isNet ? ($gross - $tax) : $gross;
                }) / 100;
            }
            $this->invoiceData = ['labels' => $invLabels, 'data' => $invData];
        }

        $taxService = app(\App\Services\Tax\TaxSettingService::class);
        $isSmallBusiness = $taxService->isCurrentSmallBusiness();

        // 2. Special Issues (nach gewähltem Bereich)
        $issuesQuery = AccountingSpecialIssue::where('admin_id', $this->getAdminId())
            ->whereBetween('execution_date', [$this->dateFrom, $this->dateTo]);
        if ($this->scope === 'business') {
            $issuesQuery->where('is_business', true);
        } elseif ($this->scope === 'private') {
            $issuesQuery->where('is_business', false);
        }
        $issues = $issuesQuery->get();

        $issGrouped = $issues->groupBy(fn($i) => Carbon::parse($i->execution_date)->format('Y-m'));
        $issLabels = [];
        $issData = [];
        foreach ($issGrouped->sortKeys() as $gDate => $items) {
            $issLabels[] = Carbon::createFromFormat('!Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4);
            $issData[] = $items->sum(function ($i) use ($isSmallBusiness) {
                $amount = (float) $i->amount;
                if ($this->isNet && $i->is_business && !$isSmallBusiness && isset($i->tax_rate) && $i->tax_rate > 0) {
                    $amount = $amount / (1 + ($i->tax_rate / 100));
                }

                return $amount;
            });
        }
        $this->specialIssueData = ['labels' => $issLabels, 'data' => $issData];

        // 3. Cost Items (nach gewähltem Bereich)
        $costsQuery = AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $this->getAdminId())->where('type', '!=', 'income'))
            ->with('group');
        if ($this->scope === 'business') {
            $costsQuery->where('is_business', true);
        } elseif ($this->scope === 'private') {
            $costsQuery->where('is_business', false);
        }
        $costs = $costsQuery->get();

        $cLabels = [];
        $cData = [];
        foreach ($costs->groupBy('accounting_group_id') as $groupItems) {
            $grpName = $groupItems->first()->group->name ?? 'Sonstige';
            $monthlySum = 0.0;

            foreach ($groupItems as $item) {
                if (($item->group->type ?? 'expense') === 'income' && $item->amount >= 0) {
                    continue;
                }
                $amount = (float) $item->amount;
                if ($this->isNet && $item->is_business && !$isSmallBusiness && isset($item->tax_rate) && $item->tax_rate > 0) {
                    $amount = $amount / (1 + ($item->tax_rate / 100));
                }
                $interval = max(1, (int) $item->interval_months);
                $monthlySum += abs($amount / $interval);
            }
            if ($monthlySum > 0) {
                $cLabels[] = $grpName;
                $cData[] = round($monthlySum, 2);
            }
        }
        $this->costItemData = ['labels' => $cLabels, 'data' => $cData];
        $this->groupData = ['labels' => $cLabels, 'data' => $cData];
    }

    public function render(FinancialService $service)
    {
        $adminId = $this->getAdminId();
        $this->selectedYear = max(2000, min(2100, (int) ($this->selectedYear ?: date('Y'))));
        $this->selectedMonth = max(1, min(12, (int) ($this->selectedMonth ?: date('n'))));
        $this->scope = in_array($this->scope, ['business', 'private', 'all'], true) ? $this->scope : 'business';

        $stats = $service->getMonthlyStats($adminId, $this->selectedMonth, $this->selectedYear, $this->isNet, $this->scope);
        $matrix = $service->getYearlyMatrix($adminId, $this->selectedYear, $this->isNet, $this->scope);
        $pieData = $service->getPieChartData($adminId, $this->dateFrom, $this->dateTo, $this->scope);
        $barData = $service->getBarChartData($adminId, $this->dateFrom, $this->dateTo, $this->isNet, $this->scope);

        $payoutPlan = $service->getDynamicSalaryAndReinvestmentData(
            $adminId,
            (int) $this->selectedMonth,
            (int) $this->selectedYear,
            [
                'sim_enabled' => $this->simEnabled,
                'sim_monthly_gross_revenue' => (float) $this->simMonthlyGrossRevenue,
                'sim_cogs_percent' => (float) $this->simCogsPercent,
                'safety_buffer' => (float) $this->configSafetyBuffer,
                'private_living_allowance' => (float) $this->configPrivateLivingAllowance,
                'reserve_afa_replacement' => $this->configReserveAfaReplacement,
                'smooth_annual_fix_costs' => $this->configSmoothAnnualFixCosts,
            ]
        );

        $this->computeAnalytics();
        $this->dispatch('analytics-updated');

        return view('livewire.shop.accounting.accounting-analytics.accounting-analytics', [
            'stats' => $stats,
            'yearlyMatrix' => $matrix,
            'pieData' => $pieData,
            'barData' => $barData,
            'payoutPlan' => $payoutPlan,
        ]);
    }
}
