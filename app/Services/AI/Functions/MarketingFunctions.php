<?php

namespace App\Services\AI\Functions;

use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

trait MarketingFunctions
{
    /**
     * Define the schemas for marketing-related functions.
     * Tools: create_coupon, update_coupon, get_coupons, delete_coupon
     */
    public static function getMarketingFunctionsSchema(): array
    {
        return [
            [
                'name' => 'create_coupon',
                'description' => 'Erstelle einen neuen manuellen Rabattgutschein für den Shop. Der User kann dir einfach sagen: "Mache einen 10% Rabatt Gutschein mit Code HALLO10". WICHTIG: Erstelle keine Auto-Gutscheine, sondern nur manuelle Gutscheine.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'string',
                            'description' => 'Der Gutscheincode, z.B. SOMMER26. Muss in Großbuchstaben sein.'
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'entweder "fixed" (für Euro-Beträge) oder "percent" (für prozentual).'
                        ],
                        'value' => [
                            'type' => 'number',
                            'description' => 'Der Wert, z.B. 10 für 10% oder 10 für 10€.'
                        ],
                        'min_order_value' => [
                            'type' => 'number',
                            'description' => 'Optional: Mindestbestellwert in Euro, ab dem der Gutschein gilt (z.B. 50 für 50€).'
                        ],
                        'usage_limit' => [
                            'type' => 'integer',
                            'description' => 'Optional: Wie oft darf der Gutschein maximal insgesamt eingelöst werden (z.B. 100).'
                        ],
                        'valid_until' => [
                            'type' => 'string',
                            'description' => 'Optional: Ablaufdatum im Format YYYY-MM-DD.'
                        ]
                    ],
                    'required' => ['code', 'type', 'value']
                ],
                'callable' => [self::class, 'executeCreateCoupon']
            ],
            [
                'name' => 'update_coupon',
                'description' => 'Ändere die Werte eines existierenden manuellen Gutscheins. Nutze "get_coupons" vorher, um die ID herauszufinden, falls der User nur den Code sagt.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Gutschein ID'
                        ],
                        'code' => [
                            'type' => 'string',
                            'description' => 'Gutscheincode'
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'fixed oder percent'
                        ],
                        'value' => [
                            'type' => 'number',
                            'description' => 'Der neue Rabattwert.'
                        ],
                        'is_active' => [
                            'type' => 'boolean',
                            'description' => 'Soll der Gutschein aktiv oder pausiert sein?'
                        ],
                        'usage_limit' => [
                            'type' => 'integer',
                            'description' => 'Neues Limit. Setze auf null, um es zu entfernen.'
                        ]
                    ],
                    'required' => ['id']
                ],
                'callable' => [self::class, 'executeUpdateCoupon']
            ],
            [
                'name' => 'get_coupons',
                'description' => 'Gibt alle aktuell gespeicherten Gutscheine und deren Metriken zurück (Nutzungen, Wert, ID). Mache das immer, bevor du einen Gutschein bearbeitest oder löschst, um den genauen Namen oder die ID zu kennen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [] // empty properties
                ],
                'callable' => [self::class, 'executeGetCoupons']
            ],
            [
                'name' => 'delete_coupon',
                'description' => 'Löscht einen manuellen Gutschein vollständig.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'string',
                            'description' => 'Der Gutscheincode, z.B. XMAS26.'
                        ]
                    ],
                    'required' => ['code']
                ],
                'callable' => [self::class, 'executeDeleteCoupon']
            ]
        ];
    }

    public static function executeCreateCoupon(array $args): array
    {
        $code = strtoupper($args['code'] ?? '');
        $type = $args['type'] ?? 'percent';
        $value = $args['value'] ?? 0;
        
        if (empty($code)) {
            return ['status' => 'error', 'message' => 'Gutscheincode fehlt.'];
        }
        
        if (Voucher::where('code', $code)->exists()) {
            return ['status' => 'error', 'message' => "Der Gutscheincode '$code' existiert bereits! Wähle einen anderen Namen."];
        }

        $dbValue = ($type === 'fixed') ? (int)($value * 100) : (int)$value;
        $dbMinOrder = isset($args['min_order_value']) ? (int)($args['min_order_value'] * 100) : null;

        $voucher = Voucher::create([
            'code' => $code,
            'title' => 'Manueller Code: ' . $code,
            'type' => $type,
            'is_active' => true,
            'usage_limit' => $args['usage_limit'] ?? null,
            'valid_until' => $args['valid_until'] ?? null,
            'value' => $dbValue,
            'min_order_value' => $dbMinOrder,
            'mode' => 'manual',
            'valid_from' => now(),
        ]);

        return [
            'status' => 'success',
            'message' => "Der Gutschein '$code' wurde erfolgreich erstellt.",
            'voucher' => $voucher->toArray(),
            '_frontend_event' => [
                'name' => 'open-ai-visualization',
                'detail' => [
                    'category' => 'voucher',
                    'data' => [$voucher->toArray()] // Wrap in array to ensure consistency for UI Router
                ]
            ],
            '_fast_track' => true
        ];
    }

    public static function executeUpdateCoupon(array $args): array
    {
        $id = $args['id'] ?? null;
        if (!$id) {
            return ['status' => 'error', 'message' => 'Gutschein ID fehlt.'];
        }

        $voucher = Voucher::find($id);
        if (!$voucher) {
            return ['status' => 'error', 'message' => 'Gutschein nicht gefunden.'];
        }

        if (strcasecmp($voucher->mode, 'auto') === 0) {
            return ['status' => 'error', 'message' => 'Saisonale Auto-Gutscheine können nicht manuell bearbeitet werden. Nur manuell angelegte Gutscheine können geändert werden.'];
        }

        if (isset($args['code'])) $voucher->code = strtoupper($args['code']);
        if (isset($args['type'])) $voucher->type = $args['type'];
        
        if (isset($args['value'])) {
             $voucher->value = ($voucher->type === 'fixed') ? (int)($args['value'] * 100) : (int)$args['value'];
        }

        if (isset($args['is_active'])) $voucher->is_active = $args['is_active'];
        if (array_key_exists('usage_limit', $args)) $voucher->usage_limit = $args['usage_limit'];

        $voucher->save();

        return [
            'status' => 'success',
            'message' => "Gutschein '{$voucher->code}' erfolgreich aktualisiert."
        ];
    }

    public static function executeGetCoupons(array $args): array
    {
        $vouchers = Voucher::where('mode', 'manual')->orderByDesc('created_at')->limit(50)->get();
        
        $mapped = $vouchers->map(function($v) {
            return [
                'id' => $v->id,
                'code' => $v->code,
                'type' => $v->type,
                'value' => $v->type === 'fixed' ? number_format($v->value / 100, 2) . '€' : $v->value . '%',
                'is_active' => $v->is_active,
                'used_count' => $v->used_count,
                'usage_limit' => $v->usage_limit,
                'valid_until' => $v->valid_until ? $v->valid_until->format('Y-m-d') : null,
                'created_at' => $v->created_at ? $v->created_at->format('Y-m-d H:i') : null
            ];
        });

        return [
            'status' => 'success',
            'coupons' => $mapped->toArray(),
            'message' => 'Gutscheine geladen. Achte darauf, dass hier 50 manuelle Gutscheine gefunden wurden. Analysiere diese Daten leise.',
            '_frontend_event' => [
                'name' => 'open-ai-visualization',
                'detail' => [
                    'category' => 'voucher',
                    'data' => $mapped->toArray()
                ]
            ],
            '_fast_track' => true
        ];
    }

    public static function executeDeleteCoupon(array $args): array
    {
        $code = $args['code'] ?? null;
        if (!$code) {
             return ['status' => 'error', 'message' => 'Kein Gutscheincode angegeben.'];
        }

        $voucher = Voucher::where('code', $code)->first();
        if (!$voucher) {
            return ['status' => 'error', 'message' => "Der Gutschein mit dem Code '{$code}' wurde nicht gefunden."];
        }

        if (strcasecmp($voucher->mode, 'auto') === 0) {
            return ['status' => 'error', 'message' => 'Saisonale Auto-Gutscheine können und dürfen nicht von der KI gelöscht werden!'];
        }

        $voucher->delete();

        return [
            'status' => 'success',
            'message' => "Der Gutschein '{$code}' wurde endgültig vernichtet."
        ];
    }
}
