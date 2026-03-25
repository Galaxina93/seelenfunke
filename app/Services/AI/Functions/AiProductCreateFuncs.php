<?php

namespace App\Services\AI\Functions;

use App\Models\Product\Product;
use Illuminate\Support\Str;

trait AiProductCreateFuncs
{
    /**
     * Define the Product PIM specific tools for the Analyst Agent
     */
    public static function getAiProductCreateFuncsSchema(): array
    {
        return [
            [
                'name' => 'product_get_details',
                'description' => 'Liest die kompletten Live-Stammdaten eines spezifischen Produkts aus (z.B. vor einem SEO-Update oder einer Preisänderung). Enthält Beschreibungen, SEO-Titel, Preise, Dimensionen und den Status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => [
                            'type' => 'string',
                            'description' => 'Die ID oder ein unpräziser Name des Produkts.'
                        ]
                    ],
                    'required' => ['product_id']
                ],
                'callable' => [self::class, 'executeGetDetails']
            ],
            [
                'name' => 'product_create',
                'description' => 'Erstellt ein komplett neues Produkt im Shop-System. Setzt den initialen Preis, Bestand, Titel und Status.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Der offizielle Produktname.'],
                        'type' => ['type' => 'string', 'description' => 'Muss "physical", "digital" oder "service" sein.', 'enum' => ['physical', 'digital', 'service']],
                        'price_eur' => ['type' => 'number', 'description' => 'Der Verkaufspreis in Euro (z.B. 19.99).'],
                        'status' => ['type' => 'string', 'description' => 'Der Status ("draft" für Entwürfe, "active" sofort für den Verkauf, "archived" fürs Archiv).', 'enum' => ['draft', 'active', 'archived']],
                        'sku' => ['type' => 'string', 'description' => 'Eindeutige Artikelnummer.'],
                        'description' => ['type' => 'string', 'description' => 'Der Webseiten-Beschreibungstext (kann Markdown/HTML enthalten).'],
                        'short_description' => ['type' => 'string', 'description' => 'Ein kurzer Teaser-Text.'],
                        'quantity' => ['type' => 'integer', 'description' => 'Der initiale Lagerbestand.']
                    ],
                    'required' => ['name', 'type', 'price_eur']
                ],
                'callable' => [self::class, 'executeCreate']
            ],
            [
                'name' => 'product_update',
                'description' => 'Aktualisiert ein oder mehrere Felder eines bestehenden Produkts (PIM Patch-Operation). Überschreibt nur die spezifischen Keys, die im JSON übergeben werden. Gut für direkte SEO Optimierung oder Preisanpassungen aus dem Chat heraus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_id' => ['type' => 'string', 'description' => 'Die exakte Produkt ID oder der ungenaue Name des zu aktualisierenden Produkts.'],
                        'name' => ['type' => 'string', 'description' => 'Neuer Produktname (ändert auch automatisch den URL-Slug).'],
                        'price_eur' => ['type' => 'number', 'description' => 'Neuer Verkaufspreis in Euro.'],
                        'compare_price_eur' => ['type' => 'number', 'description' => 'Alter "Streich-Preis" in Euro.'],
                        'description' => ['type' => 'string', 'description' => 'Neuer langer Beschreibungstext.'],
                        'seo_title' => ['type' => 'string', 'description' => 'Neuer Google Meta-Titel (max 60 Zeichen).'],
                        'seo_description' => ['type' => 'string', 'description' => 'Neue Google Meta-Beschreibung (max 160 Zeichen).'],
                        'status' => ['type' => 'string', 'description' => 'Neuer Status ("draft", "active", "archived").', 'enum' => ['draft', 'active', 'archived']],
                        'quantity' => ['type' => 'integer', 'description' => 'Neuer fixer Stückzahl-Bestand.']
                    ],
                    'required' => ['product_id']
                ],
                'callable' => [self::class, 'executeUpdate']
            ]
        ];
    }

    public static function executeGetDetails(array $args)
    {
        try {
            if (empty($args['product_id'])) return ['status' => 'error', 'message' => 'Produkt-ID fehlt.'];
            
            $product = Product::withTrashed()->find($args['product_id']);
            if (!$product) {
                // Fallback via Name
                $product = Product::withTrashed()->where('name', 'like', '%' . $args['product_id'] . '%')->first();
                if (!$product) return ['status' => 'error', 'message' => 'Produkt nicht gefunden.'];
            }

            return [
                'status' => 'success',
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'type' => $product->type,
                    'status' => $product->status,
                    'sku' => $product->sku,
                    'price_eur' => round($product->price / 100, 2),
                    'compare_price_eur' => $product->compare_at_price ? round($product->compare_at_price / 100, 2) : 0,
                    'purchase_price_eur' => $product->purchase_price ? round($product->purchase_price / 100, 2) : 0,
                    'quantity' => $product->quantity,
                    'track_quantity' => $product->track_quantity,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                    'weight' => $product->weight,
                    'dimensions_mm' => trim($product->length . 'x' . $product->width . 'x' . $product->height),
                    'attributes' => $product->attributes
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeCreate(array $args)
    {
        try {
            if (empty($args['name']) || empty($args['type'])) {
                return ['status' => 'error', 'message' => 'Name und Type (physical/digital/service) sind Pflichtfelder.'];
            }

            $priceCents = isset($args['price_eur']) ? (int)round((float)$args['price_eur'] * 100) : 0;
            $slug = Str::slug($args['name']);

            // Kollisionsvermeidung
            if (Product::withTrashed()->where('slug', $slug)->exists()) {
                $slug .= '-' . time();
            }

            $product = Product::create([
                'name' => $args['name'],
                'slug' => $slug,
                'status' => $args['status'] ?? 'draft',
                'type' => $args['type'],
                'price' => $priceCents,
                'sku' => $args['sku'] ?? 'SKU-' . date('YmdHis'),
                'description' => $args['description'] ?? null,
                'short_description' => $args['short_description'] ?? null,
                'quantity' => isset($args['quantity']) ? (int)$args['quantity'] : 0,
                'track_quantity' => isset($args['quantity']) ? true : false,
                'completion_step' => 4, // So the UI won't force wizard steps
                'attributes' => []
            ]);

            return [
                'status' => 'success',
                'message' => "Produkt erfolgreich angelegt. ID: {$product->id}",
                'product_id' => $product->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Anlegen des Produkts: ' . $e->getMessage()];
        }
    }

    public static function executeUpdate(array $args)
    {
        try {
            if (empty($args['product_id'])) return ['status' => 'error', 'message' => 'Produkt-ID fehlt.'];
            
            $product = Product::withTrashed()->find($args['product_id']);
            if (!$product) {
                // Fallback via Name
                $product = Product::withTrashed()->where('name', 'like', '%' . $args['product_id'] . '%')->first();
                if (!$product) return ['status' => 'error', 'message' => 'Produkt nicht gefunden.'];
            }

            $updates = [];

            if (isset($args['name'])) {
                $updates['name'] = $args['name'];
                $slug = Str::slug($args['name']);
                if (Product::withTrashed()->where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
                    $slug .= '-' . time();
                }
                $updates['slug'] = $slug;
            }

            if (isset($args['price_eur'])) $updates['price'] = (int)round((float)$args['price_eur'] * 100);
            if (isset($args['compare_price_eur'])) $updates['compare_at_price'] = (int)round((float)$args['compare_price_eur'] * 100);
            if (isset($args['description'])) $updates['description'] = $args['description'];
            if (isset($args['seo_title'])) $updates['seo_title'] = substr($args['seo_title'], 0, 255);
            if (isset($args['seo_description'])) $updates['seo_description'] = substr($args['seo_description'], 0, 255);
            if (isset($args['quantity'])) {
                $updates['quantity'] = (int)$args['quantity'];
                $updates['track_quantity'] = true;
            }

            if (isset($args['status']) && in_array($args['status'], ['active', 'draft', 'archived'])) {
                if ($args['status'] === 'archived') {
                    $updates['status'] = 'archived';
                    $product->update($updates);
                    $product->delete(); // Soft delete per architecture standard
                    return ['status' => 'success', 'message' => "Produkt ({$product->name}) wurde ins Archiv verschoben."];
                } else {
                    if ($product->trashed() && $args['status'] !== 'archived') {
                        $product->restore();
                    }
                    $updates['status'] = $args['status'];
                }
            }

            $product->update($updates);

            return [
                'status' => 'success',
                'message' => "Produkt ({$product->name}) wurde erfolgreich gepatcht/aktualisiert.",
                'product_id' => $product->id
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Update des Produkts: ' . $e->getMessage()];
        }
    }
}
