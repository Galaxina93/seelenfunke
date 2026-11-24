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
use App\Models\Accounting\AccountingCostItem;
use App\Models\Accounting\AccountingBankTransaction;

#[Layout('components.layouts.backend_layout')]
class AccountingBank extends Component
{
    use WithDepartmentTheming;

    public string $themingDepartment = 'Buchhaltung';

    use WithPagination, WithFileUploads;

    public $bankAccounts = [];
    public $availableCategories = [];
    public $availableCostItems = [];
    public $selectedAccountId = null;
    public $isLoading = false;
    public $availableAgents = [];
    public $selectedAgentId = '';

    // CSV Import Properties
    public $csvFile;
    public $csvTargetAccountId = '';
    public bool $showCsvModal = false;
    public bool $showAccountModal = false;

    // New Bank Account Modal Properties
    public string $newBankName = 'FYRST';
    public string $newAccountName = 'Geschäftskonto';
    public string $newIban = '';
    public string $newBalance = '0.00';
    public bool $newIsBusiness = true;

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

    public function boot(): void
    {
        if (!auth('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Bankmodul.');
        }
    }

    public function mount(BankApiService $finapi)
    {
        if (!auth('admin')->check()) {
            abort(403, 'Nur Administratoren haben Zugriff auf das Bankmodul.');
        }

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
            ->map(function (AccountingBankAccount $acc) {
                $arr = $acc->toArray();
                $arr['is_manual'] = ($acc->plaid_access_token === 'manual_managed' || $acc->plaid_item_id === 'manual_managed');
                return $arr;
            })
            ->all();
            
        $this->availableCategories = AccountingCategory::where('admin_id', $adminId)->orderBy('name')->get()->toArray();

        $this->availableCostItems = \App\Models\Accounting\AccountingCostItem::whereHas('group', function ($q) use ($adminId) {
            $q->where('admin_id', $adminId);
        })->orderBy('name')->get()->toArray();

        if ((empty($this->csvTargetAccountId) || $this->csvTargetAccountId === 'auto') && !empty($this->bankAccounts)) {
            $this->csvTargetAccountId = (string) $this->bankAccounts[0]['id'];
        } elseif (empty($this->bankAccounts)) {
            $this->csvTargetAccountId = 'auto';
        }

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
        if ($accountId) {
            $owns = AccountingBankAccount::where('admin_id', auth('admin')->id())->where('id', $accountId)->exists();
            if (!$owns) {
                return;
            }
        }
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
            $this->validate(['quickUploadFile' => 'file|mimes:pdf,jpg,jpeg,png,webp,zip,docx,txt,xml|max:15360']);
            $ext = strtolower((string) $this->quickUploadFile->getClientOriginalExtension());
            $allowedMap = [
                'pdf' => ['application/pdf'],
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'webp' => ['image/webp'],
                'zip' => ['application/zip', 'application/x-zip-compressed'],
                'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                'txt' => ['text/plain'],
                'xml' => ['text/xml', 'application/xml', 'text/plain'],
            ];
            $realPath = $this->quickUploadFile->getRealPath();
            $finfo = $realPath ? new \finfo(FILEINFO_MIME_TYPE) : null;
            $detectedMime = ($finfo && $realPath && is_file($realPath)) ? $finfo->file($realPath) : null;
            if (!isset($allowedMap[$ext]) || !in_array($detectedMime, $allowedMap[$ext], true)) {
                $this->reset(['quickUploadFile', 'uploadingBankTxId']);
                $this->addError('quickUploadFile', 'Ungültiger Dateityp.');
                return;
            }

            $tx = AccountingBankTransaction::find($this->uploadingBankTxId);
            if($tx && $tx->account && $tx->account->admin_id === auth('admin')->id()) {
                $path = $this->quickUploadFile->storeAs('buchhaltung/receipts', \Illuminate\Support\Str::uuid() . '.' . $ext, 'local');

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
                if (is_string($path) && !str_contains($path, '..') && !str_contains($path, "\0") && str_starts_with($path, 'buchhaltung/')) {
                    if (\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
                        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
                    }
                }
                unset($files[$fileIndex]);
                $tx->update(['file_paths' => array_values($files)]);
                session()->flash('success', 'Beleg gelöscht.');
            }
        }
    }

    public function assignCategory($transactionId, $categoryId)
    {
        $adminId = auth('admin')->id();
        $tx = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))->find($transactionId);
        if ($tx) {
            $value = empty($categoryId) ? null : $categoryId;
            if ($value !== null && !AccountingCategory::where('admin_id', $adminId)->where('id', $value)->exists()) {
                return;
            }
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
        $adminId = auth('admin')->id();
        $tx = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))->find($transactionId);
        if ($tx) {
            $value = empty($costItemId) ? null : $costItemId;
            if ($value !== null && !AccountingCostItem::whereHas('group', fn($q) => $q->where('admin_id', $adminId))->where('id', $value)->exists()) {
                return;
            }
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
        $adminId = auth('admin')->id();
        $tx = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))->find($transactionId);
        if ($tx) {
            // Null coalescing to inherit from parent BankAccount if previously unset
            $currentStatus = $tx->is_business ?? $tx->account->is_business;
            $tx->update(['is_business' => !$currentStatus]);
        }
    }

    public function addTag($transactionId, $tag)
    {
        $adminId = auth('admin')->id();
        $tx = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))->find($transactionId);
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
        $adminId = auth('admin')->id();
        $tx = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId))->find($transactionId);
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
        $prompt .= "1. Eine Transaktion DARF ENTWEDER ein 'cost_item_id' (Fixkosten) ODER ein 'category_id' (Variables) erhalten. NICHT BEIDES gleichzeitg.\n";
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
            $openAi = \App\Services\AI\VertexAiService::resolveOpenAiEndpoint();
            $apiUrl = $openAi['base_url'];
            $apiKey = $openAi['token'];
            $agentModelName = \App\Services\AI\VertexAiService::formatOpenAiModel(
                strtolower($agent->model ?? 'gemini-3.5-flash'),
                $openAi['is_vertex']
            );

            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->timeout(60)
                ->post(rtrim($apiUrl, '/') . '/chat/completions', [
                    'model' => $agentModelName,
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
                $mappings = $json['mappings'] ?? (is_array($json) ? $json : null);

                if (json_last_error() === JSON_ERROR_NONE && is_array($mappings)) {
                    $learnedRules = 0;
                    $allowedCatIds = collect($this->availableCategories)->pluck('id')->map(fn($id) => (string) $id)->all();
                    $allowedCostIds = collect($this->availableCostItems)->pluck('id')->map(fn($id) => (string) $id)->all();
                    foreach ($mappings as $key => $mapping) {
                        $txId = is_array($mapping) ? ($mapping['tx_id'] ?? (is_string($key) ? $key : null)) : (is_string($key) ? $key : null);
                        $currentTx = $uniqueTxs->values()->firstWhere('id', $txId);
                        if ($currentTx) {
                            if (is_array($mapping)) {
                                $rawCatId = $mapping['category_id'] ?? $mapping['product_category_id'] ?? null;
                                $rawCostId = $mapping['cost_item_id'] ?? null;
                            } elseif (is_string($mapping) || is_int($mapping)) {
                                $rawCatId = in_array((string) $mapping, $allowedCatIds, true) ? (string) $mapping : null;
                                $rawCostId = ($rawCatId === null && in_array((string) $mapping, $allowedCostIds, true)) ? (string) $mapping : null;
                            } else {
                                $rawCatId = null;
                                $rawCostId = null;
                            }
                            $catId = (!empty($rawCatId) && in_array((string) $rawCatId, $allowedCatIds, true)) ? $rawCatId : null;
                            $costId = (!empty($rawCostId) && in_array((string) $rawCostId, $allowedCostIds, true)) ? $rawCostId : null;
                            
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
            if (!str_starts_with($redirectUrl, url('/'))) {
                $redirectUrl = url()->current();
            }
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
                    [
                        'admin_id' => $adminId,
                        'plaid_account_id' => (string) $acc['id'],
                    ],
                    [
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

    public function openCsvModal($accountId = null)
    {
        if ($accountId) {
            $this->csvTargetAccountId = (string) $accountId;
        } elseif (!empty($this->bankAccounts)) {
            if (empty($this->csvTargetAccountId) || $this->csvTargetAccountId === 'auto') {
                $this->csvTargetAccountId = (string) $this->bankAccounts[0]['id'];
            }
        } else {
            $this->csvTargetAccountId = 'auto';
        }
        $this->resetValidation();
        $this->reset('csvFile');
        $this->showCsvModal = true;
    }

    public function closeCsvModal()
    {
        $this->reset('csvFile');
        $this->showCsvModal = false;
    }

    public function openAccountModal()
    {
        $this->reset(['newBankName', 'newAccountName', 'newIban', 'newBalance', 'newIsBusiness']);
        $this->newBankName = 'FYRST';
        $this->newAccountName = 'Geschäftskonto';
        $this->newBalance = '0.00';
        $this->newIsBusiness = true;
        $this->showAccountModal = true;
    }

    public function closeAccountModal()
    {
        $this->showAccountModal = false;
    }

    public function createManualAccount()
    {
        $this->validate([
            'newBankName' => 'required|string|max:100',
            'newAccountName' => 'required|string|max:100',
            'newIban' => 'nullable|string|max:34',
            'newBalance' => 'nullable|numeric',
        ]);

        $adminId = auth('admin')->id();
        $cleanIban = $this->newIban ? strtoupper(str_replace(' ', '', $this->newIban)) : null;
        $uniqueId = 'manual_' . ($cleanIban ?: uniqid());

        $account = AccountingBankAccount::create([
            'admin_id' => $adminId,
            'plaid_item_id' => 'manual_managed',
            'plaid_access_token' => 'manual_managed',
            'plaid_account_id' => $uniqueId,
            'bank_name' => trim($this->newBankName),
            'account_name' => trim($this->newAccountName),
            'iban' => $cleanIban,
            'balance' => (float) ($this->newBalance ?: 0),
            'currency' => 'EUR',
            'is_business' => $this->newIsBusiness,
            'is_active_for_analysis' => true,
            'last_synced_at' => now(),
        ]);

        $this->loadBankAccounts();
        $this->csvTargetAccountId = $account->id;
        $this->selectedAccountId = $account->id;
        $this->showAccountModal = false;

        session()->flash('success', "Bankkonto '{$account->bank_name} - {$account->account_name}' erfolgreich angelegt!");
    }

    private function normalizeCsvHeader(string $value): string
    {
        $mapped = strtr(trim($value), [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue',
            'ß' => 'ss',
        ]);
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($mapped)) ?? '';
    }

    private function parseCsvDate(?string $rawDate): ?\Carbon\Carbon
    {
        $rawDate = trim((string) $rawDate);
        if ($rawDate === '') {
            return null;
        }

        try {
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $rawDate, $m)) {
                return \Carbon\Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
            }
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2})$/', $rawDate, $m)) {
                $year = (int) $m[3] < 50 ? 2000 + (int) $m[3] : 1900 + (int) $m[3];
                return \Carbon\Carbon::createFromDate($year, (int) $m[2], (int) $m[1])->startOfDay();
            }
            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $rawDate, $m)) {
                return \Carbon\Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3])->startOfDay();
            }
            if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', $rawDate)) {
                return \Carbon\Carbon::parse($rawDate)->startOfDay();
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }

    private function parseCsvAmount(?string $rawAmount, bool $isGermanFormat = true): ?float
    {
        $clean = str_replace(["\xC2\xA0", '€', 'EUR', 'USD', 'CHF', ' '], '', trim((string) $rawAmount));
        if ($clean === '' || !preg_match('/\d/', $clean)) {
            return null;
        }

        $isDebit = str_ends_with(strtoupper($clean), 'S') || str_starts_with($clean, '-') || str_ends_with($clean, '-');
        $isCredit = str_ends_with(strtoupper($clean), 'H') || str_starts_with($clean, '+');
        $clean = trim($clean, "+-SHsh \t");

        if ($clean === '' || !preg_match('/^[\d.,]+$/', $clean)) {
            return null;
        }

        if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
            $lastComma = strrpos($clean, ',');
            $lastDot = strrpos($clean, '.');
            if ($lastComma > $lastDot) {
                // German: 1.234,56
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // English: 1,234.56
                $clean = str_replace(',', '', $clean);
            }
        } elseif (strpos($clean, ',') !== false) {
            if (!$isGermanFormat && preg_match('/^\d{1,3}(,\d{3})+$/', $clean)) {
                $clean = str_replace(',', '', $clean);
            } else {
                $clean = str_replace(',', '.', $clean);
            }
        } elseif (strpos($clean, '.') !== false) {
            // FYRST / Deutsche Bank omits ",00" on whole-Euro thousands: "-1.500" = -1500.00 EUR
            if ($isGermanFormat && preg_match('/^\d{1,3}(\.\d{3})+$/', $clean)) {
                $clean = str_replace('.', '', $clean);
            }
        }

        if (!is_numeric($clean)) {
            return null;
        }

        $value = (float) $clean;
        if ($isDebit) {
            return $value != 0.0 ? -abs($value) : 0.0;
        }
        if ($isCredit) {
            return abs($value);
        }

        return $value;
    }

    private function extractCsvMetadata(array $lines, string $delimiter, string $originalFilename, bool $isGermanFormat): array
    {
        $meta = [
            'bank_name' => null,
            'account_name' => null,
            'iban' => null,
            'currency' => 'EUR',
            'opening_balance' => null,
            'closing_balance' => null,
        ];

        $lowerFilename = mb_strtolower($originalFilename);
        if (str_contains($lowerFilename, 'fyrst') || preg_match('/kontoumsaetze_370_/i', $originalFilename)) {
            $meta['bank_name'] = 'FYRST';
        } elseif (str_contains($lowerFilename, 'sparkasse')) {
            $meta['bank_name'] = 'Sparkasse';
        } elseif (str_contains($lowerFilename, 'postbank')) {
            $meta['bank_name'] = 'Postbank';
        }

        $preambleLimit = min(15, count($lines));
        for ($i = 0; $i < $preambleLimit; $i++) {
            $row = str_getcsv($lines[$i], $delimiter);
            $joinedUpper = mb_strtoupper($lines[$i]);

            if ($meta['bank_name'] === null) {
                if (str_contains($joinedUpper, 'FYRST')) {
                    $meta['bank_name'] = 'FYRST';
                } elseif (str_contains($joinedUpper, 'DEUTSCHE BANK')) {
                    $meta['bank_name'] = 'Deutsche Bank';
                } elseif (str_contains($joinedUpper, 'POSTBANK')) {
                    $meta['bank_name'] = 'Postbank';
                } elseif (str_contains($joinedUpper, 'SPARKASSE')) {
                    $meta['bank_name'] = 'Sparkasse';
                }
            }

            // FYRST / Deutsche Bank 2-line metadata table: L1=["Konto","Filial-/Kontonummer","IBAN","Währung"], L2=["FYRST BASE",...]
            $normFirst = $this->normalizeCsvHeader($row[0] ?? '');
            if ($normFirst === 'konto' && isset($lines[$i + 1])) {
                $valRow = str_getcsv($lines[$i + 1], $delimiter);
                foreach ($row as $colIdx => $colLabel) {
                    $colKey = $this->normalizeCsvHeader($colLabel);
                    $cellVal = trim((string) ($valRow[$colIdx] ?? ''));
                    if ($cellVal === '') {
                        continue;
                    }
                    if ($colKey === 'konto') {
                        if (stripos($cellVal, 'FYRST') !== false) {
                            $meta['bank_name'] = 'FYRST';
                        }
                        $meta['account_name'] = stripos($cellVal, 'Geschäftskonto') !== false
                            ? $cellVal
                            : 'Geschäftskonto (' . $cellVal . ')';
                    } elseif ($colKey === 'iban') {
                        $cand = strtoupper(preg_replace('/\s+/', '', $cellVal));
                        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $cand)) {
                            $meta['iban'] = $cand;
                        }
                    } elseif ($colKey === 'waehrung' && preg_match('/^[A-Z]{3}$/', strtoupper($cellVal))) {
                        $meta['currency'] = strtoupper($cellVal);
                    }
                }
            }

            // Key-value IBAN fallback in preamble
            if ($meta['iban'] === null) {
                foreach ($row as $cell) {
                    $cand = strtoupper(preg_replace('/\s+/', '', (string) $cell));
                    if (preg_match('/^DE\d{20}$/', $cand) || ($normFirst === 'iban' && preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $cand))) {
                        $meta['iban'] = $cand;
                        break;
                    }
                }
            }

            if (in_array($normFirst, ['letzterkontostand', 'alterkontostand', 'anfangssaldo', 'startsaldo'], true)) {
                $bal = $this->extractBalanceFromSummaryRow($row, $isGermanFormat);
                if ($bal !== null) {
                    $meta['opening_balance'] = $bal;
                }
            } elseif (in_array($normFirst, ['kontostand', 'neuerkontostand', 'aktuellerkontostand', 'endsaldo'], true)) {
                $bal = $this->extractBalanceFromSummaryRow($row, $isGermanFormat);
                if ($bal !== null) {
                    $meta['closing_balance'] = $bal;
                }
            }
        }

        return $meta;
    }

    private function extractBalanceFromSummaryRow(array $row, bool $isGermanFormat): ?float
    {
        // Walk backwards from end of row (skipping currency & dates) to pick the balance cell
        for ($c = count($row) - 1; $c >= 1; $c--) {
            $cell = trim((string) ($row[$c] ?? ''));
            if ($cell === '' || preg_match('/^[A-Z]{3}$/i', $cell)) {
                continue;
            }
            if ($this->parseCsvDate($cell) !== null) {
                continue;
            }
            $parsed = $this->parseCsvAmount($cell, $isGermanFormat);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    public function importCsvTransactions()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $adminId = auth('admin')->id();

        try {
            $realPath = $this->csvFile->getRealPath();
            $originalName = method_exists($this->csvFile, 'getClientOriginalName')
                ? (string) $this->csvFile->getClientOriginalName()
                : '';

            $finfo = $realPath ? new \finfo(FILEINFO_MIME_TYPE) : null;
            $detectedMime = ($finfo && $realPath && is_file($realPath)) ? $finfo->file($realPath) : null;
            $allowedCsvMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel', 'text/x-csv', 'application/octet-stream', 'inode/x-empty'];
            if (!in_array($detectedMime, $allowedCsvMimes, true)) {
                session()->flash('error', 'Ungültiges CSV-Dateiformat.');
                return;
            }

            $rawContent = (string) file_get_contents($realPath);

            // Remove UTF-8 BOM
            $bom = pack('H*', 'EFBBBF');
            $rawContent = preg_replace("/^$bom/", '', $rawContent);

            // Normalize encoding (German banks often export Windows-1252 or ISO-8859-1)
            if (!mb_check_encoding($rawContent, 'UTF-8')) {
                $encoding = mb_detect_encoding($rawContent, ['Windows-1252', 'ISO-8859-1'], true) ?: 'Windows-1252';
                $rawContent = mb_convert_encoding($rawContent, 'UTF-8', $encoding);
            }

            $lines = array_slice(preg_split("/\r\n|\n|\r/", trim($rawContent)), 0, 5000);
            if (count($lines) < 1 || trim($rawContent) === '') {
                session()->flash('error', 'Die CSV-Datei ist leer.');
                return;
            }

            // Auto-detect delimiter
            $sample = implode("\n", array_slice($lines, 0, 20));
            $semicolonCount = substr_count($sample, ';');
            $commaCount = substr_count($sample, ',');
            $tabCount = substr_count($sample, "\t");

            $delimiter = ';';
            if ($commaCount > $semicolonCount && $commaCount > $tabCount) {
                $delimiter = ',';
            } elseif ($tabCount > $semicolonCount && $tabCount > $commaCount) {
                $delimiter = "\t";
            }

            // Detect German number/date format
            $isGermanFormat = ($delimiter === ';')
                || (bool) preg_match('/\b\d{1,2}\.\d{1,2}\.\d{4}\b/', $sample)
                || stripos($sample, 'Buchungstag') !== false
                || stripos($sample, 'Kontostand') !== false;

            $meta = $this->extractCsvMetadata($lines, $delimiter, $originalName, $isGermanFormat);

            // Resolve or auto-create target bank account
            $account = null;
            $autoCreatedAccount = false;

            if (!empty($this->csvTargetAccountId) && $this->csvTargetAccountId !== 'auto') {
                $account = AccountingBankAccount::where('admin_id', $adminId)->find($this->csvTargetAccountId);
            }

            if (!$account && !empty($meta['iban'])) {
                $account = AccountingBankAccount::where('admin_id', $adminId)
                    ->where('iban', $meta['iban'])
                    ->first();
            }

            if (!$account && !empty($meta['bank_name'])) {
                $account = AccountingBankAccount::where('admin_id', $adminId)
                    ->where('bank_name', $meta['bank_name'])
                    ->where(function ($q) {
                        $q->whereNull('iban')->orWhere('iban', '');
                    })
                    ->first();
            }

            if (!$account) {
                $bankName = $meta['bank_name'] ?: 'FYRST';
                $accountName = $meta['account_name'] ?: 'Geschäftskonto';
                $cleanIban = $meta['iban'];
                $initialBalance = $meta['closing_balance'] ?? $meta['opening_balance'] ?? 0.0;

                $account = AccountingBankAccount::create([
                    'admin_id' => $adminId,
                    'plaid_item_id' => 'manual_managed',
                    'plaid_access_token' => 'manual_managed',
                    'plaid_account_id' => 'manual_' . ($cleanIban ?: uniqid()),
                    'bank_name' => $bankName,
                    'account_name' => $accountName,
                    'iban' => $cleanIban,
                    'balance' => $initialBalance,
                    'currency' => $meta['currency'] ?: 'EUR',
                    'is_business' => true,
                    'is_active_for_analysis' => true,
                    'last_synced_at' => now(),
                ]);
                $autoCreatedAccount = true;
            } elseif (empty($account->iban) && !empty($meta['iban'])) {
                $account->iban = $meta['iban'];
                $account->save();
            }

            // Locate Header Row
            $headerIndex = -1;
            $headers = [];
            $hasNoBookingsNotice = stripos($rawContent, 'Keine Umsätze') !== false
                || stripos($rawContent, 'keine gebuchten Umsätze') !== false;

            foreach ($lines as $idx => $line) {
                $row = str_getcsv($line, $delimiter);
                if (count($row) < 2) {
                    continue;
                }

                $normalizedRow = array_map(fn($col) => $this->normalizeCsvHeader((string) $col), $row);

                $hasDate = false;
                $hasAmount = false;

                foreach ($normalizedRow as $colName) {
                    if (in_array($colName, ['buchungstag', 'buchungsdatum', 'datum', 'wertstellung', 'valuta', 'wert', 'date', 'bookingdate'], true)) {
                        $hasDate = true;
                    }
                    if (in_array($colName, ['betrag', 'umsatz', 'betragineur', 'betrageur', 'amount', 'soll', 'haben', 'belastung', 'gutschrift'], true)) {
                        $hasAmount = true;
                    }
                }

                if ($hasDate && $hasAmount) {
                    $headerIndex = $idx;
                    $headers = $row;
                    break;
                }
            }

            if ($headerIndex === -1) {
                if ($hasNoBookingsNotice || ($meta['opening_balance'] !== null || $meta['closing_balance'] !== null)) {
                    $finalBal = $meta['closing_balance'] ?? $meta['opening_balance'];
                    $updates = ['last_synced_at' => now()];
                    if ($finalBal !== null) {
                        $updates['balance'] = $finalBal;
                    }
                    $account->update($updates);
                    $this->selectedAccountId = $account->id;
                    $this->csvTargetAccountId = (string) $account->id;
                    $this->closeCsvModal();
                    $this->loadBankAccounts();
                    session()->flash('success', ($autoCreatedAccount ? "{$account->bank_name} Konto automatisch angelegt. " : '') . 'Aktuell liegen noch keine gebuchten Umsätze im Zeitraum vor.');
                    return;
                }

                session()->flash('error', 'Spaltenüberschriften (Datum, Betrag oder Soll/Haben) konnten in der CSV-Datei nicht erkannt werden.');
                return;
            }

            // Map Column Indices
            $dateCol = null;
            $valueDateCol = null;
            $amountCol = null;
            $sollCol = null;
            $habenCol = null;
            $shFlagCol = null;
            $nameCol = null;
            $altNameCol = null;
            $purposeCol = null;
            $ibanCol = null;
            $typeCol = null;
            $refCol = null;
            $currencyCol = null;

            foreach ($headers as $idx => $headerText) {
                $clean = $this->normalizeCsvHeader((string) $headerText);

                if ($dateCol === null && in_array($clean, ['buchungstag', 'buchungsdatum', 'datum', 'date', 'bookingdate'], true)) {
                    $dateCol = $idx;
                } elseif ($valueDateCol === null && in_array($clean, ['wert', 'wertstellung', 'valuta', 'valuedate'], true)) {
                    $valueDateCol = $idx;
                } elseif ($sollCol === null && in_array($clean, ['soll', 'belastung', 'ausgang', 'debit'], true)) {
                    $sollCol = $idx;
                } elseif ($habenCol === null && in_array($clean, ['haben', 'gutschrift', 'eingang', 'credit'], true)) {
                    $habenCol = $idx;
                } elseif ($amountCol === null && in_array($clean, ['betragineur', 'betrageur', 'umsatz', 'betrag', 'amount'], true)) {
                    $amountCol = $idx;
                } elseif ($shFlagCol === null && in_array($clean, ['sollhaben', 'sollhabenkennung', 'sh', 'umsatzkennzeichen'], true)) {
                    $shFlagCol = $idx;
                } elseif ($nameCol === null && in_array($clean, ['beguenstigterauftraggeber', 'beguenstigterzahlungspflichtiger', 'auftraggeberempfaenger', 'beguenstigter', 'auftraggeber', 'empfaenger', 'zahlungsempfaenger', 'zahlungspflichtiger', 'name', 'counterparty', 'partnername'], true)) {
                    $nameCol = $idx;
                } elseif ($altNameCol === null && in_array($clean, ['abweichenderempfaenger', 'abweichenderauftraggeber'], true)) {
                    $altNameCol = $idx;
                } elseif ($purposeCol === null && in_array($clean, ['verwendungszweck', 'buchungstext', 'umsatztext', 'text', 'zweck', 'details', 'description', 'reference'], true)) {
                    $purposeCol = $idx;
                } elseif ($ibanCol === null && in_array($clean, ['ibankontonummer', 'kontonummeriban', 'iban', 'gegenkonto', 'auftraggeberkonto', 'kontonummer', 'ibanauftraggeber'], true)) {
                    $ibanCol = $idx;
                } elseif ($typeCol === null && in_array($clean, ['umsatzart', 'buchungsart', 'transaktionstyp', 'geschaeftsvorfall', 'buchungstextart', 'type'], true)) {
                    $typeCol = $idx;
                } elseif ($refCol === null && in_array($clean, ['kundenreferenz', 'mandatsreferenz', 'endtoendreferenz'], true)) {
                    $refCol = $idx;
                } elseif ($currencyCol === null && in_array($clean, ['waehrung', 'currency', 'whrg'], true)) {
                    $currencyCol = $idx;
                }
            }

            if ($dateCol === null && $valueDateCol !== null) {
                $dateCol = $valueDateCol;
            }

            if ($dateCol === null || ($amountCol === null && $sollCol === null && $habenCol === null)) {
                session()->flash('error', 'Datum- oder Betragsspalte konnte nicht zugeordnet werden.');
                return;
            }

            $importedCount = 0;
            $skippedDuplicates = 0;
            $importedAmountSum = 0.0;
            $closingBalance = $meta['closing_balance'];
            $seenInCurrentFile = [];

            for ($i = $headerIndex + 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }

                $row = str_getcsv($line, $delimiter);
                $firstCellNorm = $this->normalizeCsvHeader((string) ($row[0] ?? ''));

                // Capture & skip footer summary rows (e.g. FYRST L17: "Kontostand;24.11.2026;;;999,99;EUR")
                if (in_array($firstCellNorm, ['kontostand', 'neuerkontostand', 'aktuellerkontostand', 'endsaldo', 'saldonachbuchung'], true)) {
                    $footerBal = $this->extractBalanceFromSummaryRow($row, $isGermanFormat);
                    if ($footerBal !== null) {
                        $closingBalance = $footerBal;
                    }
                    continue;
                }
                if (in_array($firstCellNorm, ['letzterkontostand', 'alterkontostand', 'anfangssaldo'], true)) {
                    continue;
                }

                $rawDate = trim((string) ($row[$dateCol] ?? ''));
                $parsedDate = $this->parseCsvDate($rawDate);
                if (!$parsedDate && $valueDateCol !== null) {
                    $parsedDate = $this->parseCsvDate($row[$valueDateCol] ?? null);
                }
                if (!$parsedDate) {
                    continue;
                }

                $parsedValueDate = ($valueDateCol !== null ? $this->parseCsvDate($row[$valueDateCol] ?? null) : null) ?: $parsedDate;

                // Resolve amount: prefer authoritative Soll/Haben columns when present (FYRST / Deutsche Bank), fallback to single Betrag column
                $amount = null;
                if ($sollCol !== null || $habenCol !== null) {
                    $rawSoll = $sollCol !== null ? trim((string) ($row[$sollCol] ?? '')) : '';
                    $rawHaben = $habenCol !== null ? trim((string) ($row[$habenCol] ?? '')) : '';

                    if ($rawSoll !== '') {
                        $parsedSoll = $this->parseCsvAmount($rawSoll, $isGermanFormat);
                        if ($parsedSoll !== null) {
                            $amount = $parsedSoll != 0.0 ? -abs($parsedSoll) : 0.0;
                        }
                    } elseif ($rawHaben !== '') {
                        $parsedHaben = $this->parseCsvAmount($rawHaben, $isGermanFormat);
                        if ($parsedHaben !== null) {
                            $amount = abs($parsedHaben);
                        }
                    }
                }

                if ($amount === null && $amountCol !== null) {
                    $rawAmount = trim((string) ($row[$amountCol] ?? ''));
                    $amount = $this->parseCsvAmount($rawAmount, $isGermanFormat);

                    if ($amount !== null && $shFlagCol !== null) {
                        $shFlag = strtoupper(trim((string) ($row[$shFlagCol] ?? '')));
                        if (in_array($shFlag, ['S', 'SOLL', 'D', 'DR'], true)) {
                            $amount = $amount != 0.0 ? -abs($amount) : 0.0;
                        } elseif (in_array($shFlag, ['H', 'HABEN', 'C', 'CR'], true)) {
                            $amount = abs($amount);
                        }
                    }
                }

                if ($amount === null) {
                    continue;
                }

                $name = $nameCol !== null && isset($row[$nameCol]) ? trim((string) $row[$nameCol]) : '';
                if ($name === '' && $altNameCol !== null && isset($row[$altNameCol])) {
                    $name = trim((string) $row[$altNameCol]);
                }

                $purpose = $purposeCol !== null && isset($row[$purposeCol]) ? trim((string) $row[$purposeCol]) : '';
                $type = $typeCol !== null && isset($row[$typeCol]) && trim((string) $row[$typeCol]) !== ''
                    ? trim((string) $row[$typeCol])
                    : 'transfer';
                $customerRef = $refCol !== null && isset($row[$refCol]) ? trim((string) $row[$refCol]) : '';

                if ($purpose === '') {
                    $fallbackParts = array_filter([
                        $type !== 'transfer' ? $type : null,
                        ($customerRef !== '' && strtoupper($customerRef) !== 'NONREF') ? 'Ref: ' . $customerRef : null,
                    ]);
                    $purpose = !empty($fallbackParts) ? implode(' · ', $fallbackParts) : '';
                }

                $iban = $ibanCol !== null && isset($row[$ibanCol]) ? trim((string) $row[$ibanCol]) : '';
                $rowCurrency = ($currencyCol !== null && !empty(trim((string) ($row[$currencyCol] ?? ''))))
                    ? strtoupper(trim((string) $row[$currencyCol]))
                    : ($account->currency ?? 'EUR');

                // Deterministic deduplication hash (supports genuine duplicate transfers on the same day via file occurrence counter)
                $baseSig = implode('|', [
                    $account->id,
                    $parsedDate->toDateString(),
                    number_format($amount, 2, '.', ''),
                    mb_strtolower($name),
                    mb_strtolower($purpose),
                    strtoupper(str_replace(' ', '', $iban)),
                    mb_strtolower($customerRef),
                ]);
                $seenInCurrentFile[$baseSig] = ($seenInCurrentFile[$baseSig] ?? 0) + 1;
                $occ = $seenInCurrentFile[$baseSig];

                $hashKey = 'csv_' . md5($baseSig . '|occ:' . $occ);

                // Also check legacy hash format so previously imported rows are never duplicated
                $legacyHashKey = 'csv_' . md5($account->id . '|' . $parsedDate->toDateString() . '|' . number_format($amount, 2, '.', '') . '|' . $name . '|' . mb_substr($purpose, 0, 40));

                $existing = AccountingBankTransaction::where('accounting_bank_account_id', $account->id)
                    ->whereIn('finapi_transaction_id', $occ === 1 ? [$hashKey, $legacyHashKey] : [$hashKey])
                    ->exists();

                if ($existing) {
                    $skippedDuplicates++;
                    continue;
                }

                AccountingBankTransaction::create([
                    'accounting_bank_account_id' => $account->id,
                    'finapi_transaction_id' => $hashKey,
                    'amount' => $amount,
                    'currency' => $rowCurrency,
                    'is_business' => $account->is_business,
                    'purpose' => $purpose !== '' ? $purpose : null,
                    'counterpart_name' => $name !== '' ? mb_substr($name, 0, 191) : null,
                    'counterpart_iban' => $iban !== '' ? mb_substr($iban, 0, 191) : null,
                    'transaction_date' => $parsedDate,
                    'value_date' => $parsedValueDate,
                    'type' => mb_substr($type, 0, 191),
                    'is_pending' => false,
                    'raw_data' => $row,
                ]);

                $importedCount++;
                $importedAmountSum += $amount;
            }

            $accountUpdates = ['last_synced_at' => now()];
            if ($closingBalance !== null) {
                $accountUpdates['balance'] = $closingBalance;
            } elseif ($autoCreatedAccount && $importedCount > 0 && $meta['opening_balance'] === null) {
                $accountUpdates['balance'] = round($importedAmountSum, 2);
            }
            $account->update($accountUpdates);

            $assignedCount = $this->autoAssignTransactions('system', 'Schablonen-Engine', true);
            $msgCreated = $autoCreatedAccount ? "{$account->bank_name} Konto angelegt · " : "";
            $msgAuto = $assignedCount > 0 ? " ({$assignedCount} automatisch kategorisiert)" : "";
            $msgDup = $skippedDuplicates > 0 ? " ({$skippedDuplicates} Duplikate übersprungen)" : "";

            $this->selectedAccountId = $account->id;
            $this->csvTargetAccountId = (string) $account->id;
            $this->closeCsvModal();
            $this->loadBankAccounts();

            session()->flash('success', "{$msgCreated}{$importedCount} Umsätze erfolgreich importiert!{$msgDup}{$msgAuto}");

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('CSV Bank Import Fehler: ' . $e->getMessage());
            session()->flash('error', 'Fehler beim CSV-Import: ' . $e->getMessage());
        }
    }

    public function syncAllAccounts(?BankApiService $finapi = null)
    {
        $finapi = $finapi ?? app(BankApiService::class);
        $this->isLoading = true;

        try {
            $adminId = auth('admin')->id();

            $hasFinapiAccounts = AccountingBankAccount::where('admin_id', $adminId)
                ->where('plaid_access_token', 'finapi_managed')
                ->exists();

            if ($hasFinapiAccounts) {
                $userToken = $finapi->getUserToken($adminId);
                $accounts = $finapi->getAccounts($userToken);

                foreach ($accounts as $acc) {
                    $bankAccount = AccountingBankAccount::updateOrCreate(
                        [
                            'admin_id' => $adminId,
                            'plaid_account_id' => (string) $acc['id'],
                        ],
                        [
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

                    $txs = $finapi->getTransactions($userToken, $bankAccount->plaid_account_id, 200);
                    foreach ($txs as $apiTx) {
                        AccountingBankTransaction::updateOrCreate(
                            [
                                'accounting_bank_account_id' => $bankAccount->id,
                                'finapi_transaction_id' => (string) $apiTx['id'],
                            ],
                            [
                                'amount' => $apiTx['amount'] ?? 0,
                                'currency' => $bankAccount->currency,
                                'purpose' => $apiTx['purpose'] ?? null,
                                'counterpart_name' => $apiTx['counterpartName'] ?? null,
                                'counterpart_iban' => $apiTx['counterpartIban'] ?? null,
                                'transaction_date' => isset($apiTx['bankBookingDate']) ? \Carbon\Carbon::parse($apiTx['bankBookingDate']) : null,
                                'value_date' => isset($apiTx['valueDate']) ? \Carbon\Carbon::parse($apiTx['valueDate']) : null,
                                'type' => $apiTx['type'] ?? null,
                                'is_pending' => $apiTx['isPending'] ?? false,
                                'raw_data' => $apiTx,
                            ]
                        );
                    }
                }

                $autoAssigned = $this->autoAssignTransactions('system', 'Schablonen-Engine', true);
                $msgAuto = $autoAssigned > 0 ? " ({$autoAssigned} automatisch sortiert)" : "";
                session()->flash('success', "Konten via finAPI aktualisiert!" . $msgAuto);
            } else {
                session()->flash('info', 'Keine finAPI-Verbindungen aktiv. Du kannst Umsätze bequem per CSV importieren.');
            }

            $this->loadBankAccounts();
        } catch (\Exception $e) {
            if(app()->environment('testing')) throw $e;
            \Illuminate\Support\Facades\Log::error('Bank Sync Fehler: ' . $e->getMessage());
            session()->flash('error', 'Synchronisierung fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    public function syncAccount($accountId, ?BankApiService $finapi = null)
    {
        $finapi = $finapi ?? app(BankApiService::class);
        $this->isLoading = true;

        try {
            $adminId = auth('admin')->id();
            $account = AccountingBankAccount::where('admin_id', $adminId)->find($accountId);
            if ($account) {
                if ($account->plaid_access_token === 'manual_managed') {
                    $this->openCsvModal($account->id);
                    $this->isLoading = false;
                    return;
                }

                $userToken = $finapi->getUserToken($adminId);
                $finapi->updateBankConnection($userToken, $account->plaid_item_id);
                $accounts = $finapi->getAccounts($userToken);

                foreach ($accounts as $apiAcc) {
                    if ((string)$apiAcc['id'] === $account->plaid_account_id) {
                        $account->update([
                            'balance' => $apiAcc['balance'],
                            'last_synced_at' => now(),
                        ]);

                        $txs = $finapi->getTransactions($userToken, $account->plaid_account_id, 200);
                        foreach ($txs as $apiTx) {
                            AccountingBankTransaction::updateOrCreate(
                                [
                                    'accounting_bank_account_id' => $account->id,
                                    'finapi_transaction_id' => (string) $apiTx['id'],
                                ],
                                [
                                    'amount' => $apiTx['amount'] ?? 0,
                                    'currency' => $account->currency,
                                    'purpose' => $apiTx['purpose'] ?? null,
                                    'counterpart_name' => $apiTx['counterpartName'] ?? null,
                                    'counterpart_iban' => $apiTx['counterpartIban'] ?? null,
                                    'transaction_date' => isset($apiTx['bankBookingDate']) ? \Carbon\Carbon::parse($apiTx['bankBookingDate']) : null,
                                    'value_date' => isset($apiTx['valueDate']) ? \Carbon\Carbon::parse($apiTx['valueDate']) : null,
                                    'type' => $apiTx['type'] ?? null,
                                    'is_pending' => $apiTx['isPending'] ?? false,
                                    'raw_data' => $apiTx,
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
        $account = AccountingBankAccount::where('admin_id', auth('admin')->id())->find($accountId);
        if ($account) {
            $account->delete();
            $this->loadBankAccounts();
            session()->flash('success', 'Konto lokal getrennt!');
        }
    }

    public function render()
    {
        $adminId = auth('admin')->id();
        $query = AccountingBankTransaction::whereHas('account', fn($q) => $q->where('admin_id', $adminId));

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
            $escapedSearch = addcslashes((string) $this->searchTx, '%_\\');
            $query->where(function($q) use ($escapedSearch) {
                $q->where('purpose', 'like', '%' . $escapedSearch . '%')
                  ->orWhere('counterpart_name', 'like', '%' . $escapedSearch . '%')
                  ->orWhere('counterpart_iban', 'like', '%' . $escapedSearch . '%');
            });
        }

        // 3. Category
        if ($this->filterCategoryId === 'unassigned') {
            $query->whereNull('accounting_category_id')
                  ->whereNull('accounting_cost_item_id');
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
