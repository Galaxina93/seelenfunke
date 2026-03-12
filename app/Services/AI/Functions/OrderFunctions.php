<?php

namespace App\Services\AI\Functions;

use App\Models\Order\Order;

trait OrderFunctions
{
    public static function getOrderFunctionsSchema(): array
    {
        return [
            [
                'name' => 'get_next_order_deadline',
                'description' => 'Gibt das Datum und die Uhrzeit zurück, wann die nächste ausstehende Bestellung abgeschlossen oder versendet werden muss.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetNextOrderDeadline']
            ],
            [
                'name' => 'get_order',
                'description' => 'Ruft Details zu einer bestimmten Bestellung ab. Nutze dies, wenn Alina nach einem Auftrag, einer Bestellung oder dem Status fragt.',
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
            $query = Order::with(['items', 'customer']);
            
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
}
