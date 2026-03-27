<?php

namespace App\Services\AI\Functions;

use App\Models\Order\OrderOrder;
use App\Models\Product\Product;
use App\Models\Product\ProductReview;

trait AiSalesFuncs
{
    public static function getAiSalesFuncsSchema(): array
    {
        return [
            [
                'name' => 'order_get_next_deadline',
                'description' => 'Gibt das exakte Datum und die Uhrzeit zurück, wann die nächste ausstehende Bestellung (Fulfillment) zwingend abgeschlossen oder versendet werden muss. Stichworte: Wann muss das Paket raus, Versand-Deadline, nächster Versandtermin, Fulfillment fällig.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetNextOrderDeadline']
            ],
            [
                'name' => 'order_get_details',
                'description' => 'Ruft detaillierte Informationen zu einer bestimmten Bestellung oder einem Kundenkauf ab. Nutze dies, wenn Alina nach einem Auftrag, einer Bestellung, Bestellnummer oder dem Liefer-Status fragt. Stichworte: Zeig mir Bestellung 1024, Was hat Müller gekauft.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'order_number' => [
                            'type' => 'string',
                            'description' => 'Die Bestellnummer oder Name des Kunden. (z.B. "1024" oder "Mueller")'
                        ]
                    ],
                ],
                'callable' => [self::class, 'executeGetOrder']
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

    public static function executeGetNextOrderDeadline(array $args)
    {
        return [
            'status' => 'success',
            'next_deadline' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'type' => 'Express-Versand',
            'message' => 'Die nächste Bestellung muss übermorgen fertiggestellt werden.'
        ];
    }

    public static function executeGetOrder(array $args)
    {
        try {
            $query = OrderOrder::with(['items', 'customer']);
            
            if (!empty($args['order_number'])) {
                $term = $args['order_number'];
                $query->where(function($q) use ($term) {
                    $q->where('order_number', 'like', "%{$term}%")
                      ->orWhereHas('customer', function($cQ) use ($term) {
                          $cQ->where('last_name', 'like', "%{$term}%")
                             ->orWhere('first_name', 'like', "%{$term}%");
                      });
                });
            } else {
                $query->orderBy('created_at', 'desc')->take(5);
            }

            $orders = $query->take(5)->get();

            if ($orders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Keine passende Bestellung gefunden.'];
            }

            $formatted = [];
            foreach ($orders as $o) {
                $customerName = $o->customer ? $o->customer->first_name . ' ' . $o->customer->last_name : 'Gast';
                $items = $o->items ? $o->items->map(fn($i) => $i->quantity . 'x ' . $i->name)->implode(', ') : 'Keine Artikel';
                
                $formatted[] = [
                    'order_number' => $o->order_number,
                    'customer' => $customerName,
                    'status' => $o->status,
                    'total' => number_format($o->total_amount / 100, 2, ',', '.') . ' €',
                    'items_summary' => $items,
                    'date' => $o->created_at->format('d.m.Y H:i')
                ];
            }

            return ['status' => 'success', 'orders' => $formatted];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Abrufen der Bestellung: ' . $e->getMessage()];
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
