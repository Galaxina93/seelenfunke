<?php

namespace App\Livewire\Shop;

use App\Models\QuoteRequest;
use App\Models\Order;
use App\Models\Customer;
use App\Mail\OrderConfirmation;
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

    public function render()
    {
        if ($this->selectedQuoteId) {
            return view('livewire.shop.quote-request-detail', [
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

        return view('livewire.shop.quote-requests', [
            'quotes' => $query->paginate(10)
        ]);
    }

    public function selectQuote($id)
    {
        $this->selectedQuoteId = $id;
    }

    public function closeDetail()
    {
        $this->selectedQuoteId = null;
    }

    // --- DIE MAGIE: ANGEBOT ANNEHMEN & BESTELLUNG ERSTELLEN ---
    public function convertToOrder($quoteId)
    {
        $quote = QuoteRequest::with('items')->find($quoteId);
        if (!$quote || $quote->status === 'converted') return;

        // 1. Kunde prüfen / erstellen
        $customer = Customer::firstOrCreate(
            ['email' => $quote->email],
            [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'password' => bcrypt(Str::random(16)), // Dummy Passwort
            ]
        );

        // Ggf. Profil updaten, wenn neu
        $customer->profile()->firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'phone_number' => $quote->phone,
                // Adresse müsste man eigentlich abfragen, wir lassen sie hier leer oder nehmen Dummy-Werte aus der Anfrage falls vorhanden
            ]
        );

        // 2. Bestellung erstellen
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $quote->email,
            'status' => 'processing', // Direkt in Bearbeitung, da manuell freigegeben
            'payment_status' => 'unpaid', // Rechnung folgt
            'billing_address' => [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'company' => $quote->company,
                'address' => 'Adresse bitte erfragen', // Da im Kalkulator oft keine Adresse abgefragt wird
                'postal_code' => '',
                'city' => '',
                'country' => 'DE'
            ],
            'subtotal_price' => $quote->net_total, // Oder Gross, je nach deiner Logik im Order Model
            'tax_amount' => $quote->tax_total,
            'total_price' => $quote->gross_total,
            'notes' => 'Aus Angebot ' . $quote->quote_number . ' generiert. ' . $quote->admin_notes,
        ]);

        // 3. Items übertragen
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

        // 4. Status Update
        $quote->update([
            'status' => 'converted',
            'converted_order_id' => $order->id
        ]);

        // 5. Mail senden
        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));
            session()->flash('success', 'Angebot erfolgreich in Bestellung umgewandelt! Bestätigungsmail versendet.');
        } catch (\Exception $e) {
            session()->flash('warning', 'Bestellung erstellt, aber Mail konnte nicht gesendet werden: ' . $e->getMessage());
        }

        $this->closeDetail();
    }

    public function markAsRejected($id) {
        QuoteRequest::where('id', $id)->update(['status' => 'rejected']);
        $this->closeDetail();
    }
}
