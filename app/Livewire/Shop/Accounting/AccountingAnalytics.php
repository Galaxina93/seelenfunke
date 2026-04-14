<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingSpecialIssue;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\On; // Wichtig
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use App\Models\Accounting\AccountingGroup;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\AccountingCostItem;

#[Layout('components.layouts.backend_layout')]
class AccountingAnalytics extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    // State (URL synchronisiert)
    #[Url]
    public $selectedYear;

    #[Url]
    public $selectedMonth;

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


    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }

        $this->selectedYear = $this->selectedYear ?? date('Y');
        $this->selectedMonth = $this->selectedMonth ?? date('n');

        $this->updateDateRange();

    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // Wenn die QuickEntry Componente speichert, feuert sie dieses Event.
    // Wir rendern dann neu, um die Budget-Zahlen zu aktualisieren.
    #[On('special-issue-created')]
    public function refreshStats()
    {
        // Leer lassen oder nur Variablen zurücksetzen.
        // Livewire ruft render() automatisch NACH dieser Methode auf
        // und injiziert dabei den Service korrekt.
    }

    public function updatedChartFilter()
    {
        $this->updateDateRange();
    }

    private function updateDateRange()
    {
        if ($this->chartFilter === 'last_12_months') {
            $this->dateFrom = Carbon::now()->subMonths(11)->startOfMonth()->format('Y-m-d');
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

    // --- Actions: Export ---
    public function downloadTaxExport(FinancialService $service)
    {
        $path = $service->generateTaxExport($this->getAdminId(), $this->selectedMonth, $this->selectedYear);
        return response()->download($path)->deleteFileAfterSend(true);
    }


    private function computeAnalytics()
    {
        // 1. Invoices
        $invoices = AccountingInvoice::whereBetween('invoice_date', [$this->dateFrom, $this->dateTo])
            ->where('status', '!=', 'draft')
            ->get();
            
        $invGrouped = $invoices->groupBy(fn($i) => Carbon::parse($i->invoice_date)->format('Y-m'));
        $invLabels = [];
        $invData = [];
        foreach ($invGrouped->sortKeys() as $gDate => $items) {
            $invLabels[] = Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4);
            $invData[] = $items->sum($this->isNet ? 'subtotal' : 'total') / 100; // Euro conversion assumed based on typical integration
        }
        $this->invoiceData = ['labels' => $invLabels, 'data' => $invData];

        // 2. Special Issues
        $issues = AccountingSpecialIssue::whereBetween('execution_date', [$this->dateFrom, $this->dateTo])->get();
        $issGrouped = $issues->groupBy(fn($i) => Carbon::parse($i->execution_date)->format('Y-m'));
        $issLabels = [];
        $issData = [];
        foreach ($issGrouped->sortKeys() as $gDate => $items) {
            $issLabels[] = Carbon::createFromFormat('Y-m', $gDate)->locale('de')->shortMonthName . ' ' . substr($gDate, 0, 4);
            $issData[] = $items->sum('amount');
        }
        $this->specialIssueData = ['labels' => $issLabels, 'data' => $issData];

        // 3. Cost Items
        // Show newly generated fixing items or total fixed cost by group
        $costs = AccountingCostItem::with('group')->get();
        // Since Fix costs are static, we calculate global weight
        $cLabels = [];
        $cData = [];
        foreach ($costs->groupBy('accounting_group_id') as $gId => $groupItems) {
            $grpName = $groupItems->first()->group->name ?? 'Zuweisung Fehlt';
            $cLabels[] = $grpName;
            $cData[] = $groupItems->sum('amount');
        }
        $this->costItemData = ['labels' => $cLabels, 'data' => $cData];

        // 4. Group Distribution (Pie Chart mapping of active Fix Costs vs Special items)
        $this->groupData = ['labels' => $cLabels, 'data' => $cData]; // Mapped to CostItem baseline for now
    }

    public function render(FinancialService $service)
    {
        $adminId = $this->getAdminId();

        $stats = $service->getMonthlyStats($adminId, $this->selectedMonth, $this->selectedYear, $this->isNet);
        $matrix = $service->getYearlyMatrix($adminId, $this->selectedYear, $this->isNet);

        // Charts Data
        $pieData = $service->getPieChartData($adminId, $this->dateFrom, $this->dateTo);
        $barData = $service->getBarChartData($adminId, $this->dateFrom, $this->dateTo, $this->isNet);

        $this->computeAnalytics(); // Update local custom dashboards
        $this->dispatch('analytics-updated');

        return view('livewire.shop.accounting.accounting-analytics.accounting-analytics', [
            'stats' => $stats,
            'yearlyMatrix' => $matrix,
            'pieData' => $pieData,
            'barData' => $barData
        ]);
    }
}
