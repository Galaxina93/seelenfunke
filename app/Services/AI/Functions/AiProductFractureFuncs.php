<?php

namespace App\Services\AI\Functions;

use App\Models\Product\Product;
use App\Models\Product\ProductLoss;

trait AiProductFractureFuncs
{
    /**
     * Define the Product Fracture specific tools for the Analyst Agent
     */
    public static function getAiProductFractureFuncsSchema(): array
    {
        return [
            [
                'name' => 'product_loss_get_overview',
                'description' => 'Gibt einen umfassenden Analyse-Bericht über alle Produktschäden/Verluste zurück. Zeigt globale monetäre Verluste sowie eine sortierte Hit-Liste der Produkte an, die am häufigsten kaputt gehen oder beanstandet werden. Perfekt für das Erkennen von Fehler-Trends oder Lieferanten-Problemen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetOverview']
            ],
            [
                'name' => 'product_loss_get_open_cases',
                'description' => 'Holt eine Liste aller laufenden/offenen Schadensfälle, bei denen noch eine Erstattung vom Hersteller (Refund) aussteht.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetOpenCases']
            ],
            [
                'name' => 'product_loss_report',
                'description' => 'Meldet einen neuen Produktionsfehler oder Bruch (ProductLoss) im System. Zieht den Bestand automatisch ab.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder der ungenaue Name des betroffenen Produkts.'
                        ],
                        'quantity' => [
                            'type' => 'integer',
                            'description' => 'Anzahl der defekten / kaputten Artikel.'
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Ein kurzer, glasklarer Grund für den Ausfall (z.B. "Beim Auspacken zerbrochen" oder "Tinte ausgelaufen").'
                        ]
                    ],
                    'required' => ['product_id', 'quantity', 'reason']
                ],
                'callable' => [self::class, 'executeReportLoss']
            ],
        ];
    }

    public static function executeGetOverview(array $args)
    {
        try {
            $metrics = [
                'total_open_cases' => ProductLoss::whereNull('refund_received_at')->count(),
                'total_refunded_this_month' => ProductLoss::whereNotNull('refund_received_at')->where('refund_received_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
                'total_loss_this_month' => ProductLoss::where('created_at', '>=', now()->startOfMonth())->sum('cost_value') / 100,
                'total_loss_all_time' => ProductLoss::sum('cost_value') / 100,
            ];

            $groupedByProduct = ProductLoss::with('product')
                ->selectRaw('product_id, SUM(quantity) as total_quantity, SUM(cost_value) as total_cost')
                ->groupBy('product_id')
                ->get()
                ->map(function ($loss) {
                    return [
                        'product_id' => $loss->product_id,
                        'product_name' => $loss->product->name ?? 'Unknown',
                        'total_defects_quantity' => (int) $loss->total_quantity,
                        'total_cost_lost' => round($loss->total_cost / 100, 2)
                    ];
                })
                ->sortByDesc('total_cost_lost')
                ->values();

            return [
                'status' => 'success',
                'global_metrics' => $metrics,
                'most_defective_products_ranking' => $groupedByProduct->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetOpenCases(array $args)
    {
        try {
            $openLosses = ProductLoss::with(['product', 'supplier'])
                ->whereNull('refund_received_at')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($loss) {
                    return [
                        'id' => $loss->id,
                        'date' => $loss->created_at->format('Y-m-d'),
                        'product_name' => $loss->product->name ?? 'Unknown',
                        'quantity' => $loss->quantity,
                        'reason' => $loss->reason,
                        'cost_value' => round($loss->cost_value / 100, 2),
                        'supplier_name' => $loss->supplier->name ?? 'Kein Lieferant',
                        'is_reported_to_supplier' => $loss->reported_to_supplier_at ? true : false,
                    ];
                });

            return [
                'status' => 'success',
                'open_cases_count' => $openLosses->count(),
                'cases' => $openLosses->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeReportLoss(array $args)
    {
        try {
            if (empty($args['product_id']) || empty($args['quantity']) || empty($args['reason'])) {
                return ['status' => 'error', 'message' => 'Es fehlen benötigte Parameter (product_id, quantity, reason).'];
            }

            $product = Product::find($args['product_id']);
            
            if (!$product) {
                // Fallback über Namen
                $product = Product::where('name', 'like', '%' . $args['product_id'] . '%')->first();
                if (!$product) {
                    return ['status' => 'error', 'message' => 'Produkt wurde nicht gefunden.'];
                }
            }

            if ($product->quantity < $args['quantity']) {
                return ['status' => 'error', 'message' => 'Nicht genug Bestand im Lager vorhanden, um diese Bruchmeldung durchzuführen. Buchbestand: ' . $product->quantity];
            }

            $costValue = ($product->purchase_price ?? 0) * (int)$args['quantity'];

            $loss = ProductLoss::create([
                'product_id' => $product->id,
                'supplier_id' => $product->supplier_id ?? null,
                'quantity' => (int)$args['quantity'],
                'cost_value' => $costValue,
                'reason' => substr($args['reason'], 0, 255),
                'recorded_by' => auth('admin')->id() ?? 1,
            ]);

            $product->reduceStock((int)$args['quantity']);

            return [
                'status' => 'success',
                'message' => "Bruch/Schaden erfolgreich erfasst. Lagerbestand verringert. Schaden monetär verbucht.",
                'loss_record_id' => $loss->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Erstellen der Schadensmeldung: ' . $e->getMessage()];
        }
    }
}
