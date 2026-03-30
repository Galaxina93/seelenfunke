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
                'name' => 'ticket_get_all',
                'description' => 'Gibt alle offenen Kundensupport-Tickets zurück. Nutze dies, wenn nach Support, Kundenmeldungen oder Tickets gefragt wird. Stichworte: Zeig mir die Tickets, Gibt es Support Anfragen, offene Kundentickets, Beschwerden, Anfragen von Kunden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetTickets']
            ],
            [
                'name' => 'support_get_customer_orders',
                'description' => 'Gibt alle bisherigen Bestellungen des aktuell eingeloggten Kunden zurück. Nutze dies zwingend, wenn der Kunde global fragt: "Habe ich Bestellungen?" oder "Was habe ich bestellt?".',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetCustomerOrders']
            ],
            [
                'name' => 'support_get_order_status',
                'description' => 'Prüft den Status einer Bestellung anhand der Bestellnummer (z.B. LSC-2024...) oder EMail Adresse.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'identifier' => [
                            'type' => 'string',
                            'description' => 'Die Bestellnummer oder E-Mail Adresse des Kunden.'
                        ]
                    ],
                    'required' => ['identifier']
                ],
                'callable' => [self::class, 'executeGetOrderStatus']
            ],
            [
                'name' => 'support_get_product_info',
                'description' => 'Findet Basis-Informationen zu einem oder mehreren Produkten (Preis, Kurzbeschreibung).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_term' => [
                            'type' => 'string',
                            'description' => 'Der Name, SKU oder Suchbegriff des Produkts.'
                        ]
                    ],
                    'required' => ['search_term']
                ],
                'callable' => [self::class, 'executeGetProductInfo']
            ],
            [
                'name' => 'support_mark_needs_employee',
                'description' => 'RUFE DIESES WERKZEUG AUF, WENN DU DEM KUNDEN BEI EINEM PROBLEM NICHT WEITERHELFEN KANNST (z.B. spezielle Reklamationen, Beschwerden oder individuelle Rabattanfragen, Defektdokumentationen). Dies markiert den Chat sofort für einen echten Mitarbeiter.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeNeedsEmployee']
            ],
            [
                'name' => 'support_resolve_chat',
                'description' => 'Rufe dieses Werkzeug auf, wenn der Kunde wunschlos glücklich ist und das Problem der Konversation restlos geklärt ist. Dies schließt das Ticket.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeResolveChat']
            ],
            [
                'name' => 'support_record_analytics',
                'description' => 'Speichert die wesentlichen Analysedaten dieser aktuellen Kunden-Konversation für das Backend-Dashboard des Shops. Rufe dies IMMER einmal pro Chat auf, wenn du verstanden hast, worum es im groben geht.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'top_topic' => [
                            'type' => 'string',
                            'description' => 'Das übergreifende Kategorie-Thema (Bsp: "Versanddauer", "Rabattcode", "Produktberatung", "Reklamation"). Max 3 Wörter.'
                        ],
                        'mentioned_product' => [
                            'type' => 'string',
                            'description' => 'Name des primär angefragten Produkts. Falls keines, leer lassen oder null.'
                        ],
                        'ai_summary' => [
                            'type' => 'string',
                            'description' => 'Kurze, detaillierte 1-2 Satz Zusammenfassung, worum es in diesem Gespräch exakt ging.'
                        ]
                    ],
                    'required' => ['top_topic', 'ai_summary']
                ],
                'callable' => [self::class, 'executeRecordAnalytics']
            ]
        ];
    }

    public static function executeGetTickets(array $args)
    {
        try {
            $query = SupportTicket::where('status', '!=', 'closed');
            $count = $query->count();
            $tickets = $query->orderBy('created_at', 'desc')->take(5)->get();

            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'message' => 'Es gibt aktuell keine offenen Support-Tickets. Alles super!'];
            }

            $formatted = [];
            foreach ($tickets as $t) {
                $formatted[] = [
                    'id' => $t->id,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'date' => $t->created_at->format('d.m.Y H:i')
                ];
            }

            return ['status' => 'success', 'open_tickets_count' => $count, 'tickets' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Tickets konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeGetOrderStatus(array $args)
    {
        try {
            $identifier = $args['identifier'] ?? '';
            if (empty($identifier)) {
                return ['status' => 'error', 'message' => 'Bitte gib eine Bestellnummer oder Email-Adresse an.'];
            }

            $order = OrderOrder::where('order_number', 'LIKE', "%{$identifier}%")
                        ->orWhere('customer_email', 'LIKE', "%{$identifier}%")
                        ->latest()
                        ->first();

            if (!$order) {
                return ['status' => 'not_found', 'message' => 'Unter dieser Nummer/Email konnte leider keine Bestellung gefunden werden.'];
            }

            return [
                'status' => 'success', 
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'payment_status' => $order->payment_status,
                'shipping_status' => $order->shipping_status,
                'date' => $order->created_at->format('d.m.Y'),
                'total' => $order->grand_total_formated ?? ($order->grand_total / 100) . ' €'
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Bestellstatus nicht laden.'];
        }
    }

    public static function executeGetCustomerOrders(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) {
                return ['status' => 'error', 'message' => 'Für diese Aktion musst du dem Kunden mitteilen, dass er sich zunächst in sein Kundenkonto einloggen muss.'];
            }

            $orders = OrderOrder::where('customer_id', $customerId)->latest()->take(5)->get();

            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Der Kunde hat aktuell noch keine Bestellungen aufgegeben.'];
            }

            $res = [];
            foreach ($orders as $o) {
                $res[] = [
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'date' => $o->created_at->format('d.m.Y'),
                    'total' => $o->grand_total_formated ?? ($o->grand_total / 100) . ' €'
                ];
            }

            return ['status' => 'success', 'orders' => $res];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Die Bestellungen konnten nicht geladen werden.'];
        }
    }

    public static function executeGetProductInfo(array $args)
    {
        try {
            $term = $args['search_term'] ?? '';
            $products = Product::where('status', 'active')
                        ->where(function($q) use ($term) {
                            $q->where('name', 'LIKE', "%{$term}%")
                              ->orWhere('sku', 'LIKE', "%{$term}%");
                        })
                        ->take(3)
                        ->get();

            if ($products->isEmpty()) {
                return ['status' => 'not_found', 'message' => 'Ich konnte kein aktives Produkt zu diesem Suchbegriff finden.'];
            }

            $res = [];
            foreach ($products as $p) {
                $res[] = [
                    'name' => $p->name,
                    'price' => $p->formatted_price,
                    'description_short' => mb_substr(strip_tags($p->description), 0, 150) . '...',
                    'type' => $p->type
                ];
            }

            return ['status' => 'success', 'products' => $res];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Produktdaten konnten nicht geladen werden.'];
        }
    }

    public static function executeNeedsEmployee(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if ($chatId) {
                $chat = SupportCustomerChat::find($chatId);
                if (!$chat) return ['status' => 'error', 'message' => 'Chat nicht gefunden.'];

                $customerId = auth()->guard('customer')->id();
                
                // Block guest escalations
                if (!$customerId && !auth()->check() && !(class_exists(\App\Models\System\SystemUser::class) && auth()->guard((new \App\Models\System\SystemUser)->getGuard())->check())) {
                    return [
                        'status' => 'error', 
                        'message' => 'Der Kunde ist als Gast (anonym) im Chat. Bitte teile dem Kunden mit, dass er sich kurz ein kostenloses Konto erstellen oder einloggen muss, damit du sein Anliegen offiziell an einen Mitarbeiter als Ticket eskalieren kannst.'
                    ];
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
                        'message' => 'Ein offizielles Support-Ticket wurde angelegt. Teile dem Kunden mit, dass sein Anliegen als Ticket erfasst wurde, beknne ihm die Ticketnummer (' . $ticket->ticket_number . ') und verabschiede dich freundlich. Erwähne, dass sich zeitnah ein echter Mitarbeiter meldet.'
                    ];
                }

                return ['status' => 'success', 'message' => 'Aktion ausgeführt. Der Mitarbeiter-Benötigt Status wurde gesetzt.'];
            }
            return ['status' => 'error', 'message' => 'Fehlt __chat_id im Kontext.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Datenbankfehler bei Statuswechsel.'];
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
                    'message' => 'Ticket geschlossen. Verabschiede dich freundlich vom Kunden und bitte ihn AUSDRÜCKLICH darum, oben im Chat-Fenster eine 5-Sterne-Bewertung und ein kurzes Feedback zu eurer Unterhaltung zu hinterlassen!'
                ];
            }
            return ['status' => 'error', 'message' => 'Fehlt __chat_id im Kontext.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Datenbankfehler bei Statuswechsel.'];
        }
    }

    public static function executeRecordAnalytics(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if ($chatId) {
                SupportCustomerChat::where('id', $chatId)->update([
                    'top_topic' => mb_substr($args['top_topic'] ?? 'Allgemeine Anfrage', 0, 100),
                    'mentioned_product' => mb_substr($args['mentioned_product'] ?? '', 0, 100) ?: null,
                    'ai_summary' => $args['ai_summary'] ?? ''
                ]);
                return ['status' => 'success', 'message' => 'Die Analysedaten wurden erfolgreich im CRM Dashboard hinterlegt.'];
            }
            return ['status' => 'error', 'message' => 'Fehlt __chat_id im Kontext.'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Konnte Analytics nicht speichern.'];
        }
    }
}
