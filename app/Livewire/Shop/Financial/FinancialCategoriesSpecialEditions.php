<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class FinancialCategoriesSpecialEditions extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public $selectedYear;
    #[Url]
    public $selectedMonth;

    public $specialSearch = '';

    // Category Management
    public $newCategoryName = '';
    public $editingCategoryId = null;
    public $editCategoryName = '';

    // Category Delete Modal State
    public $showCategoryDeleteModal = false;
    public $categoryToDeleteId = null;
    public $targetCategoryId = '';
    public $categoryToDeleteName = '';

    // Edit State Special Issue
    public ?string $editingSpecialId = null;
    public $editSpecialTitle;
    public $editSpecialCategory;
    public $editSpecialAmount;
    public $editSpecialDate;
    public $editSpecialLocation;
    public $editSpecialIsBusiness;
    public $editSpecialTaxRate;
    public $editSpecialInvoiceNumber;

    public $editSpecialFiles = [];
    #[Rule(['newEditFiles.*' => 'max:10240'])]
    public $newEditFiles = [];

    // Quick Upload State (Missing Receipts)
    public ?string $uploadingMissingSpecialId = null;
    #[Rule('required|file|max:10240')]
    public $quickSpecialUploadFile;

    #[On('special-issue-created')]
    public function refreshList()
    {
        // Diese Methode muss nichts tun, außer existieren.
        // Livewire rendert die Komponente automatisch neu, wenn ein Event empfangen wird.
        // Wir setzen nur die Seite zurück, damit man den neuen Eintrag sieht.
        $this->resetPage();
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf die Finanzen.');
        }
        $this->selectedYear = $this->selectedYear ?? date('Y');
        $this->selectedMonth = $this->selectedMonth ?? date('n');
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // --- Computed Properties ---

    // Holt alle Sonderausgaben ohne Datei
    public function getMissingReceiptsProperty()
    {
        // Wir prüfen auf leeres JSON Array oder NULL
        // SQLite/MySQL JSON handling kann variieren, daher sicherheitshalber Collection Filter
        return FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where(function($q) {
                $q->whereNull('file_paths')
                    ->orWhere('file_paths', '[]') // Leeres JSON Array
                    ->orWhere('file_paths', '');
            })
            ->orderByDesc('execution_date')
            ->get();
    }

    public function updatedSpecialSearch()
    {
        $this->resetPage();
    }

    // --- Actions: Quick Upload ---

    public function startSpecialUpload($id)
    {
        $this->uploadingMissingSpecialId = $id;
        $this->reset('quickSpecialUploadFile');
    }

    public function cancelSpecialUpload()
    {
        $this->uploadingMissingSpecialId = null;
        $this->reset('quickSpecialUploadFile');
    }

    public function saveSpecialUpload()
    {
        $this->validate(['quickSpecialUploadFile' => 'required|file|max:10240']);

        $special = FinanceSpecialIssue::where('id', $this->uploadingMissingSpecialId)
            ->where('admin_id', $this->getAdminId())
            ->firstOrFail();

        $path = $this->quickSpecialUploadFile->store('financial_docs/' . date('Y/m'), 'public');

        // Da file_paths ein Array ist, müssen wir es so speichern
        // Wenn vorher null, dann neues Array.
        $currentFiles = $special->file_paths ?? [];
        $currentFiles[] = $path;

        $special->update(['file_paths' => $currentFiles]);

        $this->uploadingMissingSpecialId = null;
        $this->reset('quickSpecialUploadFile');

        session()->flash('success', 'Beleg erfolgreich hochgeladen.');
    }


    // --- Kategorien Verwaltung ---
    public function getManageableCategoriesProperty()
    {
        $categories = FinanceCategory::where('admin_id', $this->getAdminId())
            ->orderBy('name')
            ->get();

        foreach($categories as $cat) {
            $cat->live_usage_count = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
                ->where('category', $cat->name)
                ->count();
        }

        return $categories;
    }

    public function getCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->pluck('name')
            ->toArray();
    }

    public function createCategory()
    {
        $this->validate(['newCategoryName' => 'required|min:2']);

        FinanceCategory::create([
            'admin_id' => $this->getAdminId(),
            'name' => $this->newCategoryName
        ]);

        $this->newCategoryName = '';
        session()->flash('success', 'Kategorie erstellt.');
        $this->dispatchChartUpdate();
    }

    public function deleteCategory($id)
    {
        $category = FinanceCategory::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();

        $usageCount = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('category', $category->name)
            ->count();

        if ($usageCount > 0) {
            $this->categoryToDeleteId = $id;
            $this->categoryToDeleteName = $category->name;
            $this->showCategoryDeleteModal = true;
            $this->targetCategoryId = '';
        } else {
            $category->delete();
            session()->flash('success', 'Kategorie gelöscht.');
            $this->dispatchChartUpdate();
        }
    }

    public function confirmDeleteCategory()
    {
        $this->validate([
            'targetCategoryId' => 'required'
        ], ['targetCategoryId.required' => 'Bitte wähle eine neue Kategorie aus.']);

        $oldCategory = FinanceCategory::findOrFail($this->categoryToDeleteId);
        $newCategory = FinanceCategory::findOrFail($this->targetCategoryId);

        FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('category', $oldCategory->name)
            ->update(['category' => $newCategory->name]);

        $oldCategory->delete();

        $this->showCategoryDeleteModal = false;
        $this->categoryToDeleteId = null;
        $this->targetCategoryId = '';

        session()->flash('success', 'Kategorie gelöscht und Einträge übertragen.');
        $this->dispatchChartUpdate();
    }

    public function cancelDeleteCategory()
    {
        $this->showCategoryDeleteModal = false;
        $this->categoryToDeleteId = null;
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
        $this->dispatchChartUpdate();
    }

    public function cancelEditCategory()
    {
        $this->editingCategoryId = null;
        $this->editCategoryName = '';
    }

    // --- Special Issues Editing ---

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
        $this->editSpecialTaxRate = $special->tax_rate;
        $this->editSpecialInvoiceNumber = $special->invoice_number;

        $this->editSpecialFiles = $special->file_paths ?? [];
        $this->newEditFiles = [];
    }

    public function cancelEditSpecial()
    {
        $this->editingSpecialId = null;
        $this->reset(['editSpecialTitle', 'editSpecialAmount', 'editSpecialDate', 'editSpecialLocation', 'editSpecialCategory', 'editSpecialIsBusiness', 'editSpecialTaxRate', 'editSpecialInvoiceNumber', 'editSpecialFiles', 'newEditFiles']);
    }

    public function updateSpecial($id)
    {
        $this->validate([
            'editSpecialTitle' => 'required',
            'editSpecialAmount' => 'required|numeric'
        ]);

        $special = FinanceSpecialIssue::where('id', $id)->where('admin_id', $this->getAdminId())->firstOrFail();

        $currentFiles = $this->editSpecialFiles;
        if (!empty($this->newEditFiles)) {
            foreach ($this->newEditFiles as $file) {
                $currentFiles[] = $file->store('financial_docs/' . date('Y/m'), 'public');
            }
        }

        $special->update([
            'title' => $this->editSpecialTitle,
            'amount' => $this->editSpecialAmount,
            'execution_date' => $this->editSpecialDate,
            'location' => $this->editSpecialLocation,
            'category' => $this->editSpecialCategory,
            'is_business' => $this->editSpecialIsBusiness,
            'tax_rate' => $this->editSpecialIsBusiness ? $this->editSpecialTaxRate : null,
            'invoice_number' => $this->editSpecialIsBusiness ? $this->editSpecialInvoiceNumber : null,
            'file_paths' => $currentFiles
        ]);

        $this->cancelEditSpecial();
        session()->flash('success', 'Eintrag aktualisiert.');
        $this->dispatchChartUpdate();
    }

    public function removeFileFromSpecial($index)
    {
        if (isset($this->editSpecialFiles[$index])) {
            unset($this->editSpecialFiles[$index]);
            $this->editSpecialFiles = array_values($this->editSpecialFiles);
        }
    }

    public function deleteSpecial($id)
    {
        FinanceSpecialIssue::where('id', $id)->where('admin_id', $this->getAdminId())->delete();
        session()->flash('success', 'Eintrag gelöscht.');
        $this->dispatchChartUpdate();
    }

    private function dispatchChartUpdate()
    {
        $data = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('amount', '<', 0)
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $this->dispatch('update-category-chart', labels: $data->pluck('category'), data: $data->pluck('total'));
    }

    public function render()
    {
        $adminId = $this->getAdminId();

        $specialsQuery = FinanceSpecialIssue::where('admin_id', $adminId);

        if (!empty($this->specialSearch)) {
            $specialsQuery->where(function($q) {
                $q->where('title', 'like', '%'.$this->specialSearch.'%')
                    ->orWhere('category', 'like', '%'.$this->specialSearch.'%')
                    ->orWhere('location', 'like', '%'.$this->specialSearch.'%');
            });
        }

        $specials = $specialsQuery->orderBy('execution_date', 'desc')->paginate(10);

        // Chart Data Initial Load
        $chartDataObj = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('amount', '<', 0)
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $chartLabels = $chartDataObj->pluck('category');
        $chartData = $chartDataObj->pluck('total');

        return view('livewire.shop.financial.financial-categories-special-editions.financial-categories-special-editions', [
            'specials' => $specials,
            'chartLabels' => $chartLabels,
            'chartData' => $chartData,
            'missingReceipts' => $this->missingReceipts
        ]);
    }
}
