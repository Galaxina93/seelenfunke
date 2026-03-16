<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class FinancialCategoriesSpecialEditions extends Component
{
    use WithFileUploads, WithPagination;

    public $specialSearch = '';

    // Category Management
    public $newCategoryName = '';
    public $editingCategoryId = null;
    public $editCategoryName = '';
    public $showCategoryDeleteModal = false;
    public $categoryToDeleteId = null;
    public $targetCategoryId = '';
    public $categoryToDeleteName = '';

    // File Uploads (Direkt im Beleg-Feld)
    public ?string $uploadingMissingSpecialId = null;

    #[Rule('required|file|max:10240')]
    public $quickUploadFile;

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    #[On('special-issue-created')]
    public function refreshList()
    {
        $this->resetPage();
        $this->refreshChartData();
    }

    // --- COMPUTED PROPERTIES ---

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

    // --- ACTIONS: CATEGORIES ---

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
        $this->refreshChartData();
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
            $this->refreshChartData();
        }
        $this->cancelDeleteCategory();
    }

    // --- ACTIONS: SPECIAL ISSUES (INLINE EDITING) ---

    public function updateSpecialField($id, $field, $value)
    {
        $special = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
        if ($special) {
            if ($field === 'amount') {
                $value = (float) str_replace(',', '.', $value);
            }
            if ($field === 'is_business') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            $special->update([$field => $value]);

            // Wenn Betrag oder Kategorie geändert wurden, Chart updaten
            if (in_array($field, ['amount', 'category'])) {
                $this->refreshChartData();
            }
        }
    }

    public function deleteSpecialFile($id, $fileIndex)
    {
        $special = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
        if ($special) {
            $files = is_string($special->file_paths) ? json_decode($special->file_paths, true) : $special->file_paths;
            if (isset($files[$fileIndex])) {
                $path = $files[$fileIndex];
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                unset($files[$fileIndex]);
                $special->update(['file_paths' => array_values($files)]);
                session()->flash('success', 'Beleg gelöscht.');
            }
        }
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
            session()->flash('success', 'Eintrag und zugehörige Belege gelöscht.');
            $this->refreshChartData();
        }
    }

    // --- FILE UPLOAD (INLINE) ---

    public function updatedQuickUploadFile()
    {
        if($this->uploadingMissingSpecialId && $this->quickUploadFile) {
            $special = FinanceSpecialIssue::find($this->uploadingMissingSpecialId);
            if($special) {
                $path = $this->quickUploadFile->store('financial/receipts', 'public');

                $files = is_string($special->file_paths) ? json_decode($special->file_paths, true) : $special->file_paths;
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

    // --- RENDER & HELPER ---

    private function refreshChartData()
    {
        $chartDataObj = FinanceSpecialIssue::where('admin_id', $this->getAdminId())
            ->where('amount', '<', 0)
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $chartLabels = $chartDataObj->pluck('category');
        $chartDataValues = $chartDataObj->pluck('total');

        $this->dispatch('update-category-chart', labels: $chartLabels, data: $chartDataValues);
    }

    public function render()
    {
        $adminId = $this->getAdminId();

        // 1. Fetch Special Issues
        $specialsData = FinanceSpecialIssue::where('admin_id', $adminId)->get()->map(function($item) {
            return [
                'type' => 'special',
                'id' => $item->id,
                'title' => $item->title,
                'category' => $item->category,
                'amount' => $item->amount,
                'execution_date' => $item->execution_date,
                'location' => $item->location,
                'is_business' => $item->is_business,
                'invoice_number' => $item->invoice_number,
                'tax_rate' => $item->tax_rate,
                'file_paths' => $item->file_paths,
                'created_at' => $item->created_at
            ];
        });

        // 2. Fetch Unassigned Bank Transactions
        $bankTxData = collect();
        if (class_exists(\App\Models\Financial\BankTransaction::class)) {
            $bankTxData = \App\Models\Financial\BankTransaction::whereHas('account', function($query) use ($adminId) {
                    $query->where('admin_id', $adminId);
                })
                ->whereNull('finance_cost_item_id')
                ->where(function($q) {
                    $q->whereNull('finance_category_id')->orWhereNotIn('finance_category_id', function($sub) {
                        $sub->select('id')->from('finance_categories');
                    });
                })
                ->get()
                ->map(function($tx) {
                    $isBusiness = false;
                    if ($tx->account) {
                        $isBusiness = $tx->account->is_business;
                    }

                    return [
                        'type' => 'bank_tx',
                        'id' => $tx->id,
                        'title' => '🏦 ' . ($tx->counterpart_name ?? $tx->purpose ?? 'Unbekannte Abbuchung'),
                        'category' => 'Nicht zugeordnet',
                        'amount' => $tx->amount,
                        'execution_date' => $tx->transaction_date ? \Carbon\Carbon::parse($tx->transaction_date) : null,
                        'location' => $tx->counterpart_iban ?? '',
                        'is_business' => $isBusiness,
                        'invoice_number' => null,
                        'tax_rate' => 0,
                        'file_paths' => [],
                        'created_at' => clone $tx->created_at
                    ];
                });
        }

        // 3. Merge and Sort
        $merged = $specialsData->concat($bankTxData);

        if (!empty($this->specialSearch)) {
            $search = strtolower($this->specialSearch);
            $merged = $merged->filter(function($item) use ($search) {
                return str_contains(strtolower($item['title']), $search) ||
                       str_contains(strtolower($item['category']), $search) ||
                       str_contains(strtolower($item['location']), $search);
            });
        }

        $merged = $merged->sortByDesc(function($item) {
            return ($item['execution_date'] ? $item['execution_date']->format('Y-m-d') : '1970-01-01') . '-' . $item['created_at']->timestamp;
        })->values();

        // 4. Manually Paginate
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $perPage = 10;
        $currentItems = $merged->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $specials = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentItems,
            $merged->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );

        // Initiale Chart Data
        $chartDataObj = FinanceSpecialIssue::where('admin_id', $adminId)
            ->where('amount', '<', 0)
            ->select('category', DB::raw('SUM(ABS(amount)) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return view('livewire.shop.financial.financial-categories-special-editions.financial-categories-special-editions', [
            'specials' => $specials,
            'chartLabels' => $chartDataObj->pluck('category'),
            'chartData' => $chartDataObj->pluck('total'),
        ]);
    }
}
