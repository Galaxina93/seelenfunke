<?php

namespace App\Livewire\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;

class QuoteAcceptance extends Component
{
    public $token;
    public $quote;
    public $success = false;
    public $error = '';

    public function mount($token)
    {
        $this->token = $token;
        $this->quote = QuoteRequest::where('token', $token)->with('items')->firstOrFail();

        // Checks
        if ($this->quote->status === 'converted') {
            $this->error = 'Dieses Angebot wurde bereits angenommen.';
        } elseif ($this->quote->status === 'rejected') {
            $this->error = 'Dieses Angebot ist nicht mehr gültig.';
        } elseif ($this->quote->expires_at->isPast()) {
            $this->error = 'Dieses Angebot ist abgelaufen (Gültigkeit: 14 Tage). Bitte kontaktieren Sie uns für ein neues Angebot.';
        }
    }

    public function acceptQuote()
    {
        if ($this->error) return;

        // Logik analog zum Admin-Backend, aber automatisiert

        // 1. Kunde finden oder erstellen
        $customer = Customer::firstOrCreate(
            ['email' => $this->quote->email],
            [
                'first_name' => $this->quote->first_name,
                'last_name' => $this->quote->last_name,
                'password' => bcrypt(Str::random(16)), // Kunde muss Passwort resetten wenn er sich einloggen will
            ]
        );

        $customer->profile()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['phone_number' => $this->quote->phone]
        );

        // 2. Order erstellen
        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $this->quote->email,
            'status' => 'processing',
            'payment_status' => 'unpaid', // Wird per Rechnung bezahlt
            'billing_address' => [
                'first_name' => $this->quote->first_name,
                'last_name' => $this->quote->last_name,
                'company' => $this->quote->company,
                'address' => 'Adresse folgt', // Ggf. Formular anzeigen um Adresse abzufragen? Hier vereinfacht.
                'postal_code' => '',
                'city' => '',
                'country' => 'DE'
            ],
            'subtotal_price' => $this->quote->net_total,
            'tax_amount' => $this->quote->tax_total,
            'total_price' => $this->quote->gross_total,
            'notes' => 'Automatisch erstellt durch Angebotsannahme (' . $this->quote->quote_number . ').',
        ]);

        // 3. Items
        foreach($this->quote->items as $qItem) {
            $order->items()->create([
                'product_id' => $qItem->product_id,
                'product_name' => $qItem->product_name,
                'quantity' => $qItem->quantity,
                'unit_price' => $qItem->unit_price,
                'total_price' => $qItem->total_price,
                'configuration' => $qItem->configuration,
            ]);
        }

        // 4. Update Quote
        $this->quote->update([
            'status' => 'converted',
            'converted_order_id' => $order->id
        ]);

        // 5. Mail
        try {
            Mail::to($order->email)->send(new OrderConfirmation($order));
        } catch (\Exception $e) {}

        $this->success = true;
    }

    public function render()
    {
        return view('livewire.shop.quote-acceptance')->layout('components.layouts.app'); // Nutzt das normale Frontend Layout
    }
}
