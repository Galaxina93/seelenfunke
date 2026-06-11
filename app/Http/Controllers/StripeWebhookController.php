<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceivedMail;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Order\OrderOrder;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

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
                $order = OrderOrder::find($orderId);

                // 3. Prüfen: Existiert die Order & ist sie noch NICHT bezahlt?
                // (Verhindert doppelte Verarbeitung bei Retries von Stripe)
                if ($order && $order->payment_status !== 'paid') {

                    // A) Bestellung abschließen (Lagerbestand reduzieren, Gutscheine generieren, Coupons entwerten, Mails versenden)
                    $order->completePayment($session->payment_intent);

                    Log::info("Webhook: OrderOrder {$order->order_number} wurde erfolgreich als bezahlt markiert und verarbeitet.");
                } else {
                    Log::info("Webhook ignoriert: OrderOrder {$orderId} nicht gefunden oder bereits bezahlt.");
                }
            }
        }

        return response('Success', 200);
    }
}
