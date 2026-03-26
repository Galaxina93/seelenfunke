<?php

namespace App\Services\AI\Functions;

use App\Models\Marketing\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait AiMarketingVoucherFuncs
{
    public static function getAiMarketingVoucherFuncsSchema(): array
    {
        return [
            [
                'name' => 'marketing_voucher_get_all',
                'description' => 'Gibt alle existierenden Gutscheine zurück (automatisch und manuell).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => [
                            'type' => 'string',
                            'description' => 'Optionaler Filter nach Modus: "auto" oder "manual".'
                        ]
                    ]
                ],
                'callable' => [self::class, 'marketing_voucher_get_all']
            ],
            [
                'name' => 'marketing_voucher_create',
                'description' => 'Erstellt einen neuen, manuellen Gutscheincode für den Shop.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'string',
                            'description' => 'Der Gutscheincode (z.B. SUMMER26).'
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Gutschein-Art: "fixed" (Fester Euro-Betrag) oder "percent" (Prozentwert).'
                        ],
                        'value' => [
                            'type' => 'number',
                            'description' => 'Der tatsächliche Wert in EURO oder als reiner Prozentwert (z.B. 10 für 10€ oder 10%).'
                        ],
                        'min_order_value' => [
                            'type' => 'number',
                            'description' => 'Mindestbestellwert in Euro. Null falls es keinen gibt.'
                        ],
                        'usage_limit' => [
                            'type' => 'integer',
                            'description' => 'Wie oft darf dieser Code insgesamt weltweit eingelöst werden? Null für unendlich.'
                        ],
                        'valid_until' => [
                            'type' => 'string',
                            'description' => 'Ablaufdatum (YYYY-MM-DD). Null für unendlich gültig.'
                        ]
                    ],
                    'required' => ['code', 'type', 'value']
                ],
                'callable' => [self::class, 'marketing_voucher_create']
            ],
            [
                'name' => 'marketing_voucher_toggle_active',
                'description' => 'Pausiert (deaktiviert) oder aktiviert einen Gutschein basierend auf seiner ID.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'ID des Gutscheins.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'marketing_voucher_toggle_active']
            ],
            [
                'name' => 'marketing_voucher_delete',
                'description' => 'Löscht einen manuellen Gutschein permanent.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'ID des zu löschenden Gutscheins.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'marketing_voucher_delete']
            ],
            [
                'name' => 'marketing_voucher_get_analytics',
                'description' => 'Ruft die Top-Gutscheine der letzten 12 Monate ab und wie oft sie bei Bestellungen verwendet wurden.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'marketing_voucher_get_analytics']
            ]
        ];
    }

    public static function marketing_voucher_get_all(array $args)
    {
        $query = Voucher::query();
        if (isset($args['mode']) && in_array($args['mode'], ['auto', 'manual'])) {
            $query->where('mode', $args['mode']);
        }
        
        $vouchers = $query->get(['id', 'code', 'type', 'value', 'mode', 'is_active', 'usage_limit', 'min_order_value', 'valid_from', 'valid_until', 'used']);
        
        // Werte für den Agenten bereinigen
        $vouchers->transform(function($v) {
            $v->value_formatted = $v->type === 'fixed' ? ($v->value / 100) . ' EUR' : $v->value . ' %';
            $v->min_order_formatted = $v->min_order_value ? ($v->min_order_value / 100) . ' EUR' : 'Kein Limit';
            return $v;
        });

        return [
            'status' => 'success',
            'count' => $vouchers->count(),
            'vouchers' => $vouchers->toArray()
        ];
    }

    public static function marketing_voucher_create(array $args)
    {
        $code = strtoupper(trim($args['code']));

        if (Voucher::where('code', $code)->exists()) {
            return ['status' => 'error', 'message' => "Der Gutscheincode '$code' existiert bereits."];
        }

        $dbValue = ($args['type'] === 'fixed') ? (int)($args['value'] * 100) : (int)$args['value'];
        $dbMinOrder = isset($args['min_order_value']) ? (int)($args['min_order_value'] * 100) : null;
        $validUntil = isset($args['valid_until']) ? Carbon::parse($args['valid_until']) : null;

        $voucher = Voucher::create([
            'code' => $code,
            'title' => 'Manueller Code: ' . $code,
            'type' => $args['type'],
            'is_active' => true,
            'usage_limit' => $args['usage_limit'] ?? null,
            'valid_until' => $validUntil,
            'value' => $dbValue,
            'min_order_value' => $dbMinOrder,
            'mode' => 'manual',
            'valid_from' => now(),
            'used' => 0
        ]);

        return ['status' => 'success', 'message' => 'Gutschein erfolgreich erstellt.', 'id' => $voucher->id];
    }

    public static function marketing_voucher_toggle_active(array $args)
    {
        $v = Voucher::find($args['id']);
        if (!$v) return ['status' => 'error', 'message' => 'Gutschein nicht gefunden.'];

        $v->is_active = !$v->is_active;
        $v->save();

        return ['status' => 'success', 'message' => 'Gutschein ist nun ' . ($v->is_active ? 'Aktiv' : 'Pausiert') . '.'];
    }

    public static function marketing_voucher_delete(array $args)
    {
        $v = Voucher::where('mode', 'manual')->find($args['id']);
        if (!$v) return ['status' => 'error', 'message' => 'Gutschein nicht gefunden oder es handelt sich um keinen manuellen Gutschein.'];

        $v->delete();
        return ['status' => 'success', 'message' => 'Gutschein permanent gelöscht.'];
    }

    public static function marketing_voucher_get_analytics(array $args)
    {
        $start = now()->subMonths(12)->startOfMonth();
        
        $topCoupons = DB::table('orders')
            ->whereNotNull('coupon_code')
            ->where('created_at', '>=', $start)
            ->select('coupon_code', DB::raw('count(*) as total_uses'), DB::raw('sum(total) as generated_revenue_cents'))
            ->groupBy('coupon_code')
            ->orderByDesc('total_uses')
            ->limit(15)
            ->get();

        $topCoupons->transform(function($c) {
            $c->generated_revenue_euro = number_format($c->generated_revenue_cents / 100, 2, ',', '.');
            unset($c->generated_revenue_cents);
            return $c;
        });

        return [
            'status' => 'success',
            'timeframe' => 'Letzte 12 Monate',
            'top_performing_coupons' => $topCoupons->toArray()
        ];
    }
}
