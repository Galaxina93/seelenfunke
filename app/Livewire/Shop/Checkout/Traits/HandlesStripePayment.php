<?php

namespace App\Livewire\Shop\Checkout\Traits;

use App\Models\Order\Order;
use App\Models\Funki\FunkiVoucher;
use App\Models\Quote\QuoteRequest;
use App\Services\CartService;
use Illuminate\Support\Facades\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

trait HandlesStripePayment
{
    /**
     * Erstellt oder aktualisiert den PaymentIntent bei Stripe
     */
    public function createPaymentIntent()
    {
        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        // WICHTIG: Versandkosten basieren IMMER auf dem Lieferland
        $targetCountry = $this->has_separate_shipping ? $this->shipping_country : $this->country;
        $totals = $cartService->calculateTotals($cart, $targetCountry);

        $amount = $totals['total']; // Betrag in Cent

        if ($amount > 0) {
            $stripeSecret = config('services.stripe.secret');
            if ($stripeSecret) {
                Stripe::setApiKey($stripeSecret);

                // Metadaten vorbereiten
                $metadata = [
                    'cart_id' => $cart->id,
                    'session_id' => Session::getId(),
                    'customer_email' => $this->email,
                    'shipping_country' => $targetCountry
                ];

                $intentData = [
                    'amount' => $amount,
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => $metadata
                ];

                // OPTIONAL: Wenn wir schon einen Intent haben, könnten wir ihn updaten.
                // Der Einfachheit halber erstellen wir hier einen neuen.
                $intent = PaymentIntent::create($intentData);

                $this->clientSecret = $intent->client_secret;

                // [FIX] Hier speichern wir die ID sofort in die Variable
                $this->currentPaymentIntentId = $intent->id;

                return $this->clientSecret;
            }
        }
        return null;
    }

    /**
     * Wird vom Frontend aufgerufen, wenn Stripe die Zahlung bestätigt hat.
     */
    public function handlePaymentSuccess($orderId = null)
    {
        // 1. Die richtige Order finden
        $order = $orderId ? Order::with('items.product')->find($orderId) : null;

        if (!$order) {
            $order = Order::with('items.product')
                ->where('stripe_payment_intent_id', $this->currentPaymentIntentId)
                ->where('status', 'pending')
                ->latest()
                ->first();
        }

        if ($order) {
            // 2. Status & Zahlung der Bestellung aktualisieren
            $order->update([
                'payment_status' => 'paid',
                'status' => 'pending'
            ]);

            // --- LAGERBESTAND REDUZIEREN ---
            foreach ($order->items as $item) {
                if ($item->product) {
                    $item->product->reduceStock($item->quantity);
                }
            }

            // GUTSCHEIN VERBRAUCHEN (Counter hochzählen)
            if ($order->coupon_code) {
                // Wir nutzen increment(), das ist atomar und sicher bei gleichzeitigen Zugriffen
                FunkiVoucher::where('code', $order->coupon_code)->increment('used_count');
            }

            $this->finalOrderNumber = $order->order_number;

            // --- VERKNÜPFUNG ZUM ANGEBOT (QUOTE) ---
            if (Session::has('checkout_from_quote_id')) {
                $quoteId = Session::get('checkout_from_quote_id');
                $quote = QuoteRequest::find($quoteId);

                if ($quote) {
                    $quote->update([
                        'status' => 'converted',
                        'converted_order_id' => $order->id
                    ]);

                    Session::forget('checkout_from_quote_id');

                    \Illuminate\Support\Facades\Log::info("Angebot {$quote->quote_number} wurde erfolgreich in Order {$order->order_number} umgewandelt.");
                }
            }

            // 3. Rechnung & Dokumente zentral erstellen
            $pdfPath = null;
            $xmlPath = null; // [NEU] Variable für XML Pfad

            try {
                $invoiceService = app(\App\Services\InvoiceService::class);

                // Invoice Model in DB erstellen
                $invoice = $invoiceService->createFromOrder($order);

                // A) PDF Generieren
                $pdfPath = storage_path("app/public/invoices/{$invoice->invoice_number}.pdf");

                if ($invoice && !file_exists($pdfPath)) {
                    $pdf = $invoiceService->generatePdf($invoice);
                    if (!file_exists(dirname($pdfPath))) {
                        mkdir(dirname($pdfPath), 0755, true);
                    }
                    file_put_contents($pdfPath, $pdf->output());
                }

                // B) XML Generieren (Nur für gewerbliche Kunden)
                $isBusiness = false;
                if ($order->customer && $order->customer->profile && $order->customer->profile->is_business) {
                    $isBusiness = true;
                } elseif (!empty($order->billing_address['company'])) {
                    $isBusiness = true;
                }

                if ($invoice && $isBusiness) {
                    try {
                        $xmlService = app(\App\Services\NativeXmlInvoiceService::class);
                        $relativePath = $xmlService->generate($invoice);
                        $xmlPath = storage_path("app/{$relativePath}");
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("XML-Generierung fehlgeschlagen für {$order->order_number}: " . $e->getMessage());
                    }
                }

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Rechnungserstellung fehlgeschlagen für {$order->order_number}: " . $e->getMessage());
            }

            // 4. Mails versenden mit zentralisierten Daten
            try {
                $mailData = $order->toFormattedArray();

                // A) Bestätigung an Kunden (JETZT MIT PDF UND XML)
                \Illuminate\Support\Facades\Mail::to($order->email)
                    ->send(new \App\Mail\OrderMailToCustomer($mailData, $pdfPath, $xmlPath));

                // B) Arbeits-Anfrage an Admin (Dich)
                \Illuminate\Support\Facades\Mail::to('kontakt@mein-seelenfunke.de')
                    ->send(new \App\Mail\OrderMailToAdmin($mailData, $pdfPath, $xmlPath));

            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Checkout Mail Fehler für {$order->order_number}: " . $e->getMessage());
            }
        }

        // 5. UI umschalten & Cleanup
        $this->isFinished = true;

        $cartService = app(CartService::class);
        $cart = $cartService->getCart();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        $this->dispatch('cart-updated');
        $this->dispatch('payment-processed');
    }
}
