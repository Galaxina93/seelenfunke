<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;
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
    public $editSpecialExistingFiles = [];

    public $editSpecialFiles = [];
    #[Rule(['newEditFiles.*' => 'max:10240'])]
    public $newEditFiles = [];

    // --- NEU/WIEDERHERGESTELLT: Quick Upload State (Missing Receipts) ---
    public ?string $uploadingMissingSpecialId = null;
    #[Rule('required|file|max:10240')]
    public $quickUploadFile;

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }

        $this->selectedYear = $this->selectedYear ?? date('Y');
        $this->selectedMonth = $this->selectedMonth ?? date('n');
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // --- Events ---

    #[On('special-issue-created')]
    public function refreshList()
    {
        $this->resetPage();
    }

    // --- Category Management Actions ---

    public function getManageableCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->addSelect([
                'usage_count' => FinanceSpecialIssue::selectRaw('count(*)')
                    ->whereColumn('category', 'finance_categories.name')
                    ->whereColumn('admin_id', 'finance_categories.admin_id')
            ])
            ->orderByDesc('usage_count')
            ->get();
    }

    public function createCategory()
    {
        $this->validate(['newCategoryName' => 'required|min:3|unique:finance_categories,name,NULL,id,admin_id,' . $this->getAdminId()]);

        FinanceCategory::create([
            'admin_id' => $this->getAdminId(),
            'name' => $this->newCategoryName,
            'usage_count' => 0
        ]);

        $this->newCategoryName = '';
        session()->flash('success', 'Kategorie erstellt.');
    }

    public function editCategory($id, $name)
    {
        $this->editingCategoryId = $id;
        $this->editCategoryName = $name;
    }

    public function cancelEditCategory()
    {
        $this->editingCategoryId = null;
        $this->editCategoryName = '';
    }

    public function updateCategory()
    {
        if (!$this->editingCategoryId) return;

        $this->validate(['editCategoryName' => 'required|min:3']);

        $cat = FinanceCategory::where('admin_id', $this->getAdminId())->find($this->editingCategoryId);
        if ($cat) {
            FinanceSpecialIssue::where('admin_id', $this->getAdminId())
                ->where('category', $cat->name)
                ->update(['category' => $this->editCategoryName]);

            $cat->update(['name' => $this->editCategoryName]);
        }

        $this->editingCategoryId = null;
        session()->flash('success', 'Kategorie aktualisiert.');
    }

    public function deleteCategory($id)
    {
        $cat = FinanceCategory::where('admin_id', $this->getAdminId())->find($id);
        if (!$cat) return;

        $usage = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('category', $cat->name)
            ->count();

        if ($usage > 0) {
            $this->categoryToDeleteId = $cat->id;
            $this->categoryToDeleteName = $cat->name;
            $this->showCategoryDeleteModal = true;
        } else {
            $cat->delete();
            session()->flash('success', 'Kategorie gelöscht.');
        }
    }

    public function cancelDeleteCategory()
    {
        $this->showCategoryDeleteModal = false;
        $this->categoryToDeleteId = null;
        $this->targetCategoryId = '';
    }

    public function confirmDeleteCategory()
    {
        $this->validate(['targetCategoryId' => 'required']);

        $oldCat = FinanceCategory::find($this->categoryToDeleteId);
        $newCat = FinanceCategory::find($this->targetCategoryId);

        if ($oldCat && $newCat) {
            FinanceSpecialIssue::where('admin_id', $this->getAdminId())
                ->where('category', $oldCat->name)
                ->update(['category' => $newCat->name]);

            $countMoved = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
                ->where('category', $newCat->name)
                ->count();

            $newCat->update(['usage_count' => $countMoved]);
            $oldCat->delete();

            session()->flash('success', 'Kategorie gelöscht und Einträge verschoben.');
        }

        $this->cancelDeleteCategory();
    }

    // --- Special Issue Actions ---

    public function editSpecial($id)
    {
        $special = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
        if(!$special) return;

        $this->editingSpecialId = $special->id;
        $this->editSpecialTitle = $special->title;
        $this->editSpecialCategory = $special->category;
        $this->editSpecialAmount = abs($special->amount);

        // FIX: Sicherstellen, dass das Datum im Format YYYY-MM-DD für das HTML-Input vorliegt
        $this->editSpecialDate = \Carbon\Carbon::parse($special->execution_date)->format('Y-m-d');

        $this->editSpecialLocation = $special->location;
        $this->editSpecialIsBusiness = $special->is_business;
        $this->editSpecialTaxRate = $special->tax_rate;
        $this->editSpecialInvoiceNumber = $special->invoice_number;

        $files = $special->file_paths;
        if (is_string($files)) {
            $files = json_decode($files, true);
        }
        $this->editSpecialExistingFiles = is_array($files) ? $files : [];
    }

    public function removeExistingFile($index)
    {
        if (isset($this->editSpecialExistingFiles[$index])) {
            unset($this->editSpecialExistingFiles[$index]);
            $this->editSpecialExistingFiles = array_values($this->editSpecialExistingFiles);
        }
    }

    public function cancelEditSpecial()
    {
        $this->editingSpecialId = null;
        $this->reset(['editSpecialTitle', 'editSpecialCategory', 'editSpecialAmount', 'editSpecialDate', 'editSpecialLocation', 'editSpecialIsBusiness', 'editSpecialTaxRate', 'editSpecialInvoiceNumber', 'newEditFiles']);
    }

    public function saveSpecial()
    {
        if(!$this->editingSpecialId) return;

        $this->validate([
            'editSpecialTitle' => 'required',
            'editSpecialAmount' => 'required|numeric',
            'editSpecialDate' => 'required|date',
        ]);

        $special = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($this->editingSpecialId);
        $finalFiles = $this->editSpecialExistingFiles;

        if (!empty($this->newEditFiles)) {
            foreach ($this->newEditFiles as $file) {
                $path = $file->store('financial/receipts', 'public');
                $finalFiles[] = $path;
            }
        }

        $special->update([
            'title' => $this->editSpecialTitle,
            'category' => $this->editSpecialCategory ?: 'Sonstiges',
            'amount' => str_replace(',', '.', $this->editSpecialAmount) * -1,
            'execution_date' => $this->editSpecialDate,
            'location' => $this->editSpecialLocation,
            'is_business' => $this->editSpecialIsBusiness,
            'tax_rate' => $this->editSpecialIsBusiness ? $this->editSpecialTaxRate : null,
            'invoice_number' => $this->editSpecialIsBusiness ? $this->editSpecialInvoiceNumber : null,
            'file_paths' => $finalFiles
        ]);

        $this->cancelEditSpecial();
        session()->flash('success', 'Eintrag aktualisiert.');
    }

    public function deleteSpecial($id)
    {
        $special = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);

        if ($special) {
            $files = $special->file_paths;
            if (is_string($files)) {
                $files = json_decode($files, true);
            }

            if (is_array($files) && count($files) > 0) {
                foreach ($files as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
            }
            $special->delete();
            session()->flash('success', 'Eintrag und zugehörige Belege wurden gelöscht.');
        }
    }

    // --- NEU/WIEDERHERGESTELLT: Quick Upload Logic ---
    public function updatedQuickUploadFile()
    {
        if($this->uploadingMissingSpecialId && $this->quickUploadFile) {
            $special = FinanceSpecialIssue::find($this->uploadingMissingSpecialId);

            if($special) {
                $path = $this->quickUploadFile->store('financial/receipts', 'public');

                $files = $special->file_paths;
                if (is_string($files)) {
                    $files = json_decode($files, true);
                }
                if (!is_array($files)) {
                    $files = [];
                }

                $files[] = $path;

                $special->update(['file_paths' => $files]);
                session()->flash('success', 'Beleg hochgeladen.');
            }

            $this->reset(['quickUploadFile', 'uploadingMissingSpecialId']);
        }
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

        $chartDataObj = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('amount', '<', 0)
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $chartLabels = $chartDataObj->pluck('category');
        $chartDataValues = $chartDataObj->pluck('total');

        $this->dispatch('update-category-chart', labels: $chartLabels, data: $chartDataValues);

        return view('livewire.shop.financial.financial-categories-special-editions.financial-categories-special-editions', [
            'specials' => $specials,
            'chartLabels' => $chartLabels,
            'chartData' => $chartDataValues,
        ]);
    }
}
