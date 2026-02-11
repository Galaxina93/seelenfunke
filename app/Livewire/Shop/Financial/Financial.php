<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceSpecialIssue;
use App\Services\FinancialService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Financial extends Component
{
    use WithFileUploads, WithPagination;

    // State
    public $selectedYear;
    public $selectedMonth;
    public ?string $activeGroupId = null;

    // Section Toggles
    public $showSpecialSection = false;
    public $showGroupsSection = false;
    public $showYearlySection = false;

    public $specialSearch = '';

    // Chart Filter
    public $chartFilter = 'last_12_months';
    public $dateFrom;
    public $dateTo;

    // Toggle für "Frei Verfügbar" ohne Sonderausgaben
    public $excludeSpecialExpenses = false;

    // Toggle für Jahresübersicht
    public $expandedCategories = [];

    // Forms: Gruppen
    public $newGroupName = '';
    public $newGroupType = 'expense';

    // Forms: Kostenstellen
    #[Rule('required', message: 'Bitte geben Sie einen Namen an.')]
    public $itemName = '';

    #[Rule('required|numeric', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $itemAmount = '';

    public $itemInterval = 1;

    #[Rule('required', message: 'Bitte wählen Sie ein Startdatum.')]
    public $itemDate;

    public $itemDescription = '';
    public $itemFile;
    public $itemIsBusiness = false;

    public ?string $editingItemId = null;
    public ?string $addingToGroupId = null;

    // Forms: Sonderausgaben
    #[Rule('required', message: 'Bitte geben Sie einen Titel an.')]
    public $specialTitle = '';

    public $specialCategory = '';

    #[Rule('required|numeric', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $specialAmount = '';

    #[Rule('required', message: 'Bitte wählen Sie ein Datum.')]
    public $specialDate;

    public $specialLocation = '';
    public $specialIsBusiness = false;

    // Inline Editing Special
    public ?string $editingSpecialId = null;
    public $editSpecialTitle;
    public $editSpecialCategory;
    public $editSpecialAmount;
    public $editSpecialDate;
    public $editSpecialLocation;
    public $editSpecialIsBusiness;

    // Category Management
    public $newCategoryName = '';
    public $editingCategoryId = null;
    public $editCategoryName = '';

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }

        $this->selectedYear = date('Y');
        $this->selectedMonth = date('n');
        $this->itemDate = date('Y-m-d');
        $this->specialDate = date('Y-m-d');

        // Default Chart Filter
        $this->updateDateRange();
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
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
        // Custom behält die Werte bei
    }

    public function updatedSpecialSearch()
    {
        $this->resetPage();
    }

    // --- View Helpers ---
    public function toggleCategory($key)
    {
        if (in_array($key, $this->expandedCategories)) {
            $this->expandedCategories = array_diff($this->expandedCategories, [$key]);
        } else {
            $this->expandedCategories[] = $key;
        }
    }

    // --- Kategorien Verwaltung ---

    public function getCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->orderByDesc('usage_count')
            ->pluck('name')
            ->toArray();
    }

    public function getManageableCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->orderBy('name')
            ->get();
    }

    public function createCategory()
    {
        $this->validate(['newCategoryName' => 'required|min:2']);

        FinanceCategory::create([
            'admin_id' => $this->getAdminId(),
            'name' => $this->newCategoryName
        ]);

        $this->newCategoryName = '';
        session()->flash('success', 'Kategorie erfolgreich erstellt.');
    }

    public function deleteCategory($id)
    {
        FinanceCategory::where('id', $id)->where('admin_id', $this->getAdminId())->delete();
        session()->flash('success', 'Kategorie gelöscht.');
    }

    public function startEditCategory($id)
    {
        $cat = FinanceCategory::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();
        $this->editingCategoryId = $id;
        $this->editCategoryName = $cat->name;
    }

    public function updateCategory()
    {
        $this->validate(['editCategoryName' => 'required|min:2']);

        $cat = FinanceCategory::where('id', $this->editingCategoryId)->where('admin_id', $this->getAdminId())->firstOrFail();
        $oldName = $cat->name;
        $cat->update(['name' => $this->editCategoryName]);

        FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('category', $oldName)
            ->update(['category' => $this->editCategoryName]);

        $this->editingCategoryId = null;
        $this->editCategoryName = '';
        session()->flash('success', 'Kategorie aktualisiert.');
    }

    public function cancelEditCategory()
    {
        $this->editingCategoryId = null;
        $this->editCategoryName = '';
    }

    private function trackCategoryUsage($categoryName)
    {
        if(empty($categoryName)) return;

        $cat = FinanceCategory::withTrashed()
            ->where('admin_id', $this->getAdminId())
            ->where('name', $categoryName)
            ->first();

        if ($cat) {
            if ($cat->trashed()) {
                $cat->restore();
            }
            $cat->increment('usage_count');
        } else {
            FinanceCategory::create([
                'admin_id' => $this->getAdminId(),
                'name' => $categoryName,
                'usage_count' => 1
            ]);
        }
    }

    // --- Actions: Groups ---

    public function createGroup()
    {
        $this->validate(['newGroupName' => 'required|min:3']);

        FinanceGroup::create([
            'admin_id' => $this->getAdminId(),
            'name' => $this->newGroupName,
            'type' => $this->newGroupType
        ]);

        $this->reset('newGroupName', 'newGroupType');
        session()->flash('success', 'Gruppe erfolgreich erstellt.');
    }

    public function deleteGroup($id)
    {
        FinanceGroup::where('id', $id)->where('admin_id', $this->getAdminId())->delete();
        session()->flash('success', 'Gruppe gelöscht.');
    }

    // --- Actions: Items ---

    public function openItemForm($groupId, $itemId = null)
    {
        $this->resetItemForm();
        $this->addingToGroupId = $groupId;

        if ($itemId) {
            $this->editingItemId = $itemId;
            $item = FinanceCostItem::findOrFail($itemId);

            if($item->group->admin_id !== $this->getAdminId()) {
                abort(403);
            }

            $this->itemName = $item->name;
            $this->itemAmount = $item->amount;
            $this->itemInterval = $item->interval_months;
            $this->itemDate = $item->first_payment_date->format('Y-m-d');
            $this->itemDescription = $item->description;
            $this->itemIsBusiness = (bool) $item->is_business;
        }
    }

    public function saveNewItem($groupId)
    {
        $this->addingToGroupId = $groupId;
        $this->saveItem();
    }

    public function saveItem()
    {
        // FIX: Nur die Felder für Kostenstellen validieren!
        $this->validate([
            'itemName' => 'required',
            'itemAmount' => 'required|numeric',
            'itemDate' => 'required|date',
        ], [
            'itemName.required' => 'Bitte geben Sie einen Namen an.',
            'itemAmount.required' => 'Bitte geben Sie einen Betrag an.',
            'itemDate.required' => 'Bitte wählen Sie ein Startdatum.',
        ]);

        $data = [
            'name' => $this->itemName,
            'amount' => $this->itemAmount,
            'interval_months' => $this->itemInterval,
            'first_payment_date' => $this->itemDate,
            'description' => $this->itemDescription,
            'is_business' => $this->itemIsBusiness,
        ];

        if ($this->itemFile) {
            $path = $this->itemFile->store('contracts', 'public');
            $data['contract_file_path'] = $path;
        }

        if ($this->editingItemId) {
            $item = FinanceCostItem::findOrFail($this->editingItemId);
            if($item->group->admin_id !== $this->getAdminId()) abort(403);
            $item->update($data);
            $this->editingItemId = null;
            session()->flash('success', 'Kostenstelle aktualisiert.');
        } else {
            if(!$this->addingToGroupId) {
                session()->flash('error', 'Fehler: Gruppe nicht gefunden.');
                return;
            }

            $group = FinanceGroup::findOrFail($this->addingToGroupId);
            if($group->admin_id !== $this->getAdminId()) abort(403);

            FinanceCostItem::create(array_merge($data, [
                'finance_group_id' => $this->addingToGroupId
            ]));
            session()->flash('success', 'Kostenstelle erstellt.');
        }

        $this->resetItemForm();
    }

    public function cancelItemEdit()
    {
        $this->editingItemId = null;
        $this->resetItemForm();
    }

    public function deleteItem($id)
    {
        $item = FinanceCostItem::findOrFail($id);
        if($item->group->admin_id !== $this->getAdminId()) abort(403);
        $item->delete();
        session()->flash('success', 'Kostenstelle gelöscht.');
    }

    public function resetItemForm()
    {
        $this->reset(['itemName', 'itemAmount', 'itemInterval', 'itemDate', 'itemDescription', 'itemFile', 'addingToGroupId', 'itemIsBusiness', 'editingItemId']);
        $this->itemDate = date('Y-m-d');
    }

    // --- Actions: Special Issues ---

    public function createSpecial()
    {
        // FIX: Nur die Felder für Sonderausgaben validieren!
        $this->validate([
            'specialTitle' => 'required',
            'specialAmount' => 'required|numeric',
            'specialDate' => 'required|date'
        ], [
            'specialTitle.required' => 'Bitte geben Sie einen Titel an.',
            'specialAmount.required' => 'Bitte geben Sie einen Betrag an.',
            'specialDate.required' => 'Bitte wählen Sie ein Datum.',
        ]);

        FinanceSpecialIssue::create([
            'admin_id' => $this->getAdminId(),
            'title' => $this->specialTitle,
            'amount' => $this->specialAmount,
            'execution_date' => $this->specialDate,
            'location' => $this->specialLocation,
            'category' => $this->specialCategory,
            'is_business' => $this->specialIsBusiness,
        ]);

        $this->trackCategoryUsage($this->specialCategory);
        $this->reset(['specialTitle', 'specialAmount', 'specialLocation', 'specialCategory', 'specialIsBusiness']);
        $this->specialDate = date('Y-m-d');

        session()->flash('success', 'Sonderausgabe erstellt.');
    }

    public function editSpecial($id)
    {
        $special = FinanceSpecialIssue::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();
        $this->editingSpecialId = $id;
        $this->editSpecialTitle = $special->title;
        $this->editSpecialAmount = $special->amount;
        $this->editSpecialDate = $special->execution_date->format('Y-m-d');
        $this->editSpecialLocation = $special->location;
        $this->editSpecialCategory = $special->category;
        $this->editSpecialIsBusiness = (bool) $special->is_business;
    }

    public function cancelEditSpecial()
    {
        $this->editingSpecialId = null;
        $this->reset(['editSpecialTitle', 'editSpecialAmount', 'editSpecialDate', 'editSpecialLocation', 'editSpecialCategory', 'editSpecialIsBusiness']);
    }

    public function updateSpecial($id)
    {
        $this->validate([
            'editSpecialTitle' => 'required',
            'editSpecialAmount' => 'required|numeric'
        ]);

        $special = FinanceSpecialIssue::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();

        $special->update([
            'title' => $this->editSpecialTitle,
            'amount' => $this->editSpecialAmount,
            'execution_date' => $this->editSpecialDate,
            'location' => $this->editSpecialLocation,
            'category' => $this->editSpecialCategory,
            'is_business' => $this->editSpecialIsBusiness,
        ]);

        $this->trackCategoryUsage($this->editSpecialCategory);
        $this->cancelEditSpecial();
        session()->flash('success', 'Sonderausgabe aktualisiert.');
    }

    public function deleteSpecial($id)
    {
        FinanceSpecialIssue::where('id', $id)->where('admin_id', $this->getAdminId())->delete();
        session()->flash('success', 'Sonderausgabe gelöscht.');
    }

    // --- Render ---

    public function render(FinancialService $service)
    {
        $adminId = $this->getAdminId();

        // Statistiken basieren weiterhin auf dem ausgewählten Monat/Jahr
        $stats = $service->getMonthlyStats($adminId, $this->selectedMonth, $this->selectedYear);
        $matrix = $service->getYearlyMatrix($adminId, $this->selectedYear);

        $groups = FinanceGroup::with('items')
            ->where('admin_id', $adminId)
            ->orderBy('type')
            ->get();

        // Änderung: Keine Filterung mehr nach Monat/Jahr für die Liste
        $specialsQuery = FinanceSpecialIssue::where('admin_id', $adminId);

        if (!empty($this->specialSearch)) {
            $specialsQuery->where(function($q) {
                $q->where('title', 'like', '%'.$this->specialSearch.'%')
                    ->orWhere('category', 'like', '%'.$this->specialSearch.'%')
                    ->orWhere('location', 'like', '%'.$this->specialSearch.'%');
            });
        }

        $specials = $specialsQuery->orderBy('execution_date', 'desc')->paginate(5);

        // Chart Data
        $pieData = $service->getPieChartData($adminId, $this->dateFrom, $this->dateTo);
        $barData = $service->getBarChartData($adminId, $this->dateFrom, $this->dateTo);

        return view('livewire.shop.financial.financial', [
            'stats' => $stats,
            'yearlyMatrix' => $matrix,
            'groups' => $groups,
            'specials' => $specials,
            'categories' => $this->categories,
            'pieData' => $pieData,
            'barData' => $barData
        ]);
    }

    public function toggleGroup($id)
    {
        if ($this->activeGroupId === $id) {
            $this->activeGroupId = null;
        } else {
            $this->activeGroupId = $id;
        }
    }
}
