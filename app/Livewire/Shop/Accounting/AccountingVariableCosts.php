<?php

namespace App\Livewire\Shop\Accounting;

use App\Models\Accounting\FinanceCategory;
use App\Models\Accounting\FinanceSpecialIssue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Rule;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class AccountingVariableCosts extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Buchhaltung';

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
            ->orderBy('name')
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

    public function updateCategoryType($categoryId, $isBusiness)
    {
        $cat = FinanceCategory::where('admin_id', $this->getAdminId())->find($categoryId);
        if ($cat) {
            $cat->update(['is_business' => filter_var($isBusiness, FILTER_VALIDATE_BOOLEAN)]);
            session()->flash('success', 'Kategorie Typ aktualisiert.');
        }
    }

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

    public function toggleBusinessStatus($type, $id)
    {
        $model = null;
        if ($type === 'special') {
            $model = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
        } elseif ($type === 'bank_tx') {
            $model = \App\Models\Accounting\BankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $this->getAdminId()))->find($id);
        }

        if ($model) {
            if ($type === 'special') {
                $model->update(['is_business' => !$model->is_business]);
            } else {
                $currentStatus = $model->is_business ?? $model->account->is_business;
                $model->update(['is_business' => !$currentStatus]);
            }
        }
    }

    public function addTag($type, $id, $tag)
    {
        $model = null;
        if ($type === 'special') {
            // Not supported for legacy yet
            return;
        } elseif ($type === 'bank_tx') {
            $model = \App\Models\Accounting\BankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $this->getAdminId()))->find($id);
        }

        $cleanTag = trim(strtolower($tag));
        if ($model && !empty($cleanTag)) {
            $tags = is_array($model->tags) ? $model->tags : [];
            if (!in_array($cleanTag, $tags)) {
                $tags[] = $cleanTag;
                $model->update(['tags' => $tags]);
            }
        }
    }

    public function removeTag($type, $id, $tag)
    {
        $model = null;
        if ($type === 'special') {
            return;
        } elseif ($type === 'bank_tx') {
            $model = \App\Models\Accounting\BankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $this->getAdminId()))->find($id);
        }

        if ($model && is_array($model->tags)) {
            $tags = array_filter($model->tags, fn($t) => strcasecmp($t, $tag) !== 0);
            $model->update(['tags' => array_values($tags)]);
        }
    }

    public function deleteSpecialFile($type, $id, $fileIndex)
    {
        $model = null;
        if ($type === 'special') {
            $model = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
        } elseif ($type === 'bank_tx') {
            $model = \App\Models\Accounting\BankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $this->getAdminId()))->find($id);
        }

        if ($model) {
            $files = is_string($model->file_paths) ? json_decode($model->file_paths, true) : $model->file_paths;
            if (isset($files[$fileIndex])) {
                $path = $files[$fileIndex];
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
                unset($files[$fileIndex]);
                $model->update(['file_paths' => array_values($files)]);
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
            $parts = explode('-', $this->uploadingMissingSpecialId, 2);
            if(count($parts) === 2) {
                $type = $parts[0];
                $id = $parts[1];
                
                $model = null;
                if ($type === 'special') {
                    $model = FinanceSpecialIssue::where('admin_id', $this->getAdminId())->find($id);
                } elseif ($type === 'bank_tx') {
                    $model = \App\Models\Accounting\BankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $this->getAdminId()))->find($id);
                }

                if($model) {
                    $path = $this->quickUploadFile->store('financial/receipts', 'public');

                    $files = is_string($model->file_paths) ? json_decode($model->file_paths, true) : $model->file_paths;
                    if (!is_array($files)) {
                        $files = [];
                    }
                    $files[] = $path;

                    $model->update(['file_paths' => $files]);
                    session()->flash('success', 'Beleg hochgeladen.');
                }
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
                'tags' => [], // Legacy Special Issue currently has no JSON tags natively
                'created_at' => $item->created_at
            ];
        });

        // 2. Fetch Unassigned Bank Transactions
        $bankTxData = collect();
        if (class_exists(\App\Models\Accounting\BankTransaction::class)) {
            $bankTxData = \App\Models\Accounting\BankTransaction::whereHas('account', function($query) use ($adminId) {
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
                        'is_business' => $tx->is_business ?? $isBusiness,
                        'invoice_number' => null,
                        'tax_rate' => 0,
                        'file_paths' => is_string($tx->file_paths) ? json_decode($tx->file_paths, true) : $tx->file_paths,
                        'tags' => is_string($tx->tags) ? json_decode($tx->tags, true) : ($tx->tags ?? []),
                        'assigned_by_type' => $tx->assigned_by_type,
                        'assigned_by_name' => $tx->assigned_by_name,
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

        return view('livewire.shop.accounting.accounting-variable-costs.accounting-variable-costs', [
            'specials' => $specials,
            'chartLabels' => $chartDataObj->pluck('category'),
            'chartData' => $chartDataObj->pluck('total'),
        ]);
    }
}
