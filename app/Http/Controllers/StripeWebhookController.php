<?php

namespace App\Http\Controllers;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        // Unterstützt sowohl services.stripe.webhook.secret als auch services.stripe.webhook_secret
        $endpoint_secret = config('services.stripe.webhook.secret') ?: config('services.stripe.webhook_secret');

        if (empty($endpoint_secret)) {
            Log::error('Stripe Webhook: Webhook Secret ist nicht konfiguriert.');
            return response('Error: Webhook secret not configured', 500);
        }

        try {
            // 1. Signatur verifizieren (Sicherheit)
            $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe Webhook Signatur Fehler: ' . $e->getMessage());
            return response('Error: Invalid Signature', 400);
        } catch (\UnexpectedValueException $e) {
            Log::error('Stripe Webhook Payload Fehler: ' . $e->getMessage());
            return response('Error: Invalid Payload', 400);
        }

        // 2. Auf relevante Events hören
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;
            $intentId = is_object($session->payment_intent) ? ($session->payment_intent->id ?? null) : $session->payment_intent;

            $order = null;
            if ($orderId) {
                $order = OrderOrder::find($orderId);
            }

            if (!$order && !empty($intentId)) {
                $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();
            }

            if ($order) {
                if ($order->payment_status !== 'paid') {
                    $order->completePayment($intentId);
                    Log::info("Webhook checkout.session.completed: Order {$order->order_number} erfolgreich als bezahlt verarbeitet.");
                } else {
                    Log::info("Webhook checkout.session.completed ignoriert: Order {$order->order_number} bereits bezahlt.");
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $session->metadata->quote_number ?? null;
                if ($quoteNumber) {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if ($quote && $quote->status !== 'converted') {
                        $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    }
                }
            } else {
                Log::warning("Webhook checkout.session.completed: Keine passende Order gefunden für Session {$session->id}.");
            }
        } elseif ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $orderId = $intent->metadata->order_id ?? null;
            $intentId = $intent->id;

            $order = null;
            if ($orderId) {
                $order = OrderOrder::find($orderId);
            }

            if (!$order && !empty($intentId)) {
                $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();
            }

            if ($order) {
                if ($order->payment_status !== 'paid') {
                    $order->completePayment($intentId);
                    Log::info("Webhook payment_intent.succeeded: Order {$order->order_number} erfolgreich als bezahlt verarbeitet.");
                } else {
                    Log::info("Webhook payment_intent.succeeded ignoriert: Order {$order->order_number} bereits bezahlt.");
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $intent->metadata->quote_number ?? null;
                if ($quoteNumber) {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if ($quote && $quote->status !== 'converted') {
                        $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    }
                }
            } else {
                Log::warning("Webhook payment_intent.succeeded: Keine passende Order gefunden für PaymentIntent {$intentId}.");
            }
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            $intentId = $intent->id ?? 'unknown';
            $errorMessage = $intent->last_payment_error->message ?? 'Zahlung fehlgeschlagen';
            Log::warning("Webhook payment_intent.payment_failed für Intent {$intentId}: {$errorMessage}");
        }

        return response('Success', 200);
    }
}
