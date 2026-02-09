<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceivedMail;
use App\Models\Order;
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
        $endpoint_secret = config('services.stripe.webhook_secret'); // Deine Webhook Secret aus .env

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

                    // A) Status in Datenbank aktualisieren
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'processing', // Wird nun bearbeitet
                        'stripe_payment_intent_id' => $session->payment_intent
                    ]);

                    Log::info("Webhook: Order {$order->order_number} wurde erfolgreich bezahlt.");

                    // B) Bestätigungsmail senden
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
