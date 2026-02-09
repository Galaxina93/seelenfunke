<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceivedMail;
use App\Models\Order;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        // WICHTIG: Hier muss der Key aus 'services.php' übereinstimmen (services.stripe.webhook_secret)
        $endpoint_secret = config('services.stripe.webhook_secret');

        try {
            // 1. Signatur verifizieren (Sicherheit)
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch(SignatureVerificationException $e) {
            Log::error('Stripe Webhook Signatur Fehler: ' . $e->getMessage());
            return response('Error: Invalid Signature', 400);
        } catch(\UnexpectedValueException $e) {
            Log::error('Stripe Webhook Payload Fehler: ' . $e->getMessage());
            return response('Error: Invalid Payload', 400);
        }

        // 2. Auf das Event hören: checkout.session.completed
        if ($event->type == 'checkout.session.completed') {
            $session = $event->data->object;

            // Order ID aus den Metadata holen (haben wir beim Erstellen des Links dort gespeichert)
            $orderId = $session->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);

                // 3. Prüfen: Existiert die Order & ist sie noch NICHT bezahlt?
                // (Verhindert doppelte Verarbeitung bei Retries von Stripe)
                if ($order && $order->payment_status !== 'paid') {

                    // A) Status der BESTELLUNG aktualisieren
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'processing', // Wird nun bearbeitet
                        'stripe_payment_intent_id' => $session->payment_intent
                    ]);

                    Log::info("Webhook: Order {$order->order_number} wurde erfolgreich als bezahlt markiert.");

                    // B) RECHNUNG aktualisieren (falls vorhanden)
                    try {
                        $invoice = Invoice::where('order_id', $order->id)
                            ->where('type', 'invoice')
                            ->first();

                        if ($invoice && $invoice->status !== 'paid') {
                            // Status in DB ändern
                            $invoice->update([
                                'status' => 'paid',
                                'paid_at' => now(),
                            ]);

                            // PDF neu generieren (damit der "BEZAHLT"-Stempel drauf ist)
                            $invoiceService = app(InvoiceService::class);
                            $invoiceService->storePdf($invoice);

                            Log::info("Webhook: Rechnung {$invoice->invoice_number} auf 'paid' gesetzt und PDF aktualisiert.");
                        }
                    } catch (\Exception $e) {
                        Log::error("Webhook Rechnungs-Update Fehler: " . $e->getMessage());
                        // Wir brechen hier nicht ab, da die Zahlung der Order wichtiger ist
                    }

                    // C) Bestätigungsmail senden
                    try {
                        // Daten für die Mail aufbereiten
                        $mailData = $order->toFormattedArray();

                        // Mail an Kunden senden
                        Mail::to($order->email)->send(new PaymentReceivedMail($mailData));

                        Log::info("Webhook: Mail 'Zahlung erhalten' an {$order->email} versendet.");
                    } catch (\Exception $e) {
                        // Wichtig: Wir fangen Mail-Fehler ab, damit der Webhook an Stripe trotzdem "200 OK" meldet.
                        // Sonst würde Stripe denken, der Webhook ist fehlgeschlagen und sendet ihn stündlich erneut.
                        Log::error("Webhook Mail Fehler für Order {$order->order_number}: " . $e->getMessage());
                    }
                } else {
                    Log::info("Webhook ignoriert: Order {$orderId} nicht gefunden oder bereits bezahlt.");
                }
            }
        }

        return response('Success', 200);
    }
}
