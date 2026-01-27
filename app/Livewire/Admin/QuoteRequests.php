<?php

namespace App\Livewire\Admin;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestItem; // Import hinzufügen
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class QuoteRequests extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStatus = '';

    // Detail View State
    public $selectedQuoteId = null;
    public $selectedQuoteItemId = null; // NEU: Für die Vorschau rechts

    public function render()
    {
        if ($this->selectedQuoteId) {
            return view('livewire.admin.quote-requests-detail', [ // Achtung: Dateiname Plural s.u.
                'quote' => QuoteRequest::with('items.product')->find($this->selectedQuoteId)
            ]);
        }

        $query = QuoteRequest::query()->latest();

        if ($this->search) {
            $query->where(function($q) {
                $q->where('quote_number', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhere('last_name', 'like', '%'.$this->search.'%')
                    ->orWhere('company', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return view('livewire.admin.quote-requests', [
            'quotes' => $query->paginate(10)
        ]);
    }

    // --- ACTIONS ---

    public function selectQuote($id)
    {
        $this->selectedQuoteId = $id;

        // NEU: Automatisch erstes Item für Vorschau wählen
        $quote = QuoteRequest::with('items')->find($id);
        if ($quote && $quote->items->isNotEmpty()) {
            $this->selectedQuoteItemId = $quote->items->first()->id;
        }
    }

    // NEU: Item wechseln für rechte Spalte
    public function selectItemForPreview($itemId)
    {
        $this->selectedQuoteItemId = $itemId;
    }

    // NEU: Property für die View
    public function getPreviewItemProperty()
    {
        if (!$this->selectedQuoteId || !$this->selectedQuoteItemId) return null;
        return QuoteRequestItem::with('product')->find($this->selectedQuoteItemId);
    }

    public function closeDetail()
    {
        $this->selectedQuoteId = null;
        $this->selectedQuoteItemId = null;
    }

    public function convertToOrder($quoteId)
    {
        $quote = QuoteRequest::with('items')->find($quoteId);
        if (!$quote || $quote->status === 'converted') return;

        // 1. Kunde
        $customer = Customer::firstOrCreate(
            ['email' => $quote->email],
            [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'password' => bcrypt(Str::random(16)),
            ]
        );

        $customer->profile()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['phone_number' => $quote->phone]
        );

        // 2. Order
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $quote->email,
            'status' => 'processing',
            'payment_status' => 'unpaid',
            'billing_address' => [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'company' => $quote->company,
                'address' => 'Adresse aus Anfrage',
                'postal_code' => '',
                'city' => '',
                'country' => 'DE'
            ],
            'subtotal_price' => $quote->net_total,
            'tax_amount' => $quote->tax_total,
            'total_price' => $quote->gross_total,
            'notes' => 'Aus Angebot ' . $quote->quote_number . ' generiert. ' . $quote->admin_notes,
        ]);

        // 3. Items
        foreach($quote->items as $qItem) {
            $order->items()->create([
                'product_id' => $qItem->product_id,
                'product_name' => $qItem->product_name,
                'quantity' => $qItem->quantity,
                'unit_price' => $qItem->unit_price,
                'total_price' => $qItem->total_price,
                'configuration' => $qItem->configuration,
            ]);
        }

        // 4. Update
        $quote->update([
            'status' => 'converted',
            'converted_order_id' => $order->id
        ]);

        // 5. Mail
        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));
            session()->flash('success', 'Angebot angenommen!');
        } catch (\Exception $e) {
            session()->flash('warning', 'Bestellung erstellt, Mail-Fehler: ' . $e->getMessage());
        }

        $this->closeDetail();
    }

    public function markAsRejected($id) {
        QuoteRequest::where('id', $id)->update(['status' => 'rejected']);
        // Wir schließen das Detail nicht zwingend, damit man Feedback sieht, aber hier OK
        $this->closeDetail();
    }

    // NEU: Status zurücksetzen
    public function markAsOpen($id) {
        $quote = QuoteRequest::find($id);
        if ($quote && $quote->status === 'rejected') {
            $quote->update(['status' => 'open']);
            session()->flash('success', 'Anfrage wurde wieder geöffnet.');
        }
    }
}
