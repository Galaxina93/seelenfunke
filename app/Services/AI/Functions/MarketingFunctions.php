<?php

namespace App\Services\AI\Functions;

trait MarketingFunctions
{
    public static function getMarketingFunctionsSchema(): array
    {
        return [
            [
                'name' => 'get_shop_stats',
                'description' => 'Returns deep shop statistics (abandoned carts, potential lost revenue, active vouchers). Use this specifically when analyzing revenue scaling and the 100k goal.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetShopStats']
            ],
            [
                'name' => 'write_blog_post',
                'description' => 'Verfasst einen neuen Blogbeitrag. Nutze dieses Tool proaktiv (von dir aus) während der spontanen Selbst-Analyse, um produktiv zu sein und ein sinnvolles Firmenthema zu behandeln.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Der Titel des Blogbeitrags.'
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Der vollständige HTML-präsente Inhalt (Formatierung mit <h2>, <p>, <strong> etc.)'
                        ],
                        'category_id' => [
                            'type' => 'integer',
                            'description' => 'Optional: Die ID der passenden Blog-Kategorie. Wenn unbekannt, sende 1.'
                        ]
                    ],
                    'required' => ['title', 'content']
                ],
                'callable' => [self::class, 'executeWriteBlogPost']
            ],
        ];
    }

    public static function executeGetShopStats(array $args)
    {
        try {
            $abandonedCarts = \App\Models\Cart\Cart::with('items')
                ->where('updated_at', '>=', now()->subHours(24))
                ->where('updated_at', '<=', now()->subHours(2))
                ->get();

            $potentialRevenueCents = 0;
            foreach ($abandonedCarts as $cart) {
                foreach ($cart->items as $item) {
                    $potentialRevenueCents += ($item->quantity * $item->unit_price);
                }
            }
            $potentialRevenue = $potentialRevenueCents / 100;

            $autoVouchers = \App\Models\Voucher::where('is_active', true)->where('mode', 'auto')->count();
            $manualVouchers = \App\Models\Voucher::where('is_active', true)->where('mode', 'manual')->count();

            return [
                'status' => 'success',
                'scaling_metrics' => [
                    'abandoned_carts_count' => $abandonedCarts->count(),
                    'potential_lost_revenue' => $potentialRevenue,
                    'active_auto_vouchers' => $autoVouchers,
                    'active_manual_vouchers' => $manualVouchers
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeWriteBlogPost(array $args)
    {
        try {
            if (empty($args['title']) || empty($args['content'])) {
                return ['status' => 'error', 'message' => 'Titel oder Inhalt fehlen für den Blogbeitrag.'];
            }

            $slug = \Illuminate\Support\Str::slug($args['title']);

            \App\Models\Blog\BlogPost::create([
                'title' => $args['title'],
                'slug' => $slug,
                'content' => $args['content'],
                'blog_category_id' => $args['category_id'] ?? 1,
                'is_published' => true,
                'published_at' => now(),
            ]);

            return [
                'status' => 'success',
                'message' => "Der Blogbeitrag '{$args['title']}' wurde erfolgreich publiziert."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Publizieren: ' . $e->getMessage()];
        }
    }
}
