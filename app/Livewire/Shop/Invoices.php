<?php

namespace App\Livewire\Shop;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Customer;
use App\Services\InvoiceService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Invoices extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = '';
    public $isCreatingManual = false;
    public $selectedCustomerId = null;

    // Feedback States
    public $saveSuccess = false;
    public $draftSuccess = false;

    // Sorting Table
    public $sortField = 'invoice_date'; // Standard-Sortierung
    public $sortDirection = 'desc';

    public $infoTexts = [
        'customer' => 'Wählen Sie einen bestehenden Kunden aus, um die Adressdaten automatisch zu befüllen.',
        'invoice_info' => 'Pflichtangaben für eine rechtssichere Rechnung gemäß GoBD.',
        'due_date' => 'Das Fälligkeitsdatum wird automatisch berechnet, wenn Sie die Tage anpassen.',
        'header_text' => 'Dieser Text erscheint oben auf der Rechnung unterhalb der Anschrift.',
        'footer_text' => 'Hier können Sie Zahlungsinformationen und Grußformeln hinterlegen.',
        'e_invoice' => 'Aktiviert das strukturierte XML-Format für elektronische Rechnungen.',
        'status_draft' => 'Ein Entwurf kann später bearbeitet werden und erhält noch keine endgültige Buchung.'
    ];

    public $manualInvoice = [
        'id' => null,
        'customer_email' => '',
        'first_name' => '',
        'last_name' => '',
        'company' => '',
        'address' => '',
        'address_addition' => '',
        'city' => '',
        'postal_code' => '',
        'country' => 'DE',
        'invoice_date' => '',
        'delivery_date' => '',
        'invoice_number' => '',
        'reference_number' => '',
        'due_days' => 14,
        'due_date' => '',
        'subject' => '',
        'header_text' => "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!\nHiermit stelle ich Ihnen die folgenden Leistungen in Rechnung:",
        'footer_text' => "Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer auf das unten angegebene Konto.\nDer Rechnungsbetrag ist bis zum [%ZAHLUNGSZIEL%] fällig.\n\nMit freundlichen Grüßen\n[%KONTAKTPERSON%]",
        'is_e_invoice' => false,
        'items' => [],
        'shipping_cost' => 0,
        'discount_amount' => 0,
        'volume_discount' => 0,
    ];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function updatedSelectedCustomerId($value)
    {
        if ($value) {
            $customer = Customer::with('profile')->find($value);
            if ($customer) {
                $this->manualInvoice['customer_email'] = $customer->email;
                $this->manualInvoice['first_name'] = $customer->first_name;
                $this->manualInvoice['last_name'] = $customer->last_name;
                $this->manualInvoice['company'] = $customer->company;

                // Laden aus User Profile wie gewünscht
                if ($customer->profile) {
                    $this->manualInvoice['address'] = trim(($customer->profile->street ?? '') . ' ' . ($customer->profile->house_number ?? ''));
                    $this->manualInvoice['city'] = $customer->profile->city ?? '';
                    $this->manualInvoice['postal_code'] = $customer->profile->postal ?? '';
                }

                $this->manualInvoice['country'] = $customer->billing_address['country'] ?? 'DE';
            }
        }
    }

    public function editDraft($id)
    {
        $invoice = Invoice::findOrFail($id);

        $this->manualInvoice = [
            'id' => $invoice->id,
            'customer_email' => $invoice->billing_address['email'] ?? '',
            'first_name' => $invoice->billing_address['first_name'] ?? '',
            'last_name' => $invoice->billing_address['last_name'] ?? '',
            'company' => $invoice->billing_address['company'] ?? '',
            'address' => $invoice->billing_address['address'] ?? '',
            'address_addition' => $invoice->billing_address['address_addition'] ?? '',
            'city' => $invoice->billing_address['city'] ?? '',
            'postal_code' => $invoice->billing_address['postal_code'] ?? '',
            'country' => $invoice->billing_address['country'] ?? 'DE',
            'invoice_date' => $invoice->invoice_date ? $invoice->invoice_date->format('Y-m-d') : '',
            'delivery_date' => $invoice->delivery_date ? $invoice->delivery_date->format('Y-m-d') : '',
            'invoice_number' => $invoice->invoice_number,
            'reference_number' => $invoice->reference_number,
            'due_days' => $invoice->due_days ?? 14,
            'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : '',
            'subject' => $invoice->subject,
            'header_text' => $invoice->header_text,
            'footer_text' => $invoice->footer_text,
            'is_e_invoice' => $invoice->is_e_invoice,
            'items' => collect($invoice->custom_items)->map(fn($item) => [
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'] / 100,
                'tax_rate' => $item['tax_rate']
            ])->toArray(),
            'shipping_cost' => $invoice->shipping_cost / 100,
            'discount_amount' => $invoice->discount_amount / 100,
            'volume_discount' => $invoice->volume_discount / 100,
        ];

        $this->isCreatingManual = true;
    }

    public function updatedManualInvoiceDueDays($value)
    {
        $this->calculateDueDate();
    }

    public function updatedManualInvoiceInvoiceDate($value)
    {
        $this->calculateDueDate();
        if (empty($this->manualInvoice['subject'])) {
            $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $this->manualInvoice['invoice_number'];
        }
    }

    public function updatedManualInvoiceInvoiceNumber($value)
    {
        $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $value;
    }

    protected function calculateDueDate()
    {
        if (!empty($this->manualInvoice['invoice_date'])) {
            $date = Carbon::parse($this->manualInvoice['invoice_date']);
            $this->manualInvoice['due_date'] = $date->addDays((int)$this->manualInvoice['due_days'])->format('Y-m-d');
        }
    }

    public function toggleManualCreate()
    {
        // Automatische Entwurfsspeicherung beim Verlassen des Edits
        if ($this->isCreatingManual && !empty($this->manualInvoice['invoice_number'])) {
            $this->autoSaveDraft();
        }

        $this->isCreatingManual = !$this->isCreatingManual;
        if($this->isCreatingManual) {
            $this->resetManualInvoice();
        }
    }

    protected function resetManualInvoice()
    {
        $this->selectedCustomerId = null;
        $this->manualInvoice = [
            'id' => null,
            'customer_email' => '',
            'first_name' => '',
            'last_name' => '',
            'company' => '',
            'address' => '',
            'address_addition' => '',
            'city' => '',
            'postal_code' => '',
            'country' => 'DE',
            'invoice_date' => now()->format('Y-m-d'),
            'delivery_date' => now()->format('Y-m-d'),
            'invoice_number' => 'RE-' . date('Y') . '-' . (Invoice::count() + 1001),
            'reference_number' => '',
            'due_days' => 14,
            'due_date' => '',
            'subject' => '',
            'header_text' => "Sehr geehrte Damen und Herren,\n\nvielen Dank für Ihren Auftrag und das damit verbundene Vertrauen!\nHiermit stelle ich Ihnen die folgenden Leistungen in Rechnung:",
            'footer_text' => "Bitte überweisen Sie den Rechnungsbetrag unter Angabe der Rechnungsnummer auf das unten angegebene Konto.\nDer Rechnungsbetrag ist bis zum [%ZAHLUNGSZIEL%] fällig.\n\nMit freundlichen Grüßen\n[%KONTAKTPERSON%]",
            'is_e_invoice' => false,
            'items' => [],
            'shipping_cost' => 0,
            'discount_amount' => 0,
            'volume_discount' => 0,
        ];
        $this->manualInvoice['subject'] = 'Rechnung Nr. ' . $this->manualInvoice['invoice_number'];
        $this->calculateDueDate();
        $this->addItem();
    }

    protected function autoSaveDraft()
    {
        // Prüfen ob bereits existiert oder neu ist, dann im Hintergrund sichern
        if (!empty($this->manualInvoice['last_name'])) {
            $this->saveManualInvoice('draft', true);
        }
    }

    public function addItem()
    {
        $this->manualInvoice['items'][] = [
            'product_name' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'tax_rate' => 19
        ];
    }

    public function removeItem($index)
    {
        unset($this->manualInvoice['items'][$index]);
        $this->manualInvoice['items'] = array_values($this->manualInvoice['items']);
    }

    public function downloadPdf($id)
    {
        $invoice = Invoice::findOrFail($id);

        $pdf = Pdf::loadView('global.mails.invoice_pdf_template', [
            'invoice' => $invoice,
            'items' => $invoice->order->items, // Wir nutzen die OrderItems, da diese unveränderbar sein sollten
            'isStorno' => $invoice->type === 'cancellation',
        ]);


        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "Rechnung_{$invoice->invoice_number}.pdf");
    }

    public function generateForPaidOrders(InvoiceService $service)
    {
        $orders = Order::where('payment_status', 'paid')
            ->whereDoesntHave('invoices')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $service->createFromOrder($order);
            $count++;
        }

        session()->flash('success', "$count Rechnungen wurden generiert.");
    }

    public function saveManualInvoice($finalStatus = 'paid', $isAutoSave = false)
    {
        if ($finalStatus === 'draft') {
            $this->validate([
                'manualInvoice.invoice_number' => 'required|unique:invoices,invoice_number,' . ($this->manualInvoice['id'] ?? 'null') . ',id',
            ]);
        } else {
            // Klare Validierung für Abschluss
            $this->validate([
                'manualInvoice.customer_email' => 'required|email',
                'manualInvoice.first_name' => 'required',
                'manualInvoice.last_name' => 'required',
                'manualInvoice.address' => 'required',
                'manualInvoice.postal_code' => 'required',
                'manualInvoice.city' => 'required',
                'manualInvoice.invoice_date' => 'required|date',
                'manualInvoice.delivery_date' => 'required|date',
                'manualInvoice.invoice_number' => 'required|unique:invoices,invoice_number,' . ($this->manualInvoice['id'] ?? 'null') . ',id',
                'manualInvoice.items' => 'required|array|min:1',
                'manualInvoice.items.*.product_name' => 'required',
                'manualInvoice.items.*.unit_price' => 'required|numeric',
                'manualInvoice.items.*.quantity' => 'required|numeric|min:1',
            ], [], [
                'manualInvoice.customer_email' => 'E-Mail',
                'manualInvoice.last_name' => 'Nachname',
                'manualInvoice.items' => 'Positionen',
            ]);
        }

        DB::transaction(function () use ($finalStatus) {
            $subtotal = 0;
            $taxTotal = 0;
            $items = [];

            foreach ($this->manualInvoice['items'] as $item) {
                $priceInCent = (int)round(($item['unit_price'] ?? 0) * 100);
                $lineTotal = $priceInCent * ($item['quantity'] ?? 0);

                $taxDivisor = 1 + (($item['tax_rate'] ?? 19) / 100);
                $netLine = $lineTotal / $taxDivisor;
                $taxLine = $lineTotal - $netLine;

                $subtotal += $lineTotal;
                $taxTotal += (int)round($taxLine);

                $items[] = [
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $priceInCent,
                    'total_price' => $lineTotal,
                    'tax_rate' => $item['tax_rate']
                ];
            }

            $shippingInCent = (int)round(($this->manualInvoice['shipping_cost'] ?? 0) * 100);
            $discountInCent = (int)round(($this->manualInvoice['discount_amount'] ?? 0) * 100);
            $volumeDiscountInCent = (int)round(($this->manualInvoice['volume_discount'] ?? 0) * 100);

            $totalBrutto = ($subtotal + $shippingInCent) - ($discountInCent + $volumeDiscountInCent);

            $invoice = Invoice::updateOrCreate(
                ['invoice_number' => $this->manualInvoice['invoice_number']],
                [
                    'reference_number' => $this->manualInvoice['reference_number'],
                    'invoice_date' => $this->manualInvoice['invoice_date'] ?: now(),
                    'delivery_date' => $this->manualInvoice['delivery_date'] ?: now(),
                    'due_date' => $this->manualInvoice['due_date'],
                    'due_days' => $this->manualInvoice['due_days'],
                    'subject' => $this->manualInvoice['subject'],
                    'header_text' => $this->manualInvoice['header_text'],
                    'footer_text' => $this->manualInvoice['footer_text'],
                    'is_e_invoice' => $this->manualInvoice['is_e_invoice'],
                    'type' => 'invoice',
                    'status' => $finalStatus,
                    'customer_id' => $this->selectedCustomerId,
                    'billing_address' => [
                        'first_name' => $this->manualInvoice['first_name'],
                        'last_name' => $this->manualInvoice['last_name'],
                        'company' => $this->manualInvoice['company'],
                        'address' => $this->manualInvoice['address'],
                        'address_addition' => $this->manualInvoice['address_addition'],
                        'postal_code' => $this->manualInvoice['postal_code'],
                        'city' => $this->manualInvoice['city'],
                        'country' => $this->manualInvoice['country'],
                        'email' => $this->manualInvoice['customer_email'],
                    ],
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingInCent,
                    'discount_amount' => $discountInCent,
                    'volume_discount' => $volumeDiscountInCent,
                    'tax_amount' => $taxTotal,
                    'total' => $totalBrutto,
                    'custom_items' => $items,
                ]
            );

            // E-Rechnungs Logik: Technischer Export / Trigger
            if ($this->manualInvoice['is_e_invoice'] && $finalStatus === 'paid') {
                $this->processEInvoice($invoice);
            }
        });

        if (!$isAutoSave) {
            if ($finalStatus === 'paid') {
                $this->saveSuccess = true;
                $this->dispatch('notify', ['type' => 'success', 'message' => 'Rechnung abgeschlossen']);
                $this->isCreatingManual = false;
            } else {
                $this->draftSuccess = true;
                // Reset success state after few seconds
                $this->dispatch('resetDraftSuccess');
                session()->flash('success', 'Entwurf gespeichert.');
            }
        }
    }

    protected function processEInvoice($invoice)
    {
        // Hier erfolgt die technische Generierung des XML (ZUGFeRD / XRechnung)
        // Platzhalter für Service-Aufruf
        // app(EInvoiceService::class)->generate($invoice);
    }

    // Methode zum Sortieren hinzufügen:
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render()
    {
        $query = Invoice::query()->with(['order', 'customer']);

        if ($this->search) {
            $query->where(function($q) {
                $q->where('invoice_number', 'like', '%'.$this->search.'%')
                    ->orWhere('billing_address->last_name', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterType) {
            if ($this->filterType === 'draft') {
                $query->where('status', 'draft');
            } else {
                $query->where('type', $this->filterType);
            }
        }

        // SORTIERUNG ANWENDEN
        if ($this->sortField === 'recipient') {
            // Spezialfall für JSON-Feld (Empfänger-Nachname)
            $query->orderBy('billing_address->last_name', $this->sortDirection);
        } else {
            // Standardsortierung für normale Spalten (invoice_number, total, status, invoice_date)
            $query->orderBy($this->sortField, $this->sortDirection)
                ->orderBy('created_at', 'desc'); // Zweitrangige Sortierung für absolute Aktualität
        }

        $totalsPreview = [
            'net' => 0,
            'tax' => 0,
            'gross' => 0
        ];

        if($this->isCreatingManual) {
            foreach($this->manualInvoice['items'] as $item) {
                $line = (float)($item['unit_price'] ?: 0) * (float)($item['quantity'] ?: 0);
                $taxDiv = 1 + (($item['tax_rate'] ?: 19) / 100);
                $net = $line / $taxDiv;
                $totalsPreview['net'] += $net;
                $totalsPreview['tax'] += ($line - $net);
                $totalsPreview['gross'] += $line;
            }
            $totalsPreview['gross'] += (float)$this->manualInvoice['shipping_cost'];
            $totalsPreview['gross'] -= ((float)$this->manualInvoice['discount_amount'] + (float)$this->manualInvoice['volume_discount']);
        }

        return view('livewire.shop.invoices', [
            'invoices' => $query->latest('invoice_date')->paginate(15),
            'totalsPreview' => $totalsPreview,
            'customers' => Customer::orderBy('last_name')->get()
        ]);
    }
}
