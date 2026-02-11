<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\On; // Wichtig
use Livewire\Component;

class FinancialEvaluation extends Component
{
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
    public $excludeSpecialExpenses = false;
    public $expandedCategories = [];

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
        $this->render();
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

    public function render(FinancialService $service)
    {
        $adminId = $this->getAdminId();

        $stats = $service->getMonthlyStats($adminId, $this->selectedMonth, $this->selectedYear);
        $matrix = $service->getYearlyMatrix($adminId, $this->selectedYear);

        // Charts Data
        $pieData = $service->getPieChartData($adminId, $this->dateFrom, $this->dateTo);
        $barData = $service->getBarChartData($adminId, $this->dateFrom, $this->dateTo);

        return view('livewire.shop.financial.financial-evaluation.financial-evaluation', [
            'stats' => $stats,
            'yearlyMatrix' => $matrix,
            'pieData' => $pieData,
            'barData' => $barData
        ]);
    }
}
