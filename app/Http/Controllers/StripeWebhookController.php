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
            \App\Services\System\SystemSecurityService::logEvent(
                'security:webhook_signature_invalid',
                'Ungültige Stripe-Webhook-Signatur',
                'Ein Aufruf an /stripe/webhook wurde wegen ungültiger kryptografischer Signatur abgewiesen.',
                'warning'
            );
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
                if (isset($session->payment_status) && $session->payment_status !== 'paid') {
                    Log::info("Webhook checkout.session.completed: Session {$session->id} für Order {$order->order_number} noch nicht bezahlt (Status: {$session->payment_status}). Warte auf Zahlungseingang.");
                    return response('Success', 200);
                }

                if (isset($session->currency) && strtolower((string) $session->currency) !== 'eur') {
                    Log::error("Webhook checkout.session.completed: Währungsabweichung für Order {$order->order_number} (Session: {$session->currency}).");
                    return response('Error: Currency mismatch', 400);
                }

                if (isset($session->amount_total) && (int) $session->amount_total < (int) $order->total_price) {
                    Log::error("Webhook checkout.session.completed: Betragsabweichung für Order {$order->order_number} (Session: {$session->amount_total}, Order: {$order->total_price}).");
                    return response('Error: Amount mismatch', 400);
                }

                if (
                    !in_array($order->payment_status, ['paid', 'refunded'], true)
                    && !in_array($order->status, ['cancelled', 'refunded'], true)
                ) {
                    $order->completePayment($intentId);
                    Log::info("Webhook checkout.session.completed: Order {$order->order_number} erfolgreich als bezahlt verarbeitet.");
                } else {
                    Log::info("Webhook checkout.session.completed ignoriert: Order {$order->order_number} bereits bezahlt, storniert oder erstattet.");
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $session->metadata->quote_number ?? null;
                if ($quoteNumber && $order->payment_status === 'paid') {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if (
                        $quote
                        && $quote->status !== 'converted'
                        && empty($quote->converted_order_id)
                        && (int) $order->total_price >= (int) $quote->gross_total
                    ) {
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
                if (isset($intent->currency) && strtolower((string) $intent->currency) !== 'eur') {
                    Log::error("Webhook payment_intent.succeeded: Währungsabweichung für Order {$order->order_number} (Intent: {$intent->currency}).");
                    return response('Error: Currency mismatch', 400);
                }

                if (isset($intent->amount) && (int) $intent->amount < (int) $order->total_price) {
                    Log::error("Webhook payment_intent.succeeded: Betragsabweichung für Order {$order->order_number} (Intent: {$intent->amount}, Order: {$order->total_price}).");
                    return response('Error: Amount mismatch', 400);
                }

                if (
                    !in_array($order->payment_status, ['paid', 'refunded'], true)
                    && !in_array($order->status, ['cancelled', 'refunded'], true)
                ) {
                    $order->completePayment($intentId);
                    Log::info("Webhook payment_intent.succeeded: Order {$order->order_number} erfolgreich als bezahlt verarbeitet.");
                } else {
                    Log::info("Webhook payment_intent.succeeded ignoriert: Order {$order->order_number} bereits bezahlt, storniert oder erstattet.");
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $intent->metadata->quote_number ?? null;
                if ($quoteNumber && $order->payment_status === 'paid') {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if (
                        $quote
                        && $quote->status !== 'converted'
                        && empty($quote->converted_order_id)
                        && (int) $order->total_price >= (int) $quote->gross_total
                    ) {
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
        } elseif ($event->type === 'charge.refunded') {
            $charge = $event->data->object;
            $intentId = is_object($charge->payment_intent) ? ($charge->payment_intent->id ?? null) : $charge->payment_intent;

            $order = null;
            if (!empty($intentId)) {
                $order = OrderOrder::with('items.product')->where('stripe_payment_intent_id', $intentId)->first();
            }

            if ($order) {
                $isFullRefund = $charge->refunded ?? false;
                if ($isFullRefund) {
                    if ($order->payment_status === 'refunded' && $order->status === 'refunded') {
                        Log::info("Webhook charge.refunded ignoriert: Order {$order->order_number} bereits vollständig als erstattet verarbeitet.");
                        return response('Success', 200);
                    }

                    $previousStatus = $order->status;
                    $previousPaymentStatus = $order->payment_status;

                    // Vollständige Rückabwicklung (Lager Standardware, Spende, Gutscheine, Referral) VOR Status-Update
                    $order->handleCancellation('Stripe Webhook Rückerstattung', $previousStatus, $previousPaymentStatus);

                    $order->update([
                        'payment_status' => 'refunded',
                        'status' => 'refunded',
                    ]);
                    Log::info("Webhook charge.refunded: Order {$order->order_number} erfolgreich als erstattet verarbeitet.");
                } else {
                    Log::info("Webhook charge.refunded: Order {$order->order_number} wurde teilweise erstattet.");
                }
            } else {
                Log::warning("Webhook charge.refunded: Keine passende Order gefunden für Intent {$intentId}.");
            }
        } elseif ($event->type === 'payment_intent.canceled') {
            $intent = $event->data->object;
            $intentId = $intent->id ?? null;
            if ($intentId) {
                $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->where('payment_status', 'unpaid')->first();
                if ($order && $order->status === 'pending') {
                    $previousStatus = $order->status;
                    $previousPaymentStatus = $order->payment_status;
                    $order->handleCancellation('Stripe PaymentIntent abgebrochen', $previousStatus, $previousPaymentStatus);
                    $order->update([
                        'status' => 'cancelled',
                        'cancellation_reason' => 'Stripe PaymentIntent abgebrochen/abgelaufen'
                    ]);
                    Log::info("Webhook payment_intent.canceled: Unbezahlte Order {$order->order_number} storniert.");
                }
            }
        }

        return response('Success', 200);
    }
}
