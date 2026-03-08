<?php

namespace App\Livewire\Shop\Financial;

use Livewire\Component;
use App\Services\BankApiService;
use App\Models\Financial\BankAccount;

class FinancialBank extends Component
{
    public $bankAccounts = [];
    public $recentTransactions = [];
    public $isLoading = false;

    public function mount(BankApiService $finapi)
    {
        if (request()->has('finapi_sync')) {
            return $this->importAccountsFromFinapi($finapi);
        }

        $this->loadBankAccounts();
    }

    public function loadBankAccounts()
    {
        // FIX: Wir nutzen zwingend die UUID des eingeloggten Admins
        $adminId = auth('admin')->id();

        $this->bankAccounts = BankAccount::where('admin_id', $adminId)
            ->orderBy('bank_name')
            ->get()
            ->toArray();
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
                    }
                }
                session()->flash('success', 'Kontostand erfolgreich synchronisiert!');
                $this->loadBankAccounts();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Synchronisierung fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('livewire.shop.financial.financial-bank');
    }
}
