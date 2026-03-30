<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Services\BankApiService;
use App\Models\Accounting\AccountingBankAccount;
use App\Models\Accounting\AccountingCategory;
use App\Models\Accounting\AccountingBankTransaction;

#[Layout('components.layouts.backend_layout')]
class AccountingBank extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Buchhaltung';

    use WithPagination, WithFileUploads;

    public $bankAccounts = [];
    public $availableCategories = [];
    public $availableCostItems = [];
    public $selectedAccountId = null;
    public $isLoading = false;
    public $availableAgents = [];
    public $selectedAgentId = '';

    // Filter Properties
    public $searchTx = '';
    public $filterCategoryId = ''; 
    public $filterType = ''; // 'income', 'expense'
    public $filterAssignedType = '';
    public $dateFrom = '';
    public $dateTo = '';

    // Receipt Upload Properties
    public $quickUploadFile;
    public ?string $uploadingBankTxId = null;

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
    public function updatingFilterAssignedType() { $this->resetPage(); }
    public function updatingDateFrom() { $this->resetPage(); }
    public function updatingDateTo() { $this->resetPage(); }

    public function loadBankAccounts()
    {
        $adminId = auth('admin')->id();

        $this->bankAccounts = AccountingBankAccount::where('admin_id', $adminId)
            ->orderBy('bank_name')
            ->get()
            ->toArray();
            
        $this->availableCategories = AccountingCategory::where('admin_id', $adminId)->orderBy('name')->get()->toArray();

        $this->availableCostItems = \App\Models\Accounting\AccountingCostItem::whereHas('group', function ($q) use ($adminId) {
            $q->where('admin_id', $adminId);
        })->orderBy('name')->get()->toArray();

        $dept = \App\Models\Ai\AiDepartment::where('name', $this->themingDepartment)->first();
        if ($dept) {
            $this->availableAgents = \App\Models\Ai\AiAgent::where('is_active', true)
                ->where('ai_department_id', $dept->id)
                ->with('role')
                ->orderBy('name')
                ->get()
                ->toArray();
        } else {
            $this->availableAgents = [];
        }
    }

    public function selectBank($accountId)
    {
        $this->selectedAccountId = ($this->selectedAccountId == $accountId) ? null : $accountId;
        $this->resetPage();
    }

    public function toggleBankActive($accountId)
    {
        $account = AccountingBankAccount::find($accountId);
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
        $account = AccountingBankAccount::find($accountId);
        if ($account && $account->admin_id === auth('admin')->id()) {
            $newStatus = !$account->is_business;
            
            $account->update([
                'is_business' => $newStatus
            ]);

            // Sync all underlying transactions to inherit the new account status natively.
            AccountingBankTransaction::where('accounting_bank_account_id', $account->id)
                ->update(['is_business' => null]);

            $this->loadBankAccounts();
            
            session()->flash('success', 'Konto-Art aktualisiert & Umsätze synchronisiert.');
        }
    }

    public function resetFilters()
    {
        $this->reset(['searchTx', 'filterCategoryId', 'filterType', 'filterAssignedType', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function updatedQuickUploadFile()
    {
        if($this->uploadingBankTxId && $this->quickUploadFile) {
            $tx = AccountingBankTransaction::find($this->uploadingBankTxId);
            if($tx && $tx->account->admin_id === auth('admin')->id()) {
                $path = $this->quickUploadFile->store('financial/receipts', 'public');

                $files = is_string($tx->file_paths) ? json_decode($tx->file_paths, true) : $tx->file_paths;
                if (!is_array($files)) {
                    $files = [];
                }
                $files[] = $path;

                $tx->update(['file_paths' => $files]);
                session()->flash('success', 'Beleg hochgeladen.');
            }
            $this->reset(['quickUploadFile', 'uploadingBankTxId']);
        }
    }

    public function deleteReceipt($transactionId, $fileIndex)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        if ($tx && $tx->account->admin_id === auth('admin')->id()) {
            $files = is_string($tx->file_paths) ? json_decode($tx->file_paths, true) : $tx->file_paths;
            if (isset($files[$fileIndex])) {
                $path = $files[$fileIndex];
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($path);
                }
                unset($files[$fileIndex]);
                $tx->update(['file_paths' => array_values($files)]);
                session()->flash('success', 'Beleg gelöscht.');
            }
        }
    }

    public function assignCategory($transactionId, $categoryId)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        if ($tx) {
            $value = empty($categoryId) ? null : $categoryId;
            $tx->update([
                'accounting_category_id' => $value,
                'accounting_cost_item_id' => null, // Mutually exclusive
                'assigned_by_type' => 'admin',
                'assigned_by_name' => 'Admin'
            ]);
            
            // Fix: System lernt von manueller Zuweisung!
            if ($value) {
                $this->applyCategorizationRule($tx, 'accounting_category_id', $value, true);
            }
        }
    }

    public function assignCostItem($transactionId, $costItemId)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        if ($tx) {
            $value = empty($costItemId) ? null : $costItemId;
            $tx->update([
                'accounting_cost_item_id' => $value,
                'accounting_category_id' => null, // Mutually exclusive
                'assigned_by_type' => 'admin',
                'assigned_by_name' => 'Admin'
            ]);
            
            // Fix: System lernt von manueller Zuweisung!
            if ($value) {
                $this->applyCategorizationRule($tx, 'accounting_cost_item_id', $value, true);
            }
        }
    }

    public function toggleBusinessStatus($transactionId)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        if ($tx) {
            // Null coalescing to inherit from parent BankAccount if previously unset
            $currentStatus = $tx->is_business ?? $tx->account->is_business;
            $tx->update(['is_business' => !$currentStatus]);
        }
    }

    public function addTag($transactionId, $tag)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        $cleanTag = trim(strtolower($tag));
        
        if ($tx && !empty($cleanTag)) {
            $tags = is_array($tx->tags) ? $tx->tags : [];
            if (!in_array($cleanTag, $tags)) {
                $tags[] = $cleanTag;
                $tx->update(['tags' => $tags]);
            }
        }
    }

    public function removeTag($transactionId, $tag)
    {
        $tx = AccountingBankTransaction::find($transactionId);
        if ($tx && is_array($tx->tags)) {
            $tags = array_filter($tx->tags, fn($t) => strcasecmp($t, $tag) !== 0);
            $tx->update(['tags' => array_values($tags)]);
        }
    }

    private function applyCategorizationRule(\App\Models\Accounting\AccountingBankTransaction $tx, $field, $value, $autoAssign = true)
    {
        if ($value !== null) {
            $adminId = auth('admin')->id();
            
            $nameRaw = $tx->counterpart_name ?? '';
            $purposeRaw = $tx->purpose ?? '';

            // 1. Intelligent Search Term Extraction
            // If the counterpart is a generic aggregator, relying on the name alone is disastrous 
            // (e.g. all "PayPal" purchases would become the same category).
            $aggregators = ['paypal', 'klarna', 'stripe', 'sumup', 'mollie', 'amazon', 'stadt', 'gemeinde', 'finanzamt', 'bundeskasse'];
            
            $isAggregator = false;
            foreach ($aggregators as $agg) {
                if (stripos($nameRaw, $agg) !== false) {
                    $isAggregator = true;
                    break;
                }
            }

            if ($isAggregator || empty($nameRaw)) {
                $rawTerm = $purposeRaw;
            } else {
                $rawTerm = $nameRaw;
            }

            // Fallback if purpose is also empty
            if (empty(trim($rawTerm))) {
                $rawTerm = $nameRaw . ' ' . $purposeRaw;
            }
            
            // 2. Clean up term (Remove generic reference numbers, dates, IBANs, but keep meaningful words)
            // a) Remove Quarters (Q1, Q2, q3, q4)
            $searchTerm = preg_replace('/\bQ[1-4]\b/i', '', $rawTerm);
            
            // b) Remove numerical sequences connected by dots/dashes/slashes (Dates: 03/2026, Kassenzeichen: 44.123.456, IBAN fragments)
            $searchTerm = preg_replace('/\b[\d\.\-\/]{3,}\b/', '', $searchTerm);
            
            // c) Remove mixed alphanumeric reference codes (like '8A72B28B') that are 6+ chars long, to prevent recurring monthly invoice ID mismatches
            $searchTerm = preg_replace('/\b(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d\.\-\/]{6,}\b/', '', $searchTerm);

            // d) Strip all leftover symbols except basic alphabet and spaces
            $searchTerm = preg_replace('/[^\p{L}\s]/u', ' ', $searchTerm);
            
            // e) Collapse multiple spaces
            $searchTerm = trim(preg_replace('/\s+/', ' ', $searchTerm));
            if (strlen($searchTerm) < 3) {
                $searchTerm = rtrim(mb_substr($rawTerm, 0, 50));
            } else {
                $searchTerm = rtrim(mb_substr($searchTerm, 0, 50));
            }

            $amountType = ($field === 'accounting_cost_item_id') ? 'fix' : 'variable';

            // 4. Save to Engine
            $existing = \App\Models\Accounting\AccountingCategorizationRule::where('admin_id', $adminId)
                ->where('search_term', $searchTerm)
                ->first();

            if (!$existing) {
                \App\Models\Accounting\AccountingCategorizationRule::create([
                    'admin_id' => $adminId,
                    'search_term' => $searchTerm,
                    $field => $value,
                    'amount_type' => $amountType,
                    'priority' => 0
                ]);
            } else {
                $existing->update([
                    $field => $value,
                    'accounting_category_id' => ($field === 'accounting_category_id') ? $value : null,
                    'accounting_cost_item_id' => ($field === 'accounting_cost_item_id') ? $value : null,
                    'amount_type' => $amountType
                ]);
            }

            // 5. Apply instantly via Rule Engine instead of direct string match
            if ($autoAssign) {
                $this->autoAssignTransactions();
            }
        }
    }

    public function autoAssignTransactions($assignedByType = 'system', $assignedByName = 'Schablonen-Engine', $silent = false)
    {
        $adminId = auth('admin')->id();
        $rules = \App\Models\Accounting\AccountingCategorizationRule::where('admin_id', $adminId)
            ->orderBy('priority', 'desc')
            ->get();

        $unassignedTxs = AccountingBankTransaction::whereHas('account', function($q) use ($adminId) {
                $q->where('admin_id', $adminId);
            })
            ->whereNull('accounting_category_id')
            ->whereNull('accounting_cost_item_id')
            ->get();

        $assignedCount = 0;

        foreach ($unassignedTxs as $tx) {
            foreach ($rules as $rule) {
                if ($rule->matches($tx)) {
                    if ($rule->accounting_cost_item_id) {
                        $tx->update([
                            'accounting_cost_item_id' => $rule->accounting_cost_item_id,
                            'assigned_by_type' => $assignedByType,
                            'assigned_by_name' => $assignedByName
                        ]);
                        $assignedCount++;
                        break;
                    } elseif ($rule->accounting_category_id) {
                        $tx->update([
                            'accounting_category_id' => $rule->accounting_category_id,
                            'assigned_by_type' => $assignedByType,
                            'assigned_by_name' => $assignedByName
                        ]);
                        $assignedCount++;
                        break;
                    }
                }
            }
        }

        if (!$silent) {
            session()->flash('success', "{$assignedCount} Umsätze wurden automatisch nach Regeln zugeordnet.");
            $this->resetPage();
        }

        return $assignedCount;
    }

    public function startAgentSorting()
    {
        if (empty($this->selectedAgentId)) {
            if (!empty($this->availableAgents)) {
                $this->selectedAgentId = $this->availableAgents[0]['id'];
            } else {
                session()->flash('error', 'Bitte wähle zuerst einen KI-Agenten aus.');
                return;
            }
        }

        $this->isLoading = true;
        
        $agent = \App\Models\Ai\AiAgent::find($this->selectedAgentId);
        if (!$agent) {
            $this->isLoading = false;
            session()->flash('error', 'Agent nicht gefunden.');
            return;
        }

        $adminId = auth('admin')->id();

        // 1. Fetch ALL possible unassigned transactions
        $allTxs = AccountingBankTransaction::whereHas('account', function($q) use ($adminId) {
                $q->where('admin_id', $adminId);
            })
            ->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('accounting_category_id')->whereNull('accounting_cost_item_id');
                })->orWhere('assigned_by_type', 'agent');
            })
            ->where(function($q) {
                $q->whereNull('assigned_by_type')->orWhere('assigned_by_type', '!=', 'admin');
            })
            ->get();

        if ($allTxs->isEmpty()) {
            $this->isLoading = false;
            session()->flash('success', 'Keine unzugeordneten Umsätze gefunden.');
            return;
        }

        // 2. Group by unique texts to drastically save LLM tokens
        $uniqueTxs = collect();
        foreach ($allTxs as $tx) {
            // Create a pseudo-unique hash based on counterpart and purpose
            $key = md5(trim($tx->counterpart_name . mb_substr($tx->purpose, 0, 30)));
            if (!$uniqueTxs->has($key)) {
                $uniqueTxs->put($key, $tx);
                if ($uniqueTxs->count() >= 40) {
                    break; // Max 40 unique patterns per LLM call
                }
            }
        }

        // 3. Prepare Context (Categories and CostItems)
        $cats = collect($this->availableCategories)->map(fn($c) => ['id' => $c['id'], 'name' => $c['name']])->toJson();
        $costs = collect($this->availableCostItems)->map(fn($c) => ['id' => $c['id'], 'name' => $c['name']])->toJson();

        // 4. Prepare Transactions Context (only sending the grouped unique ones)
        $txData = $uniqueTxs->values()->map(function ($tx) {
            return [
                'tx_id' => $tx->id,
                'counterpart_name' => $tx->counterpart_name,
                'purpose' => mb_substr($tx->purpose ?? '', 0, 50),
                'amount' => $tx->amount,
                'type' => $tx->amount < 0 ? 'expense' : 'income'
            ];
        })->toJson();

        // 5. Construct Prompt
        $prompt = "Du bist ein intelligenter Finanz-Buchhalter.\n";
        $prompt .= "Deine Aufgabe: Ordne die folgenden Banktransaktionen bestmöglich in Fixkosten oder Variable Kosten (Kategorien) ein.\n\n";
        $prompt .= "Vorgaben:\n";
        $prompt .= "1. Eine Transaktion DARF ENTWEDER ein 'cost_item_id' (Fixkosten) ODER ein 'product_category_id' (Variables) erhalten. NICHT BEIDES gleichzeitg.\n";
        $prompt .= "2. Wenn du absolut nicht sicher bist, weise NULL für beides zu.\n";
        $prompt .= "3. Fixkosten (cost_item_id) sind Verträge wie Miete, Strom, Versicherungen, Software-Abos (Netflix, Adobe), Gehälter, Steuern.\n";
        $prompt .= "4. Variable Kategorien (category_id) sind Einkäufe, Tanken, Restaurants, Drogerie, etc.\n";
        $prompt .= "5. DU MUSST ZWINGEND DEINE ANTWORT ALS REINES JSON-ARRAY FORMATIEREN! KEIN TEXT DAVOR ODER DANACH! KEIN MARKDOWN!\n";
        $prompt .= "6. DU MUSST FÜR JEDE EINZELNE VERFÜGBARE TRANSAKTION IN DER EINGABE EIN MAPPING ZURÜCKGEBEN (Auch wenn category_id/cost_item_id null sind)!\n\n";
        
        $prompt .= "Verfügbare FIXKOSTEN:\n$costs\n\n";
        $prompt .= "Verfügbare VARIABLE KATEGORIEN:\n$cats\n\n";
        $prompt .= "ZU ZUORDNENDE TRANSAKTIONEN:\n$txData\n\n";
        
        $prompt .= "Beispiel (Format zwingend einhalten!):\n";
        $prompt .= '{"mappings": [{"tx_id": "uuid-here", "cost_item_id": "uuid-here", "category_id": null}, {"tx_id": "uuid-here2", "cost_item_id": null, "category_id": "uuid-here3"}]}';

        // 6. Query LLM
        try {
            $response = \Illuminate\Support\Facades\Http::withToken(config('services.mittwald.key'))
                ->timeout(60)
                ->post(config('services.mittwald.url') . '/chat/completions', [
                    'model' => $agent->model ?? 'gpt-oss-120b',
                    'messages' => [
                        ['role' => 'system', 'content' => $agent->system_prompt],
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.1, // Very low temperature for structured data
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';
                
                // Cleanup potential markdown blocks in answer
                $content = preg_replace('/```json/i', '', $content);
                $content = preg_replace('/```/i', '', $content);
                $content = trim($content);

                $json = json_decode($content, true);

                if (json_last_error() === JSON_ERROR_NONE && isset($json['mappings'])) {
                    $learnedRules = 0;
                    foreach ($json['mappings'] as $mapping) {
                        $currentTx = $uniqueTxs->values()->firstWhere('id', $mapping['tx_id'] ?? null);
                        if ($currentTx) {
                            $catId = empty($mapping['product_category_id']) ? null : $mapping['product_category_id'];
                            $costId = empty($mapping['cost_item_id']) ? null : $mapping['cost_item_id'];
                            
                            // Prevent array syntax passing logic errors
                            if ($catId !== null && $costId !== null) {
                                $catId = null; // Prefer cost if both set illegally
                            }

                            if ($catId !== null || $costId !== null) {
                                // Update this single representative TX manually to ensure the engine learns it
                                $currentTx->update([
                                    'accounting_category_id' => $catId,
                                    'accounting_cost_item_id' => $costId,
                                    'assigned_by_type' => 'agent',
                                    'assigned_by_name' => 'Agent: ' . $agent->name
                                ]);
                                
                                // Feed it back into the rule engine to learn (pass false to skip N+1 autoAssign)
                                if ($catId) $this->applyCategorizationRule($currentTx, 'accounting_category_id', $catId, false);
                                if ($costId) $this->applyCategorizationRule($currentTx, 'accounting_cost_item_id', $costId, false);
                                
                                $learnedRules++;
                            }
                        }
                    }

                    if ($learnedRules > 0) {
                        // Now execute the Rule Engine globally on all remaining transactions
                        $this->autoAssignTransactions('agent', 'Agent: ' . $agent->name);
                        
                        // Overwrite the standard success message to highlight the Agent's contribution
                        $totalAssignedMessage = session()->get('success', ''); 
                        session()->flash('success', "Der Agent {$agent->name} hat {$learnedRules} neue Schablonen entwickelt. {$totalAssignedMessage}");
                    } else {
                        session()->flash('success', "Der Agent {$agent->name} hat den Batch geprüft, aber keine sicheren Zuweisungen gefunden.");
                    }
                } else {
                    \Illuminate\Support\Facades\Log::error("JSON Decoding Error during Agent Sorting: $content");
                    session()->flash('error', "Der Agent hat in einem ungültigen Format geantwortet. Versuche es gleich noch einmal.");
                }
            } else {
                session()->flash('error', 'API Verbindungsfehler zum LLM: ' . $response->status());
            }
        } catch (\Exception $e) {
            if(app()->environment('testing')) throw $e;
            session()->flash('error', 'Fehler während der KI-Verarbeitung: ' . $e->getMessage());
        }

        $this->isLoading = false;
        $this->resetPage();
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
                AccountingBankAccount::updateOrCreate(
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
                $bankAccount = AccountingBankAccount::updateOrCreate(
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
                    \App\Models\Accounting\AccountingBankTransaction::updateOrCreate(
                        ['finapi_transaction_id' => (string) $apiTx['id']],
                        [
                            'accounting_bank_account_id' => $bankAccount->id,
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
            
            $autoAssigned = $this->autoAssignTransactions('system', 'Schablonen-Engine', true);
            $msgAuto = $autoAssigned > 0 ? " ({$autoAssigned} automatisch sortiert)" : "";
            
            session()->flash('success', 'Alle Konten und Umsätze manuell aus finAPI aktualisiert!' . $msgAuto);
            $this->loadBankAccounts();
        } catch (\Exception $e) {
            if(app()->environment('testing')) throw $e;
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
            $account = AccountingBankAccount::find($accountId);
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
                            \App\Models\Accounting\AccountingBankTransaction::updateOrCreate(
                                ['finapi_transaction_id' => (string) $apiTx['id']],
                                [
                                    'accounting_bank_account_id' => $account->id,
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
                
                $autoAssigned = $this->autoAssignTransactions('system', 'Schablonen-Engine', true);
                $msgAuto = $autoAssigned > 0 ? " ({$autoAssigned} automatisch sortiert)" : "";
                
                session()->flash('success', 'Kontostand und Umsätze erfolgreich synchronisiert!' . $msgAuto);
                $this->loadBankAccounts();
            }
        } catch (\Exception $e) {
            if(app()->environment('testing')) throw $e;
            session()->flash('error', 'Synchronisierung fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }
    
    public function disconnectAccount($accountId) 
    {
        $account = AccountingBankAccount::find($accountId);
        if ($account) {
            $account->delete();
            $this->loadBankAccounts();
            session()->flash('success', 'Konto lokal getrennt!');
        }
    }

    public function render()
    {
        $query = AccountingBankTransaction::query();

        // 1. Bank Account Filter
        if ($this->selectedAccountId) {
            $query->where('accounting_bank_account_id', $this->selectedAccountId);
        } else {
            // ONLY LOAD ACTIVE BANKS FOR ANALYSIS SUMMARY
            $activeAccountIds = array_column(
                array_filter($this->bankAccounts, fn($acc) => $acc['is_active_for_analysis']),
                'id'
            );
            
            if (empty($activeAccountIds)) {
                $query->where('id', '<', 0); // Always empty if no active banks
            } else {
                $query->whereIn('accounting_bank_account_id', $activeAccountIds);
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
            $query->whereNull('accounting_category_id');
        } elseif (!empty($this->filterCategoryId)) {
            $query->where('accounting_category_id', $this->filterCategoryId);
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

        // --- CALC KPI STATS BEFORE ASSIGNMENT FILTERING ---
        $kpiQuery = clone $query;
        $kpiStats = [
            'admin' => (clone $kpiQuery)->where('assigned_by_type', 'admin')->count(),
            'agent' => (clone $kpiQuery)->where(function($q) {
                $q->where('assigned_by_type', 'agent')->orWhere('assigned_by_type', 'system');
            })->count(),
            'unassigned' => (clone $kpiQuery)->whereNull('assigned_by_type')->count(),
        ];

        // 6. Assigned Type (KPI Filtering) - applied to the ACTUAL list query
        if ($this->filterAssignedType === 'admin') {
            $query->where('assigned_by_type', 'admin');
        } elseif ($this->filterAssignedType === 'agent') {
            $query->where(function($q) {
                $q->where('assigned_by_type', 'agent')
                  ->orWhere('assigned_by_type', 'system');
            });
        } elseif ($this->filterAssignedType === 'unassigned') {
            $query->whereNull('assigned_by_type');
        }

        $transactions = $query->with('account')
                              ->orderBy('transaction_date', 'desc')
                              ->orderBy('id', 'desc')
                              ->paginate(20);

        return view('livewire.shop.accounting.accounting-bank', [
            'paginatedTransactions' => $transactions,
            'kpiStats' => $kpiStats
        ]);
    }
}
