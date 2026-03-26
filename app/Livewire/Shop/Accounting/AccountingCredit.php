<?php

namespace App\Livewire\Shop\Accounting;

use Livewire\Attributes\Layout;

use App\Models\Customer\Customer;
use App\Models\Accounting\AccountingInvoice;
use App\Services\InvoiceService;
use Livewire\Component;
use App\Livewire\Traits\WithDepartmentTheming;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class AccountingCredit extends Component
{
    use WithDepartmentTheming;

    protected string $themingDepartment = 'Buchhaltung';

    use WithPagination;

    public $search = '';
    public $activeTab = 'list'; // list, create

    // Modal / Creation States
    public $showCreateModal = false;
    public $newCredit = [
        'customer_id' => '',
        'subject' => 'Gutschrift',
        'header_text' => 'Hiermit schreiben wir Ihnen die untenstehenden Positionen gut.',
        'footer_text' => 'Wir bitten um Beachtung des gutgeschriebenen Betrags.',
        'notes' => '',
    ];

    // Typischerweise [ ['name' => '', 'quantity' => 1, 'unit_price' => 0, 'tax_rate' => 19] ]
    public $creditItems = [];

    // Kunde suchen & Analyse
    public $searchCustomer = '';
    public $selectedCustomerId = null;
    public $customerStats = null;

    public function mount()
    {
        $this->addCreditItem(); // Start with at least one item
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    // --- Items Management ---

    public function addCreditItem()
    {
        $this->creditItems[] = [
            'name' => '',
            'quantity' => 1,
            'unit_price' => 0.0,
            'tax_rate' => (float)shop_setting('default_tax_rate', 19.0),
        ];
    }

    public function removeCreditItem($index)
    {
        unset($this->creditItems[$index]);
        $this->creditItems = array_values($this->creditItems);
    }


    // --- Creation ---

    public function openCreateModal()
    {
        $this->resetValidation();
        $this->newCredit['customer_id'] = '';
        $this->searchCustomer = '';
        $this->creditItems = [];
        $this->addCreditItem();
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function setCustomer($id, $name)
    {
        $this->newCredit['customer_id'] = $id;
        $this->searchCustomer = $name;
    }

    public function selectCustomer($id)
    {
        $this->selectedCustomerId = $id;

        if ($id) {
            $customer = Customer::find($id);
            if ($customer) {
                $totalCredits = AccountingInvoice::where('customer_id', $id)
                    ->whereIn('type', ['credit_note', 'cancellation'])
                    ->count();

                $totalVolume = AccountingInvoice::where('customer_id', $id)
                    ->whereIn('type', ['credit_note', 'cancellation'])
                    ->sum('total');

                $this->customerStats = [
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'total_credits' => $totalCredits,
                    'total_volume' => $totalVolume,
                ];
            }
        } else {
            $this->customerStats = null;
        }
    }

    public function sendCreditEmail($invoiceId)
    {
        $invoice = AccountingInvoice::find($invoiceId);
        if ($invoice && $invoice->customer) {

            // Asynchrones Dispatching an den Mail-Worker
            \Illuminate\Support\Facades\Mail::to($invoice->customer->email)
                ->send(new \App\Mail\CreditNoteMailToCustomer($invoice));

            $invoice->update(['email_sent_at' => now()]);
            session()->flash('success', 'Die Gutschrift wurde an ' . $invoice->customer->email . ' gesendet!');
        } else {
            session()->flash('error', 'Kunden-Oder Beleg-Daten unvollständig!');
        }
    }

    public function markAsSent($invoiceId)
    {
        $invoice = AccountingInvoice::find($invoiceId);
        if ($invoice) {
            $invoice->update(['email_sent_at' => now()]);
            session()->flash('success', 'Gutschrift wurde als versendet markiert (ohne E-Mail Versand).');
        }
    }

    public function generateCreditNote(InvoiceService $invoiceService)
    {
        $this->validate([
            'newCredit.subject' => 'required|string|max:255',
            'creditItems' => 'required|array|min:1',
            'creditItems.*.name' => 'required|string',
            'creditItems.*.quantity' => 'required|numeric|min:0.01',
            'creditItems.*.unit_price' => 'required|numeric|min:0',
            'creditItems.*.tax_rate' => 'required|numeric|min:0',
        ], [
            'creditItems.*.name.required' => 'Bitte geben Sie eine Bezeichnung für den Posten ein.',
            'creditItems.*.unit_price.required' => 'Bitte geben Sie einen Preis ein.',
            'creditItems.*.unit_price.min' => 'Preis darf nicht negativ sein (Betrag wird automatisch als Gutschrift gewertet).',
        ]);

        $subtotal = 0;
        $taxAmount = 0;
        $formattedItems = [];

        foreach ($this->creditItems as $item) {
            // "Einzelbrutto" vom UI (z.B. 222) in Cents umwandeln -> 22200
            $unitGrossCents = round((float)$item['unit_price'] * 100);
            $rowTotalGrossCents = round($unitGrossCents * (float)$item['quantity']);
            
            // Rückrechnung von Brutto auf Netto
            $taxFactor = 1 + ($item['tax_rate'] / 100);
            $rowTotalNetCents = (int) round($rowTotalGrossCents / $taxFactor);
            $rowTaxCents = $rowTotalGrossCents - $rowTotalNetCents;
            
            // Einzel-Netto in Cent
            $unitNetCents = (int) round($unitGrossCents / $taxFactor);

            $subtotal += $rowTotalNetCents;
            $taxAmount += $rowTaxCents;

            $formattedItems[] = [
                'product_name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitNetCents, // Format erwartet Netto Einzelpreis in Cent
                'tax_rate' => $item['tax_rate'],
                'total_price' => $rowTotalNetCents + $rowTaxCents,
                'configuration' => []
            ];
        }

        $total = $subtotal + $taxAmount;

        // Fetch customer explicitly to get addresses
        $customer = Customer::find($this->newCredit['customer_id']);

        $billingAddress = null;
        if ($customer) {
            $billingAddress = [
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'company' => $customer->profile->company_name ?? '',
                'address' => trim(($customer->profile->street ?? '') . ' ' . ($customer->profile->house_number ?? '')),
                'postal_code' => $customer->profile->postal ?? '',
                'city' => $customer->profile->city ?? '',
                'country' => $customer->profile->country ?? 'DE',
            ];
        }

        $data = [
            'customer_id' => $this->newCredit['customer_id'] ?: null,
            'subject' => $this->newCredit['subject'],
            'header_text' => $this->newCredit['header_text'],
            'footer_text' => $this->newCredit['footer_text'],
            'notes' => $this->newCredit['notes'],
            'billing_address' => $billingAddress,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'custom_items' => $formattedItems,
        ];

        $invoiceService->createCreditNote($data);

        session()->flash('success', 'Gutschrift erfolgreich erstellt!');
        $this->closeCreateModal();
        $this->activeTab = 'list';
    }


    public function render()
    {
        // Alle Rechnungen vom Typ Gutschrift oder Storno
        $credits = AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])
            ->when($this->search, function ($query) {
                $query->where('invoice_number', 'like', "%{$this->search}%")
                      ->orWhereHas('customer', function ($q) {
                          $q->where('last_name', 'like', "%{$this->search}%")
                            ->orWhere('first_name', 'like', "%{$this->search}%");
                      });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        // Kunden für Suche vorschlagen
        $customers = collect();
        if (strlen($this->searchCustomer) >= 2) {
            $customers = Customer::where('first_name', 'like', "%{$this->searchCustomer}%")
                ->orWhere('last_name', 'like', "%{$this->searchCustomer}%")
                ->orWhere('email', 'like', "%{$this->searchCustomer}%")
                ->take(5)
                ->get();
        }

        $stats = [
            'total_credits' => AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])->count(),
            'total_volume' => AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])->sum('total'),
            'this_month' => AccountingInvoice::whereIn('type', ['credit_note', 'cancellation'])
                                   ->whereMonth('created_at', now()->month)
                                   ->whereYear('created_at', now()->year)
                                   ->count(),
        ];

        return view('livewire.shop.accounting.accounting-credit', [
            'credits' => $credits,
            'customers' => $customers,
            'stats' => $stats,
        ]);
    }
}
