<?php

namespace App\Services\AI\Functions;

use App\Models\Product\Product;
use App\Models\Product\ProductReview;
use App\Models\Product\ProductLoss;
use App\Models\Product\ProductNicheItem;
use App\Models\Product\ProductNicheCrawlerRun;
use App\Jobs\RunProductNicheCrawlerJob;
use App\Models\Product\ProductPackaging;
use App\Models\Product\ProductTemplate;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

trait AiProductFuncs
{
    public static function getAiProductFuncsSchema(): array
    {
        return [
[
                'name' => 'product_analytics_get_overview',
                'description' => 'Liefert eine extrem detaillierte betriebswirtschaftliche Übersicht über alle aktiven physischen Produkte. Beinhaltet Marge, Verpackungskosten, Absatz-Geschwindigkeit (Velocity), Reichweite des Lagerbestands und den Bestell-Status. ACHTUNG TOKEN-LIMIT: Diese Liste enthält NICHT alle Tiefendetails (wie SEO, Supplier-Kontaktdaten). Wenn du spezielle oder ALLE Daten zu EINEM konkreten Produkt wissen willst, nutze zwingend `product_get_details`!',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeAnalyticsGetOverview']
            ],
            [
                'name' => 'product_analytics_get_lucid_report',
                'description' => 'Liefert eine detaillierte Auswertung des Verpackungsmülls (LUCID) für das aktuelle Jahr, aufgeschlüsselt nach Materialien (Papier, Plastik, Glas, etc.) und Produkten.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeGetLucidReport']
            ],
[
                'name' => 'review_get_list',
                'description' => 'Liefert eine strukturierte Liste von Shop-Kundenbewertungen. Standardmäßig wird nach Status "pending" gefiltert, um ungelesene/unmoderierte Bewertungen abzurufen. Beinhaltet Produktname, Kundenname, Sterne-Vergabe und den Bewertungstext.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'status' => ['type' => 'string', 'description' => 'Optional: Filter nach Status ("pending", "approved", "rejected"). Standard ist "pending" für neu eintreffende Reviews.', 'enum' => ['pending', 'approved', 'rejected']],
                        'limit' => ['type' => 'integer', 'description' => 'Optional: Maximale Anzahl an erfassten Bewertungen (default: 15).']
                    ]
                ],
                'callable' => [self::class, 'executeGetList']
            ],
            [
                'name' => 'review_moderate',
                'description' => 'Entscheidet über das Schicksal einer Bewertung. "approved" schaltet sie öffentlich für alle Shop-Kunden sichtbar. "rejected" lehnt sie ab (z.B. wegen Spam oder Beleidigung) und versteckt sie.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'review_id' => ['type' => 'integer', 'description' => 'Die ID der Kundenbewertung aus dem review_get_list Aufruf.'],
                        'action' => ['type' => 'string', 'description' => 'Muss exakt "approved" oder "rejected" sein.', 'enum' => ['approved', 'rejected']]
                    ],
                    'required' => ['review_id', 'action']
                ],
                'callable' => [self::class, 'executeModerate']
            ],
            [
                'name' => 'review_delete',
                'description' => 'Löscht eine Bewertung endgültig aus der Datenbank, inklusive eventuell angehängter Kunden-Bilder auf dem App-Server.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'review_id' => ['type' => 'integer', 'description' => 'Die ID der zu löschenden Bewertung.']
                    ],
                    'required' => ['review_id']
                ],
                'callable' => [self::class, 'executeProductReviewDelete']
            ],
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
                'callable' => [self::class, 'executeProductDraftGetDetails']
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
                'callable' => [self::class, 'executeProductDraftUpdate']
            ],
[
                'name' => 'product_loss_get_overview',
                'description' => 'Gibt einen umfassenden Analyse-Bericht über alle Produktschäden/Verluste zurück. Zeigt globale monetäre Verluste sowie eine sortierte Hit-Liste der Produkte an, die am häufigsten kaputt gehen oder beanstandet werden. Perfekt für das Erkennen von Fehler-Trends oder Lieferanten-Problemen.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
                'callable' => [self::class, 'executeLossGetOverview']
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
[
                'name' => 'niche_run_crawler',
                'description' => 'Startet einen im Hintergrund laufenden Crawler-Job für die tiefgehende Markt- und Nischenforschung auf Marktplätzen (Etsy/Amazon). Da das Auswerten von Webseiten dauert (ca. 1-2 Minuten), informiere den Nutzer im Chat, dass er gleich nach den Ergebnissen fragen soll.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'description' => 'Der exakte Suchbegriff (z.B. "personalisierte tasse weihnachten").'],
                        'platforms' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => ['Etsy', 'Amazon']],
                            'description' => 'Die Zielplattform(en). Meist macht es Sinn, beide Plattformen zu scannen.'
                        ]
                    ],
                    'required' => ['keyword', 'platforms']
                ],
                'callable' => [self::class, 'executeRunCrawler']
            ],
            [
                'name' => 'niche_get_live_data',
                'description' => 'Liest die aktuellen Live-Rankings des Nischen-Scanners aus der Datenbank (immer die Ergebnisse des ZULETZT gestarteten niche_run_crawler Jobs). Rufe dies auf, um dem Nutzer 1-2 finale Empfehlungen für Produkte abzuleiten, die man mit dem CO2- oder Faserlaser rentabel gravieren könnte.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => 'Maximale Anzahl abzurufender Top-Produkte (default: 10, max: 25).']
                    ]
                ],
                'callable' => [self::class, 'executeGetLiveData']
            ],
            [
                'name' => 'niche_save_live_run',
                'description' => 'Speichert die temporären Live-Scans sicher ins Archiv ab, damit sie nicht vom nächsten Crawler-Lauf überschrieben werden. Mach dies, wenn du oder der Nutzer ein Set an Suchergebnissen besonders erfolgreich und speicherungswürdig findest.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Ein aussagekräftiger Titel für das Archiv (z.B. "Top Tassen Analyse Q4 2026").']
                    ],
                    'required' => ['name']
                ],
                'callable' => [self::class, 'executeSaveLiveRun']
            ],
            [
                'name' => 'niche_get_historical_runs',
                'description' => 'Gibt dir eine Liste aller jemals GESPEICHERTEN (archivierten) Nischen-Scans zurück (Meta-Daten wie Name und ID).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass()
                ],
                'callable' => [self::class, 'executeGetHistoricalRuns']
            ],
            [
                'name' => 'niche_get_historical_data',
                'description' => 'Liest die konkreten Analyse-Produkte aus einem spezifisch ausgewählten, historischen (gespeicherten) Scan aus.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'product_niche_crawler_run_id' => ['type' => 'integer', 'description' => 'Die ID des gespeicherten Scans aus niche_get_historical_runs.']
                    ],
                    'required' => ['product_niche_crawler_run_id']
                ],
                'callable' => [self::class, 'executeGetHistoricalData']
            ],
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
            ],
[
                'name' => 'template_get_all',
                'description' => 'Liefert eine Liste aller Shop-Vorlagen (Templates). Ideal für einen Überblick über saisonale Layouts (z.B. "Weihnachten" oder "Muttertag"), die mit Produkten verknüpft sind.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filter_holiday' => ['type' => 'string', 'description' => 'Optional: Filter nach dem eingetragenen Holiday-Tag, z.B. "Ostern", "Christmas", oder "Valentine".'],
                        'filter_active' => ['type' => 'boolean', 'description' => 'Optional: Nur aktive (true) oder inaktive (false) Vorlagen anzeigen.']
                    ]
                ],
                'callable' => [self::class, 'executeProductTemplateGetAll']
            ],
            [
                'name' => 'template_update',
                'description' => 'Patched Metadaten (Name, Holiday, aktiver Status) einer bestimmten Produkt-Vorlage. Der wichtigste Einsatz-Zweck: Vorlagen zum Saison-Start einschalten (is_active = true) bzw. nach Saisonsende ausschalten (is_active = false).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'string', 'description' => 'Die exakte ID oder der ungenaue Name der Vorlage.'],
                        'name' => ['type' => 'string', 'description' => 'Neuer Anzeigename.'],
                        'holiday' => ['type' => 'string', 'description' => 'Saisonaler Tag der Vorlage (z.B. "Easter"). Leer lassen, um den Tag zu löschen.'],
                        'is_active' => ['type' => 'boolean', 'description' => 'True setzen, um die Vorlage im Konfigurator anzuzeigen, False zum deaktivieren/verbergen.']
                    ],
                    'required' => ['template_id']
                ],
                'callable' => [self::class, 'executeProductTemplateUpdate']
            ],
            [
                'name' => 'template_delete',
                'description' => 'Löscht eine Konfigurations-Vorlage komplett.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'template_id' => ['type' => 'string', 'description' => 'Die ID oder der Name der zu löschenden Vorlage.']
                    ],
                    'required' => ['template_id']
                ],
                'callable' => [self::class, 'executeProductTemplateDelete']
            ],
            [
                "name" => "product_get_supplier",
                "description" => "Gibt den Lieferanten (Großhändler) für ein spezifisches Produkt zurück. Beinhaltet Kontaktinformationen, Lieferzeiten und Herkunftsland des Lieferanten.",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "product_id" => [
                            "type" => "string",
                            "description" => "Die exakte ID oder der Name des Produkts (z.B. \"Der Seelenkristall\")."
                        ]
                    ],
                    "required" => ["product_id"]
                ],
                "callable" => [self::class, "executeProductGetSupplier"]
            ]
        ];
    }






    
    public static function executeAnalyticsGetOverview(array $args)
    {
        try {
            $data = \App\Livewire\Shop\Product\ProductAnalytics::getCombinedAnalyticsData();
            
            // TOKEN OPTIMIERUNG: Entferne die riesigen Supplier-Arrays und lange Texte aus der Massenabfrage
            $mappedData = $data->map(function ($item) {
                unset($item['supplier']); 
                unset($item['short_description']);
                return $item;
            })->toArray();

            return [
                'status' => 'success',
                'products_count' => $data->count(),
                'report' => $mappedData
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetLucidReport(array $args)
    {
        try {
            $data = \App\Livewire\Shop\Product\ProductAnalytics::getLucidData();
            return [
                'status' => 'success',
                'report' => $data
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }









    
    public static function executeGetList(array $args)
    {
        try {
            $status = $args['status'] ?? 'pending';
            $limit = isset($args['limit']) ? min((int)$args['limit'], 50) : 15;

            $query = ProductReview::with(['product', 'customer'])->latest();

            if (!empty($status)) {
                $query->where('status', $status);
            }

            $reviews = $query->take($limit)->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'status' => $r->status,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'content' => $r->content,
                    'product_name' => $r->product ? $r->product->name : 'Unbekanntes Produkt',
                    'customer_name' => $r->customer ? trim($r->customer->first_name . ' ' . $r->customer->last_name) : 'Gast/Anonym',
                    'created_at' => $r->created_at->format('Y-m-d H:i')
                ];
            });

            return [
                'status' => 'success',
                'filter' => $status,
                'count' => $reviews->count(),
                'reviews' => $reviews->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeModerate(array $args)
    {
        try {
            if (empty($args['review_id']) || empty($args['action'])) {
                return ['status' => 'error', 'message' => 'Review ID und Action (approved/rejected) fehlen.'];
            }

            $review = ProductReview::find($args['review_id']);
            if (!$review) return ['status' => 'error', 'message' => 'Bewertung existiert nicht oder wurde bereits gelöscht.'];

            if (!in_array($args['action'], ['approved', 'rejected'])) {
                return ['status' => 'error', 'message' => 'Ungültige Action. Erlaubt sind nur "approved" oder "rejected".'];
            }

            $review->update(['status' => $args['action']]);

            return [
                'status' => 'success',
                'message' => "Die Bewertung #{$review->id} wurde als '{$args['action']}' markiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler bei der Moderation: ' . $e->getMessage()];
        }
    }

    public static function executeProductReviewDelete(array $args)
    {
        try {
            if (empty($args['review_id'])) return ['status' => 'error', 'message' => 'Review ID fehlt.'];

            $review = ProductReview::find($args['review_id']);
            if (!$review) return ['status' => 'error', 'message' => 'Bewertung existiert nicht oder wurde bereits gelöscht.'];

            // Clean up attached media to save server disk space
            if (!empty($review->media) && is_array($review->media)) {
                foreach ($review->media as $media) {
                    Storage::disk('public')->delete($media);
                }
            }

            $review->delete();

            return [
                'status' => 'success',
                'message' => "Bewertung #{$args['review_id']} mitsamt assoziierter Medien erfolgreich gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }









    
    public static function executeProductDraftGetDetails(array $args)
    {
        try {
            if (empty($args['product_id'])) return ['status' => 'error', 'message' => 'Produkt-ID fehlt.'];
            
            $product = \App\Models\Product\Product::with(['supplier', 'packagings'])->withTrashed()->find($args['product_id']);
            
            if (!$product) {
                // Robuste Suche wie bei get_supplier
                $words = explode(' ', str_replace(['-', '_'], ' ', $args["product_id"]));
                usort($words, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });
                $longestWord = mb_strtolower($words[0] ?? '');
                
                if (mb_strlen($longestWord) >= 4) {
                    $product = \App\Models\Product\Product::with(['supplier', 'packagings'])
                        ->withTrashed()
                        ->whereRaw("REPLACE(REPLACE(LOWER(name), ' ', ''), '-', '') LIKE ?", ['%' . $longestWord . '%'])
                        ->first();
                }
            }

            if (!$product) {
                // Fallback via Name
                $product = \App\Models\Product\Product::with(['supplier', 'packagings'])->withTrashed()->where('name', 'like', '%' . $args['product_id'] . '%')->first();
                if (!$product) return ['status' => 'error', 'message' => 'Produkt nicht gefunden. Nutze product_analytics_get_overview, um alle gültigen Produkte aufzulisten.'];
            }

            // Calculation logic (wie in Analytics)
            $netPrice = $product->net_price;
            $purchase = $product->purchase_price ?? 0;
            $laserCost = ($product->laser_runtime_minutes ?? 0) * ($product->electricity_wear_factor > 0 ? $product->electricity_wear_factor : 1);
            $packaging = $product->packaging_cost ?? 0;
            $shipping = $product->shipping_cost > 0 ? $product->shipping_cost : 490;
            $totalCost = $purchase + $laserCost + $packaging + $shipping;
            $netMargin = $netPrice - $totalCost;
            $marginPercent = $netPrice > 0 ? round(($netMargin / $netPrice) * 100, 1) : 0;

            // Sales in last 30 days
            $thirtyDaysAgo = \Carbon\Carbon::now()->subDays(30);
            $soldLast30 = \App\Models\Order\OrderOrderItem::where('product_id', $product->id)->whereHas('order', function ($query) use ($thirtyDaysAgo) {
                $query->where('created_at', '>=', $thirtyDaysAgo)->whereNotIn('status', ['cancelled', 'draft']);
            })->sum('quantity');

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
                    'net_price_eur' => round($netPrice / 100, 2),
                    'purchase_price_eur' => round($purchase / 100, 2),
                    'laser_cost_eur' => round($laserCost / 100, 2),
                    'packaging_cost_eur' => round($packaging / 100, 2),
                    'shipping_cost_eur' => round($shipping / 100, 2),
                    'calculated_total_costs_eur' => round($totalCost / 100, 2),
                    'calculated_net_margin_eur' => round($netMargin / 100, 2),
                    'calculated_margin_percent' => $marginPercent,
                    'compare_price_eur' => $product->compare_at_price ? round($product->compare_at_price / 100, 2) : 0,
                    'quantity_in_stock' => $product->quantity,
                    'sold_last_30_days' => $soldLast30,
                    'track_quantity' => $product->track_quantity,
                    'description' => $product->description,
                    'short_description' => $product->short_description,
                    'seo_title' => $product->seo_title,
                    'seo_description' => $product->seo_description,
                    'weight_grams' => $product->weight,
                    'dimensions_mm' => trim($product->length . 'x' . $product->width . 'x' . $product->height),
                    'supplier' => $product->supplier ? $product->supplier->toArray() : null,
                    'packagings' => $product->packagings ? $product->packagings->toArray() : [],
                ]
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeProductDraftUpdate(array $args)
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









    
    public static function executeLossGetOverview(array $args)
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
                'product_supplier_id' => $product->supplier_id ?? null,
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











    
    public static function executeRunCrawler(array $args)
    {
        try {
            if (empty($args['keyword']) || empty($args['platforms'])) {
                return ['status' => 'error', 'message' => 'Keyword und Plattform-Array fehlen.'];
            }

            // Flush old live data
            ProductNicheItem::truncate();
            Cache::forget('niche_scanner_live_ai_rec');
            Cache::forget('niche_scanner_live_ai_agent');

            $activeJobs = Cache::get('active_crawler_jobs', []);
            $dispatchedJobs = [];

            foreach ($args['platforms'] as $platform) {
                $jobId = uniqid('crawler_') . '_' . strtolower($platform);

                if (!in_array($jobId, $activeJobs)) {
                    $activeJobs[] = $jobId;
                }

                Cache::put("crawler_job_{$jobId}", [
                    'id' => $jobId,
                    'keyword' => $args['keyword'],
                    'platform' => $platform,
                    'progress' => 1,
                    'status' => 'Job gestartet via AI Agent...',
                    'is_running' => true
                ], 600);

                RunProductNicheCrawlerJob::dispatch($jobId, $platform, $args['keyword']);
                $dispatchedJobs[] = $jobId;
            }

            Cache::put('active_crawler_jobs', $activeJobs, 3600);

            return [
                'status' => 'success',
                'message' => 'Der Crawler wurde gestartet. Plattformen: ' . implode(', ', $args['platforms']) . '. Sag dem Nutzer unbedingt, dass er ca. 1-2 Minuten warten soll, und erhalte dann Live-Ergebnisse mit niche_get_live_data.',
                'dispatched_jobs' => $dispatchedJobs
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Starten des Crawlers: ' . $e->getMessage()];
        }
    }

    public static function executeGetLiveData(array $args)
    {
        try {
            $limit = isset($args['limit']) ? min((int)$args['limit'], 25) : 10;

            $products = ProductNicheItem::orderBy('niche_score', 'desc')->take($limit)->get();

            if ($products->isEmpty()) {
                return [
                    'status' => 'success',
                    'message' => 'Die Live-Datenbank ist aktuell leer. Zeigt an, dass der Scan entweder aktuell noch läuft (bitte noch 30 Sekunden warten) oder noch kein Scan gestartet wurde.'
                ];
            }

            return [
                'status' => 'success',
                'total_live_items' => ProductNicheItem::count(),
                'top_products' => $products->map(function($p) {
                    return [
                        'title' => $p->title,
                        'platform' => $p->platform,
                        'price' => $p->price,
                        'reviews' => $p->reviews,
                        'niche_score' => $p->niche_score,
                        'url' => $p->url
                    ];
                })->toArray()
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeSaveLiveRun(array $args)
    {
        try {
            if (empty($args['name'])) {
                return ['status' => 'error', 'message' => 'Bitte einen Namens-Titel für das Archiv vergeben.'];
            }

            $products = ProductNicheItem::orderBy('niche_score', 'desc')->get();
            if ($products->isEmpty()) {
                return ['status' => 'error', 'message' => 'Keine Live-Daten zum Speichern vorhanden.'];
            }

            // Platform String extrahieren:
            $platforms = $products->pluck('platform')->unique()->implode(', ');

            // Normalerweise steht das Keyword nur Session-basiert bereit, daher Hardcodierter Fallback mit AI Hinweis
            $run = ProductNicheCrawlerRun::create([
                'admin_id' => 1,
                'name' => $args['name'],
                'keyword' => 'AI Initiated Scan Output',
                'platform' => $platforms,
                'products_data' => $products->toArray(),
            ]);

            return [
                'status' => 'success',
                'message' => "Live Scorecard erfolgreich archiviert unter der History-ID: {$run->id}."
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetHistoricalRuns(array $args)
    {
        try {
            // Get last 20 runs
            $runs = ProductNicheCrawlerRun::orderBy('created_at', 'desc')->take(20)->get()->map(function($r) {
                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'keyword' => $r->keyword,
                    'platform' => $r->platform,
                    'created_at' => $r->created_at->format('Y-m-d H:i')
                ];
            });

            return [
                'status' => 'success',
                'archives' => $runs->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeGetHistoricalData(array $args)
    {
        try {
            if (empty($args['product_niche_crawler_run_id'])) return ['status' => 'error', 'message' => 'run_id fehlt.'];

            $run = ProductNicheCrawlerRun::find($args['product_niche_crawler_run_id']);
            if (!$run) return ['status' => 'error', 'message' => 'Historischer Lauf nicht gefunden.'];

            $data = is_array($run->products_data) ? collect($run->products_data) : collect(json_decode($run->products_data, true));

            return [
                'status' => 'success',
                'run_name' => $run->name,
                'top_products' => $data->sortByDesc('niche_score')->take(15)->map(function($p) {
                    return [
                        'title' => $p['title'] ?? '',
                        'platform' => $p['platform'] ?? '',
                        'price' => $p['price'] ?? '',
                        'reviews' => $p['reviews'] ?? '',
                        'niche_score' => $p['niche_score'] ?? '',
                        'url' => $p['url'] ?? ''
                    ];
                })->values()->toArray()
            ];

        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
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









    
    public static function executeProductTemplateGetAll(array $args)
    {
        try {
            $query = ProductTemplate::with('product');
            
            if (isset($args['filter_holiday']) && !empty($args['filter_holiday'])) {
                $query->where('holiday', 'like', '%' . $args['filter_holiday'] . '%');
            }
            if (isset($args['filter_active'])) {
                $query->where('is_active', (bool)$args['filter_active']);
            }

            $templates = $query->get()->map(function ($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'is_active' => $t->is_active,
                    'holiday' => $t->holiday,
                    'product_id' => $t->product_id,
                    'product_name' => $t->product ? $t->product->name : 'Unbekannt'
                ];
            });

            return [
                'status' => 'success',
                'total_templates' => $templates->count(),
                'templates' => $templates->toArray()
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public static function executeProductTemplateUpdate(array $args)
    {
        try {
            if (empty($args['template_id'])) return ['status' => 'error', 'message' => 'Template ID fehlt.'];
            
            $template = self::findTemplate($args['template_id']);
            if (!$template) return ['status' => 'error', 'message' => 'Vorlage wurde nicht gefunden.'];

            $updates = [];
            
            if (isset($args['name'])) $updates['name'] = $args['name'];
            if (isset($args['holiday'])) $updates['holiday'] = empty($args['holiday']) ? null : $args['holiday'];
            if (isset($args['is_active'])) $updates['is_active'] = (bool)$args['is_active'];

            if (!empty($updates)) {
                $template->update($updates);
            }

            return [
                'status' => 'success',
                'message' => "Vorlage ({$template->name}) wurde erfolgreich aktualisiert."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Update: ' . $e->getMessage()];
        }
    }

    public static function executeProductTemplateDelete(array $args)
    {
        try {
            if (empty($args['template_id'])) return ['status' => 'error', 'message' => 'Template ID fehlt.'];
            
            $template = self::findTemplate($args['template_id']);
            if (!$template) return ['status' => 'error', 'message' => 'Vorlage wurde nicht gefunden.'];

            $name = $template->name;
            
            // Delete image if it belongs uniquely to the template
            if ($template->preview_image && Str::startsWith($template->preview_image, 'produkte/product-templates/')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($template->preview_image);
            }
            
            $template->delete();

            return [
                'status' => 'success',
                'message' => "Vorlage '{$name}' wurde gelöscht."
            ];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Fehler beim Löschen: ' . $e->getMessage()];
        }
    }

    private static function findTemplate($identifier)
    {
        $template = ProductTemplate::find($identifier);
        if (!$template) {
            $template = ProductTemplate::where('name', 'like', '%' . $identifier . '%')->first();
        }
        return $template;
    }



    public static function executeProductGetSupplier(array $args)
    {
        try {
            if (empty($args["product_id"])) return ["status" => "error", "message" => "Produkt-ID fehlt."];
            
            $product = \App\Models\Product\Product::with("supplier")->find($args["product_id"]);
            if (!$product) {
                $product = \App\Models\Product\Product::with("supplier")->where("name", $args["product_id"])->first();
                
                if (!$product) {
                    $searchTerm = str_replace(' ', '%', $args["product_id"]);
                    $product = \App\Models\Product\Product::with("supplier")->where("name", "like", "%" . $searchTerm . "%")->first();
                }

                if (!$product) {
                    // Robuste Suche: Nimm das längste Wort aus der User-Eingabe (z.B. "Seelenkristall" aus "Seelenkristall Trophäe")
                    $words = explode(' ', str_replace(['-', '_'], ' ', $args["product_id"]));
                    usort($words, function($a, $b) { return mb_strlen($b) - mb_strlen($a); });
                    $longestWord = mb_strtolower($words[0] ?? '');
                    
                    if (mb_strlen($longestWord) >= 4) {
                        // Vergleiche ohne Leerzeichen in der Datenbank (damit "Seelenkristall" "Der Seelen Kristall" findet)
                        $product = \App\Models\Product\Product::with("supplier")
                            ->whereRaw("REPLACE(REPLACE(LOWER(name), ' ', ''), '-', '') LIKE ?", ['%' . $longestWord . '%'])
                            ->first();
                    }
                }
                
                if (!$product) {
                    $slugTerm = \Illuminate\Support\Str::slug($args["product_id"]);
                    $product = \App\Models\Product\Product::with("supplier")->where("slug", "like", "%" . $slugTerm . "%")->first();
                }

                if (!$product) return ["status" => "error", "message" => "Produkt nicht gefunden."];
            }

            if (!$product->supplier) {
                return [
                    "status" => "success",
                    "message" => "Das Produkt {$product->name} hat aktuell keinen Lieferanten zugewiesen."
                ];
            }

            return [
                "status" => "success",
                "product_name" => $product->name,
                "supplier" => [
                    "name" => $product->supplier->name,
                    "contact_person" => $product->supplier->contact_person,
                    "email" => $product->supplier->email,
                    "phone" => $product->supplier->phone,
                    "website" => $product->supplier->website,
                    "lead_time_days" => $product->supplier->lead_time_land_days,
                    "country" => $product->supplier->country,
                    "shipping_method" => $product->supplier->shipping_method
                ]
            ];
        } catch (\Exception $e) {
            return ["status" => "error", "message" => $e->getMessage()];
        }
    }
}
