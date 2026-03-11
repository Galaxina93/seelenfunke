<?php

namespace App\Services\AI\Functions;

use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;

trait ShopFunctions
{
    public static function getShopFunctionsSchema(): array
    {
        return [
            [
                'name' => 'check_inventory',
                'description' => 'Prüft den aktuellen Lagerbestand von physischen Produkten im Shop. Nutze dies IMMER, wenn Alina nach Beständen, Mengen oder ausverkauften Artikeln fragt.',
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
                'name' => 'get_product_reviews',
                'description' => 'Checkt die aktuell freizugebenden/ungelesenen Produktbewertungen der Kunden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetProductReviews']
            ],
            [
                'name' => 'get_gamification_leaderboard',
                'description' => 'Zeigt die aktuell motiviertesten Gamification-Kunden nach XP und Leveln (Highscore-Liste).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetGamificationLeaderboard']
            ],
            [
                'name' => 'search_customers',
                'description' => 'Sucht nach einem Kunden im System. Nutze dies, wenn du Infos über eine bestimmte Person heraussuchen sollst.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search_query' => [
                            'type' => 'string',
                            'description' => 'Vorname, Nachname oder Email des Kunden.'
                        ]
                    ],
                    'required' => ['search_query']
                ],
                'callable' => [self::class, 'executeSearchCustomers']
            ],
        ];
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

    public static function executeGetGamificationLeaderboard(array $args)
    {
        try {
            $leaders = CustomerGamification::with('customer')
                ->orderBy('total_xp', 'desc')
                ->take(5)->get();

            if ($leaders->isEmpty()) {
                return ['status' => 'success', 'message' => 'Noch keine Spieler in der Gamification-Tabelle.'];
            }

            $formatted = [];
            foreach ($leaders as $idx => $l) {
                $cName = $l->customer ? ($l->customer->first_name . ' ' . substr($l->customer->last_name, 0, 1) . '.') : 'Unbekannt';
                $formatted[] = [
                    'rank' => $idx + 1,
                    'customer' => $cName,
                    'level' => $l->current_level,
                    'xp' => number_format($l->total_xp, 0, ',', '.') . ' XP',
                    'title' => $l->title ?? 'Novize'
                ];
            }

            return ['status' => 'success', 'leaderboard' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Level-Statistiken konnten nicht geladen werden: ' . $e->getMessage()];
        }
    }

    public static function executeSearchCustomers(array $args)
    {
        try {
            if (empty($args['search_query'])) return ['status' => 'error', 'message' => 'Suchbegriff fehlt.'];
            $term = $args['search_query'];

            $customers = Customer::where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->take(3)->get();

            if ($customers->isEmpty()) {
                return ['status' => 'success', 'message' => "Kunde '$term' nicht gefunden."];
            }

            $formatted = [];
            foreach ($customers as $c) {
                $orderCount = \App\Models\Order\Order::where('customer_id', $c->id)->count();
                $spentCents = \App\Models\Order\Order::where('customer_id', $c->id)->where('status', 'completed')->sum('total_amount');
                
                $formatted[] = [
                    'name' => $c->first_name . ' ' . $c->last_name,
                    'email' => $c->email,
                    'registered_since' => $c->created_at ? $c->created_at->format('d.m.Y') : '-',
                    'total_orders' => $orderCount,
                    'total_spent' => number_format($spentCents / 100, 2, ',', '.') . ' €'
                ];
            }

            return ['status' => 'success', 'customers' => $formatted];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Kundensuche fehlgeschlagen: ' . $e->getMessage()];
        }
    }
}
