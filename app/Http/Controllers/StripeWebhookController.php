<?php

namespace App\Http\Controllers;

use App\Models\Order\OrderOrder;
use App\Models\Order\OrderQuoteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public const WEBHOOK_PAID_INTENT_CACHE_PREFIX = 'stripe_webhook_pi_paid:';

    /**
     * Optionaler Hook für deterministische Race-Condition-Tests in PHPUnit ohne echtes usleep().
     */
    public static ?\Closure $testingOrderLookupRetryHook = null;

    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');

        // Unterstützt sowohl services.stripe.webhook.secret als auch services.stripe.webhook_secret sowie shop_setting
        $endpoint_secret = config('services.stripe.webhook.secret') ?: config('services.stripe.webhook_secret') ?: shop_setting('stripe_webhook_secret');

        if (empty($endpoint_secret)) {
            Log::error('Stripe Webhook: Webhook Secret ist nicht konfiguriert.');
            return response('Error: Webhook secret not configured', 500);
        }

        if (!is_string($sig_header) || trim($sig_header) === '') {
            return response('Error: Missing Signature', 400);
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

        $eventObject = $event->data->object ?? null;
        if (!is_object($eventObject)) {
            Log::warning('Stripe Webhook: Event enthält kein gültiges data.object.');
            return response('Error: Invalid Event Data', 400);
        }

        // 2. Auf relevante Events hören
        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $session = $eventObject;
            $orderId = $session->metadata->order_id ?? null;
            $intentId = is_object($session->payment_intent ?? null)
                ? ($session->payment_intent->id ?? null)
                : ($session->payment_intent ?? null);

            $order = null;
            if ($orderId) {
                $order = OrderOrder::find($orderId);
            }

            if (!$order && !empty($intentId)) {
                $order = OrderOrder::where('stripe_payment_intent_id', $intentId)->first();
            }

            if ($order) {
                if (isset($session->currency) && strtolower((string) $session->currency) !== 'eur') {
                    Log::error("Webhook {$event->type}: Währungsabweichung für Order {$order->order_number} (Session: {$session->currency}).");
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:webhook_currency_mismatch',
                        'Stripe-Webhook Währungsabweichung blockiert',
                        "Order {$order->order_number}: Erwartet EUR, erhalten {$session->currency}.",
                        'error',
                        ['order_id' => $order->id, 'currency' => $session->currency]
                    );
                    return response('Error: Currency mismatch', 400);
                }

                if (isset($session->amount_total) && (int) $session->amount_total < (int) $order->total_price) {
                    Log::error("Webhook {$event->type}: Betragsabweichung für Order {$order->order_number} (Session: {$session->amount_total}, Order: {$order->total_price}).");
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:webhook_amount_mismatch',
                        'Stripe-Webhook Betragsabweichung blockiert',
                        "Order {$order->order_number}: Erwartet {$order->total_price} Cent, erhalten {$session->amount_total} Cent.",
                        'error',
                        ['order_id' => $order->id, 'expected_cents' => (int) $order->total_price, 'received_cents' => (int) $session->amount_total]
                    );
                    return response('Error: Amount mismatch', 400);
                }

                if (isset($session->payment_status) && $session->payment_status !== 'paid') {
                    if (empty($order->stripe_payment_intent_id) && !empty($intentId) && $order->payment_status === 'unpaid') {
                        $order->update(['stripe_payment_intent_id' => $intentId]);
                    }
                    Log::info("Webhook {$event->type}: Session {$session->id} für Order {$order->order_number} noch nicht bezahlt (Status: {$session->payment_status}). Warte auf Zahlungseingang.");
                    return response('Success', 200);
                }

                if (
                    !in_array($order->payment_status, ['paid', 'refunded'], true)
                    && !in_array($order->status, ['cancelled', 'refunded'], true)
                ) {
                    $order->completePayment($intentId);
                    Log::info("Webhook {$event->type}: Order {$order->order_number} erfolgreich als bezahlt verarbeitet.");
                } else {
                    Log::info("Webhook {$event->type} ignoriert: Order {$order->order_number} bereits bezahlt, storniert oder erstattet.");
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $session->metadata->quote_number ?? null;
                if ($quoteNumber && $order->payment_status === 'paid') {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if (
                        $quote
                        && $quote->status !== 'converted'
                        && empty($quote->converted_order_id)
                        && ((int) $order->total_price + (int) ($order->discount_amount ?? 0)) >= (int) $quote->gross_total
                    ) {
                        $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    }
                }
            } else {
                Log::warning("Webhook {$event->type}: Keine passende Order gefunden für Session " . ($session->id ?? 'unknown') . '.');
            }
        } elseif ($event->type === 'payment_intent.succeeded') {
            $intent = $eventObject;
            $metadata = is_object($intent->metadata ?? null)
                ? $intent->metadata
                : (object) (is_array($intent->metadata ?? null) ? $intent->metadata : []);
            $orderId = isset($metadata->order_id) && trim((string) $metadata->order_id) !== ''
                ? trim((string) $metadata->order_id)
                : null;
            $intentId = isset($intent->id) && trim((string) $intent->id) !== ''
                ? trim((string) $intent->id)
                : null;
            $isForeignEnvironment = $this->isForeignEnvironmentMetadata($metadata);

            $order = $this->findOrderForPaymentIntent(
                $isForeignEnvironment ? null : $orderId,
                $intentId,
                !$isForeignEnvironment
            );

            if ($order) {
                if (isset($intent->currency) && strtolower((string) $intent->currency) !== 'eur') {
                    Log::error("Webhook payment_intent.succeeded: Währungsabweichung für Order {$order->order_number} (Intent: {$intent->currency}).");
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:webhook_currency_mismatch',
                        'Stripe-Webhook Währungsabweichung blockiert',
                        "Order {$order->order_number}: Erwartet EUR, erhalten {$intent->currency}.",
                        'error',
                        ['order_id' => $order->id, 'currency' => $intent->currency]
                    );
                    return response('Error: Currency mismatch', 400);
                }

                if (
                    (isset($intent->amount) && (int) $intent->amount < (int) $order->total_price)
                    || (isset($intent->amount_received) && (int) $intent->amount_received < (int) $order->total_price)
                ) {
                    $receivedCents = isset($intent->amount_received) ? (int) $intent->amount_received : (int) ($intent->amount ?? 0);
                    Log::error("Webhook payment_intent.succeeded: Betragsabweichung für Order {$order->order_number} (Intent: {$receivedCents}, Order: {$order->total_price}).");
                    \App\Services\System\SystemSecurityService::logEvent(
                        'security:webhook_amount_mismatch',
                        'Stripe-Webhook Betragsabweichung blockiert',
                        "Order {$order->order_number}: Erwartet {$order->total_price} Cent, erhalten {$receivedCents} Cent.",
                        'error',
                        ['order_id' => $order->id, 'expected_cents' => (int) $order->total_price, 'received_cents' => $receivedCents]
                    );
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

                if (!empty($intentId)) {
                    Cache::forget(self::WEBHOOK_PAID_INTENT_CACHE_PREFIX . $intentId);
                }

                // Falls aus Angebot (Quote) entstanden
                $quoteNumber = $metadata->quote_number ?? null;
                if ($quoteNumber && $order->payment_status === 'paid') {
                    $quote = OrderQuoteRequest::where('quote_number', $quoteNumber)->first();
                    if (
                        $quote
                        && $quote->status !== 'converted'
                        && empty($quote->converted_order_id)
                        && ((int) $order->total_price + (int) ($order->discount_amount ?? 0)) >= (int) $quote->gross_total
                    ) {
                        $quote->update(['status' => 'converted', 'converted_order_id' => $order->id]);
                    }
                }
            } else {
                if (!empty($intentId) && !$isForeignEnvironment) {
                    $verifiedAmount = isset($intent->amount) ? (int) $intent->amount : 0;
                    $verifiedAmountReceived = isset($intent->amount_received) ? (int) $intent->amount_received : $verifiedAmount;
                    $verifiedCurrency = isset($intent->currency) ? strtolower(trim((string) $intent->currency)) : 'eur';

                    Cache::put(
                        self::WEBHOOK_PAID_INTENT_CACHE_PREFIX . $intentId,
                        [
                            'intent_id' => $intentId,
                            'event_id' => is_string($event->id ?? null) ? $event->id : null,
                            'amount' => $verifiedAmount,
                            'amount_received' => $verifiedAmountReceived,
                            'currency' => $verifiedCurrency,
                            'quote_number' => isset($metadata->quote_number) ? (string) $metadata->quote_number : null,
                            'checkout_flow' => isset($metadata->checkout_flow) ? (string) $metadata->checkout_flow : null,
                            'app_env' => isset($metadata->app_env) ? (string) $metadata->app_env : (string) config('app.env', ''),
                            'verified_at' => now()->toIso8601String(),
                        ],
                        now()->addHours(2)
                    );
                }

                if (!$isForeignEnvironment && $orderId !== null) {
                    Log::warning("Webhook payment_intent.succeeded: Keine passende Order gefunden für PaymentIntent {$intentId} (order_id: {$orderId}).");
                }
            }
        } elseif ($event->type === 'payment_intent.payment_failed') {
            $intent = $eventObject;
            $intentId = $intent->id ?? 'unknown';
            $errorMessage = $intent->last_payment_error->message ?? 'Zahlung fehlgeschlagen';
            Log::warning("Webhook payment_intent.payment_failed für Intent {$intentId}: {$errorMessage}");
        } elseif ($event->type === 'charge.refunded') {
            $charge = $eventObject;
            $intentId = is_object($charge->payment_intent ?? null)
                ? ($charge->payment_intent->id ?? null)
                : ($charge->payment_intent ?? null);
            $metaOrderId = $charge->metadata->order_id ?? null;

            $order = null;
            if (!empty($metaOrderId)) {
                $order = OrderOrder::with(['items.product', 'invoices'])->find($metaOrderId);
            }
            if (!$order && !empty($intentId)) {
                $order = OrderOrder::with(['items.product', 'invoices'])->where('stripe_payment_intent_id', $intentId)->first();
            }

            if ($order) {
                app(\App\Services\StripeAgentExecutionService::class)->syncWebhookRefund(
                    $order,
                    $charge,
                    is_string($event->id ?? null) ? $event->id : null
                );
                Log::info("Webhook charge.refunded: Order {$order->order_number} idempotent mit GoBD-Gutschrift synchronisiert.");
            } else {
                Log::warning("Webhook charge.refunded: Keine passende Order gefunden für Intent {$intentId}.");
            }
        } elseif ($event->type === 'charge.dispute.created') {
            $dispute = $eventObject;
            $disputeId = (string) ($dispute->id ?? 'unknown');
            $secActionId = 'security:stripe_dispute_created:' . $disputeId;
            if (
                !\Illuminate\Support\Facades\Schema::hasTable('system_logs')
                || !\App\Models\System\SystemLog::query()->where('action_id', $secActionId)->exists()
            ) {
                \App\Services\System\SystemSecurityService::logEvent(
                    $secActionId,
                    'Stripe Chargeback / Dispute eingegangen',
                    "Für Stripe Dispute {$disputeId} wurde eine Rückbuchungsanfrage gemeldet.",
                    'warning',
                    [
                        'dispute_id' => $disputeId,
                        'amount_cents' => (int) ($dispute->amount ?? 0),
                        'reason' => (string) ($dispute->reason ?? 'unknown'),
                    ]
                );
            }
            app(\App\Services\StripeAgentExecutionService::class)->handleWebhookDisputeCreated(
                $dispute,
                is_string($event->id ?? null) ? $event->id : null
            );
            Log::warning('Webhook charge.dispute.created verarbeitet: ' . $disputeId);
        } elseif ($event->type === 'charge.dispute.closed') {
            $dispute = $eventObject;
            app(\App\Services\StripeAgentExecutionService::class)->handleWebhookDisputeClosed(
                $dispute,
                is_string($event->id ?? null) ? $event->id : null
            );
            Log::info('Webhook charge.dispute.closed verarbeitet: ' . ($dispute->id ?? 'unknown'));
        } elseif ($event->type === 'payout.paid') {
            $payout = $eventObject;
            app(\App\Services\StripeAgentExecutionService::class)->handleWebhookPayoutPaid(
                $payout,
                is_string($event->id ?? null) ? $event->id : null
            );
            Log::info('Webhook payout.paid für FYRST-Abgleich vorgemerkt: ' . ($payout->id ?? 'unknown'));
        } elseif ($event->type === 'payment_intent.canceled') {
            $intent = $eventObject;
            $intentId = $intent->id ?? null;
            $metaOrderId = $intent->metadata->order_id ?? null;
            $order = null;

            if ($intentId) {
                $order = OrderOrder::where('stripe_payment_intent_id', $intentId)
                    ->where('payment_status', 'unpaid')
                    ->first();
            }

            if (!$order && !empty($metaOrderId)) {
                $candidate = OrderOrder::find($metaOrderId);
                if (
                    $candidate
                    && $candidate->payment_status === 'unpaid'
                    && (empty($candidate->stripe_payment_intent_id) || $candidate->stripe_payment_intent_id === $intentId)
                ) {
                    $order = $candidate;
                }
            }

            if ($order && $order->payment_status === 'unpaid' && $order->status === 'pending') {
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

        return response('Success', 200);
    }

    /**
     * Sucht die zugehörige Order und führt bei gleicher Umgebung bis zu 2 kurze Nachprüfungen durch,
     * falls HandlesOrderCreation im parallelen Livewire-Request wenige Millisekunden später committet.
     */
    private function findOrderForPaymentIntent(?string $orderId, ?string $intentId, bool $allowRetry): ?OrderOrder
    {
        $lookup = static function () use ($orderId, $intentId): ?OrderOrder {
            $order = null;
            if (!empty($orderId)) {
                $order = OrderOrder::useWritePdo()->find($orderId);
            }

            if (!$order && !empty($intentId)) {
                $order = OrderOrder::useWritePdo()->where('stripe_payment_intent_id', $intentId)->first();
            }

            return $order;
        };

        $order = $lookup();
        if ($order || !$allowRetry || (empty($orderId) && empty($intentId))) {
            return $order;
        }

        $maxRetries = max(0, (int) config('services.stripe.webhook_lookup_retries', 2));
        $defaultDelayUs = app()->runningUnitTests() ? 0 : 350000;
        $delayUs = max(0, (int) config('services.stripe.webhook_lookup_retry_delay_us', $defaultDelayUs));

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            if (self::$testingOrderLookupRetryHook instanceof \Closure) {
                (self::$testingOrderLookupRetryHook)($attempt, $intentId, $orderId);
            } elseif ($delayUs > 0) {
                usleep($delayUs);
            }

            $order = $lookup();
            if ($order) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Erkennt PaymentIntents aus einer anderen App-Umgebung (z.B. lokaler Testkauf im geteilten Stripe-Testkonto).
     */
    private function isForeignEnvironmentMetadata(?object $metadata): bool
    {
        if (!$metadata) {
            return false;
        }

        $metaAppEnv = trim((string) ($metadata->app_env ?? ''));
        $currentAppEnv = trim((string) config('app.env', ''));
        if ($metaAppEnv !== '' && $currentAppEnv !== '' && strcasecmp($metaAppEnv, $currentAppEnv) !== 0) {
            return true;
        }

        $metaAppUrl = rtrim(strtolower(trim((string) ($metadata->app_url ?? ''))), '/');
        $currentAppUrl = rtrim(strtolower(trim((string) config('app.url', ''))), '/');
        if ($metaAppUrl !== '' && $currentAppUrl !== '' && $metaAppUrl !== $currentAppUrl) {
            return true;
        }

        return false;
    }
}
