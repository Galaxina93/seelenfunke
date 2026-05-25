<?php

namespace App\Services\AI\Functions;

use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Management\ManagementTask;
use App\Services\Export\FileDownloadService;
use App\Models\Order\OrderOrderItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

trait AiOrderFuncs
{
    public static function getAiOrderFuncsSchema(): array
    {
        return [
            [
                'name' => 'order_get_current_express_status',
                'description' => 'Prüft, wie viele Express-Aufträge gerade offen sind und wie der Stau ist. Erkläre dem Kunden bei Nachfragen immer, dass Express "Priorisierte Fertigung so schnell wie möglich" bedeutet, aber keine garantierten Zustelldaten beinhaltet.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetCurrentExpressStatus']
            ],
            [
                'name' => 'order_get_details',
                'description' => 'Ruft detaillierte Informationen zu einer bestimmten Bestellung oder mehreren passenden Bestellungen ab. Unterstützt Suche nach Bestellnummer, E-Mail, Kundenname, Betragsspannen (in Euro) und Status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Optional: Die genaue Bestellnummer oder E-Mail des Kunden.'
                        ],
                        'customer_name' => [
                            'type' => 'string',
                            'description' => 'Optional: Unscharfe Suche nach Vorname oder Nachname des Kunden.'
                        ],
                        'min_total' => [
                            'type' => 'number',
                            'description' => 'Optional: Mindest-Bestellwert in Euro (z.B. 49.99).'
                        ],
                        'max_total' => [
                            'type' => 'number',
                            'description' => 'Optional: Maximal-Bestellwert in Euro (z.B. 150.00).'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter nach Bestellstatus.',
                            'enum' => ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded']
                        ],
                        'payment_status' => [
                            'type' => 'string',
                            'description' => 'Optional: Filter nach Zahlungsstatus.',
                            'enum' => ['paid', 'unpaid', 'pending', 'refunded']
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetOrder']
            ],
            [
                'name' => 'order_update_status',
                'description' => 'Ändert den Hauptstatus oder Zahlungsstatus einer Bestellung. Status-Möglichkeiten: pending, processing, shipped, completed, cancelled, refunded. Zahlungsstatus-Möglichkeiten: paid, unpaid, pending, refunded.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Die Bestellnummer der Bestellung.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Der neue Hauptstatus. Gültige Werte: pending, processing, shipped, completed, cancelled, refunded.',
                            'enum' => ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded']
                        ],
                        'payment_status' => [
                            'type' => 'string',
                            'description' => 'Der neue Zahlungsstatus. Gültige Werte: paid, unpaid, pending, refunded.',
                            'enum' => ['paid', 'unpaid', 'pending', 'refunded']
                        ]
                    ],
                    'required' => ['order_number']
                ],
                'callable' => [self::class, 'executeOrderUpdateStatus']
            ],
            [
                'name' => 'order_generate_xtool_svg',
                'description' => 'Generiert die Laser-Produktionsdatei (XTool SVG) für eine Bestellposition und führt eine Aktion aus: Mail an Kunde/Produktion, Herunterladen als Link oder Speichern im Dateimanager.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_item_id' => [
                            'type' => 'integer',
                            'description' => 'Die ID der Bestellposition (order_item_id).'
                        ],
                        'side' => [
                            'type' => 'string',
                            'description' => 'Welche Seite gelasert wird. Standard ist front.',
                            'enum' => ['front', 'back']
                        ],
                        'action' => [
                            'type' => 'string',
                            'description' => 'Was mit der Datei passieren soll: "mail" (sendet Email), "download" (liefert Download-Link), "filemanager" (Speichert im Workspace-Tresor).',
                            'enum' => ['mail', 'download', 'filemanager']
                        ],
                        'mail_to' => [
                            'type' => 'string',
                            'description' => 'Optional: Falls action=mail, kann hier eine spezifische E-Mail-Adresse angegeben werden. Wenn leer, wird an die Kunden-Email gesendet.'
                        ],
                        'design' => [
                            'type' => 'string',
                            'description' => 'Das visuelle Design der E-Mail (nur relevant falls action=mail). "seelenfunke" (inkl. Briefkopf, CI-Farben, Logo) oder "generic" (neutrales Design ohne Firmenbezug). Standardmäßig "seelenfunke", es sei denn, der Nutzer wünscht neutral.',
                            'enum' => ['seelenfunke', 'generic']
                        ]
                    ],
                    'required' => ['order_item_id', 'action']
                ],
                'callable' => [self::class, 'executeOrderGenerateXtool']
            ],
            [
                'name' => 'order_get_urgent_tasks',
                'description' => 'Prüft, welche Aufgabe (Task) aktuell die wichtigste oder dringlichste ist. Dies durchsucht die To-Do Listen nach unerledigten Aufgaben mit hoher Priorität.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Wie viele wichtige Aufgaben sollen abgerufen werden? (Standard: 3)'
                        ]
                    ]
                ],
                'callable' => [self::class, 'executeGetUrgentTasks']
            ],
            [
                'name' => 'product_check_inventory',
                'description' => 'Prüft den aktuellen Lagerbestand von physischen Produkten im Shop. Nutze dies IMMER, wenn Alina nach Beständen, Mengen oder ausverkauften Artikeln fragt. Stichworte: Haben wir noch XYZ auf Lager, Was ist ausverkauft, Lagerbestand prüfen, Inventory checken.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Optional: Ein detaillierter Suchbegriff (Produktname oder SKU), um bestimmte Artikel zu prüfen.'
                        ]
                    ], 
                ],
                'callable' => [self::class, 'executeCheckInventory']
            ],
            [
                'name' => 'product_get_reviews',
                'description' => 'Checkt die aktuell unmoderierten / ungelesenen Produktbewertungen (Reviews) der Kunden für den Shop. Stichworte: Neue Bewertungen, Was sagen die Kunden, ungelesene Reviews, Produkt Feedback, Shop Rezensionen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetProductReviews']
            ]
        ];
    }

    public static function executeGetCurrentExpressStatus(array $args)
    {
        $expressCount = OrderOrder::whereIn('status', ['pending', 'processing'])->where('is_express', true)->count();
        return [
            'status' => 'success',
            'open_express_orders' => $expressCount,
            'message' => 'Aktuell gibt es ' . $expressCount . ' offene Express-Aufträge in der Manufaktur.'
        ];
    }

    public static function executeGetOrder(array $args)
    {
        try {
            $query = OrderOrder::with(['items', 'customer', 'invoices']);
            
            $hasFilters = false;

            if (!empty($args['order_number'])) {
                $term = $args['order_number'];
                $query->where(function($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
                $hasFilters = true;
            }

            if (!empty($args['customer_name'])) {
                $name = $args['customer_name'];
                $query->where(function($q) use ($name) {
                    $q->whereHas('customer', function($cQ) use ($name) {
                        $cQ->where('last_name', 'like', "%{$name}%")
                           ->orWhere('first_name', 'like', "%{$name}%");
                    })
                    ->orWhere('billing_address->first_name', 'like', "%{$name}%")
                    ->orWhere('billing_address->last_name', 'like', "%{$name}%")
                    ->orWhere('shipping_address->first_name', 'like', "%{$name}%")
                    ->orWhere('shipping_address->last_name', 'like', "%{$name}%");
                });
                $hasFilters = true;
            }

            if (isset($args['min_total'])) {
                $minCents = (int) round($args['min_total'] * 100);
                $query->where('total_price', '>=', $minCents);
                $hasFilters = true;
            }

            if (isset($args['max_total'])) {
                $maxCents = (int) round($args['max_total'] * 100);
                $query->where('total_price', '<=', $maxCents);
                $hasFilters = true;
            }

            if (!empty($args['status'])) {
                $query->where('status', $args['status']);
                $hasFilters = true;
            }

            if (!empty($args['payment_status'])) {
                $query->where('payment_status', $args['payment_status']);
                $hasFilters = true;
            }

            if (!$hasFilters) {
                $query->orderBy('created_at', 'desc')->take(1);
                $limit = 1;
            } else {
                $query->orderBy('created_at', 'desc');
                $limit = 10;
            }

            $orders = $query->take($limit)->get();

            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Keine passende Bestellung gefunden.'];
            }

            $formatted = [];
            foreach ($orders as $o) {
                $customerName = $o->customer ? $o->customer->first_name . ' ' . $o->customer->last_name : 'Gast';
                
                $itemsDetail = [];
                if ($o->items) {
                    foreach ($o->items as $item) {
                        $itemsDetail[] = [
                            'id' => $item->id,
                            'name' => $item->name,
                            'quantity' => $item->quantity,
                            'unit_price' => number_format($item->unit_price / 100, 2, ',', '.') . ' €',
                            'total_price' => number_format($item->total_price / 100, 2, ',', '.') . ' €'
                        ];
                    }
                }

                $billing = $o->billing_address ?? [];
                $shipping = $o->shipping_address ?? [];
                
                $addressDeviation = false;
                if (!empty($billing) && !empty($shipping)) {
                    $billingStreet = $billing['address'] ?? $billing['street'] ?? '';
                    $shippingStreet = $shipping['address'] ?? $shipping['street'] ?? '';
                    $billingZip = $billing['postal_code'] ?? $billing['zip'] ?? '';
                    $shippingZip = $shipping['postal_code'] ?? $shipping['zip'] ?? '';
                    if (
                        $billingStreet !== $shippingStreet ||
                        $billingZip !== $shippingZip ||
                        ($billing['city'] ?? '') !== ($shipping['city'] ?? '') ||
                        ($billing['last_name'] ?? '') !== ($shipping['last_name'] ?? '')
                    ) {
                        $addressDeviation = true;
                    }
                }

                $formatted[] = [
                    'order_id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'payment_method' => $o->payment_method,
                    'customer' => [
                        'name' => $customerName,
                        'email' => $o->email,
                    ],
                    'cost_overview' => [
                        'subtotal' => number_format($o->subtotal_price / 100, 2, ',', '.') . ' €',
                        'discount' => number_format($o->discount_amount / 100, 2, ',', '.') . ' €',
                        'shipping' => number_format($o->shipping_price / 100, 2, ',', '.') . ' €',
                        'tax' => number_format($o->tax_amount / 100, 2, ',', '.') . ' €',
                        'total' => number_format($o->total_price / 100, 2, ',', '.') . ' €'
                    ],
                    'address_info' => [
                        'billing' => $billing,
                        'shipping' => $shipping,
                        'has_deviation' => $addressDeviation
                    ],
                    'items_count' => count($itemsDetail),
                    'items' => $itemsDetail,
                    'date' => $o->created_at->format('d.m.Y H:i')
                ];
            }

            return ['status' => 'success', 'orders' => $formatted];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Bestellungen: ' . $e->getMessage()];
        }
    }

    public static function executeOrderUpdateStatus(array $args)
    {
        try {
            $order = OrderOrder::where('order_number', $args['order_number'])->first();
            if (!$order) {
                return ['status' => 'error', 'message' => 'Bestellung nicht gefunden.'];
            }

            $updates = [];
            if (!empty($args['status'])) {
                if (!in_array($args['status'], ['pending', 'processing', 'shipped', 'completed', 'cancelled', 'refunded'])) {
                    return ['status' => 'error', 'message' => 'Ungültiger Status: ' . $args['status']];
                }
                $updates['status'] = $args['status'];
            }

            if (!empty($args['payment_status'])) {
                if (!in_array($args['payment_status'], ['paid', 'unpaid', 'pending', 'refunded'])) {
                    return ['status' => 'error', 'message' => 'Ungültiger Payment Status: ' . $args['payment_status']];
                }
                $updates['payment_status'] = $args['payment_status'];
            }

            if (empty($updates)) {
                return ['status' => 'error', 'message' => 'Keine Statuswerte zum Aktualisieren übergeben.'];
            }

            $order->update($updates);

            return [
                'status' => 'success', 
                'message' => 'Bestellung ' . $order->order_number . ' erfolgreich aktualisiert.',
                'new_state' => $updates
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Aktualisieren: ' . $e->getMessage()];
        }
    }

    public static function executeOrderGenerateXtool(array $args, $agent = null)
    {
        try {
            $item = OrderOrderItem::with('order')->find($args['order_item_id']);
            if (!$item) {
                return ['status' => 'error', 'message' => 'Bestellposition nicht gefunden.'];
            }

            $side = $args['side'] ?? 'front';
            $action = $args['action'] ?? 'download';
            
            // Generate the SVG content via FileDownloadService Logic directly or capture its output
            $service = new FileDownloadService();
            // Since downloadLaserSvg returns a stream response, we need to capture the output buffering
            ob_start();
            $response = $service->downloadLaserSvg($item->id, $side);
            $response->sendContent();
            $svgContent = ob_get_clean();

            $filename = 'xTool-F2-Druckdatei-' . ($item->order->order_number ?? 'Angebot') . '-Pos-' . $item->id . '-' . ($side === 'back' ? 'Rueckseite' : 'Vorderseite') . '.svg';

            if ($action === 'filemanager') {
                $path = 'agenten/workspace/laser-svgs/' . $filename;
                Storage::disk('public')->put($path, $svgContent);
                return [
                    'status' => 'success',
                    'message' => 'Die Datei wurde erfolgreich im Dateimanager (AI Workspace Tresor) gespeichert.',
                    'filename' => $filename,
                    'path' => $path
                ];
            } 
            elseif ($action === 'download') {
                $path = 'ai_downloads/' . $filename;
                Storage::disk('public')->put($path, $svgContent);
                $url = url('storage/' . $path);
                return [
                    'status' => 'success',
                    'message' => 'Die SVG Datei wurde generiert. Du kannst dem Nutzer nun folgenden Download-Link geben:',
                    'download_url' => $url
                ];
            }
            elseif ($action === 'mail') {
                $path = 'ai_downloads/' . $filename;
                Storage::disk('public')->put($path, $svgContent);
                
                $targetEmail = $args['mail_to'] ?? $item->order->email;
                if (!$targetEmail) {
                    return ['status' => 'error', 'message' => 'Keine E-Mail Adresse hinterlegt (weder vom Kunden, noch in mail_to übergeben).'];
                }

                $subject = "Produktions-Datei: " . $filename;
                $absolutePath = storage_path('app/public/' . $path);
                
                $agentName = $agent ? $agent->name : 'Bestell-Agent';
                $body = "Hallo,\n\nanbei befindet sich die generierte XTool Laserdatei für die Bestellung " . $item->order->order_number . ".\n\nViele Grüße";
                $design = $args['design'] ?? 'seelenfunke';

                Mail::to($targetEmail)->send(new \App\Services\AI\Mails\AiAgentMessageMail($subject, $body, $agentName, [$absolutePath], $design));

                return [
                    'status' => 'success',
                    'message' => 'Die Datei wurde generiert und per E-Mail an ' . $targetEmail . ' gesendet.'
                ];
            }

            return ['status' => 'error', 'message' => 'Unbekannte Aktion.'];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der SVG Generierung: ' . $e->getMessage()];
        }
    }

    public static function executeGetUrgentTasks(array $args)
    {
        try {
            $limit = $args['limit'] ?? 3;
            $tasks = ManagementTask::with('list')
                ->where('is_completed', false)
                ->where('is_archived', false)
                ->orderBy('priority', 'desc')
                ->orderBy('created_at', 'asc')
                ->take($limit)
                ->get();

            if ($tasks->isEmpty()) {
                return ['status' => 'success', 'message' => 'Aktuell gibt es keine offenen Aufgaben. Alles erledigt!'];
            }

            $formatted = [];
            foreach ($tasks as $t) {
                $formatted[] = [
                    'id' => $t->id,
                    'title' => $t->title,
                    'priority' => $t->priority,
                    'list_name' => $t->list ? $t->list->name : 'Allgemein',
                    'created_at' => $t->created_at->format('d.m.Y H:i')
                ];
            }

            return [
                'status' => 'success', 
                'message' => 'Die aktuell wichtigsten/dringendsten Aufgaben:',
                'tasks' => $formatted
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden der Aufgaben: ' . $e->getMessage()];
        }
    }

    public static function executeCheckInventory(array $args)
    {
        try {
            $query = Product::where('status', 'active')
                ->where('track_quantity', true);
                
            if (!empty($args['search_query'])) {
                $query->where(function($q) use ($args) {
                    $q->where('name', 'like', '%' . $args['search_query'] . '%')
                      ->orWhere('sku', 'like', '%' . $args['search_query'] . '%');
                });
            }

            $products = $query->orderBy('quantity', 'asc')->take(20)->get();

            if ($products->isEmpty()) {
                return ['status' => 'success', 'message' => 'Keine physischen Produkte gefunden, oder der Lagerbestand wird nicht getrackt.'];
            }

            $inventory = [];
            foreach ($products as $p) {
                $inventory[] = [
                    'name' => $p->name,
                    'sku' => $p->sku,
                    'quantity' => $p->quantity,
                    'status' => $p->quantity <= 0 ? 'Ausverkauft' : 'Verfügbar'
                ];
            }

            return ['status' => 'success', 'products' => $inventory];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Laden des Inventars: ' . $e->getMessage()];
        }
    }

    public static function executeGetProductReviews(array $args)
    {
        try {
            $reviews = ProductReview::with('product')
                ->where('is_approved', false)
                ->orderBy('created_at', 'desc')->take(5)->get();

            if ($reviews->isEmpty()) {
                return ['status' => 'success', 'message' => 'Aktuell gibt es keine neuen Bewertungen, die auf Freigabe warten.'];
            }

            $formatted = [];
            foreach ($reviews as $r) {
                $formatted[] = [
                    'product_name' => $r->product ? $r->product->name : 'Unbekannt',
                    'rating' => $r->rating . '/5 Sterne',
                    'comment' => \Illuminate\Support\Str::limit($r->comment, 80),
                    'customer' => $r->customer_name ?: 'Gast',
                    'date' => $r->created_at->format('d.m.Y')
                ];
            }

            return ['status' => 'success', 'reviews' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Produktbewertungen konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }
}
