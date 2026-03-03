<?php

namespace App\Livewire\Shop\Offer;

use App\Mail\OrderMailToCustomer;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Quote\QuoteRequest;
use App\Models\Quote\QuoteRequestItem;
use App\Services\InvoiceService;
use App\Services\NativeXmlInvoiceService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

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
            return view('livewire.shop.offer.quote-requests-detail', [
                'quote' => QuoteRequest::with(['items.product', 'customer'])->find($this->selectedQuoteId)
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

        return view('livewire.shop.offer.quote-requests', [
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

    /**
     * Neue Signatur: Wir übergeben den gewünschten Typ direkt beim Klick.
     * $type kann sein: 'invoice' oder 'stripe_link'
     */
    /**
     * Neue Signatur: Wir übergeben den gewünschten Typ direkt beim Klick.
     * $type kann sein: 'invoice' oder 'stripe_link'
     */
    public function convertToOrder($quoteId, $type = 'invoice')
    {
        $quote = QuoteRequest::with('items')->find($quoteId);

        // Sicherheitscheck: Existiert das Angebot und ist es nicht schon umgewandelt?
        if (!$quote || $quote->status === 'converted') return;

        $isSmallBusiness = (bool) shop_setting('is_small_business', false);

        // ---------------------------------------------------------
        // 1. KUNDEN ERSTELLEN ODER FINDEN
        // ---------------------------------------------------------
        $customer = Customer::firstOrCreate(
            ['email' => $quote->email],
            [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'password' => Hash::make(Str::random(16)), // Zufallspasswort
            ]
        );

        // Kundendaten im Profil vervollständigen / aktualisieren
        $customer->profile()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'phone_number' => $quote->phone,
                'street' => $quote->street ?? '',
                'house_number' => $quote->house_number ?? '',
                'postal' => $quote->postal,
                'city' => $quote->city,
                'country' => $quote->country ?? 'DE',
            ]
        );

        // ---------------------------------------------------------
        // 2. ORDER ERSTELLEN
        // ---------------------------------------------------------
        // Wir speichern in der DB, was gewählt wurde
        $methodDbString = ($type === 'stripe_link') ? 'stripe_link' : 'invoice';

        $order = Order::create([
            'order_number' => 'ORD-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'customer_id' => $customer->id,
            'email' => $quote->email,
            'status' => 'pending',
            'payment_status' => 'unpaid',

            // Wir setzen 'stripe_link', damit wir wissen: Hier wurde ein Link gesendet
            'payment_method' => $methodDbString,

            // WICHTIG: Express Daten & Versandkosten übernehmen!
            'is_express' => $quote->is_express,
            'deadline' => $quote->deadline,
            'shipping_price' => $quote->shipping_price ?? 0,

            'billing_address' => [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'company' => $quote->company,
                'address' => trim(($quote->street ?? '') . ' ' . ($quote->house_number ?? '')),
                'postal_code' => $quote->postal,
                'city' => $quote->city,
                'country' => $quote->country ?? 'DE'
            ],
            // Lieferadresse initial identisch zur Rechnungsadresse
            'shipping_address' => [
                'first_name' => $quote->first_name,
                'last_name' => $quote->last_name,
                'company' => $quote->company,
                'address' => trim(($quote->street ?? '') . ' ' . ($quote->house_number ?? '')),
                'postal_code' => $quote->postal,
                'city' => $quote->city,
                'country' => $quote->country ?? 'DE'
            ],

            // Rabatte exakt aus dem Angebot übernehmen
            'volume_discount' => $quote->volume_discount ?? 0,
            'discount_amount' => $quote->discount_amount ?? 0,
            'coupon_code'     => $quote->coupon_code ?? null,

            'subtotal_price' => $quote->net_total,
            'tax_amount' => $isSmallBusiness ? 0 : $quote->tax_total,
            'total_price' => $quote->gross_total,
            'notes' => 'Aus Angebot ' . $quote->quote_number . ' generiert. ' . $quote->admin_notes,
        ]);

        // ---------------------------------------------------------
        // 3. ITEMS ÜBERTRAGEN
        // ---------------------------------------------------------
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

        // ---------------------------------------------------------
        // 4. STRIPE PAYMENT LINK GENERIEREN
        // ---------------------------------------------------------
        $paymentUrl = null;
        try {
            $stripeSecret = config('services.stripe.secret');
            if($stripeSecret && $type === 'stripe_link') {
                Stripe::setApiKey($stripeSecret);

                $session = StripeSession::create([
                    'payment_method_types' => ['card', 'paypal', 'klarna', 'sofort'],
                    'line_items' => [[
                        'price_data' => [
                            'currency' => 'eur',
                            'product_data' => [
                                'name' => 'Bestellung ' . $order->order_number,
                                'description' => 'Zahlung für Ihr angenommenes Angebot',
                            ],
                            'unit_amount' => $order->total_price, // Betrag in Cent
                        ],
                        'quantity' => 1,
                    ]],
                    'mode' => 'payment',
                    'success_url' => route('shop') . '?payment_success=true',
                    'cancel_url' => route('shop'),
                    'customer_email' => $order->email,
                    'metadata' => [
                        'order_id' => $order->id,
                        'quote_number' => $quote->quote_number
                    ]
                ]);

                $paymentUrl = $session->url;

                // Link in der Order speichern (Der Job zieht sich den später da raus)
                $order->update(['payment_url' => $paymentUrl]);
            }
        } catch (\Exception $e) {
            Log::error("Stripe Link Fehler bei Umwandlung: " . $e->getMessage());
        }

        // ---------------------------------------------------------
        // 5. STATUS DES ANGEBOTS AKTUALISIEREN
        // ---------------------------------------------------------
        $quote->update([
            'status' => 'converted',
            'converted_order_id' => $order->id
        ]);

        // ---------------------------------------------------------
        // 6. JOB FÜR DOKUMENTE & MAILS ANSTOSSEN (NEU!)
        // ---------------------------------------------------------
        // Anstatt hier alles blockierend selbst zu bauen, übergeben wir
        // die frische Order an den Background-Job.
        \App\Jobs\ProcessOrderDocumentsAndMails::dispatch($order);

        session()->flash('success', 'Angebot erfolgreich in Bestellung umgewandelt! Dokumente und E-Mails werden im Hintergrund versendet. ✨');

        $this->closeDetail();
    }

    public function markAsRejected($id) {
        QuoteRequest::where('id', $id)->update(['status' => 'rejected']);
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
