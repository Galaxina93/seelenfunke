<?php

namespace App\Services\AI\Functions;

use App\Models\Support\SupportTicket;
use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Support\SupportCustomerChat;
use Illuminate\Support\Facades\Log;

trait AiSupportFuncs
{
    public static function getAiSupportFuncsSchema(): array
    {
        return [
            [
                'name' => 'support_get_my_tickets',
                'description' => 'Gibt alle aktiven Support-Tickets des aktuell eingeloggten Kunden zurück.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetMyTickets']
            ],
            [
                'name' => 'support_get_customer_orders',
                'description' => 'Gibt alle bisherigen Bestellungen des aktuell eingeloggten Kunden zurück.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetCustomerOrders']
            ],
            [
                'name' => 'support_get_order_status',
                'description' => 'Prüft den Status einer Bestellung anhand der Bestellnummer.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['identifier' => ['type' => 'string']],
                    'required' => ['identifier']
                ],
                'callable' => [self::class, 'executeGetOrderStatus']
            ],
            [
                'name' => 'support_get_ticket_status',
                'description' => 'Sucht den Status eines SPEZIELLEN Tickets anhand seiner Ticketnummer (TCK-XXXX).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['ticket_number' => ['type' => 'string']],
                    'required' => ['ticket_number']
                ],
                'callable' => [self::class, 'executeGetTicketStatus']
            ],
            [
                'name' => 'support_get_order_details',
                'description' => 'Liefert genaue Details und Gravuren einer Bestellung.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['order_number' => ['type' => 'string']],
                    'required' => ['order_number']
                ],
                'callable' => [self::class, 'executeGetOrderDetails']
            ],
            [
                'name' => 'support_get_tracking_link',
                'description' => 'Gibt die direkte DHL Sendungsnummer zu einer Bestellung zurück.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['order_number' => ['type' => 'string']],
                    'required' => ['order_number']
                ],
                'callable' => [self::class, 'executeGetTrackingLink']
            ],
            [
                'name' => 'support_get_gamification_stats',
                'description' => 'Gibt die Funken-Punkte und Level des Kunden zurück.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetGamificationStats']
            ],
            [
                'name' => 'support_get_customer_full_profile',
                'description' => 'Gibt eine globale Konto-Übersicht des Kunden zurück.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetCustomerFullProfile']
            ],
            [
                'name' => 'support_get_product_info',
                'description' => 'Findet Preise und Basis-Informationen zu Produkten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['search_term' => ['type' => 'string']],
                    'required' => ['search_term']
                ],
                'callable' => [self::class, 'executeGetProductInfo']
            ],
            [
                'name' => 'support_mark_needs_employee',
                'description' => 'Eskaliert die Situation an einen menschlichen Mitarbeiter.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeNeedsEmployee']
            ],
            [
                'name' => 'support_resolve_chat',
                'description' => 'Schließt das Support-Gespräch final ab.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeResolveChat']
            ],
            [
                'name' => 'support_record_analytics',
                'description' => 'Speichert KI Support Analysen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'top_topic' => ['type' => 'string'],
                        'mentioned_product' => ['type' => 'string'],
                        'ai_summary' => ['type' => 'string']
                    ],
                    'required' => ['top_topic', 'ai_summary']
                ],
                'callable' => [self::class, 'executeRecordAnalytics']
            ]
        ];
    }

    public static function executeGetMyTickets(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'Bitte den Kunden sich einzuloggen.'];

            $tickets = SupportTicket::where('customer_id', $customerId)->where('status', '!=', 'closed')->orderBy('created_at', 'desc')->take(3)->get();
            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Für dich sind aktuell keine offenen Support-Tickets im System hinterlegt."'];
            }

            $tLines = [];
            foreach ($tickets as $t) {
                $tLines[] = "- Ticket {$t->ticket_number} (Status: {$t->status}) vom {$t->created_at->format('d.m.Y H:i')}";
            }
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:\n\"Ich habe offene Tickets in deinem Profil gefunden:\n" . implode("\n", $tLines) . "\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Laden fehlgeschlagen.'];
        }
    }

    public static function executeGetTicketStatus(array $args)
    {
        try {
            $ticketNumber = $args['ticket_number'] ?? '';
            if (empty($ticketNumber)) return ['status' => 'error', 'message' => 'Ticketnummer fehlt.'];

            $ticket = SupportTicket::where('ticket_number', 'ILIKE', "%{$ticketNumber}%")->first();
            if (!$ticket) {
                return ['status' => 'not_found', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Sogar nach intensiver Systemsuche konnte ich absolut kein Ticket mit dieser Nummer finden. Bitte überprüfe die Ticketnummer noch einmal."'];
            }
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Dein Ticket {$ticket->ticket_number} ('{$ticket->subject}') hat aktuell den Status: {$ticket->status}. Das Support-Team wird sich bald dazu melden!\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Laden fehlgeschlagen.'];
        }
    }

    public static function executeGetOrderStatus(array $args)
    {
        try {
            $identifier = $args['identifier'] ?? '';
            if (empty($identifier)) return ['status' => 'error', 'message' => 'IDENTIFIER fehlt.'];

            $order = OrderOrder::where('order_number', 'LIKE', "%{$identifier}%")
                        ->orWhere('customer_email', 'LIKE', "%{$identifier}%")->latest()->first();

            if (!$order) {
                return ['status' => 'not_found', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Unter dieser Nummer oder Email konnte ich leider keine Bestellung in unserer Manufaktur finden."'];
            }
            $total = $order->total_price ? number_format($order->total_price / 100, 2) . ' €' : '0,00 €';
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Deine Bestellung {$order->order_number} vom {$order->created_at->format('d.m.Y')} ist aktuell im Status: {$order->status}. Der Bestellwert beträgt {$total}.\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Laden fehlgeschlagen.'];
        }
    }

    public static function executeGetCustomerOrders(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'Bitte den Kunden sich einzuloggen.'];

            $orders = OrderOrder::where('customer_id', $customerId)->latest()->take(5)->get();
            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Du hast aktuell noch keine Bestellungen in deinem Profil hinterlegt."'];
            }

            $oLines = [];
            foreach ($orders as $o) {
                $total = $o->total_price ? number_format($o->total_price / 100, 2) . ' €' : '0,00 €';
                $oLines[] = "- {$o->order_number} ({$total}) - Status: {$o->status}";
            }
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:\n\"Deine letzten Bestellungen in der Übersicht:\n" . implode("\n", $oLines) . "\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Laden fehlgeschlagen.'];
        }
    }

    public static function executeGetProductInfo(array $args)
    {
        try {
            $term = $args['search_term'] ?? '';
            $products = Product::where('status', 'active')->where(function($q) use ($term) {
                            $q->where('name', 'LIKE', "%{$term}%")->orWhere('sku', 'LIKE', "%{$term}%");
                        })->take(3)->get();

            if ($products->isEmpty()) {
                return ['status' => 'not_found', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Ich konnte kein Produkt zu diesem Suchbegriff in unserer Manufaktur finden."'];
            }
            $pLines = [];
            foreach ($products as $p) {
                $pLines[] = "- {$p->name} für {$p->formatted_price}: " . mb_substr(strip_tags($p->description), 0, 80) . "...";
            }
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:\n\"Folgende Produkte habe ich dazu gefunden:\n" . implode("\n", $pLines) . "\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Laden fehlgeschlagen.'];
        }
    }

    public static function executeNeedsEmployee(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if ($chatId) {
                $chat = SupportCustomerChat::find($chatId);
                if (!$chat) return ['status' => 'error', 'message' => 'Chat fehlt.'];

                $customerId = auth()->guard('customer')->id();
                if (!$customerId && !auth()->check()) {
                    return ['status' => 'error', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Da du aktuell als Gast im Chat bist, müsstest du dich kurz einloggen oder registrieren, damit ich dies offiziell an einen Mitarbeiter weiterleiten kann."'];
                }

                $chat->update(['status' => 'needs_employee']);
                if ($customerId) {
                    $ticket = SupportTicket::create([
                        'ticket_number' => 'TCK-' . strtoupper(\Illuminate\Support\Str::random(8)),
                        'customer_id'   => $customerId,
                        'subject'       => 'Automatisches KI-Eskalationsticket',
                        'category'      => 'allgemein',
                        'status'        => 'open',
                        'priority'      => 'normal',
                    ]);
                    \App\Models\Support\SupportTicketMessage::create([
                        'support_ticket_id' => $ticket->id,
                        'sender_type'       => 'customer',
                        'message'           => "Kunde hat im Funki-Chat nach einem Mitarbeiter verlangt. Bitte Chat-Verlauf prüfen."
                    ]);
                    $chat->update(['support_ticket_id' => $ticket->id]);
                    return [
                        'status' => 'success', 
                        'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Ein offizielles Support-Ticket wurde angelegt. Ich stoße hier als System an meine Grenzen und habe das Thema an einen internen Mitarbeiter von Mein-Seelenfunke weitergeleitet. Deine Ticketnummer lautet: ' . $ticket->ticket_number . ' - Das Team meldet sich!"'
                    ];
                }
            }
            return ['status' => 'error', 'message' => 'SYSTEM-DIREKTIVE: Fehler aufgetreten.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'SYSTEM-DIREKTIVE: Datenbankfehler.'];
        }
    }

    public static function executeResolveChat(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if ($chatId) {
                SupportCustomerChat::where('id', $chatId)->update(['status' => 'resolved']);
                return [
                    'status' => 'success', 
                    'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Wunderbar! Ich schließe diesen Chat-Bereich nun ab. Es wäre mir eine riesige Freude, wenn du mir oben über das Sternchen-Menü eine ehrliche Bewertung für unsere Unterhaltung da lässt!"'
                ];
            }
            return ['status' => 'error', 'message' => 'Fehler.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler.'];
        }
    }

    public static function executeRecordAnalytics(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if ($chatId) {
                SupportCustomerChat::where('id', $chatId)->update([
                    'top_topic' => mb_substr($args['top_topic'] ?? 'Allgemein', 0, 100),
                    'mentioned_product' => mb_substr($args['mentioned_product'] ?? '', 0, 100) ?: null,
                    'ai_summary' => $args['ai_summary'] ?? ''
                ]);
                return ['status' => 'success', 'message' => 'Analysedaten gesichert. Du darfst in deinem eigenen Ermessen weiter antworten.'];
            }
            return ['status' => 'error', 'message' => 'Fehler.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler.'];
        }
    }

    public static function executeGetOrderDetails(array $args)
    {
        try {
            $orderNumber = $args['order_number'] ?? '';
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'Bestellnummer fehlt.'];

            $order = \App\Models\Order\OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->with('items')->first();
            if (!$order) return ['status' => 'not_found', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Bestellung nicht gefunden."'];

            $items = [];
            foreach ($order->items as $item) {
                $configStr = "Keine Personalisierung";
                if (is_array($item->configuration) && !empty($item->configuration)) {
                    $configStr = json_encode($item->configuration, JSON_UNESCAPED_UNICODE);
                }
                $items[] = "- {$item->quantity}x {$item->product_name} (Gravur/Details: {$configStr})";
            }
            return [
                'status' => 'success',
                'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus:\n\"Hier sind die exakten Details zu der Bestellung {$order->order_number} (Status: {$order->status}):\n" . implode("\n", $items) . "\""
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Details konnten nicht geladen werden.'];
        }
    }

    public static function executeGetTrackingLink(array $args)
    {
        try {
            $orderNumber = $args['order_number'] ?? '';
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'Fehlt.'];

            $order = \App\Models\Order\OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->first();
            if (!$order) return ['status' => 'not_found', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Bestellung nicht gefunden."'];

            $tracking = $order->tracking_number;
            if (!$tracking) {
                return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Dein Paket hat aktuell noch keine Sendungsnummer (Status: {$order->status}). Vermutlich ist es noch in der Produktion!\""];
            }
            return [
                'status' => 'success',
                'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Dein Paket ist unterwegs! Hier ist dein offizieller DHL-Trackinglink: https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode={$tracking} \""
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler.'];
        }
    }

    public static function executeGetGamificationStats(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'Bitte einloggen.'];

            $stats = \App\Models\Customer\CustomerGamification::where('customer_id', $customerId)->first();
            if (!$stats || !$stats->is_active) {
                return ['status' => 'success', 'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Ich sehe, du hast das Seelenfunken-Programm in deiner Zentrale noch gar nicht aktiviert! Dort entgehen dir tolle Belohnungen."'];
            }
            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Du bist aktuell Level {$stats->level} und hast stolze {$stats->funken_balance} Seelenfunken gesammelt! (Funkenflug Highscore: {$stats->funkenflug_highscore})\""];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler.'];
        }
    }

    public static function executeGetCustomerFullProfile(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) {
                return ['status' => 'error', 'message' => 'FALSCH. SYSTEM-DIREKTIVE: Sage dem Kunden neutral, dass er sich einloggen muss.'];
            }

            $customer = \App\Models\Customer\Customer::with(['profile'])->find($customerId);
            $orders = \App\Models\Order\OrderOrder::where('customer_id', $customerId)->get();
            $openOrders = $orders->whereIn('status', ['open', 'processing', 'in_production', 'shipped'])->count();
            $closedOrders = $orders->whereIn('status', ['completed', 'cancelled'])->count();
            $gamification = \App\Models\Customer\CustomerGamification::where('customer_id', $customerId)->first();
            $activeTickets = \App\Models\Support\SupportTicket::where('customer_id', $customerId)
                                ->whereIn('status', ['open', 'customer_reply', 'in_progress'])->count();

            return [
                'status' => 'success',
                'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: \"Hallo {$customer->first_name}, ich habe dein Profil geladen! Du bist seit dem {$customer->created_at->format('d.m.Y')} bei uns registriert. Aktuell hast du {$openOrders} offene und {$closedOrders} abgeschlossene Bestellungen. In unserem Funken-Programm bist du auf Level " . ($gamification ? $gamification->level : 1) . ". Du hast {$activeTickets} aktive Support-Tickets. Was darf ich mir davon näher für dich ansehen?\""
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler.'];
        }
    }
}
