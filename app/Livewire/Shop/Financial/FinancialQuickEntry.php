<?php

namespace App\Livewire\Shop\Financial;

use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class FinancialQuickEntry extends Component
{
    use WithFileUploads;

    // Forms: Schnellerfassung Sonderausgaben
    #[Rule('required', message: 'Bitte geben Sie einen Titel an.')]
    public $specialTitle = '';

    public $specialCategory = '';

    #[Rule('required|numeric', message: 'Bitte geben Sie einen gültigen Betrag an.')]
    public $specialAmount = '';

    #[Rule('required', message: 'Bitte wählen Sie ein Datum.')]
    public $specialDate;

    public $specialLocation = '';
    public $specialIsBusiness = false;

    // Business Felder Schnellerfassung
    public $specialTaxRate = 19;
    public $specialInvoiceNumber = '';

    // Uploads Schnellerfassung
    #[Rule(['specialFiles.*' => 'max:10240'])]
    public $specialFiles = [];

    public function mount()
    {
        $this->specialDate = date('Y-m-d');
    }

    private function getAdminId()
    {
        return Auth::guard('admin')->id();
    }

    // --- View Helpers ---
    public function getCategoriesProperty()
    {
        return FinanceCategory::where('admin_id', $this->getAdminId())
            ->orderByDesc('usage_count')
            ->pluck('name')
            ->toArray();
    }

    // --- Actions: Create Special ---
    public function createSpecial()
    {
        $this->validate();

        // Upload Handling
        $filePaths = [];
        if (!empty($this->specialFiles)) {
            foreach ($this->specialFiles as $file) {
                $filePaths[] = $file->store('financial_docs/' . date('Y/m'), 'public');
            }
        }

        FinanceSpecialIssue::create([
            'admin_id' => $this->getAdminId(),
            'title' => $this->specialTitle,
            'amount' => $this->specialAmount,
            'execution_date' => $this->specialDate,
            'location' => $this->specialLocation,
            'category' => $this->specialCategory,
            'is_business' => $this->specialIsBusiness,
            'tax_rate' => $this->specialIsBusiness ? $this->specialTaxRate : null,
            'invoice_number' => $this->specialIsBusiness ? $this->specialInvoiceNumber : null,
            'file_paths' => $filePaths
        ]);

        $this->trackCategoryUsage($this->specialCategory);

        // Reset Form
        $this->reset(['specialTitle', 'specialAmount', 'specialLocation', 'specialCategory', 'specialIsBusiness', 'specialFiles', 'specialInvoiceNumber', 'specialTaxRate']);
        $this->specialDate = date('Y-m-d');
        $this->specialTaxRate = 19;

        // WICHTIG: Event senden, damit FinancialEvaluation die Stats aktualisiert
        $this->dispatch('special-issue-created');

        session()->flash('success', 'Sonderausgabe erfolgreich erstellt.');
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

    public function render()
    {
        return view('livewire.shop.financial.financial-quick-entry.financial-quick-entry');
    }
}
