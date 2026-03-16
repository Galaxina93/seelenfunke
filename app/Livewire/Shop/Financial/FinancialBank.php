<?php

namespace App\Livewire\Shop\Financial;

use Livewire\Component;
use Livewire\WithPagination;
use App\Services\BankApiService;
use App\Models\Financial\BankAccount;
use App\Models\Financial\FinanceCategory;
use App\Models\Financial\BankTransaction;

class FinancialBank extends Component
{
    use WithPagination;

    public $bankAccounts = [];
    public $availableCategories = [];
    public $availableCostItems = [];
    public $selectedAccountId = null;
    public $isLoading = false;

    // Filter Properties
    public $searchTx = '';
    public $filterCategoryId = ''; 
    public $filterType = ''; // 'income', 'expense'
    public $dateFrom = '';
    public $dateTo = '';

    public function mount(BankApiService $finapi)
    {
        if (request()->has('finapi_sync')) {
            return $this->importAccountsFromFinapi($finapi);
        }
        $this->loadBankAccounts();
    }

    public function updatingSearchTx() { $this->resetPage(); }
    public function updatingFilterCategoryId() { $this->resetPage(); }
    public function updatingFilterType() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }

    public function loadBankAccounts()
    {
        $adminId = auth('admin')->id();

        $this->bankAccounts = BankAccount::where('admin_id', $adminId)
            ->orderBy('bank_name')
            ->get()
            ->toArray();
            
        $this->availableCategories = FinanceCategory::where('admin_id', $adminId)->orderBy('name')->get()->toArray();

        $this->availableCostItems = \App\Models\Financial\FinanceCostItem::whereHas('group', function ($q) use ($adminId) {
            $q->where('admin_id', $adminId);
        })->orderBy('name')->get()->toArray();
    }

    public function selectBank($accountId)
    {
        $this->selectedAccountId = ($this->selectedAccountId == $accountId) ? null : $accountId;
        $this->resetPage();
    }

    public function toggleBankActive($accountId)
    {
        $account = BankAccount::find($accountId);
        if ($account && $account->admin_id === auth('admin')->id()) {
            $account->update([
                'is_active_for_analysis' => !$account->is_active_for_analysis
            ]);
            $this->loadBankAccounts();
            
            session()->flash('success', 'Analyse-Status aktualisiert.');
        }
    }

    public function toggleBankBusiness($accountId)
    {
        $account = BankAccount::find($accountId);
        if ($account && $account->admin_id === auth('admin')->id()) {
            $account->update([
                'is_business' => !$account->is_business
            ]);
            $this->loadBankAccounts();
            
            session()->flash('success', 'Konto-Art aktualisiert.');
        }
    }

    public function resetFilters()
    {
        $this->reset(['searchTx', 'filterCategoryId', 'filterType', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function assignCategory($transactionId, $categoryId)
    {
        $tx = BankTransaction::find($transactionId);
        if ($tx) {
            $value = empty($categoryId) ? null : $categoryId;
            $tx->update(['finance_category_id' => $value]);
            
            $this->applyCategorizationRule($tx, 'finance_category_id', $value);
        }
    }

    public function assignCostItem($transactionId, $costItemId)
    {
        $tx = BankTransaction::find($transactionId);
        if ($tx) {
            $value = empty($costItemId) ? null : $costItemId;
            $tx->update(['finance_cost_item_id' => $value]);
            
            $this->applyCategorizationRule($tx, 'finance_cost_item_id', $value);
        }
    }

    private function applyCategorizationRule(BankTransaction $tx, $field, $value)
    {
        $searchTerm = $tx->counterpart_name ?? $tx->purpose;
        
        if (!empty($searchTerm) && $value !== null) {
            $adminId = auth('admin')->id();
            
            \App\Models\Financial\FinanceCategorizationRule::updateOrCreate(
                ['admin_id' => $adminId, 'search_term' => rtrim(mb_substr($searchTerm, 0, 50))],
                [$field => $value]
            );

            // Apply to past transactions with same string
            BankTransaction::where('bank_account_id', $tx->bank_account_id)
                ->where(function($q) use ($searchTerm) {
                    $q->where('counterpart_name', $searchTerm)
                      ->orWhere('purpose', $searchTerm);
                })
                ->whereNull($field)
                ->update([$field => $value]);
        }
    }

    public function connectNewBank(BankApiService $finapi)
    {
        $this->isLoading = true;

        try {
            $adminId = auth('admin')->id();
            $userToken = $finapi->getUserToken($adminId);

            $redirectUrl = url()->previous();
            $separator = parse_url($redirectUrl, PHP_URL_QUERY) ? '&' : '?';
            $redirectUrl = $redirectUrl . $separator . 'finapi_sync=1';
            $redirectUrl = str_replace('http://', 'https://', $redirectUrl);

            $webFormUrl = $finapi->createBankImportWebForm($userToken, $redirectUrl);

            return redirect()->away($webFormUrl);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('finAPI Import Fehler: ' . $e->getMessage());
            session()->flash('error', 'Konnte keine Verbindung zu finAPI aufbauen: ' . $e->getMessage());
            $this->isLoading = false;
        }
    }

    private function importAccountsFromFinapi(BankApiService $finapi)
    {
        try {
            // FIX: Wir nutzen zwingend die UUID des eingeloggten Admins
            $adminId = auth('admin')->id();
            $userToken = $finapi->getUserToken($adminId);

            $accounts = $finapi->getAccounts($userToken);

            foreach ($accounts as $acc) {
                BankAccount::updateOrCreate(
                    ['plaid_account_id' => (string) $acc['id']],
                    [
                        'admin_id' => $adminId,
                        'plaid_item_id' => (string) $acc['bankConnectionId'],
                        'plaid_access_token' => 'finapi_managed',
                        'bank_name' => $acc['bankName'] ?? 'Demo Bank',
                        'account_name' => $acc['accountName'] ?? 'Bankkonto',
                        'iban' => $acc['iban'] ?? null,
                        'balance' => $acc['balance'] ?? 0,
                        'currency' => $acc['accountCurrency'] ?? 'EUR',
                        'last_synced_at' => now(),
                    ]
                );
            }

            session()->flash('success', 'Bankkonten erfolgreich importiert!');
            return redirect()->to(url()->current());

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('finAPI Account Fetch Fehler: ' . $e->getMessage());
            session()->flash('error', 'Konten konnten nach dem Login nicht geladen werden.');
        }
    }

    public function syncAllAccounts(BankApiService $finapi)
    {
        $this->isLoading = true;
        try {
            $adminId = auth('admin')->id();
            $userToken = $finapi->getUserToken($adminId);

            $accounts = $finapi->getAccounts($userToken);
            
            if (empty($accounts)) {
                $connections = \Illuminate\Support\Facades\Http::withToken($userToken)->get("https://live.finapi.io/api/v2/bankConnections")->json('connections') ?? [];
                if (empty($connections)) {
                    session()->flash('error', 'Keine Bankverbindungen bei finAPI gefunden. Hast du die Datenbank zurückgesetzt? Dann musst du die Bank einmalig über "Neue Bank verbinden" neu verknüpfen, da sich deine User-ID geändert hat!');
                    $this->isLoading = false;
                    return;
                }
            }

            foreach ($accounts as $acc) {
                $bankAccount = BankAccount::updateOrCreate(
                    ['plaid_account_id' => (string) $acc['id']],
                    [
                        'admin_id' => $adminId,
                        'plaid_item_id' => (string) $acc['bankConnectionId'],
                        'plaid_access_token' => 'finapi_managed',
                        'bank_name' => $acc['bankName'] ?? 'Demo Bank',
                        'account_name' => $acc['accountName'] ?? 'Bankkonto',
                        'iban' => $acc['iban'] ?? null,
                        'balance' => $acc['balance'] ?? 0,
                        'currency' => $acc['accountCurrency'] ?? 'EUR',
                        'last_synced_at' => now(),
                    ]
                );
                
                // TRANSAKTIONEN LADEN WIE BEI EINZEL-SYNC
                $txs = $finapi->getTransactions($userToken, $bankAccount->plaid_account_id, 200);
                foreach($txs as $apiTx) {
                    \App\Models\Financial\BankTransaction::updateOrCreate(
                        ['finapi_transaction_id' => (string) $apiTx['id']],
                        [
                            'bank_account_id' => $bankAccount->id,
                            'amount' => $apiTx['amount'] ?? 0,
                            'currency' => $bankAccount->currency,
                            'purpose' => $apiTx['purpose'] ?? null,
                            'counterpart_name' => $apiTx['counterpartName'] ?? null,
                            'counterpart_iban' => $apiTx['counterpartIban'] ?? null,
                            'transaction_date' => isset($apiTx['bankBookingDate']) ? \Carbon\Carbon::parse($apiTx['bankBookingDate']) : null,
                            'value_date' => isset($apiTx['valueDate']) ? \Carbon\Carbon::parse($apiTx['valueDate']) : null,
                            'type' => $apiTx['type'] ?? null,
                            'is_pending' => $apiTx['isPending'] ?? false,
                            'raw_data' => $apiTx
                        ]
                    );
                }
            }
            session()->flash('success', 'Alle Konten und Umsätze manuell aus finAPI aktualisiert!');
            $this->loadBankAccounts();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('finAPI Sync Fehler: ' . $e->getMessage());
            session()->flash('error', 'Synchronisierung fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    public function syncAccount($accountId, BankApiService $finapi)
    {
        $this->isLoading = true;
        try {
            $account = BankAccount::find($accountId);
            if($account) {
                $adminId = auth('admin')->id();
                $userToken = $finapi->getUserToken($adminId);

                $finapi->updateBankConnection($userToken, $account->plaid_item_id);
                $accounts = $finapi->getAccounts($userToken);

                foreach ($accounts as $apiAcc) {
                    if ((string)$apiAcc['id'] === $account->plaid_account_id) {
                        $account->update([
                            'balance' => $apiAcc['balance'],
                            'last_synced_at' => now(),
                        ]);
                        
                        // TRANSAKTIONEN LADEN
                        $txs = $finapi->getTransactions($userToken, $account->plaid_account_id, 200);
                        foreach($txs as $apiTx) {
                            \App\Models\Financial\BankTransaction::updateOrCreate(
                                ['finapi_transaction_id' => (string) $apiTx['id']],
                                [
                                    'bank_account_id' => $account->id,
                                    'amount' => $apiTx['amount'] ?? 0,
                                    'currency' => $account->currency,
                                    'purpose' => $apiTx['purpose'] ?? null,
                                    'counterpart_name' => $apiTx['counterpartName'] ?? null,
                                    'counterpart_iban' => $apiTx['counterpartIban'] ?? null,
                                    'transaction_date' => isset($apiTx['bankBookingDate']) ? \Carbon\Carbon::parse($apiTx['bankBookingDate']) : null,
                                    'value_date' => isset($apiTx['valueDate']) ? \Carbon\Carbon::parse($apiTx['valueDate']) : null,
                                    'type' => $apiTx['type'] ?? null,
                                    'is_pending' => $apiTx['isPending'] ?? false,
                                    'raw_data' => $apiTx
                                ]
                            );
                        }
                    }
                }
                session()->flash('success', 'Kontostand und Umsätze erfolgreich synchronisiert!');
                $this->loadBankAccounts();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Synchronisierung fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }
    
    public function disconnectAccount($accountId) 
    {
        $account = BankAccount::find($accountId);
        if ($account) {
            $account->delete();
            $this->loadBankAccounts();
            session()->flash('success', 'Konto lokal getrennt!');
        }
    }

    public function render()
    {
        $query = BankTransaction::query();

        // 1. Bank Account Filter
        if ($this->selectedAccountId) {
            $query->where('bank_account_id', $this->selectedAccountId);
        } else {
            // ONLY LOAD ACTIVE BANKS FOR ANALYSIS SUMMARY
            $activeAccountIds = array_column(
                array_filter($this->bankAccounts, fn($acc) => $acc['is_active_for_analysis']),
                'id'
            );
            
            if (empty($activeAccountIds)) {
                $query->where('id', '<', 0); // Always empty if no active banks
            } else {
                $query->whereIn('bank_account_id', $activeAccountIds);
            }
        }

        // 2. Search Text
        if (!empty($this->searchTx)) {
            $query->where(function($q) {
                $q->where('purpose', 'like', '%' . $this->searchTx . '%')
                  ->orWhere('counterpart_name', 'like', '%' . $this->searchTx . '%')
                  ->orWhere('counterpart_iban', 'like', '%' . $this->searchTx . '%');
            });
        }

        // 3. Category
        if ($this->filterCategoryId === 'unassigned') {
            $query->whereNull('finance_category_id');
        } elseif (!empty($this->filterCategoryId)) {
            $query->where('finance_category_id', $this->filterCategoryId);
        }

        // 4. Amount Type
        if ($this->filterType === 'income') {
            $query->where('amount', '>', 0);
        } elseif ($this->filterType === 'expense') {
            $query->where('amount', '<', 0);
        }

        // 5. Date Range
        if (!empty($this->dateFrom)) {
            $query->whereDate('transaction_date', '>=', $this->dateFrom);
        }
        if (!empty($this->dateTo)) {
            $query->whereDate('transaction_date', '<=', $this->dateTo);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')
                              ->orderBy('id', 'desc')
                              ->paginate(50);

        return view('livewire.shop.financial.financial-bank', [
            'paginatedTransactions' => $transactions
        ]);
    }
}
