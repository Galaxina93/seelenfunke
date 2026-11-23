<?php

namespace App\Services\AI\Functions;

use App\Services\AI\AiAuthHelper;
use App\Services\AI\FunkiraSkillPoolService;
use App\Services\StripeAgentExecutionService;
use App\Services\System\SystemSecurityService;

trait AiStripeFuncs
{
    public static function getAiStripeFuncsSchema(): array
    {
        return [
            [
                'name' => 'stripe_create_checkout_link',
                'description' => 'Erstellt autonom einen Stripe-Checkout-Zahlungslink für ein Angebot (OrderQuoteRequest), eine bestehende unbezahlte Bestellung oder Direktpositionen inklusive Prüfung aktiver Versandzonen (LogisticsShippingZone) und 19 % USt. in Cent.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'quote_number' => [
                            'type' => 'string',
                            'description' => 'Optional: Angebotsnummer (z. B. ANG-2026-10001), aus der Positionen und Kundendaten übernommen werden.',
                        ],
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Optional: Bestehende unbezahlte Bestellnummer (z. B. ORD-2026-ABCDEF).',
                        ],
                        'customer_email' => [
                            'type' => 'string',
                            'description' => 'E-Mail-Adresse des Kunden für den Checkout und optionalen Direktversand.',
                        ],
                        'customer_first_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Vorname des Kunden.',
                        ],
                        'customer_last_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Nachname des Kunden.',
                        ],
                        'shipping_country' => [
                            'type' => 'string',
                            'description' => 'Zweistelliger ISO-Ländercode des Lieferlandes (Standard: DE). Muss in einer aktiven Versandzone liegen.',
                        ],
                        'items' => [
                            'type' => 'array',
                            'description' => 'Optional: Direktpositionen mit name, quantity, unit_price_cents und optional product_id.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'product_id' => ['type' => 'string'],
                                    'name' => ['type' => 'string'],
                                    'quantity' => ['type' => 'integer'],
                                    'unit_price_cents' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                        'shipping_cents' => [
                            'type' => 'integer',
                            'description' => 'Optional: Explizite Versandkosten in Cent (sonst automatische Berechnung via ShippingCalculatorService).',
                        ],
                        'expires_in_hours' => [
                            'type' => 'integer',
                            'description' => 'Optional: Gültigkeitsdauer des Links in Stunden (Standard: 24).',
                        ],
                        'send_email' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Wenn true, wird der Zahlungslink direkt per E-Mail an den Kunden gesendet.',
                        ],
                        'idempotency_key' => [
                            'type' => 'string',
                            'description' => 'Optional: Deterministischer Idempotency-Key gegen Doppelbuchungen.',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeCreateCheckoutLink'],
            ],
            [
                'name' => 'stripe_execute_refund',
                'description' => 'Führt eine autonome Voll- oder Teilerstattung in Cent auf den Stripe PaymentIntent einer Bestellung aus, aktualisiert OrderOrder/OrderRevocation und erstellt idempotent die GoBD-Gutschrift (AccountingInvoice type=credit_note). Oberhalb der Sicherheitslimits wird automatisch eine Freigabe-Aufgabe für Alina angelegt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Bestellnummer der zu erstattenden Bestellung (z. B. ORD-2026-ABCDEF).',
                        ],
                        'amount_cents' => [
                            'type' => 'integer',
                            'description' => 'Optional: Zu erstattender Betrag in Cent (<= rest_refundable_cents). Wenn leer, wird der volle offene Restbetrag erstattet.',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Erstattungsgrund: requested_by_customer, duplicate oder defective.',
                            'enum' => ['requested_by_customer', 'duplicate', 'defective'],
                        ],
                        'idempotency_key' => [
                            'type' => 'string',
                            'description' => 'Optional: Deterministischer Idempotency-Key.',
                        ],
                    ],
                    'required' => ['order_number'],
                ],
                'callable' => [self::class, 'executeStripeExecuteRefund'],
            ],
            [
                'name' => 'stripe_charge_saved_mandate',
                'description' => 'Bucht eine Folge- oder B2B-Bestellung über ein gespeichertes Off-Session-Mandat bei Stripe ab. Bei PSD2/SCA 3D-Secure-Anforderung (authentication_required) wird automatisch ein 1-Klick-Checkout-Link erzeugt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Bestellnummer der offenen Bestellung.',
                        ],
                        'customer_id' => [
                            'type' => 'string',
                            'description' => 'Stripe Customer-ID (cus_...) mit hinterlegtem Mandat.',
                        ],
                        'payment_method_id' => [
                            'type' => 'string',
                            'description' => 'Gespeicherte Stripe PaymentMethod-ID (pm_...).',
                        ],
                        'amount_cents' => [
                            'type' => 'integer',
                            'description' => 'Optional: Abzubuchender Betrag in Cent (Standard: Gesamtbetrag der Bestellung).',
                        ],
                        'send_email' => [
                            'type' => 'boolean',
                            'description' => 'Optional: Bei SCA-Fallback automatisch den Bestätigungslink per Mail senden (Standard: true).',
                        ],
                    ],
                    'required' => ['order_number', 'customer_id', 'payment_method_id'],
                ],
                'callable' => [self::class, 'executeStripeChargeSavedMandate'],
            ],
            [
                'name' => 'stripe_reconcile_payouts_fees',
                'description' => 'Liest Stripe Payouts und BalanceTransactions für einen Zeitraum aus, prüft Dubletten und liefert die Cent-genaue Aufschlüsselung von Bruttoumsatz, Erstattungen und Stripe-Gebühren für den FYRST-Bankabgleich.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Startdatum im Format YYYY-MM-DD (Standard: vor 30 Tagen).',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'Enddatum im Format YYYY-MM-DD (Standard: heute).',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeReconcilePayoutsFees'],
            ],
            [
                'name' => 'stripe_defend_dispute',
                'description' => 'Bündelt Rechnung, Gravur-Freigabe, AGB-Bestätigung und DHL-Sendungsnummer (OrderShipment) für einen Stripe-Konfliktfall (Dispute), lädt das Beweispaket zu Stripe hoch und erstellt eine Prioritäts-Aufgabe im Dashboard.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dispute_id' => [
                            'type' => 'string',
                            'description' => 'Stripe Dispute-ID (dp_...). Optional, falls order_number angegeben ist.',
                        ],
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Bestellnummer der betroffenen Bestellung.',
                        ],
                        'submit_immediately' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, wird das Beweispaket sofort verbindlich bei Stripe eingereicht (Standard: false als geprüfter Entwurf).',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeDefendDispute'],
            ],
            [
                'name' => 'stripe_get_live_telemetry',
                'description' => 'Liefert DSGVO-konforme Echtzeit-Telemetrie über das verfügbare und ausstehende Stripe-Guthaben (in Cent), letzte Zahlungen, Fehlversuche, offene Konfliktfälle und aktive Sicherheitslimits.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximale Anzahl geladener Einzeltransaktionen (1 bis 25, Standard: 10).',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeGetLiveTelemetry'],
            ],
            [
                'name' => 'stripe_diagnose_and_manage_webhooks',
                'description' => 'Liest alle registrierten Stripe-Webhook-Endpunkte (/v1/webhook_endpoints) aus, erkennt Fehlkonfigurationen (z. B. Sandbox-Endpunkt auf Live-Domain statt Stage) und kann Endpunkte per auto_heal, update_url, disable oder delete direkt reparieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'action' => [
                            'type' => 'string',
                            'description' => 'Aktion: diagnose (Standard), auto_heal, update_url, disable oder delete.',
                            'enum' => ['diagnose', 'auto_heal', 'update_url', 'disable', 'delete'],
                        ],
                        'endpoint_id' => [
                            'type' => 'string',
                            'description' => 'Optional: Stripe Webhook-Endpoint-ID (we_...) für update_url, disable oder delete.',
                        ],
                        'target_url' => [
                            'type' => 'string',
                            'description' => 'Optional: Ziel-URL aus der Seelenfunke-Whitelist für update_url.',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeDiagnoseAndManageWebhooks'],
            ],
            [
                'name' => 'stripe_sync_missed_events',
                'description' => 'Prüft die Stripe-Ereignisliste (/v1/events) der letzten Tage auf bezahlte Checkout-Sessions, PaymentIntents und offene Konfliktfälle und bucht eventuell durch Webhook-Störungen (z. B. HTTP 503) verpasste Zahlungen idempotent in OrderOrder nach.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'days_back' => [
                            'type' => 'integer',
                            'description' => 'Rückblick-Zeitraum in Tagen (1 bis 30, Standard: 10).',
                        ],
                        'dry_run' => [
                            'type' => 'boolean',
                            'description' => 'Wenn true, werden fehlende Zahlungen nur simuliert gemeldet ohne DB-Schreibzugriff (Standard: false).',
                        ],
                    ],
                    'required' => [],
                ],
                'callable' => [self::class, 'executeStripeSyncMissedEvents'],
            ],
        ];
    }

    public static function executeStripeCreateCheckoutLink(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_create_checkout_link', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->createCheckoutLink($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeExecuteRefund(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_execute_refund', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->executeRefund($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeChargeSavedMandate(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_charge_saved_mandate', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->chargeSavedMandate($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeReconcilePayoutsFees(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_reconcile_payouts_fees', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->reconcilePayoutsAndFees($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeDefendDispute(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_defend_dispute', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->defendDispute($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeGetLiveTelemetry(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_get_live_telemetry', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->getLiveTelemetry($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeDiagnoseAndManageWebhooks(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_diagnose_and_manage_webhooks', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->diagnoseAndManageWebhooks($args, self::resolveStripeActorName($agent));
    }

    public static function executeStripeSyncMissedEvents(array $args = [], $agent = null): array
    {
        if ($violation = self::guardStripeExecutiveAccess('stripe_sync_missed_events', $args, $agent)) {
            return $violation;
        }

        return app(StripeAgentExecutionService::class)
            ->syncMissedEvents($args, self::resolveStripeActorName($agent));
    }

    /**
     * Zentrale Zero-Trust- & Air-Gap-Prüfung innerhalb jeder einzelnen Stripe-Tool-Methode:
     * 1. Öffentlicher Support-Chat / Funki -> sofort SANDBOX VIOLATION (403)
     * 2. Nicht-Admin (Gast, Customer, Influencer, Steuerberater, Mitarbeiter) -> sofort ROLE VIOLATION (403)
     * 3. Nur Executive-Root-Agenten (Falcone, Falcon, Funkira) -> sonst ROLE VIOLATION (403)
     */
    private static function guardStripeExecutiveAccess(string $toolName, array $args = [], $agent = null): ?array
    {
        $rawAgentName = self::extractAgentName($agent);

        if (strcasecmp($rawAgentName, 'Funki') === 0 || !empty($args['__chat_id']) || !empty(self::$globalContext['__chat_id'])) {
            SystemSecurityService::logEvent(
                'security:ai_stripe_airgap_blocked',
                'Stripe Air-Gap-Schutz griff ein',
                "Unzulässiger Aufruf von '{$toolName}' im öffentlichen oder Funki-Kontext blockiert.",
                'error',
                ['tool' => $toolName, 'agent' => $rawAgentName]
            );

            return [
                'error' => true,
                'status' => 'error',
                'http_status' => 403,
                'message' => "SANDBOX VIOLATION: Der öffentliche Support-Agent 'Funki' und öffentliche Chats haben keinerlei Zugriff auf '{$toolName}'.",
            ];
        }

        if (!AiAuthHelper::isAdmin()) {
            $actorId = AiAuthHelper::getUserId() ?? AiAuthHelper::getEmployeeId() ?? 'guest';
            SystemSecurityService::logEvent(
                'security:ai_stripe_role_violation',
                'Unautorisierter Stripe-Tool-Zugriff blockiert',
                "Nicht-Administrator ({$actorId}) versuchte '{$toolName}' auszuführen.",
                'error',
                ['tool' => $toolName, 'actor_id' => $actorId]
            );

            return [
                'error' => true,
                'status' => 'error',
                'http_status' => 403,
                'message' => "ROLE VIOLATION: Das Werkzeug '{$toolName}' erfordert Administrator-Berechtigungen (403).",
            ];
        }

        if (!FunkiraSkillPoolService::isExecutiveRoot($agent)) {
            SystemSecurityService::logEvent(
                'security:ai_stripe_non_executive_blocked',
                'Stripe-Tool durch Nicht-Executive-Agent blockiert',
                "Agent '{$rawAgentName}' besitzt keine Executive-Root-Freigabe für '{$toolName}'.",
                'error',
                ['tool' => $toolName, 'agent' => $rawAgentName]
            );

            return [
                'error' => true,
                'status' => 'error',
                'http_status' => 403,
                'message' => "ROLE VIOLATION: Nur Executive-Root-Agenten (Funkira) dürfen '{$toolName}' ausführen (403).",
            ];
        }

        return null;
    }

    private static function extractAgentName($agent): string
    {
        if (is_string($agent)) {
            return trim($agent);
        }
        if (is_object($agent) && (isset($agent->name) || property_exists($agent, 'name'))) {
            return trim((string) ($agent->name ?? ''));
        }
        if (is_array($agent) && isset($agent['name'])) {
            return trim((string) $agent['name']);
        }

        return '';
    }

    private static function resolveStripeActorName($agent): string
    {
        $name = self::extractAgentName($agent);
        return $name !== '' ? $name : 'Funkira';
    }
}
