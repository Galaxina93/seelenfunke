<?php

namespace App\Services\AI\Functions;

use App\Models\Product\Product;
use App\Models\Product\ProductPackaging;

trait AiProductPackagingConfiguratorFuncs
{
    /**
     * Define the Product Packaging configuration tools for the Analyst Agent
     */
    public static function getAiProductPackagingConfiguratorFuncsSchema(): array
    {
        return [
            [
                'name' => 'packaging_get_logic',
                'description' => 'Liefert das aktuell hinterlegte Verpackungs-Gewicht (Gramm) und die zugewiesenen Verpackungs-Materialien für ein bestimmtes Produkt. Dieses Tool listet im Antwort-Payload ebenfalls auf, welche Material-Typen (z.B. "Kartonage", "Luftpolsterfolie") das Shop-System prinzipiell überhaupt unterstützt (allowed_system_materials).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'description' => 'Die ID des Shop-Produkts.']
                    ],
                    'required' => ['product_id']
                ],
                'callable' => [self::class, 'executeGetLogic']
            ],
            [
                'name' => 'packaging_add_material',
                'description' => 'Fügt einem Produkt ein neues Verpackungsmaterial und das dazugehörige Gewicht (wichtig für spätere Porto-Kalkulationen) hinzu. Wenn das gesuchte Material am Produkt bereits existiert, entscheidet das Shop-Backend automatisch, das neue Gewicht als Summe aufzuaddieren.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'integer', 'description' => 'Die Produkt-ID.'],
                        'material_type' => ['type' => 'string', 'description' => 'Das hinzuzufügende Material (Exakt aus den allowed_system_materials von packaging_get_logic kopieren!).'],
                        'weight_grams' => ['type' => 'number', 'description' => 'Das Tara-Gewicht dieses Materials in Gramm.']
                    ],
                    'required' => ['product_id', 'material_type', 'weight_grams']
                ],
                'callable' => [self::class, 'executeAddMaterial']
            ],
            [
                'name' => 'packaging_update_weight',
                'description' => 'Überschreibt das Gewicht einer bereits fest verknüpften Verpackung (mithilfe der spezifischen Packaging-ID).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'packaging_id' => ['type' => 'integer', 'description' => 'Die ID der Verpackungs-Zuordnung (erhältlich aus packaging_get_logic).'],
                        'weight_grams' => ['type' => 'number', 'description' => 'Das neue absolute, finale Gewicht in Gramm.']
                    ],
                    'required' => ['packaging_id', 'weight_grams']
                ],
                'callable' => [self::class, 'executeUpdateWeight']
            ],
            [
                'name' => 'packaging_remove_material',
                'description' => 'Trennt und löscht ein Verpackungsmaterial samt Gewicht komplett von einem Produkt (via Packaging-ID).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'packaging_id' => ['type' => 'integer', 'description' => 'Die ID der konkreten Verpackungs-Zuordnung (erhältlich aus packaging_get_logic).']
                    ],
                    'required' => ['packaging_id']
                ],
                'callable' => [self::class, 'executeRemoveMaterial']
            ]
        ];
    }

    public static function executeGetLogic(array $args)
    {
        try {
            if (empty($args['product_id'])) return ['status' => 'error', 'message' => 'Produkt-ID fehlt.'];
            
            $product = Product::find($args['product_id']);
            if (!$product) return ['status' => 'error', 'message' => 'Produkt existiert nicht.'];

            $packagings = $product->packagings()->orderBy('material_type')->get()->map(function($p) {
                return [
                    'packaging_id' => $p->id,
                    'material_type' => $p->material_type,
                    'weight_grams' => $p->weight_grams
                ];
            });

            $allowedTypes = method_exists(ProductPackaging::class, 'getMaterialTypes') ? ProductPackaging::getMaterialTypes() : [];

            return [
                'status' => 'success',
                'product_name' => $product->name,
                'total_packaging_weight_grams' => $packagings->sum('weight_grams'),
                'packagings' => $packagings->toArray(),
                'allowed_system_materials' => $allowedTypes
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeAddMaterial(array $args)
    {
        try {
            if (empty($args['product_id']) || empty($args['material_type']) || empty($args['weight_grams'])) {
                return ['status' => 'error', 'message' => 'Es fehlen product_id, material_type oder weight_grams.'];
            }

            $product = Product::find($args['product_id']);
            if (!$product) return ['status' => 'error', 'message' => 'Produkt nicht gefunden.'];

            $existing = $product->packagings()->where('material_type', $args['material_type'])->first();

            if ($existing) {
                $existing->increment('weight_grams', $args['weight_grams']);
                return [
                    'status' => 'success',
                    'message' => "Material '{$args['material_type']}' existierte bereits am Produkt. Das System hat das Gewicht um {$args['weight_grams']}g kumuliert. Neues Einzelgewicht: {$existing->weight_grams}g."
                ];
            } else {
                $created = $product->packagings()->create([
                    'material_type' => $args['material_type'],
                    'weight_grams' => $args['weight_grams']
                ]);
                return [
                    'status' => 'success',
                    'message' => "Material '{$args['material_type']}' mit {$args['weight_grams']}g erfolgreich hinzugefügt.",
                    'packaging_id' => $created->id
                ];
            }
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Speichern der Materialzuordnung: ' . $e->getMessage()];
        }
    }

    public static function executeUpdateWeight(array $args)
    {
        try {
            if (empty($args['packaging_id']) || empty($args['weight_grams'])) {
                return ['status' => 'error', 'message' => 'packaging_id oder weight_grams fehlt.'];
            }

            $packaging = ProductPackaging::find($args['packaging_id']);
            if (!$packaging) return ['status' => 'error', 'message' => 'Verpackungs-Eintrag nicht gefunden.'];

            $packaging->update(['weight_grams' => $args['weight_grams']]);

            return [
                'status' => 'success',
                'message' => "Gewicht von '{$packaging->material_type}' erfolgreich auf {$args['weight_grams']}g aktualisiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Update: ' . $e->getMessage()];
        }
    }

    public static function executeRemoveMaterial(array $args)
    {
        try {
            if (empty($args['packaging_id'])) return ['status' => 'error', 'message' => 'packaging_id fehlt.'];

            $packaging = ProductPackaging::find($args['packaging_id']);
            if (!$packaging) return ['status' => 'error', 'message' => 'Verpackungs-Eintrag existiert nicht (mehr).'];

            $typeStr = $packaging->material_type;
            $packaging->delete();

            return [
                'status' => 'success',
                'message' => "Verpackungsmaterial '{$typeStr}' erfolgreich vom Produkt entfernt."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen der Verpackung: ' . $e->getMessage()];
        }
    }
}
