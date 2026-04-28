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
                'name' => 'support_get_website_links',
                'description' => 'Gibt alle wichtigen, aktuell gültigen Verlinkungen (URLs) der Webseite zurück (z.B. für Login, Registrierung, Shop, Startseite, Kundenbereich). Nutze dies IMMER, falls der Kunde fragt, wo er was findet oder du einen Link schicken willst!',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetWebsiteLinks']
            ],
            [
                'name' => 'support_analyze_current_cart',
                'description' => 'Liest den aktuellen Warenkorb des Kunden aus, um bei Checkout-Problemen oder fehlenden Informationen (fehlende Gravuren) zu helfen.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeAnalyzeCart']
            ],
            [
                'name' => 'support_get_delivery_times',
                'description' => 'Liefert die tagesaktuellen Fertigungszeiten und Bearbeitungszeiten der Manufaktur inklusive Express-Daten.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetDeliveryTimes']
            ],
            [
                'name' => 'support_check_returns_policy',
                'description' => 'Prüft ob eine Bestellung retourniert werden kann, abhängig davon ob die bestellten Artikel personalisiert sind.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['order_number' => ['type' => 'string']],
                    'required' => ['order_number']
                ],
                'callable' => [self::class, 'executeCheckReturnsPolicy']
            ],
            [
                'name' => 'support_create_claim_ticket',
                'description' => 'WICHTIGE REGEL: Darf NIEMALS sofort ausgeführt werden! 1. Zeige dem Kunden erst die Zusammenfassung seiner Reklamation (Grund & Artikel). 2. Frage ihn explizit: "Darf ich das Ticket so für dich einreichen?". 3. Erst wenn er mit "Ja" antwortet, darfst du dieses Tool ausführen, um das Ticket anzulegen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => ['type' => 'string'],
                        'reason_summary' => ['type' => 'string']
                    ],
                    'required' => ['order_number', 'reason_summary']
                ],
                'callable' => [self::class, 'executeCreateClaimTicket']
            ],
            [
                'name' => 'support_modify_pending_order',
                'description' => 'VERWENDE DIES NUR FÜR ÄNDERUNGEN: Ändert die Lieferadresse einer Bestellung. WICHTIG: Stornierungen über diese Funktion sind strengstens untersagt! Geht NUR so lange die Bestellung im Status ausstehend/pending ist!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => ['type' => 'string'],
                        'action_type' => ['type' => 'string', 'enum' => ['change_address']],
                        'new_address_data' => [
                            'type' => 'object',
                            'description' => 'Nötig für change_address. JSON Objekt mit Keys: first_name, last_name, street, house_number, zipcode, city'
                        ]
                    ],
                    'required' => ['order_number', 'action_type']
                ],
                'callable' => [self::class, 'executeModifyPendingOrder']
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
                'description' => 'Findet Preise und Basis-Informationen zu Produkten. Wenn du "all" als search_term übergibst (oder es leer lässt), erhältst du eine Liste aller Produkte. Nutze dies zwingend, wenn der Kunde nach dem Sortiment fragt oder eine vorherige spezifische Suche fehlgeschlagen ist (z.B. wegen Leerzeichen/Rechtschreibung)!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['search_term' => ['type' => 'string', 'description' => 'Suchbegriff (z.B. "Seelenkristall"). Leer oder "all" für alle Produkte.']]
                ],
                'callable' => [self::class, 'executeGetProductInfo']
            ],
            [
                'name' => 'support_mark_needs_employee',
                'description' => 'Achtung: Darf niemals sofort ausgelöst werden! Zeige dem Kunden erst eine Zusammenfassung: Warum braucht er einen Mitarbeiter? Frage ihn: "Soll ich das als Ticket für dich an unser Team einreichen?". Sobald er mit Ja antwortet, rufe das Tool mit confirmed_by_customer=true auf.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'escalation_reason' => ['type' => 'string'],
                        'confirmed_by_customer' => ['type' => 'boolean']
                    ],
                    'required' => ['escalation_reason', 'confirmed_by_customer']
                ],
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
            ],
            [
                'name' => 'support_get_invoice_link',
                'description' => 'Prüft, ob für eine Bestellung bereits eine Rechnung existiert.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => ['order_number' => ['type' => 'string']],
                    'required' => ['order_number']
                ],
                'callable' => [self::class, 'executeGetInvoiceLink']
            ],
            [
                'name' => 'support_get_manufacturing_guidelines',
                'description' => 'Liefert Richtlinien zu Lasergravuren, Emojis und Sonderzeichen.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetManufacturingGuidelines']
            ],
            [
                'name' => 'support_get_cart_total',
                'description' => 'Liefert die aktuellen Zwischensummen des Warenkorbs.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass()],
                'callable' => [self::class, 'executeGetCartTotal']
            ],
            [
                'name' => 'support_penalize_offtopic',
                'description' => 'Verwende dieses Tool IMMER SOFORT, wenn der Kunde Smalltalk beginnt, extrem vom Thema e-commerce abweicht oder Dinge fragt/sagt, die nichts mit Support/Produkten zu tun haben (z.B. "Erzähl einen Witz"). Gib eine Gewichtung (severity) von 1 bis 10 an. 1 = leichtes Abweichen vom Thema, 3 = "Hallo was geht?", 8 = Witz eingefordert, 10 = extreme Provokation. Bei 10 sammelten Punkten schließt das System den Chat.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'severity' => ['type' => 'integer', 'description' => 'Gewichtung des Vergehens (1-10).'],
                        'tag' => ['type' => 'string', 'enum' => ['SMALLTALK', 'JOKE', 'INSULT', 'PROVOCATION', 'OTHER'], 'description' => 'Fachliche Kategorisierung des Vergehens.']
                    ],
                    'required' => ['severity', 'tag']
                ],
                'callable' => [self::class, 'executePenalizeOfftopic']
            ]
        ];
    }

    private static function fetchChatHistory($chatId)
    {
        $chatHistoryStr = "";
        try {
            $messages = \App\Models\Support\SupportCustomerChatMessage::where('support_customer_chat_id', $chatId)
                ->orderBy('created_at', 'asc')->get();
            if ($messages->count() > 0) {
                $chatHistoryStr .= "\n\n--- CHAT VERLAUF ---\n";
                foreach ($messages as $msg) {
                    $sender = $msg->sender === 'ai' ? 'Funki (KI)' : 'Kunde';
                    $chatHistoryStr .= "{$sender}: {$msg->message}\n";
                }
                $chatHistoryStr .= "--------------------\n";
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Konnte Chat History nicht laden: ' . $e->getMessage());
        }
        return $chatHistoryStr;
    }

    public static function executePenalizeOfftopic(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            if (!$chatId) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Chat-ID fehlt.'];

            $severity = (int)($args['severity'] ?? 3);
            if ($severity < 1) $severity = 1;
            if ($severity > 10) $severity = 10;
            
            $tag = $args['tag'] ?? 'OTHER';

            // Sichere die Severity & Tag direkt auf der ZULETZT verfassten Kunden-Nachricht
            $lastCustomerMessage = \App\Models\Support\SupportCustomerChatMessage::where('support_customer_chat_id', $chatId)
                ->where('sender', 'customer')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastCustomerMessage) {
                $lastCustomerMessage->update([
                    'severity' => $severity,
                    'tag' => $tag
                ]);
            }

            $cacheKey = "chat_severity_{$chatId}";
            $currentScore = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
            $newScore = $currentScore + $severity;
            
            \Illuminate\Support\Facades\Cache::put($cacheKey, $newScore, now()->addHour());

            if ($newScore >= 10) {
                // Den Chat komplett auflösen und schließen
                \App\Models\Support\SupportCustomerChat::where('id', $chatId)->update(['status' => 'resolved_auto']);
                return [
                    'status' => 'success',
                    'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Aufgrund wiederholt unpassender oder offtopic Nachrichten habe ich diesen Support-Chat nun endgültig geschlossen. Ich bitte um Verständnis, dass ich ausschließlich für Produkt- und Bestellsupport zur Verfügung stehe."'
                ];
            } else {
                $missing = 10 - $newScore;
                return [
                    'status' => 'success',
                    'message' => "HINTERGRUND-INFO FÜR KI: Das Vergehen wurde mit einer Schwere von {$severity}/10 gewertet. Bisher angesammelter Score: {$newScore}/10. Es fehlen noch {$missing} Punkte bis der Chat automatisch beendet wird. Sage dem Kunden nun extrem formell und höflich, dass du ausschließlich für Bestell- oder Produktfragen verfügbar bist, aber formuliere es diplomatisch."
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler (Penalize): ' . $e->getMessage());
            return ['status' => 'error', 'message' => "Systemfehler bei Penalize: " . $e->getMessage()];
        }
    }

    public static function executeGetMyTickets(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Der Kunde ist nicht eingeloggt.'];

            $tickets = SupportTicket::where('customer_id', $customerId)->where('status', '!=', 'closed')->orderBy('created_at', 'desc')->take(3)->get();
            if ($tickets->isEmpty()) {
                return ['status' => 'success', 'data' => []];
            }

            $tData = [];
            foreach ($tickets as $t) {
                $tData[] = ['ticket_number' => $t->ticket_number, 'status' => $t->status, 'date' => $t->created_at->format('d.m.Y H:i')];
            }
            return ['status' => 'success', 'data' => $tData];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "Datenbankfehler: " . $e->getMessage()];
        }
    }

    public static function executeGetTicketStatus(array $args)
    {
        try {
            $ticketNumber = trim($args['ticket_number'] ?? '');
            if (empty($ticketNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Ticketnummer fehlt.'];

            $ticket = SupportTicket::where('ticket_number', 'ILIKE', "%{$ticketNumber}%")->first();
            if (!$ticket) {
                return ['status' => 'not_found', 'data' => null];
            }
            return ['status' => 'success', 'data' => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status
            ]];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "Systemfehler: " . $e->getMessage()];
        }
    }

    public static function executeGetOrderStatus(array $args)
    {
        try {
            $identifier = trim($args['identifier'] ?? '');
            if (empty($identifier)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Suchbegriff fehlt.'];

            $order = OrderOrder::where('order_number', 'LIKE', "%{$identifier}%")
                        ->orWhere('customer_email', 'LIKE', "%{$identifier}%")->latest()->first();

            if (!$order) {
                return ['status' => 'not_found', 'data' => null];
            }
            
            $currentCustomerId = auth()->guard('customer')->id();
            if (!$currentCustomerId || $order->customer_id !== $currentCustomerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Aus Datenschutzgründen strikt verweigert. Diese Bestellung gehört nicht zum aktuell eingeloggten Kunden! Bitte den Kunden höflich, sich in das korrekte Konto einzuloggen.'];
            }
            
            return ['status' => 'success', 'data' => [
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'status' => $order->status,
                'total_price' => $order->total_price ? $order->total_price / 100 : 0
            ]];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "Temporärer Fehler: " . $e->getMessage()];
        }
    }

    public static function executeGetCustomerOrders(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Kunde nicht eingeloggt.'];

            $orders = OrderOrder::where('customer_id', $customerId)->latest()->take(5)->get();
            if ($orders->isEmpty()) {
                return ['status' => 'success', 'data' => []];
            }

            $oData = [];
            foreach ($orders as $o) {
                $oData[] = [
                    'order_number' => $o->order_number,
                    'total_price' => $o->total_price ? $o->total_price / 100 : 0,
                    'status' => $o->status
                ];
            }
            return ['status' => 'success', 'data' => $oData];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "Datenbankfehler: " . $e->getMessage()];
        }
    }

    public static function executeGetProductInfo(array $args)
    {
        try {
            $term = trim($args['search_term'] ?? '');
            $query = \App\Models\Product\Product::where('status', 'active');
            
            if (empty($term) || strtolower($term) === 'all' || strtolower($term) === 'aktuelle produkte') {
                $products = $query->take(15)->get();
            } else {
                $qTerm = '%' . $term . '%';
                $dbProducts = (clone $query)->where(function($q) use ($qTerm) {
                    $q->where('name', 'LIKE', $qTerm)
                      ->orWhere('sku', 'LIKE', $qTerm);
                })->take(5)->get();
                
                if ($dbProducts->isEmpty()) {
                    // Fallback: Fuzzy search in PHP um Leerzeichen, Bindestriche etc. zu ignorieren (z.B. "Seelenkristall" findet "Seelen Kristall")
                    $cleanTerm = str_replace([' ', '-'], '', strtolower($term));
                    $allProducts = $query->get();
                    $dbProducts = $allProducts->filter(function($p) use ($cleanTerm) {
                        $cleanName = str_replace([' ', '-'], '', strtolower($p->name));
                        return str_contains($cleanName, $cleanTerm) || str_contains($cleanTerm, $cleanName);
                    })->take(5);
                }
                $products = $dbProducts;
            }

            if ($products->isEmpty()) {
                return ['status' => 'success', 'message' => 'HINTERGRUND-INFO FÜR KI: Kein Produkt mit diesem Namen gefunden. Bitte rufe das Tool sofort noch einmal mit search_term="all" auf, um dir selbst einen Überblick der verfügbaren Produkte zu verschaffen und dem Kunden dann eine sinnvolle Alternative zu empfehlen. Sag dem Kunden nicht, dass die Suche fehlschlug, sondern empfiehl stattdessen die gefundenen Produkte aus dem "all" Aufruf!'];
            }
            $pData = [];
            foreach ($products as $p) {
                try {
                    $url = route('product.show', $p->slug);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
                    $url = url('/produkt/' . $p->slug);
                }
                
                // Wir übergeben dem AI-Agenten nur die relevantesten Daten, OHNE interne Einkaufsdaten/3D-Settings.
                $pData[] = [
                    'name' => $p->name,
                    'price' => $p->formatted_price,
                    'url' => $url,
                    'short_description' => $p->short_description ? strip_tags($p->short_description) : '',
                    'description' => $p->description ? strip_tags($p->description) : '',
                    'attributes' => $p->attributes ?? null
                ];
            }
            return ['status' => 'success', 'data' => $pData];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Es gab einen Systemfehler bei der Produktsuche ('{$e->getMessage()}')."];
        }
    }

    public static function executeNeedsEmployee(array $args)
    {
        try {
            $chatId = $args['__chat_id'] ?? null;
            $confirmed = filter_var($args['confirmed_by_customer'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $reason = trim($args['escalation_reason'] ?? 'Generelle Eskalation');

            if ($chatId) {
                $chat = SupportCustomerChat::find($chatId);
                if (!$chat) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Fehler - Konnte Chat ID nicht zuordnen.'];

                $customerId = auth()->guard('customer')->id();
                if (!$customerId && !auth()->check()) {
                    return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Da du aktuell als Gast im Chat bist, müsstest du dich kurz einloggen oder registrieren, damit ich dies offiziell an einen Mitarbeiter weiterleiten kann.'];
                }

                if (!$confirmed) {
                    return [
                        'status' => 'draft_pending',
                        'data' => [
                            'draft_reason' => $reason,
                            'instruction_for_ai' => 'Zeige dem Kunden den Entwurf und frage ihn, ob du das Ticket jetzt SO für ihn beim Team hinterlegen sollst.'
                        ]
                    ];
                }

                $chat->update(['status' => 'needs_employee']);
                if ($customerId) {
                    $ticket = SupportTicket::create([
                        'ticket_number' => 'TCK-' . strtoupper(\Illuminate\Support\Str::random(8)),
                        'customer_id'   => $customerId,
                        'subject'       => 'Automatisches KI-Eskalationsticket: ' . $reason,
                        'category'      => 'allgemein',
                        'status'        => 'open',
                        'priority'      => 'normal',
                    ]);
                    \App\Models\Support\SupportTicketMessage::create([
                        'support_ticket_id' => $ticket->id,
                        'sender_type'       => 'customer',
                        'message'           => "Kunde hat im Funki-Chat nach einem Mitarbeiter verlangt. KI-Zusammenfassung: {$reason}. Bitte Chat-Verlauf prüfen.\n" . self::fetchChatHistory($chatId)
                    ]);
                    $chat->update(['support_ticket_id' => $ticket->id]);
                    return [
                        'status' => 'success',
                        'data' => [
                            'ticket_number' => $ticket->ticket_number,
                            'info' => 'Ticket wurde erfolgreich angelegt. Sag dem Kunden Bescheid.'
                        ]
                    ];
                }
            }
            return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Der Chat konnte nicht identifiziert werden.'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Datenbankfehler bei der Ticket-Übergabe ('{$e->getMessage()}')."];
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
                    'message' => 'SYSTEM-DIREKTIVE: Gib folgenden Text exakt so aus: "Wunderbar! Ich schließe diesen Chat-Bereich nun ab. Es wäre mir eine riesige Freude, wenn du mir unten über das Sternchen-Menü eine ehrliche Bewertung für unsere Unterhaltung da lässt!"'
                ];
            }
            return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Der Chat konnte nicht identifiziert werden.'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Chat Fehler ('{$e->getMessage()}')."];
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
                return ['status' => 'success', 'message' => 'HINTERGRUND-INFO FÜR KI: Analysedaten gesichert. Du darfst in deinem eigenen Ermessen weiter antworten.'];
            }
            return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Konnte nicht gesichert werden, Chat ID fehlt.'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetOrderDetails(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bestellnummer fehlt.'];

            $order = \App\Models\Order\OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->with('items')->first();
            if (!$order) return ['status' => 'not_found', 'data' => null];

            $currentCustomerId = auth()->guard('customer')->id();
            if (!$currentCustomerId || $order->customer_id !== $currentCustomerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Aus Datenschutzgründen strikt verweigert. Diese Bestellung gehört nicht zum aktuell eingeloggten Kunden! Bitte den Kunden höflich, sich einzuloggen.'];
            }

            $itemsData = [];
            foreach ($order->items as $item) {
                $configStr = "Keine Personalisierung";
                if (is_array($item->configuration) && !empty($item->configuration)) {
                    $details = [];
                    if (!empty($item->configuration['text'])) {
                        $details[] = "Wunschtext: '" . $item->configuration['text'] . "'";
                    }
                    if (!empty($item->configuration['notes'])) {
                        $details[] = "Notiz: '" . $item->configuration['notes'] . "'";
                    }
                    if (!empty($item->configuration['texts']) && is_array($item->configuration['texts'])) {
                        $canvasTexts = [];
                        foreach ($item->configuration['texts'] as $t) {
                            if (!empty($t['text'])) $canvasTexts[] = $t['text'];
                        }
                        if (!empty($canvasTexts)) {
                            $details[] = "LeinwandTexte: '" . implode(', ', $canvasTexts) . "'";
                        }
                    }
                    $configStr = empty($details) ? "Personalisierung hinterlegt (ohne lesbaren Text)" : implode(' | ', $details);
                }
                $itemsData[] = [
                    'quantity' => $item->quantity,
                    'product_name' => $item->product_name,
                    'configuration' => $configStr
                ];
            }
            return [
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'items' => $itemsData
                ]
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Details konnten wegen eines Systemfehlers nicht geladen werden ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetTrackingLink(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bestellnummer fehlt.'];

            $order = \App\Models\Order\OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->first();
            if (!$order) return ['status' => 'not_found', 'data' => null];

            $currentCustomerId = auth()->guard('customer')->id();
            if (!$currentCustomerId || $order->customer_id !== $currentCustomerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Aus Datenschutzgründen strikt verweigert. Diese Bestellung gehört nicht zum aktuell eingeloggten Kunden! Auskunft verweigert.'];
            }

            $tracking = $order->tracking_number;
            if (!$tracking) {
                return ['status' => 'success', 'data' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'tracking_number' => null,
                    'tracking_link' => null
                ]];
            }
            return [
                'status' => 'success',
                'data' => [
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'tracking_number' => $tracking,
                    'tracking_link' => "https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode={$tracking}"
                ]
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler beim Abrufen der Trackingnummer ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetGamificationStats(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Kunde ist nicht eingeloggt.'];

            $stats = \App\Models\Customer\CustomerGamification::where('customer_id', $customerId)->first();
            if (!$stats || !$stats->is_active) {
                return ['status' => 'success', 'data' => null];
            }
            
            $nextLevelThreshold = ($stats->level * 1000); 
            $missing = max(0, $nextLevelThreshold - $stats->funken_balance);

            return ['status' => 'success', 'data' => [
                'level' => $stats->level,
                'funken_balance' => $stats->funken_balance,
                'funken_missing_for_next_level' => $missing,
                'funkenflug_highscore' => $stats->funkenflug_highscore
            ]];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Stats konnten nicht geladen werden ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetCustomerFullProfile(array $args)
    {
        try {
            $customerId = auth()->guard('customer')->id();
            if (!$customerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Kunde nicht eingeloggt.'];
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
                'data' => [
                    'first_name' => $customer->first_name,
                    'registered_since' => $customer->created_at->format('Y-m-d'),
                    'open_orders_count' => $openOrders,
                    'closed_orders_count' => $closedOrders,
                    'gamification_level' => $gamification ? $gamification->level : 1,
                    'active_support_tickets' => $activeTickets
                ]
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Das Profil konnte nicht geladen werden ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetWebsiteLinks(array $args)
    {
        try {
            $allRoutes = \Illuminate\Support\Facades\Route::getRoutes();
            $links = [];
            foreach ($allRoutes as $route) {
                // Nur GET-Routen ohne zwingende Parameter
                if (in_array('GET', $route->methods()) && empty($route->parameterNames())) {
                    $uri = $route->uri();

                    // Schließe System- und Admin-Routen strikt aus
                    if (str_starts_with($uri, 'admin') || str_starts_with($uri, 'api') || str_starts_with($uri, '_') || str_starts_with($uri, 'sanctum') || str_starts_with($uri, 'livewire') || str_starts_with($uri, 'broadcasting') || $uri === 'up' || str_contains($uri, 'success') || str_contains($uri, 'verify') || str_contains($uri, 'auth/')) {
                        continue;
                    }

                    $name = $route->getName() ?: '/' . $uri;

                    // Unnötige/Kryptische Müll-Routen filtern
                    if (str_contains($name, 'verification') || str_contains($name, 'checkout.success') || str_contains($name, 'newsletter.verify') || str_contains($name, 'auth.')) {
                        continue;
                    }

                    $actionName = $name;

                    // Lesbare Namen für die KI generieren
                    if (str_contains($name, 'login') || $uri === 'login') $actionName = 'Login';
                    elseif (str_contains($name, 'register') || $uri === 'register') $actionName = 'Konto erstellen / Registrierung';
                    elseif ($name === 'password.request' || $uri === 'forgot-password') $actionName = 'Passwort vergessen';
                    elseif ($name === 'home' || $uri === '/') $actionName = 'Startseite';
                    elseif ($name === 'shop' || $uri === 'shop') $actionName = 'Shop Startseite';
                    elseif ($name === 'cart' || $uri === 'warenkorb') $actionName = 'Warenkorb';
                    elseif ($name === 'checkout' || $uri === 'checkout') $actionName = 'Kasse (Checkout)';
                    elseif ($name === 'contact' || $uri === 'kontakt') $actionName = 'Kontakt & Support';
                    elseif ($name === 'marketing/marketing/blog' || $uri === 'marketing/marketing/blog') $actionName = 'Magazin / Blog';
                    elseif ($name === 'manufacture' || $uri === 'manufaktur') $actionName = 'Unsere Manufaktur';
                    elseif (str_contains($name, 'customer.')) $actionName = 'Kundenportal - ' . ucfirst(str_replace('customer.', '', $name));

                    $links[] = "- **{$actionName}**: [" . url($uri) . "](" . url($uri) . ")";
                }
            }
            $linksStr = implode("\n", array_unique($links));
            return [
                'status' => 'success',
                'message' => "HINTERGRUND-INFO FÜR DIE KI (NICHT DIREKT AN DEN KUNDEN AUSGEBEN!): \nDu hast die Routen-Datenbank abgefragt. Suche dir aus der folgenden Liste den einen korrekten Link heraus, nachdem der Kunde gefragt hat (z.b. Kontakt oder Konto erstellen).\nFormuliere dann eine freundliche eigene Antwort und zeige dem Kunden NUR den passenden Link als echten Markdown-Link (z.b. [Kontakt aufnehmen](url)).\n\nHier das Lexikon:\n\n" . $linksStr
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
             return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler beim Laden der Routen ('{$e->getMessage()}')."];
        }
    }

    public static function executeAnalyzeCart(array $args)
    {
        try {
            $cartService = app(\App\Services\CartService::class);
            $cart = $cartService->getCart();
            if (!$cart || $cart->items->isEmpty()) {
                return ['status' => 'success', 'message' => "HINTERGRUND-INFO AN KI: Der Warenkorb des Kunden ist aktuell komplett leer."];
            }

            $info = [];
            foreach ($cart->items as $item) {
                $product = $item->product;
                if (!$product) continue;
                $configStr = empty($item->configuration) ? "KEINE Personalisierung eingegeben" : json_encode($item->configuration, JSON_UNESCAPED_UNICODE);
                $info[] = "- {$item->quantity}x {$product->name} (Benötigt Konfiguration? " . ($product->requires_configuration ? 'JA' : 'NEIN') . " | Eingegebene Konfiguration: {$configStr})";
            }

            return [
                'status' => 'success',
                'message' => "HINTERGRUND-INFO FÜR DIE KI (NICHT DIREKT AN DEN KUNDEN AUSGEBEN!): Hier sind die aktuellen Artikel im Warenkorb des Nutzers:\n" . implode("\n", $info) . "\nHinweis: Wenn ein Produkt Konfiguration benötigt, aber keine eingegeben wurde, weise den Nutzer freundlich darauf hin, diese einzutragen."
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler beim Analysieren des Warenkorbs ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetDeliveryTimes(array $args)
    {
        try {
            $msg = "HINTERGRUND-INFO FÜR DIE KI (NICHT DIREKT AN DEN KUNDEN AUSGEBEN!): \nUnsere aktuelle Standard-Produktionszeit beträgt 2-3 Werktage. Der Standardversand dauert danach 1-2 Werktage.\nMit der optionalen EXPRESS-Fertigung (im Warenkorb auswählbar) priorisieren wir die Bestellung extrem (meist wird schon am nächsten Werktag versendet).";
            return ['status' => 'success', 'message' => $msg];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Lieferzeiten aktuell nicht abrufbar.'];
        }
    }

    public static function executeCheckReturnsPolicy(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bitte frage erst nach der Bestellnummer!'];

            $order = \App\Models\Order\OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->with('items')->first();
            if (!$order) {
                return ['status' => 'not_found', 'message' => 'HINTERGRUND-INFO: Bestellung mit dieser Nummer absolut nicht gefunden.'];
            }

            $currentCustomerId = auth()->guard('customer')->id();
            if (!$currentCustomerId || $order->customer_id !== $currentCustomerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Aus Datenschutzgründen strikt verweigert. Diese Bestellung gehört nicht zum aktuell eingeloggten Kunden! Auskunft verweigert.'];
            }

            $now = now();
            $orderDate = $order->created_at;
            $daysPassed = $orderDate->diffInDays($now);
            $isWithin14Days = $daysPassed <= 14;

            $personalizedItems = [];
            foreach ($order->items as $item) {
                if (!empty($item->configuration)) {
                    $personalizedItems[] = $item->product_name;
                }
            }

            $dateInfo = "Die Bestellung ist $daysPassed Tage alt " . ($isWithin14Days ? "(Innerhalb der 14 Tage Widerrufsfrist)." : "(AUSSERHALB der rechtlichen 14 Tage Widerrufsfrist! Ein Widerruf ist abgelaufen).");

            if (!empty($personalizedItems)) {
                $pList = implode(", ", $personalizedItems);
                return [
                    'status' => 'success', 
                    'message' => "HINTERGRUND-INFO FÜR KI: $dateInfo ACHTUNG: Die Bestellung enthält PERSONALISIERTE Artikel ($pList). Nenne dem Kunden freundlich, dass personalisierte Artikel per Gesetz streng vom Widerrufsrecht ausgeschlossen sind. (Ein Umtausch wegen Nicht-Gefallens ist NICHT möglich. Falls defekt: Biete Reklamation an)."
                ];
            }

            return ['status' => 'success', 'message' => "HINTERGRUND-INFO FÜR KI: $dateInfo Diese Bestellung besteht nur aus Standard-Artikeln. Zeige dem Kunden das Verhalten passend zum Frist-Status. Link zum offiziellen Formular: /widerruf"];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler ('{$e->getMessage()}')."];
        }
    }

    public static function executeCreateClaimTicket(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            $reason = trim($args['reason_summary'] ?? '');

            if (empty($orderNumber) || empty($reason)) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Es muss zwingend eine Bestellnummer und ein Grund (mit exaktem Artikelnamen) genannt werden!'];
            }

            $customerId = auth()->guard('customer')->id();
            if (!$customerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Kunde muss sich erst einloggen, um ein Reklamationsticket zu öffnen. Bitte ihn darum.'];
            }

            $order = OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->where('customer_id', $customerId)->first();
            if (!$order) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Diese Bestellung gehört nicht zum eingeloggten Kunden oder existiert nicht.'];
            }

            $chatHistoryStr = "";
            if (isset($args['__chat_id'])) {
                $agentId = $args['__agent_id'] ?? null;
                $chatHistoryStr = self::fetchChatHistory($args['__chat_id']);
            }

            $log = \App\Models\System\SystemLog::start(
                'ai_claim_ticket',
                'Automatisches KI-Reklamationsticket',
                'automation',
                $agentId
            );

            $ticket = SupportTicket::create([
                'ticket_number' => 'TCK-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'customer_id'   => $customerId,
                'order_id'      => $order->id,
                'subject'       => 'Reklamation Bestellung: ' . $order->order_number,
                'category'      => 'reklamation',
                'status'        => 'open',
                'priority'      => 'high',
            ]);

            \App\Models\Support\SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'sender_type'       => 'customer',
                'message'           => "KI-Zusammenfassung der Reklamation: \n" . $reason . "\n" . $chatHistoryStr
            ]);

            $log->finish('success', 'Ticket TCK-'.$ticket->ticket_number.' erstellt.', [
                'ticket_id' => $ticket->id,
                'reason' => $reason
            ]);

            return [
                'status' => 'success',
                'message' => 'HINTERGRUND-INFO FÜR KI: Das Reklamationsticket wurde mit Prio Hoch unter Nummer '.$ticket->ticket_number.' angelegt! Teile dem Kunden diese Ticketnummer freudig mit und bitte ihn DRINGEND, 1-2 Fotos des Schadens als Antwort auf die nun eintreffende Ticket-Mail zu senden.'
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler bei der Ticketerstellung ('{$e->getMessage()}')."];
        }
    }

    public static function executeModifyPendingOrder(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            $action = trim($args['action_type'] ?? '');

            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bestellnummer fehlt.'];

            $customerId = auth()->guard('customer')->id();
            if (!$customerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Kunde muss sich zum Ändern einloggen.'];
            }

            $order = OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->where('customer_id', $customerId)->first();
            if (!$order) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bestellung nicht gefunden.'];
            }

            // RIGIDES SICHERHEITSNETZ
            if ($order->status !== 'pending') {
                return [
                    'status' => 'error',
                    'message' => 'HINTERGRUND-INFO FÜR KI: ABSOLUTE SPERRE - Datenrettung nicht mehr möglich! Die Bestellung hat den Status pending verlassen und ist bereits in Bearbeitung/Produktion! Verweigere die Änderung strikt und sage dem Kunden, er muss sofort telefonisch anrufen.'
                ];
            }

            $agentId = null;
            if (isset($args['__chat_id'])) {
                $agentId = $args['__agent_id'] ?? null;
            }

            if ($action === 'change_address') {
                $newAddr = is_array($args['new_address_data']) ? $args['new_address_data'] : [];
                if (empty($newAddr['street']) || empty($newAddr['city']) || empty($newAddr['zipcode'])) {
                    return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Es fehlen Strasse, Stadt oder PLZ in den Daten. Bitte beim Kunden genauer erfragen.'];
                }

                $beforeData = ['shipping_address' => $order->shipping_address];
                
                $log = \App\Models\System\SystemLog::start(
                    'ai_order_modify',
                    "KI hat Adresse von Order {$order->order_number} geändert",
                    'automation',
                    $agentId
                );

                $addr = $order->shipping_address;
                $addr['first_name'] = $newAddr['first_name'] ?? ($addr['first_name'] ?? '');
                $addr['last_name']  = $newAddr['last_name'] ?? ($addr['last_name'] ?? '');
                $addr['street']     = $newAddr['street'] ?? ($addr['street'] ?? '');
                $addr['house_number']= $newAddr['house_number'] ?? ($addr['house_number'] ?? '');
                $addr['zipcode']    = $newAddr['zipcode'] ?? ($addr['zipcode'] ?? '');
                $addr['city']       = $newAddr['city'] ?? ($addr['city'] ?? '');

                $order->update(['shipping_address' => $addr]);

                $log->finish('success', 'Adresse erfolgreich überschrieben.', [
                    'before' => $beforeData,
                    'after' => ['shipping_address' => $addr]
                ]);

                return ['status' => 'success', 'message' => 'HINTERGRUND-INFO FÜR KI: Adresse wurde erfolgreich überschrieben. Gib dem Kunden kurz bescheid.'];
            } elseif ($action === 'cancel_order') {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: TECHNISCHE SPERRE. DU DARFST KEINE BESTELLUNGEN STORNIEREN! Antworte dem Kunden nun verbindlich, dass er bitte das Widerrufsformular unter /widerruf nutzen muss.'];
            }

            return ['status' => 'error', 'message' => 'Unbekannte Aktion.'];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Abbruch durch Systemfehler ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetInvoiceLink(array $args)
    {
        try {
            $orderNumber = trim($args['order_number'] ?? '');
            if (empty($orderNumber)) return ['status' => 'error', 'message' => 'HINTERGRUND-INFO: Bestellnummer nicht übergeben.'];

            $order = OrderOrder::where('order_number', 'LIKE', "%{$orderNumber}%")->with('invoices')->first();
            if (!$order) {
                return ['status' => 'not_found', 'message' => 'HINTERGRUND-INFO: Bestellung nicht gefunden.'];
            }

            $currentCustomerId = auth()->guard('customer')->id();
            if (!$currentCustomerId || $order->customer_id !== $currentCustomerId) {
                return ['status' => 'error', 'message' => 'HINTERGRUND-INFO FÜR KI: Aus Datenschutzgründen strikt verweigert. Diese Bestellung gehört nicht zum aktuell eingeloggten Kunden! Bitte um Loginstruct.'];
            }

            $invoices = $order->invoices;
            if ($invoices->isEmpty()) {
                return ['status' => 'success', 'message' => 'HINTERGRUND-INFO FÜR KI: Zu dieser Bestellung wurde noch keine Rechnung generiert (Status ist evt. noch nicht versendet). Vertröste den Kunden.'];
            }

            $invLines = [];
            foreach ($invoices as $inv) {
                $url = url("/invoice/{$inv->id}/download");
                $invLines[] = "- Rechnung {$inv->invoice_number} über " . number_format($inv->total / 100, 2, ',', '.') . " € (Link: {$url})";
            }

            return ['status' => 'success', 'message' => "SYSTEM-DIREKTIVE: Gib folgenden Text so ähnlich aus: \"Ich habe offizielle Rechnungs-Dokumente zu deiner Bestellung gefunden:\n" . implode("\n", $invLines) . "\""];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Fehler beim Suchen der Rechnung ('{$e->getMessage()}')."];
        }
    }

    public static function executeGetManufacturingGuidelines(array $args)
    {
        return [
            'status' => 'success',
            'message' => "HINTERGRUND-INFO / REGELWERK ZUR PRODUKTION:\n1. Emojis: Klassische bunte Handy-Emojis können wir mit dem Laser NICHT gravieren. Reine Text-Symbole wie <3 oder ein Standard-Herzchen (❤) sind aber meist möglich.\n2. Zeichenfolgen: Wir gravieren so, wie der Kunde es eintippt. Es wird nicht nochmal auf Rechtschreibung geprüft.\n3. Sonderwünsche: Wenn etwas graviert werden muss, was unser Konfigurator nicht zulässt, muss das Produkt ggf. abgelehnt werden."
        ];
    }

    public static function executeGetCartTotal(array $args)
    {
        try {
            $cartService = app(\App\Services\CartService::class);
            $cart = $cartService->getCart();
            if (!$cart || $cart->items->isEmpty()) {
                return ['status' => 'success', 'message' => "HINTERGRUND-INFO AN KI: Der Warenkorb des Kunden ist aktuell komplett leer."];
            }

            $totals = $cartService->calculateTotals($cart);
            $gross = number_format($totals['gross_total'] / 100, 2, ',', '.') . ' €';
            $shipping = number_format($totals['shipping'] / 100, 2, ',', '.') . ' €';

            return [
                'status' => 'success',
                'message' => "HINTERGRUND-INFO AN KI: Aktuelle Summe im Warenkorb:\nVersandkosten: {$shipping}\nGesamtbetrag: {$gross}\nTeile dies dem Kunden freundlich mit."
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AiSupportFuncs Fehler: ' . $e->getMessage());
            return ['status' => 'error', 'message' => "HINTERGRUND-INFO FÜR KI: Warenkorbsumme konnte nicht berechnet werden. ('{$e->getMessage()}')"];
        }
    }
}
