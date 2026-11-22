<?php

namespace App\Services\AI;

use App\Models\Customer\Customer;
use App\Models\Management\Mail\MailMessage;
use App\Models\Order\OrderOrder;
use App\Models\Order\OrderRevocation;
use App\Models\Product\Product;
use App\Models\Support\SupportTicket;
use Carbon\Carbon;

class ContextDossierBuilder
{
    /**
     * Baut in < 10 ms ein verifiziertes Bestell-Fakten-Dossier direkt aus MySQL zusammen.
     * Respektiert strikt den DSGVO-KI-Einwilligungsvorbehalt (Baustein 8).
     *
     * @param OrderOrder|string $orderOrIdentifier
     * @return array<string, mixed>|null
     */
    public static function buildOrderDossier($orderOrIdentifier): ?array
    {
        if ($orderOrIdentifier instanceof OrderOrder) {
            $order = $orderOrIdentifier->loadMissing(['items.product', 'shipments', 'customer.profile']);
        } else {
            $cleanId = ltrim(trim((string) $orderOrIdentifier), '#');
            if ($cleanId === '') {
                return null;
            }
            $order = OrderOrder::with(['items.product', 'shipments', 'customer.profile'])
                ->where('order_number', $cleanId)
                ->orWhere('id', $cleanId)
                ->first();
        }

        if (!$order) {
            return null;
        }

        $customer = $order->customer;
        $hasConsent = AiAuthHelper::hasCustomerAiConsent($customer, $order->customer_id);

        $customerDisplay = 'Anonyme Person (Keine DSGVO-KI-Freigabe)';
        $customerEmail = '[DSGVO-geschützt]';
        $shippingCity = '[DSGVO-geschützt]';

        if ($hasConsent) {
            if ($customer) {
                $fullName = trim($customer->first_name . ' ' . $customer->last_name);
                $customerDisplay = $fullName !== '' ? $fullName : 'Kunde';
                $customerEmail = (string) ($customer->email ?? '');
            } elseif (!empty($order->shipping_address) && is_array($order->shipping_address)) {
                $customerDisplay = trim(($order->shipping_address['first_name'] ?? '') . ' ' . ($order->shipping_address['last_name'] ?? '')) ?: 'Gastbesteller';
            }
            if (!empty($order->shipping_address) && is_array($order->shipping_address)) {
                $shippingCity = trim(($order->shipping_address['postal_code'] ?? $order->shipping_address['zipcode'] ?? '') . ' ' . ($order->shipping_address['city'] ?? ''));
            }
        }

        $totalCents = (int) ($order->total_price ?? $order->grand_total ?? 0);
        $itemsSummary = [];
        foreach ($order->items as $item) {
            $prodName = $item->product?->name ?? $item->product_name ?? 'Manufaktur-Artikel';
            $hasCustomConfig = !empty($item->configuration);
            $itemsSummary[] = [
                'quantity' => (int) ($item->quantity ?? 1),
                'product_name' => (string) $prodName,
                'is_personalized' => $hasCustomConfig,
                'unit_price_eur' => number_format(((int) ($item->unit_price ?? 0)) / 100, 2, ',', '.'),
            ];
        }

        $trackingNumbers = [];
        if ($order->relationLoaded('shipments') && $order->shipments) {
            foreach ($order->shipments as $shipment) {
                if (!empty($shipment->tracking_number)) {
                    $trackingNumbers[] = (string) $shipment->tracking_number;
                }
            }
        }
        if (empty($trackingNumbers) && !empty($order->tracking_number)) {
            $trackingNumbers[] = (string) $order->tracking_number;
        }

        $hasOpenRevocation = OrderRevocation::where('order_number', $order->order_number)
            ->whereIn('status', ['open', 'pending', 'in_progress'])
            ->exists();

        $createdAtStr = $order->created_at instanceof Carbon
            ? $order->created_at->format('Y-m-d H:i')
            : (string) $order->created_at;

        return [
            'order_id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'status' => (string) $order->status,
            'payment_status' => (string) ($order->payment_status ?? 'unbekannt'),
            'is_express' => (bool) ($order->is_express ?? false),
            'created_at' => $createdAtStr,
            'total_cents' => $totalCents,
            'total_eur' => number_format($totalCents / 100, 2, ',', '.'),
            'gdpr_ai_consent' => $hasConsent,
            'customer_display' => $customerDisplay,
            'customer_email' => $customerEmail,
            'shipping_city' => $shippingCity,
            'items' => $itemsSummary,
            'tracking_numbers' => array_values(array_unique($trackingNumbers)),
            'has_open_revocation' => $hasOpenRevocation,
        ];
    }

    /**
     * Baut ein Prompt-Injection-bereinigtes E-Mail-Dossier inklusive verknüpfter
     * Bestellungen, Support-Tickets und Anhänge.
     *
     * @param MailMessage|int|string $mailOrId
     * @return array<string, mixed>|null
     */
    public static function buildMailDossier($mailOrId): ?array
    {
        $mail = $mailOrId instanceof MailMessage
            ? $mailOrId->loadMissing('attachments')
            : MailMessage::with('attachments')->find($mailOrId);

        if (!$mail) {
            return null;
        }

        $rawBody = (string) ($mail->body_plain ?: strip_tags((string) $mail->body_html));
        $safeBody = self::sanitizeExternalUntrustedText($rawBody, 2500);
        $safeSubject = self::sanitizeExternalUntrustedText((string) $mail->subject, 250);

        $fromEmail = strtolower(trim((string) $mail->from_email));
        $customer = $fromEmail !== '' ? Customer::with('profile')->where('email', $fromEmail)->first() : null;
        $hasConsent = $customer ? AiAuthHelper::hasCustomerAiConsent($customer, $customer->id) : false;

        $linkedOrders = [];
        if (preg_match_all('/\b(ORD-[A-Z0-9\-]{4,30})\b/iu', $safeSubject . ' ' . $safeBody, $matches)) {
            foreach (array_unique($matches[1]) as $ordNum) {
                $dossier = self::buildOrderDossier(strtoupper($ordNum));
                if ($dossier) {
                    $linkedOrders[$dossier['order_number']] = $dossier;
                }
            }
        }

        if ($customer && count($linkedOrders) < 3) {
            $recentOrders = OrderOrder::where('customer_id', $customer->id)
                ->latest()
                ->take(3)
                ->get();
            foreach ($recentOrders as $ro) {
                if (!isset($linkedOrders[$ro->order_number])) {
                    $dossier = self::buildOrderDossier($ro);
                    if ($dossier) {
                        $linkedOrders[$dossier['order_number']] = $dossier;
                    }
                }
            }
        }

        $openTickets = [];
        if ($customer) {
            $openTickets = SupportTicket::where('customer_id', $customer->id)
                ->whereIn('status', ['open', 'in_progress', 'waiting'])
                ->latest()
                ->take(3)
                ->get(['ticket_number', 'subject', 'status'])
                ->toArray();
        }

        $attachments = [];
        foreach ($mail->attachments as $att) {
            $attachments[] = [
                'id' => (int) $att->id,
                'filename' => (string) ($att->filename ?? basename((string) $att->path)),
                'mime_type' => (string) ($att->mime_type ?? ''),
                'path' => (string) ($att->path ?? ''),
            ];
        }

        return [
            'mail_id' => (int) $mail->id,
            'subject' => $safeSubject,
            'received_at' => $mail->received_at ? Carbon::parse($mail->received_at)->format('Y-m-d H:i') : '',
            'sender_display' => $customer
                ? ($hasConsent ? (trim($customer->first_name . ' ' . $customer->last_name) ?: 'Kunde') : 'Anonyme Person (Keine DSGVO-KI-Freigabe)')
                : ($hasConsent ? (string) ($mail->from_name ?: 'Absender') : 'Externe Anfrage'),
            'gdpr_ai_consent' => $hasConsent,
            'body_excerpt' => $safeBody,
            'attachments' => $attachments,
            'linked_orders' => array_values($linkedOrders),
            'open_tickets' => $openTickets,
        ];
    }

    /**
     * Liefert ein kompaktes Rohling- & Bestands-Dossier für Einkaufi-Analysen.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function buildLowInventoryDossier(int $limit = 10): array
    {
        $products = Product::with('supplier')
            ->where('type', 'physical')
            ->where('status', 'active')
            ->orderBy('quantity', 'asc')
            ->take($limit * 2)
            ->get();

        $lowItems = [];
        foreach ($products as $prod) {
            $qty = (int) ($prod->quantity ?? 0);
            $minStock = max(1, (int) ($prod->min_stock ?? 5));
            if ($qty <= $minStock) {
                $lowItems[] = [
                    'product_name' => (string) $prod->name,
                    'sku' => (string) ($prod->sku ?? ''),
                    'current_stock' => $qty,
                    'min_stock' => $minStock,
                    'recommended_reorder_qty' => max(10, ($minStock * 3) - $qty),
                    'supplier' => (string) ($prod->supplier?->name ?? 'Nicht hinterlegt'),
                ];
            }
            if (count($lowItems) >= $limit) {
                break;
            }
        }

        return $lowItems;
    }

    /**
     * Formatiert ein Dossier-Array kompakt als strukturierten Prompt-Block.
     *
     * @param array<string, mixed> $dossiers
     */
    public static function formatAsPromptBlock(array $dossiers): string
    {
        if (empty($dossiers)) {
            return '';
        }

        return "\n[VERIFIZIERTES PHP-FAKTEN-DOSSIER (100 % MY-SQL ECHTDATEN, NICHT RATEN)]\n"
            . json_encode($dossiers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n[/VERIFIZIERTES PHP-FAKTEN-DOSSIER]\n";
    }

    /**
     * Neutralisiert Prompt-Injection-Muster, Steuer-Tags (<fw_action>, [SYSTEM...]),
     * unsichtbare Zero-Width- sowie BiDi-Override-Zeichen in externen Texten vor Übergabe an ein LLM.
     */
    public static function sanitizeExternalUntrustedText(string $text, int $maxLength = 2500): string
    {
        $clean = self::stripPromptInjectionPayloads($text);
        $clean = preg_replace('/\s+/u', ' ', trim($clean)) ?? '';

        return mb_substr($clean, 0, $maxLength);
    }

    /**
     * Bereinigt mehrzeilige Dokument-/OCR-Texte vor der Speicherung im Dateitrichter oder der
     * AiKnowledgeBase (Schutz gegen indirekte RAG-Prompt-Injection auf Funkira & Falcone).
     */
    public static function sanitizeUntrustedDocumentForRag(string $text, int $maxLength = 60000): string
    {
        $clean = self::stripPromptInjectionPayloads($text);
        $clean = str_replace('```', "'''", $clean);
        $clean = preg_replace("/\r\n|\r/u", "\n", $clean) ?? $clean;
        $clean = preg_replace("/[ \t]+/u", ' ', $clean) ?? $clean;
        $clean = preg_replace("/\n{4,}/u", "\n\n\n", trim($clean)) ?? '';

        return mb_substr($clean, 0, $maxLength);
    }

    /**
     * Prüft deterministisch, ob ein externer Text verdächtige Prompt-Injection- oder Tool-Hijack-Muster enthält.
     */
    public static function containsPromptInjectionAttempt(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        if (preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 1) {
            return true;
        }

        return preg_match(self::promptInjectionPattern(), $text) === 1;
    }

    private static function stripPromptInjectionPayloads(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Unsichtbare Zero-Width- & BiDi-Spoofing-Zeichen (inkl. RLO \u202E für Dateiendungs-Tarnung) entfernen
        $clean = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}\x{00AD}\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', '', $text) ?? $text;

        // 2. Komplette <fw_action>...</fw_action>, <system>...</system> und gefälschte Dossier-Blöcke neutralisieren
        $clean = preg_replace('/<fw_action\b[^>]*>.*?<\/fw_action>/isu', '[gefiltert]', $clean) ?? $clean;
        $clean = preg_replace('/<\/?(?:fw_action|system|instruction|developer_instruction|tool_call|function_call)\b[^>]*>/iu', '[gefiltert]', $clean) ?? $clean;
        $clean = preg_replace('/\[\/?(?:VERIFIZIERTES\s+PHP-FAKTEN-DOSSIER|SYSTEM(?:-DIREKTIVE|-PROMPT|-STARTIMPULS)?|DEVELOPER)[^\]]*\]/iu', '[gefiltert]', $clean) ?? $clean;

        // 3. Bekannte deutsche & englische Prompt-Injection- & Jailbreak-Befehle entschärfen
        $clean = preg_replace(self::promptInjectionPattern(), '[gefiltert]', $clean) ?? $clean;

        return $clean;
    }

    private static function promptInjectionPattern(): string
    {
        return '/(?:'
            . 'ignore\s+(?:all\s+|any\s+)?(?:previous|prior|above|earlier|system)\s+(?:instructions|prompts|rules|directives)'
            . '|disregard\s+(?:all\s+|any\s+)?(?:previous|prior|above|system)\s+(?:instructions|prompts|rules)'
            . '|forget\s+(?:all\s+|any\s+)?(?:previous|prior|above)\s+(?:instructions|prompts|rules)'
            . '|new\s+system\s+(?:instructions|prompt|directive)'
            . '|ignoriere\s+(?:bitte\s+)?(?:alle\s+|sämtliche\s+)?(?:vorherigen\s+|bisherigen\s+|obigen\s+|alten\s+)?(?:anweisungen|regeln|instruktionen|systemvorgaben)'
            . '|vergiss\s+(?:alle\s+)?(?:vorherigen\s+|bisherigen\s+)?(?:anweisungen|regeln|vorgaben)'
            . '|neue\s+system(?:anweisung|instruktion|vorgabe)'
            . '|system\s*prompt'
            . '|developer\s*mode'
            . '|jailbreak'
            . '|du\s+bist\s+ab\s+sofort'
            . '|you\s+are\s+now\s+(?:in\s+)?(?:developer|unrestricted|dan|admin)'
            . '|ich\s+befehle\s+dir'
            . '|f[üu]hre\s+(?:sofort\s+)?folgendes\s+(?:tool|werkzeug|kommando)\s+aus'
            . '|rufe\s+(?:sofort\s+)?das\s+(?:tool|werkzeug)\s+[a-z0-9_]+\s+auf'
            . '|ordne\s+dieses\s+dokument\s+zwingend\s+in'
            . '|benenne\s+diese\s+datei\s+zwingend\s+um\s+in'
            . ')/iu';
    }
}
