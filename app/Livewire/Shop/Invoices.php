<?php

namespace App\Livewire\Shop;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class Invoices extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = '';

    public $isCreatingManual = false;
    public $manualInvoice = [
        'customer_email' => '',
        'first_name' => '',
        'last_name' => '',
        'address' => '',
        'city' => '',
        'postal_code' => '',
        'country' => 'DE',
        'items' => [],
        'shipping_cost' => 0,
        'discount_amount' => 0,
        'volume_discount' => 0,
    ];

    protected $listeners = ['refreshComponent' => '$refresh'];

    public function toggleManualCreate()
    {
        $this->isCreatingManual = !$this->isCreatingManual;
        if($this->isCreatingManual && empty($this->manualInvoice['items'])) {
            $this->addItem();
        }
    }

    public function addItem()
    {
        $this->manualInvoice['items'][] = [
            'product_name' => '',
            'quantity' => 1,
            'unit_price' => 0
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

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice])
            ->setPaper('a4')
            ->setWarnings(false);

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

    public function saveManualInvoice()
    {
        $this->validate([
            'manualInvoice.customer_email' => 'required|email',
            'manualInvoice.first_name' => 'required',
            'manualInvoice.last_name' => 'required',
            'manualInvoice.address' => 'required',
            'manualInvoice.postal_code' => 'required',
            'manualInvoice.city' => 'required',
            'manualInvoice.items.*.product_name' => 'required',
            'manualInvoice.items.*.unit_price' => 'required|numeric',
        ]);

        DB::transaction(function () {
            $subtotal = 0;
            $items = [];

            foreach ($this->manualInvoice['items'] as $item) {
                $priceInCent = (int)round($item['unit_price'] * 100);
                $lineTotal = $priceInCent * $item['quantity'];
                $subtotal += $lineTotal;

                $items[] = [
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $priceInCent,
                    'total_price' => $lineTotal
                ];
            }

            $shippingInCent = (int)round($this->manualInvoice['shipping_cost'] * 100);
            $discountInCent = (int)round($this->manualInvoice['discount_amount'] * 100);
            $volumeDiscountInCent = (int)round($this->manualInvoice['volume_discount'] * 100);

            $totalBrutto = ($subtotal + $shippingInCent) - ($discountInCent + $volumeDiscountInCent);
            $taxAmount = Invoice::calculateTax($totalBrutto, $this->manualInvoice['country']);

            Invoice::create([
                'invoice_number' => 'MAN-' . date('Y') . '-' . strtoupper(Str::random(4)),
                'invoice_date' => now(),
                'type' => 'invoice',
                'status' => 'paid',
                'billing_address' => [
                    'first_name' => $this->manualInvoice['first_name'],
                    'last_name' => $this->manualInvoice['last_name'],
                    'address' => $this->manualInvoice['address'],
                    'postal_code' => $this->manualInvoice['postal_code'],
                    'city' => $this->manualInvoice['city'],
                    'country' => $this->manualInvoice['country'],
                    'email' => $this->manualInvoice['customer_email'],
                ],
                'subtotal' => $subtotal,
                'shipping_cost' => $shippingInCent,
                'discount_amount' => $discountInCent,
                'volume_discount' => $volumeDiscountInCent,
                'tax_amount' => $taxAmount,
                'total' => $totalBrutto,
                'custom_items' => $items,
            ]);
        });

        $this->isCreatingManual = false;
        session()->flash('success', 'Rechnung erfolgreich erstellt.');
    }

    public function render()
    {
        $query = Invoice::query()->with('order');

        if ($this->search) {
            $query->where(function($q) {
                $q->where('invoice_number', 'like', '%'.$this->search.'%')
                    ->orWhere('billing_address->last_name', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }

        return view('livewire.shop.invoices', [
            'invoices' => $query->latest('invoice_date')->paginate(15)
        ]);
    }
}
